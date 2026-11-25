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
 * Teacher page for setting per-activity study duration and importance in one course.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_studyplanner\form\activity_estimate_form;
use local_studyplanner\local\activity_estimate_manager;
use local_studyplanner\local\plugin_guard;

require(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);

require_login($course);
$context = context_course::instance($course->id);
require_capability('local/studyplanner:manageestimates', $context);
plugin_guard::require_enabled();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studyplanner/courseestimates.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('courseestimates', 'local_studyplanner'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

$cms = activity_estimate_manager::list_course_activities($course);
$cmids = array_column($cms, 'cmid');

$form = new activity_estimate_form(null, ['courseid' => $courseid, 'cms' => $cms]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
} else if ($data = $form->get_data()) {
    activity_estimate_manager::save($courseid, $data, $cmids);
    redirect(
        new moodle_url('/local/studyplanner/courseestimates.php', ['courseid' => $courseid]),
        get_string('estimatessaved', 'local_studyplanner'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('courseestimates', 'local_studyplanner'));

if (empty($cms)) {
    echo html_writer::tag('p', get_string('nocourseactivities', 'local_studyplanner'));
} else {
    echo html_writer::tag('p', get_string('courseestimatesintro', 'local_studyplanner'));
    $form->display();
}

echo $OUTPUT->footer();
