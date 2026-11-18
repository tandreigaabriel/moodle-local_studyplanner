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
 * Moves a missed plan item into the next available study slot, with an audit trail.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\local;

/**
 * Reschedules a single plan item and records the move in local_studyplanner_reschedule_log.
 *
 * This is the plugin's main differentiator over Moodle's stock calendar: a
 * missed session does not just sit there overdue, it actively moves to the
 * next slot the student actually has capacity for.
 */
class rescheduling_service {
    /**
     * Reschedules one plan item into the next available slot before its due date.
     *
     * If no capacity can be found this week (or before the item's own due
     * date if sooner), the item is left as-is and the attempt is still logged
     * so the audit trail shows a reschedule was tried and failed; the
     * workload summary is what surfaces this to the student as an overload
     * warning rather than a silently dropped task.
     *
     * @param int $planitemid The plan item id to reschedule.
     * @param string $reason One of "missed", "manual", "conflict" or "availability_changed".
     * @return bool True if a new slot was found and applied.
     */
    public static function reschedule_item(int $planitemid, string $reason = 'missed'): bool {
        global $DB;

        $item = $DB->get_record('local_studyplanner_plan_items', ['id' => $planitemid], '*', MUST_EXIST);
        if (!in_array($item->status, ['planned', 'missed'], true)) {
            return false;
        }

        $now = time();
        $weekstart = workload_calculator::get_week_start($item->plannedstart);
        $weekend = $weekstart + (7 * DAYSECS);
        $searchend = $item->duedate > 0 ? min((int) $item->duedate, $weekend) : $weekend;

        $consumed = self::get_consumed_minutes_by_day((int) $item->userid, $weekstart, $weekend, $planitemid);
        $capacitymap = workload_calculator::build_capacity_map((int) $item->userid, $weekstart, $consumed);

        $duration = max(1, (int) round(($item->plannedend - $item->plannedstart) / MINSECS));

        foreach ($capacitymap as $daymidnight => $day) {
            if ($daymidnight > $searchend || $daymidnight + DAYSECS <= $now) {
                continue;
            }
            if ($day['minutes'] < $duration) {
                continue;
            }

            $start = max($daymidnight + ($day['start'] * MINSECS), $now);
            $end = $start + ($duration * MINSECS);

            self::apply_reschedule($item, $start, $end, $reason);

            return true;
        }

        self::log_failed_attempt($item, $reason);

        return false;
    }

    /**
     * Updates a plan item's schedule and writes the corresponding audit log entry.
     *
     * @param \stdClass $item The plan item being moved.
     * @param int $start New planned start timestamp.
     * @param int $end New planned end timestamp.
     * @param string $reason The reschedule reason.
     * @return void
     */
    private static function apply_reschedule(\stdClass $item, int $start, int $end, string $reason): void {
        global $DB;

        $oldstart = (int) $item->plannedstart;
        $oldend = (int) $item->plannedend;

        $item->plannedstart = $start;
        $item->plannedend = $end;
        $item->status = 'rescheduled';
        $item->timemodified = time();
        $DB->update_record('local_studyplanner_plan_items', $item);

        $DB->insert_record('local_studyplanner_reschedule_log', (object) [
            'planitemid' => $item->id,
            'userid' => $item->userid,
            'oldstart' => $oldstart,
            'oldend' => $oldend,
            'newstart' => $start,
            'newend' => $end,
            'reason' => $reason,
            'timecreated' => time(),
        ]);
    }

    /**
     * Logs a reschedule attempt that found no available capacity.
     *
     * @param \stdClass $item The plan item that could not be moved.
     * @param string $reason The reschedule reason.
     * @return void
     */
    private static function log_failed_attempt(\stdClass $item, string $reason): void {
        global $DB;

        $DB->insert_record('local_studyplanner_reschedule_log', (object) [
            'planitemid' => $item->id,
            'userid' => $item->userid,
            'oldstart' => $item->plannedstart,
            'oldend' => $item->plannedend,
            'newstart' => 0,
            'newend' => 0,
            'reason' => $reason,
            'timecreated' => time(),
        ]);
    }

    /**
     * Sums other plan items' durations by the calendar day they fall on, for capacity accounting.
     *
     * @param int $userid The user id.
     * @param int $weekstart Monday 00:00 timestamp for the target week.
     * @param int $weekend Timestamp marking the end of the target week.
     * @param int $excludeitemid The plan item being rescheduled, excluded from its own conflict count.
     * @return array<int, int> Minutes consumed, keyed by day-start timestamp.
     */
    private static function get_consumed_minutes_by_day(int $userid, int $weekstart, int $weekend, int $excludeitemid): array {
        global $DB;

        $sql = "SELECT id, plannedstart, plannedend
                  FROM {local_studyplanner_plan_items}
                 WHERE userid = :userid
                   AND plannedstart >= :weekstart
                   AND plannedstart < :weekend
                   AND status NOT IN ('cancelled', 'skipped')
                   AND id <> :excludeid";
        $rows = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'weekstart' => $weekstart,
            'weekend' => $weekend,
            'excludeid' => $excludeitemid,
        ]);

        $consumed = [];
        foreach ($rows as $row) {
            $daystart = workload_calculator::get_day_start((int) $row->plannedstart);
            $minutes = (int) round(($row->plannedend - $row->plannedstart) / MINSECS);
            $consumed[$daystart] = ($consumed[$daystart] ?? 0) + $minutes;
        }

        return $consumed;
    }
}
