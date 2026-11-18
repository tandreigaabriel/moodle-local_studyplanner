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
 * Renderable data for the "This Week" plan view, with a per-day workload heatmap.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\output;

use local_studyplanner\local\weekly_summary;
use local_studyplanner\local\workload_calculator;

/**
 * Builds the template context for week.php: the current week's plan items
 * grouped by day, each day showing planned vs. available minutes as a
 * simple heatmap bar.
 */
class week_page implements \renderable, \templatable {
    /** @var int The user whose plan is being shown. */
    private int $userid;

    /**
     * Constructor.
     *
     * @param int $userid The user whose plan is being shown.
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Exports the data required to render the week.mustache template.
     *
     * @param \renderer_base $output The output renderer requesting this export.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $DB;

        $now = time();
        $weekstart = workload_calculator::get_week_start($now);
        $weekend = $weekstart + (7 * DAYSECS);

        $items = $DB->get_records_select(
            'local_studyplanner_plan_items',
            'userid = :userid AND plannedstart >= :start AND plannedstart < :end AND status <> :cancelled',
            ['userid' => $this->userid, 'start' => $weekstart, 'end' => $weekend, 'cancelled' => 'cancelled'],
            'plannedstart ASC'
        );

        $itemsbyday = [];
        foreach ($items as $item) {
            $daystart = workload_calculator::get_day_start((int) $item->plannedstart);
            $itemsbyday[$daystart][] = $item;
        }

        $weekdaymap = workload_calculator::get_weekday_availability($this->userid);
        $days = $this->export_days($weekstart, $weekdaymap, $itemsbyday);

        $summary = weekly_summary::for_user($this->userid, $now);

        return [
            'weeklabel' => userdate($weekstart, get_string('strftimedate', 'langconfig'))
                . ' - ' . userdate($weekend - DAYSECS, get_string('strftimedate', 'langconfig')),
            'days' => $days,
            'overloaded' => $summary->deficitminutes > 0,
            'deficithours' => round($summary->deficitminutes / 60, 1),
            'totalavailableminutes' => $summary->totalavailableminutes,
            'totalrequiredminutes' => $summary->totalrequiredminutes,
            'todayurl' => (new \moodle_url('/local/studyplanner/today.php'))->out(false),
            'weekurl' => (new \moodle_url('/local/studyplanner/week.php'))->out(false),
            'preferencesurl' => (new \moodle_url('/local/studyplanner/preferences.php'))->out(false),
        ];
    }

    /**
     * Builds the per-day heatmap rows for the week.
     *
     * @param int $weekstart Monday 00:00 timestamp for the target week.
     * @param array $weekdaymap Availability template, keyed 0..6 (shape: array<int, array{minutes: int, start: int}>).
     * @param array $itemsbyday Plan items, keyed by day-start timestamp (shape: array<int, \stdClass[]>).
     * @return array
     */
    private function export_days(int $weekstart, array $weekdaymap, array $itemsbyday): array {
        $days = [];

        for ($offset = 0; $offset < 7; $offset++) {
            // Monday (offset 0) is ISO weekday 1, so PHP's date('w') weekday (0 = Sunday) is
            // simply (offset + 1) % 7 -- no timezone-sensitive date() call needed here.
            $dow = ($offset + 1) % 7;
            $daymidnight = workload_calculator::get_day_start($weekstart + ($offset * DAYSECS));

            $dayitems = $itemsbyday[$daymidnight] ?? [];
            $planned = 0;
            foreach ($dayitems as $item) {
                $planned += (int) $item->estimatedminutes;
            }

            $available = $weekdaymap[$dow]['minutes'] ?? 0;
            $percentage = $available > 0 ? min(100, (int) round(($planned / $available) * 100)) : ($planned > 0 ? 100 : 0);

            $days[] = [
                'label' => userdate($daymidnight, get_string('strftimedaydate', 'langconfig')),
                'plannedminutes' => $planned,
                'availableminutes' => $available,
                'percentage' => $percentage,
                'overloaded' => $planned > $available,
                'hasitems' => !empty($dayitems),
                'items' => array_map([plan_item_exporter::class, 'export'], $dayitems),
            ];
        }

        return $days;
    }
}
