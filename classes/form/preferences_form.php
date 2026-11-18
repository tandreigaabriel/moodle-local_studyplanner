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
 * Weekly availability and session preferences form.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\form;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Lets a student declare, per weekday, how much study time they have and
 * when they prefer it to start, plus session-shape preferences.
 */
class preferences_form extends \moodleform {
    /**
     * Defines the form fields.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'availabilityheader', get_string('availability', 'local_studyplanner'));
        $mform->setExpanded('availabilityheader');

        $hours = [];
        for ($hour = 0; $hour <= 23; $hour++) {
            $hours[$hour] = sprintf('%02d:00', $hour);
        }

        foreach ($this->weekdays() as $dow => $label) {
            $group = [
                $mform->createElement('text', 'minutes_' . $dow, '', ['size' => 4]),
                $mform->createElement('select', 'start_' . $dow, '', $hours),
            ];
            $mform->setType('minutes_' . $dow, PARAM_INT);
            $mform->setDefault('minutes_' . $dow, 0);
            $mform->setDefault('start_' . $dow, 18);

            $mform->addGroup(
                $group,
                'daygroup_' . $dow,
                $label,
                [' ' . get_string('availableminutes', 'local_studyplanner') . ' ' . get_string('from', 'local_studyplanner') . ' '],
                false
            );
        }

        $mform->addElement('header', 'sessionheader', get_string('sessionsettings', 'local_studyplanner'));
        $mform->setExpanded('sessionheader');

        $mform->addElement('text', 'maxsessionminutes', get_string('maxsessionminutes', 'local_studyplanner'), ['size' => 5]);
        $mform->setType('maxsessionminutes', PARAM_INT);
        $mform->addRule('maxsessionminutes', null, 'required', null, 'client');

        $mform->addElement('text', 'breakminutes', get_string('breakminutes', 'local_studyplanner'), ['size' => 5]);
        $mform->setType('breakminutes', PARAM_INT);
        $mform->addRule('breakminutes', null, 'required', null, 'client');

        $intensities = [
            'light' => get_string('intensitylight', 'local_studyplanner'),
            'normal' => get_string('intensitynormal', 'local_studyplanner'),
            'intensive' => get_string('intensityintensive', 'local_studyplanner'),
        ];
        $mform->addElement('select', 'intensity', get_string('intensity', 'local_studyplanner'), $intensities);

        $mform->addElement('advcheckbox', 'remindersenabled', get_string('remindersenabled', 'local_studyplanner'));
        $mform->setDefault('remindersenabled', 1);

        $mform->addElement('select', 'remindertimehour', get_string('remindertime', 'local_studyplanner'), $hours);
        $mform->setDefault('remindertimehour', 8);
        $mform->hideIf('remindertimehour', 'remindersenabled', 'notchecked');

        $this->add_action_buttons();
    }

    /**
     * Validates the submitted preferences.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return string[] Validation errors, keyed by field name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ((int) $data['maxsessionminutes'] <= 0) {
            $errors['maxsessionminutes'] = get_string('errorpositiveminutes', 'local_studyplanner');
        }
        if ((int) $data['breakminutes'] < 0) {
            $errors['breakminutes'] = get_string('errornonnegativeminutes', 'local_studyplanner');
        }

        return $errors;
    }

    /**
     * Returns the weekday labels, keyed by PHP date('w') weekday number (0 = Sunday).
     *
     * @return array<int, string>
     */
    private function weekdays(): array {
        return [
            1 => get_string('monday', 'calendar'),
            2 => get_string('tuesday', 'calendar'),
            3 => get_string('wednesday', 'calendar'),
            4 => get_string('thursday', 'calendar'),
            5 => get_string('friday', 'calendar'),
            6 => get_string('saturday', 'calendar'),
            0 => get_string('sunday', 'calendar'),
        ];
    }
}
