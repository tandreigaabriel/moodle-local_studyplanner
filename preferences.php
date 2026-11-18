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
 * Weekly availability and session preferences page.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_studyplanner\form\preferences_form;
use local_studyplanner\local\plugin_guard;
use local_studyplanner\local\preferences_manager;
use local_studyplanner\task\build_user_plan;

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/studyplanner:manageownplan', $context);
plugin_guard::require_enabled();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studyplanner/preferences.php'));
$PAGE->set_title(get_string('preferences', 'local_studyplanner'));
$PAGE->set_heading(get_string('pluginname', 'local_studyplanner'));
$PAGE->set_pagelayout('standard');

$form = new preferences_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/studyplanner/today.php'));
} else if ($data = $form->get_data()) {
    preferences_manager::save((int) $USER->id, $data);

    // Availability just changed: refresh this user's plan straight away rather than
    // waiting for the nightly orchestrator.
    $task = new build_user_plan();
    $task->set_custom_data(['userid' => (int) $USER->id]);
    \core\task\manager::queue_adhoc_task($task, true);

    redirect(
        new moodle_url('/local/studyplanner/today.php'),
        get_string('preferencessaved', 'local_studyplanner'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} else if (!$form->is_submitted()) {
    $form->set_data(preferences_manager::load_form_data((int) $USER->id));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('preferences', 'local_studyplanner'));
$form->display();

$feedurl = new moodle_url('/local/studyplanner/calendar.php');

echo $OUTPUT->heading(get_string('calendarfeed', 'local_studyplanner'), 3);
echo html_writer::tag('p', get_string('calendarfeedintro', 'local_studyplanner'));
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'readonly' => 'readonly',
    'class' => 'form-control',
    'value' => $feedurl->out(false),
    'onclick' => 'this.select();',
]);

echo $OUTPUT->footer();
