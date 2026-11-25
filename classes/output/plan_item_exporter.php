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
 * Shared plan-item-to-template export, used by both the Today and This Week views.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\output;

/**
 * Converts a local_studyplanner_plan_items record into template-ready context.
 */
class plan_item_exporter {
    /**
     * Exports one plan item row for a mustache template.
     *
     * @param \stdClass $item The plan item record.
     * @return array
     */
    public static function export(\stdClass $item): array {
        $activityurl = '';
        if ($item->source === 'moodle_activity' && (int) $item->cmid > 0 && !empty($item->modname)) {
            $activityurl = (new \moodle_url('/mod/' . $item->modname . '/view.php', ['id' => $item->cmid]))->out(false);
        }

        return [
            'id' => (int) $item->id,
            'title' => format_string($item->title),
            'starttime' => userdate((int) $item->plannedstart, get_string('strftimetime', 'langconfig')),
            'endtime' => userdate((int) $item->plannedend, get_string('strftimetime', 'langconfig')),
            'estimatedminutes' => (int) $item->estimatedminutes,
            'status' => $item->status,
            'statuslabel' => get_string('status:' . $item->status, 'local_studyplanner'),
            'ismissed' => $item->status === 'missed',
            'iscompleted' => $item->status === 'completed',
            'isactionable' => in_array($item->status, ['planned', 'missed'], true),
            'ispersonal' => $item->source === 'personal_task',
            'hasactivityurl' => $activityurl !== '',
            'activityurl' => $activityurl,
        ];
    }
}
