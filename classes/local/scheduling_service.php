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
 * Greedy day-by-day allocation of prioritised tasks into available study capacity.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\local;

/**
 * Packs tasks into a week's available capacity, splitting long tasks into
 * multiple sessions where a single day cannot hold the whole thing.
 *
 * Deliberately rule-based, not AI-assisted: tasks are walked highest priority
 * first and placed into the earliest day with spare capacity before their
 * due date. If a task cannot be fully placed before its due date, the
 * remainder is still packed somewhere in the week on a best-effort basis so
 * the work is visible; workload_calculator separately reports the resulting
 * overload so the student is warned rather than the work silently vanishing.
 */
class scheduling_service {
    /**
     * Allocates tasks into the given week's capacity map.
     *
     * @param \stdClass[] $tasks Unified task drafts: title, courseid, cmid, personaltaskid,
     *                           duedate, estimatedminutes, priority, source.
     * @param array $capacitymap Remaining capacity, keyed by day-start timestamp
     *                          (shape: array<int, array{minutes: int, start: int}>).
     * @param int $maxsessionminutes Longest allowed single study session.
     * @param int $breakminutes Break inserted between consecutive sessions on the same day.
     * @return \stdClass[] Plan item drafts, ready to be persisted.
     */
    public static function allocate(array $tasks, array $capacitymap, int $maxsessionminutes, int $breakminutes): array {
        $daystate = self::build_day_state($capacitymap);
        $dayindex = array_keys($daystate);
        sort($dayindex);

        usort($tasks, function ($a, $b) {
            if ($a->priority !== $b->priority) {
                return $b->priority <=> $a->priority;
            }
            return $a->duedate <=> $b->duedate;
        });

        $drafts = [];
        foreach ($tasks as $task) {
            $segments = self::place_task($task, $dayindex, $daystate, $maxsessionminutes, $breakminutes);
            $drafts = array_merge($drafts, self::segments_to_drafts($task, $segments));
        }

        return $drafts;
    }

    /**
     * Places a single task's minutes into the shared day state, splitting into segments as needed.
     *
     * @param \stdClass $task The task to place.
     * @param int[] $dayindex Ordered list of day-start timestamps.
     * @param array $daystate Mutable per-day capacity state (shape: array<int, array{remaining: int, cursor: int}>).
     * @param int $maxsessionminutes Longest allowed single study session.
     * @param int $breakminutes Break inserted between consecutive sessions on the same day.
     * @return \stdClass[] Segments: plannedstart, plannedend, estimatedminutes.
     */
    private static function place_task(
        \stdClass $task,
        array $dayindex,
        array &$daystate,
        int $maxsessionminutes,
        int $breakminutes
    ): array {
        $remainingminutes = (int) $task->estimatedminutes;
        if ($remainingminutes <= 0) {
            return [];
        }

        $segments = [];

        // First pass: only use days on or before the task's due date, if it has one.
        foreach ($dayindex as $daymidnight) {
            if ($remainingminutes <= 0) {
                break;
            }
            if ($task->duedate > 0 && $daymidnight > $task->duedate) {
                continue;
            }
            $remainingminutes = self::fill_from_day(
                $daystate[$daymidnight],
                $remainingminutes,
                $maxsessionminutes,
                $breakminutes,
                $segments
            );
        }

        // Second pass, best-effort only: if the deadline could not absorb everything,
        // pack the remainder anywhere in the week so the task still appears in the plan.
        foreach ($dayindex as $daymidnight) {
            if ($remainingminutes <= 0) {
                break;
            }
            $remainingminutes = self::fill_from_day(
                $daystate[$daymidnight],
                $remainingminutes,
                $maxsessionminutes,
                $breakminutes,
                $segments
            );
        }

        return $segments;
    }

    /**
     * Consumes as much of a task's remaining minutes as one day's state allows.
     *
     * @param array $state The day's mutable capacity state (shape: array{remaining: int, cursor: int}).
     * @param int $remainingminutes Minutes of the task still needing a slot.
     * @param int $maxsessionminutes Longest allowed single study session.
     * @param int $breakminutes Break inserted after each session.
     * @param \stdClass[] $segments Segments accumulator, appended to by reference.
     * @return int Minutes of the task still remaining after this day.
     */
    private static function fill_from_day(
        array &$state,
        int $remainingminutes,
        int $maxsessionminutes,
        int $breakminutes,
        array &$segments
    ): int {
        while ($remainingminutes > 0 && $state['remaining'] > 0) {
            $chunk = min($remainingminutes, $state['remaining'], $maxsessionminutes);
            if ($chunk <= 0) {
                break;
            }

            $start = $state['cursor'];
            $end = $start + ($chunk * MINSECS);

            $segments[] = (object) [
                'plannedstart' => $start,
                'plannedend' => $end,
                'estimatedminutes' => $chunk,
            ];

            $state['cursor'] = $end + ($breakminutes * MINSECS);
            $state['remaining'] -= $chunk;
            $remainingminutes -= $chunk;
        }

        return $remainingminutes;
    }

    /**
     * Converts a capacity map into mutable per-day scheduling state.
     *
     * @param array $capacitymap Remaining capacity, keyed by day-start timestamp
     *                          (shape: array<int, array{minutes: int, start: int}>).
     * @return array<int, array{remaining: int, cursor: int}> Mutable state, keyed by day-start timestamp.
     */
    private static function build_day_state(array $capacitymap): array {
        $daystate = [];
        foreach ($capacitymap as $daymidnight => $day) {
            $daystate[$daymidnight] = [
                'remaining' => (int) $day['minutes'],
                'cursor' => (int) $daymidnight + ((int) $day['start'] * MINSECS),
            ];
        }

        return $daystate;
    }

    /**
     * Converts a task's placed segments into persistable plan item drafts.
     *
     * @param \stdClass $task The task the segments belong to.
     * @param \stdClass[] $segments Segments returned by place_task().
     * @return \stdClass[] Plan item drafts.
     */
    private static function segments_to_drafts(\stdClass $task, array $segments): array {
        $segmentcount = count($segments);
        $drafts = [];

        foreach ($segments as $index => $segment) {
            $title = $task->title;
            if ($segmentcount > 1) {
                $title = get_string('sessionsegmenttitle', 'local_studyplanner', (object) [
                    'title' => $task->title,
                    'index' => $index + 1,
                    'count' => $segmentcount,
                ]);
            }

            $drafts[] = (object) [
                'courseid' => $task->courseid,
                'cmid' => $task->cmid,
                'modname' => $task->modname,
                'personaltaskid' => $task->personaltaskid,
                'title' => $title,
                'plannedstart' => $segment->plannedstart,
                'plannedend' => $segment->plannedend,
                'estimatedminutes' => $segment->estimatedminutes,
                'priority' => $task->priority,
                'duedate' => $task->duedate,
                'source' => $task->source,
                'segmentindex' => $index + 1,
                'segmentcount' => $segmentcount,
            ];
        }

        return $drafts;
    }
}
