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
 * List, add, edit and delete a student's own personal (non-Moodle) tasks.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_studyplanner\form\personal_task_form;
use local_studyplanner\local\plugin_guard;
use local_studyplanner\task\build_user_plan;

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/studyplanner:manageownplan', $context);
plugin_guard::require_enabled();

$action = optional_param('action', 'list', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studyplanner/personaltasks.php'));
$PAGE->set_title(get_string('personaltasks', 'local_studyplanner'));
$PAGE->set_heading(get_string('pluginname', 'local_studyplanner'));
$PAGE->set_pagelayout('standard');

if ($action === 'delete' && $id > 0) {
    require_sesskey();
    $DB->get_record('local_studyplanner_personal_tasks', ['id' => $id, 'userid' => $USER->id], '*', MUST_EXIST);
    $DB->delete_records('local_studyplanner_personal_tasks', ['id' => $id, 'userid' => $USER->id]);
    redirect(
        new moodle_url('/local/studyplanner/personaltasks.php'),
        get_string('taskdeleted', 'local_studyplanner'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($action === 'add' || $action === 'edit') {
    $existing = null;
    if ($action === 'edit' && $id > 0) {
        $existing = $DB->get_record('local_studyplanner_personal_tasks', ['id' => $id, 'userid' => $USER->id], '*', MUST_EXIST);
    }

    $formurl = new moodle_url('/local/studyplanner/personaltasks.php', array_filter([
        'action' => $action,
        'id' => $id ?: null,
    ]));
    $form = new personal_task_form($formurl);

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/local/studyplanner/personaltasks.php'));
    } else if ($data = $form->get_data()) {
        $now = time();
        $record = (object) [
            'userid' => $USER->id,
            'title' => $data->title,
            'description' => $data->description,
            'duedate' => (int) $data->duedate,
            'estimatedminutes' => (int) $data->estimatedminutes,
            'priority' => (int) $data->priority,
            'status' => 'active',
            'timemodified' => $now,
        ];

        if (!empty($data->id)) {
            $record->id = $data->id;
            $DB->update_record('local_studyplanner_personal_tasks', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_studyplanner_personal_tasks', $record);
        }

        $task = new build_user_plan();
        $task->set_custom_data(['userid' => (int) $USER->id]);
        \core\task\manager::queue_adhoc_task($task, true);

        redirect(
            new moodle_url('/local/studyplanner/personaltasks.php'),
            get_string('tasksaved', 'local_studyplanner'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else if (!$form->is_submitted() && $existing) {
        $form->set_data([
            'id' => $existing->id,
            'title' => $existing->title,
            'description' => $existing->description,
            'duedate' => $existing->duedate,
            'estimatedminutes' => $existing->estimatedminutes,
            'priority' => $existing->priority,
        ]);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('personaltasks', 'local_studyplanner'));
    echo html_writer::link(
        new moodle_url('/local/studyplanner/personaltasks.php'),
        get_string('back'),
        ['class' => 'mb-3 d-inline-block']
    );
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

$tasks = $DB->get_records_select(
    'local_studyplanner_personal_tasks',
    'userid = :userid AND status = :status',
    ['userid' => $USER->id, 'status' => 'active'],
    'duedate ASC, timecreated ASC'
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('personaltasks', 'local_studyplanner'));

echo html_writer::link(
    new moodle_url('/local/studyplanner/personaltasks.php', ['action' => 'add']),
    get_string('addpersonaltask', 'local_studyplanner'),
    ['class' => 'btn btn-primary mb-3']
);

if ($tasks) {
    $table = new html_table();
    $table->head = [
        get_string('tasktitle', 'local_studyplanner'),
        get_string('taskduedate', 'local_studyplanner'),
        get_string('estimatedminutes', 'local_studyplanner'),
        '',
    ];

    foreach ($tasks as $task) {
        $editurl = new moodle_url('/local/studyplanner/personaltasks.php', ['action' => 'edit', 'id' => $task->id]);
        $deleteurl = new moodle_url(
            '/local/studyplanner/personaltasks.php',
            ['action' => 'delete', 'id' => $task->id, 'sesskey' => sesskey()]
        );

        $table->data[] = [
            format_string($task->title),
            $task->duedate ? userdate($task->duedate, get_string('strftimedate', 'langconfig')) : '-',
            $task->estimatedminutes,
            html_writer::link($editurl, get_string('edit')) . ' | ' . html_writer::link($deleteurl, get_string('delete')),
        ];
    }

    echo html_writer::table($table);
} else {
    echo html_writer::tag('p', get_string('nopersonaltasks', 'local_studyplanner'));
}

echo $OUTPUT->footer();
