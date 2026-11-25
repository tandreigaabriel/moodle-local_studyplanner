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
 * Shared "required vs. available minutes this week" lookup.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\local;

/**
 * Reads the current active plan's committed minutes and the user's declared
 * availability, and reports the resulting deficit. Used by both the Today
 * page banner and the daily reminder task, so the two never disagree.
 */
class weekly_summary {
    /**
     * Builds the workload summary for one user's week containing the given timestamp.
     *
     * @param int $userid The user id.
     * @param int $timestamp Any timestamp within the target week.
     * @return \stdClass Summary: totalavailableminutes, totalrequiredminutes, deficitminutes, overloaded.
     */
    public static function for_user(int $userid, int $timestamp): \stdClass {
        global $DB;

        $weekstart = workload_calculator::get_week_start($timestamp);
        $plan = $DB->get_record('local_studyplanner_plans', [
            'userid' => $userid,
            'periodstart' => $weekstart,
            'status' => 'active',
        ]);

        $requiredminutes = 0;
        if ($plan) {
            $requiredminutes = (int) $DB->get_field_select(
                'local_studyplanner_plan_items',
                'COALESCE(SUM(estimatedminutes), 0)',
                "planid = :planid AND status IN ('planned', 'missed')",
                ['planid' => $plan->id]
            );
        }

        $weekdaymap = workload_calculator::get_weekday_availability($userid);
        $totalavailable = array_sum(array_column($weekdaymap, 'minutes'));
        $deficit = max(0, $requiredminutes - $totalavailable);

        return (object) [
            'totalavailableminutes' => $totalavailable,
            'totalrequiredminutes' => $requiredminutes,
            'deficitminutes' => $deficit,
            'overloaded' => $deficit > 0,
        ];
    }
}
