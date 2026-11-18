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
 * Weekly capacity math: availability, consumption and overload detection.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\local;

/**
 * Turns a student's declared weekly availability into concrete day-by-day
 * capacity, and compares it against required study minutes to flag overload.
 */
class workload_calculator {
    /**
     * Returns the Monday 00:00 timestamp, in the user's timezone, for the week containing a moment.
     *
     * @param int $timestamp Any unix timestamp within the target week.
     * @return int The Monday 00:00 timestamp for that week.
     */
    public static function get_week_start(int $timestamp): int {
        $dt = self::get_day_start_datetime($timestamp);
        $isoday = (int) $dt->format('N'); // 1 = Monday .. 7 = Sunday.

        if ($isoday > 1) {
            $dt->modify('-' . ($isoday - 1) . ' days');
        }

        return $dt->getTimestamp();
    }

    /**
     * Returns 00:00, in the user's timezone, for the calendar day containing a moment.
     *
     * @param int $timestamp Any unix timestamp within the target day.
     * @return int The day-start timestamp.
     */
    public static function get_day_start(int $timestamp): int {
        return self::get_day_start_datetime($timestamp)->getTimestamp();
    }

    /**
     * Returns the user's declared availability template, one entry per weekday.
     *
     * @param int $userid The user id.
     * @return array<int, array{minutes: int, start: int}> Keyed 0 (Sunday) .. 6 (Saturday).
     */
    public static function get_weekday_availability(int $userid): array {
        global $DB;

        $map = [];
        for ($dow = 0; $dow <= 6; $dow++) {
            $map[$dow] = ['minutes' => 0, 'start' => 18 * 60];
        }

        $rows = $DB->get_records('local_studyplanner_availability', ['userid' => $userid]);
        foreach ($rows as $row) {
            $map[(int) $row->dayofweek] = [
                'minutes' => (int) $row->availableminutes,
                'start' => (int) $row->preferredstarttime,
            ];
        }

        return $map;
    }

    /**
     * Builds a concrete 7-day capacity map for one specific week.
     *
     * @param int $userid The user id.
     * @param int $weekstart Monday 00:00 timestamp for the target week.
     * @param array $consumedminutesbyday Minutes already committed, keyed by day-start timestamp (shape: array<int, int>).
     * @return array<int, array{minutes: int, start: int}> Remaining capacity, keyed by day-start timestamp.
     */
    public static function build_capacity_map(int $userid, int $weekstart, array $consumedminutesbyday = []): array {
        $weekdaymap = self::get_weekday_availability($userid);
        $cursor = self::get_day_start_datetime($weekstart);
        $capacity = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $daydt = clone $cursor;
            $daydt->modify('+' . $offset . ' days');
            $daymidnight = $daydt->getTimestamp();
            $dow = (int) $daydt->format('w'); // 0 = Sunday .. 6 = Saturday.

            $minutes = $weekdaymap[$dow]['minutes'] ?? 0;
            $consumed = $consumedminutesbyday[$daymidnight] ?? 0;

            $capacity[$daymidnight] = [
                'minutes' => max(0, $minutes - $consumed),
                'start' => $weekdaymap[$dow]['start'] ?? (18 * 60),
            ];
        }

        return $capacity;
    }

    /**
     * Summarises available vs. required minutes for a set of tasks over one week.
     *
     * @param \stdClass[] $tasks Unified task drafts, each with duedate and estimatedminutes.
     * @param int $userid The user id.
     * @param int $weekstart Monday 00:00 timestamp for the target week.
     * @param int $weekend Timestamp marking the end of the target week.
     * @return \stdClass Summary: totalavailableminutes, totalrequiredminutes, overloaded, deficitminutes.
     */
    public static function summarize(array $tasks, int $userid, int $weekstart, int $weekend): \stdClass {
        $weekdaymap = self::get_weekday_availability($userid);

        $totalavailable = 0;
        foreach ($weekdaymap as $day) {
            $totalavailable += (int) $day['minutes'];
        }

        $now = time();
        $totalrequired = 0;
        foreach ($tasks as $task) {
            $duedate = (int) $task->duedate;
            $duewithinweek = $duedate >= $weekstart && $duedate < $weekend;
            if ($duedate === 0 || $duedate < $now || $duewithinweek) {
                $totalrequired += (int) $task->estimatedminutes;
            }
        }

        $deficit = max(0, $totalrequired - $totalavailable);

        return (object) [
            'totalavailableminutes' => $totalavailable,
            'totalrequiredminutes' => $totalrequired,
            'overloaded' => $deficit > 0,
            'deficitminutes' => $deficit,
        ];
    }

    /**
     * Returns a DateTime set to 00:00 in the user's timezone for the day containing a moment.
     *
     * @param int $timestamp Any unix timestamp within the target day.
     * @return \DateTime The day-start DateTime, in the user's timezone.
     */
    private static function get_day_start_datetime(int $timestamp): \DateTime {
        $timezone = \core_date::get_user_timezone_object();
        $dt = new \DateTime('@' . $timestamp);
        $dt->setTimezone($timezone);
        $dt->setTime(0, 0, 0);

        return $dt;
    }
}
