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
 * Add/edit form for a student's own non-Moodle personal tasks.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\form;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Lets a student create a task that does not come from a Moodle activity,
 * such as "attend revision session" or "buy textbook".
 */
class personal_task_form extends \moodleform {
    /**
     * Defines the form fields.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'title', get_string('tasktitle', 'local_studyplanner'), ['maxlength' => 255, 'size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required', null, 'client');

        $mform->addElement(
            'textarea',
            'description',
            get_string('taskdescription', 'local_studyplanner'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('date_selector', 'duedate', get_string('taskduedate', 'local_studyplanner'), ['optional' => true]);

        $mform->addElement('text', 'estimatedminutes', get_string('estimatedminutes', 'local_studyplanner'), ['size' => 6]);
        $mform->setType('estimatedminutes', PARAM_INT);
        $mform->setDefault('estimatedminutes', 30);
        $mform->addRule('estimatedminutes', null, 'required', null, 'client');

        $priorities = [
            -10 => get_string('prioritylow', 'local_studyplanner'),
            0 => get_string('prioritynormal', 'local_studyplanner'),
            20 => get_string('priorityhigh', 'local_studyplanner'),
        ];
        $mform->addElement('select', 'priority', get_string('taskpriority', 'local_studyplanner'), $priorities);
        $mform->setDefault('priority', 0);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Validates the submitted task.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return string[] Validation errors, keyed by field name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ((int) $data['estimatedminutes'] <= 0) {
            $errors['estimatedminutes'] = get_string('errorpositiveminutes', 'local_studyplanner');
        }

        return $errors;
    }
}
