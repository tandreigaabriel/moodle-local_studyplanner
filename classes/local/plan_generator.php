<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Orchestrates building or refreshing one student's weekly study plan.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\local;

/**
 * Builds and refreshes a single student's current-week study plan.
 *
 * This is the only place plan rows are created or replaced. It is always
 * called for one user at a time (from the build_user_plan adhoc task), never
 * for a whole cohort inline, so a slow user never blocks the others.
 */
class plan_generator {
    /**
     * Returns the id of the current week's active plan, generating one on the spot if
     * this is the user's first visit and none exists yet.
     *
     * Safe to call from a page load for a single user (unlike cohort-wide generation,
     * which must always go through the ad-hoc task queue).
     *
     * @param int $userid The user to ensure a plan for.
     * @return int The plan id.
     */
    public static function ensure_current_plan(int $userid): int {
        global $DB;

        $weekstart = workload_calculator::get_week_start(time());
        $plan = $DB->get_record('local_studyplanner_plans', [
            'userid' => $userid,
            'periodstart' => $weekstart,
            'status' => 'active',
        ]);

        if ($plan) {
            return (int) $plan->id;
        }

        return self::generate_for_user($userid);
    }

    /**
     * Generates or refreshes the current week's study plan for one user.
     *
     * Plan items the student has already acted on (completed, in progress,
     * missed, rescheduled or skipped) are preserved untouched. Only items
     * still in the "planned" state are replaced, alongside any
     * newly-discovered tasks that are not yet represented in the plan.
     *
     * @param int $userid The user to generate a plan for.
     * @return int The plan id that was created or refreshed.
     */
    public static function generate_for_user(int $userid): int {
        global $DB;

        $preferences = self::get_or_create_preferences($userid);
        $now = time();
        $weekstart = workload_calculator::get_week_start($now);
        $weekend = $weekstart + (7 * DAYSECS);

        $tasks = self::collect_tasks($userid);

        $transaction = $DB->start_delegated_transaction();

        try {
            $planid = self::get_or_create_plan($userid, $weekstart, $weekend, $now);

            // A "planned" item whose end time has already passed is a missed session, not
            // just stale data to discard: flip it to "missed" so the student can act on it
            // (reschedule/complete/skip) instead of it silently disappearing on refresh.
            $DB->set_field_select(
                'local_studyplanner_plan_items',
                'status',
                'missed',
                'planid = :planid AND status = :planned AND plannedend < :cutoff',
                ['planid' => $planid, 'planned' => 'planned', 'cutoff' => $now]
            );

            $preserved = $DB->get_records_select(
                'local_studyplanner_plan_items',
                'planid = :planid AND status <> :planned',
                ['planid' => $planid, 'planned' => 'planned']
            );
            $DB->delete_records('local_studyplanner_plan_items', ['planid' => $planid, 'status' => 'planned']);

            $remainingtasks = self::exclude_already_handled($tasks, $preserved);
            $consumedbyday = self::consumed_minutes_by_day($preserved);
            $capacitymap = workload_calculator::build_capacity_map($userid, $weekstart, $consumedbyday);

            $drafts = scheduling_service::allocate(
                $remainingtasks,
                $capacitymap,
                (int) $preferences->maxsessionminutes,
                (int) $preferences->breakminutes
            );

            foreach ($drafts as $draft) {
                $draft->planid = $planid;
                $draft->userid = $userid;
                $draft->actualminutes = 0;
                $draft->status = 'planned';
                $draft->timecreated = $now;
                $draft->timemodified = $now;
                $DB->insert_record('local_studyplanner_plan_items', $draft);
            }

            $transaction->allow_commit();
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }

        return $planid;
    }

    /**
     * Finds this week's active plan for a user, creating one if it does not exist yet.
     *
     * @param int $userid The user id.
     * @param int $weekstart Monday 00:00 timestamp for the target week.
     * @param int $weekend Timestamp marking the end of the target week.
     * @param int $now The current timestamp, used for created/modified fields.
     * @return int The plan id.
     */
    private static function get_or_create_plan(int $userid, int $weekstart, int $weekend, int $now): int {
        global $DB;

        $plan = $DB->get_record('local_studyplanner_plans', [
            'userid' => $userid,
            'periodstart' => $weekstart,
            'status' => 'active',
        ]);

        if ($plan) {
            return (int) $plan->id;
        }

        return (int) $DB->insert_record('local_studyplanner_plans', (object) [
            'userid' => $userid,
            'periodstart' => $weekstart,
            'periodend' => $weekend,
            'status' => 'active',
            'generatedby' => 'auto',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Removes tasks that are already represented by a preserved (untouched) plan item.
     *
     * @param \stdClass[] $tasks Unified task drafts.
     * @param \stdClass[] $preserved Plan items not being regenerated this run.
     * @return \stdClass[] Tasks still needing a slot.
     */
    private static function exclude_already_handled(array $tasks, array $preserved): array {
        $handledkeys = [];
        foreach ($preserved as $item) {
            $handledkeys[self::task_key((int) $item->cmid, (int) $item->personaltaskid)] = true;
        }

        return array_values(array_filter($tasks, function ($task) use ($handledkeys) {
            return empty($handledkeys[self::task_key($task->cmid, $task->personaltaskid)]);
        }));
    }

    /**
     * Builds the unified, prioritised task list from Moodle activities and personal tasks.
     *
     * @param int $userid The user to collect tasks for.
     * @return \stdClass[] Unified task drafts, ready for the scheduling service.
     */
    private static function collect_tasks(int $userid): array {
        global $DB;

        $tasks = [];

        foreach (activity_provider::get_upcoming_activities($userid, 21) as $activity) {
            $estimatedminutes = duration_estimator::estimate_minutes((int) $activity->cmid, $activity->modname);
            $importance = duration_estimator::get_importance((int) $activity->cmid);

            $tasks[] = (object) [
                'title' => $activity->title,
                'courseid' => (int) $activity->courseid,
                'cmid' => (int) $activity->cmid,
                'modname' => $activity->modname,
                'personaltaskid' => 0,
                'duedate' => (int) $activity->duedate,
                'estimatedminutes' => $estimatedminutes,
                'priority' => priority_calculator::calculate((int) $activity->duedate, $importance),
                'source' => 'moodle_activity',
            ];
        }

        $personaltasks = $DB->get_records_select(
            'local_studyplanner_personal_tasks',
            'userid = :userid AND status = :status',
            ['userid' => $userid, 'status' => 'active']
        );

        foreach ($personaltasks as $task) {
            $priority = priority_calculator::calculate((int) $task->duedate, 'recommended') + (int) $task->priority;

            $tasks[] = (object) [
                'title' => $task->title,
                'courseid' => 0,
                'cmid' => 0,
                'modname' => null,
                'personaltaskid' => (int) $task->id,
                'duedate' => (int) $task->duedate,
                'estimatedminutes' => (int) $task->estimatedminutes,
                'priority' => $priority,
                'source' => 'personal_task',
            ];
        }

        return $tasks;
    }

    /**
     * Returns the user's planning preferences, creating a sensible default row if none exist yet.
     *
     * @param int $userid The user id.
     * @return \stdClass The preferences record.
     */
    private static function get_or_create_preferences(int $userid): \stdClass {
        global $DB;

        $preferences = $DB->get_record('local_studyplanner_preferences', ['userid' => $userid]);
        if ($preferences) {
            return $preferences;
        }

        $now = time();
        $preferences = (object) [
            'userid' => $userid,
            'maxsessionminutes' => 60,
            'breakminutes' => 10,
            'intensity' => 'normal',
            'remindersenabled' => 1,
            'remindertime' => 480,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $preferences->id = $DB->insert_record('local_studyplanner_preferences', $preferences);

        return $preferences;
    }

    /**
     * Sums preserved plan items' durations by the calendar day they fall on.
     *
     * @param \stdClass[] $preserved Plan items that were not regenerated this run.
     * @return array<int, int> Minutes consumed, keyed by day-start timestamp.
     */
    private static function consumed_minutes_by_day(array $preserved): array {
        $consumed = [];
        foreach ($preserved as $item) {
            $daystart = workload_calculator::get_day_start((int) $item->plannedstart);
            $minutes = (int) round(($item->plannedend - $item->plannedstart) / MINSECS);
            $consumed[$daystart] = ($consumed[$daystart] ?? 0) + $minutes;
        }

        return $consumed;
    }

    /**
     * Builds a stable identity key for a task, used to detect tasks already represented by a preserved item.
     *
     * @param int $cmid The course module id, or 0 for a personal task.
     * @param int $personaltaskid The personal task id, or 0 for a Moodle activity.
     * @return string The identity key.
     */
    private static function task_key(int $cmid, int $personaltaskid): string {
        return $cmid > 0 ? 'cm:' . $cmid : 'task:' . $personaltaskid;
    }
}
