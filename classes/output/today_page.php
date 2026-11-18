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
 * Renderable data for the "Today" plan view.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\output;

use local_studyplanner\local\weekly_summary;
use local_studyplanner\local\workload_calculator;

/**
 * Builds the template context for today.php: today's plan items plus a
 * week-level workload warning banner.
 */
class today_page implements \renderable, \templatable {
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
     * Exports the data required to render the today.mustache template.
     *
     * @param \renderer_base $output The output renderer requesting this export.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $DB;

        $now = time();
        $daystart = workload_calculator::get_day_start($now);
        $dayend = $daystart + DAYSECS;

        $items = $DB->get_records_select(
            'local_studyplanner_plan_items',
            'userid = :userid AND plannedstart >= :start AND plannedstart < :end AND status <> :cancelled',
            ['userid' => $this->userid, 'start' => $daystart, 'end' => $dayend, 'cancelled' => 'cancelled'],
            'plannedstart ASC'
        );

        $summary = weekly_summary::for_user($this->userid, $now);
        $exporteditems = array_map([plan_item_exporter::class, 'export'], array_values($items));

        return [
            'today' => userdate($now, get_string('strftimedaydate', 'langconfig')),
            'hasitems' => !empty($exporteditems),
            'items' => $exporteditems,
            'overloaded' => $summary->deficitminutes > 0,
            'deficithours' => round($summary->deficitminutes / 60, 1),
            'todayurl' => (new \moodle_url('/local/studyplanner/today.php'))->out(false),
            'weekurl' => (new \moodle_url('/local/studyplanner/week.php'))->out(false),
            'preferencesurl' => (new \moodle_url('/local/studyplanner/preferences.php'))->out(false),
            'personaltasksurl' => (new \moodle_url('/local/studyplanner/personaltasks.php'))->out(false),
        ];
    }
}
