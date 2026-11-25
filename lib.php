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
 * Legacy callback functions for local_studyplanner.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds the study planner and, for teachers, the course estimates link to the navigation.
 *
 * @param global_navigation $navigation The main navigation node.
 * @return void
 */
function local_studyplanner_extend_navigation(global_navigation $navigation): void {
    global $USER, $PAGE;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    if (!get_config('local_studyplanner', 'enabled')) {
        return;
    }

    if (!has_capability('local/studyplanner:manageownplan', context_system::instance())) {
        return;
    }

    $node = $navigation->add(
        get_string('pluginname', 'local_studyplanner'),
        new moodle_url('/local/studyplanner/today.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_studyplanner'
    );
    $node->showinflatnavigation = true;

    if ($PAGE->course && $PAGE->course->id != SITEID) {
        $context = context_course::instance($PAGE->course->id);
        if (has_capability('local/studyplanner:manageestimates', $context)) {
            $node->add(
                get_string('courseestimates', 'local_studyplanner'),
                new moodle_url('/local/studyplanner/courseestimates.php', ['courseid' => $PAGE->course->id])
            );
        }
    }
}
