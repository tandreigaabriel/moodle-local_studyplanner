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
 * Teacher form for setting study duration and importance per course activity.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Lists every dated activity in a course with an editable estimated study
 * duration and an importance flag (essential, recommended, optional).
 */
class activity_estimate_form extends \moodleform {
    /**
     * Defines the form fields, one group per activity passed in via custom data.
     *
     * Custom data: courseid (int), cms (array of stdClass: cmid, name, modname,
     * currentestimate, currentimportance).
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $cms = $this->_customdata['cms'];

        $importanceoptions = [
            'essential' => get_string('importanceessential', 'local_studyplanner'),
            'recommended' => get_string('importancerecommended', 'local_studyplanner'),
            'optional' => get_string('importanceoptional', 'local_studyplanner'),
        ];

        foreach ($cms as $cm) {
            $group = [
                $mform->createElement('text', 'estimate_' . $cm->cmid, '', ['size' => 5]),
                $mform->createElement('select', 'importance_' . $cm->cmid, '', $importanceoptions),
            ];
            $mform->setType('estimate_' . $cm->cmid, PARAM_INT);
            $mform->setDefault('estimate_' . $cm->cmid, $cm->currentestimate);
            $mform->setDefault('importance_' . $cm->cmid, $cm->currentimportance);

            $label = $cm->name . ' (' . $cm->modname . ')';
            $mform->addGroup($group, 'group_' . $cm->cmid, $label, [' '], false);
        }

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons();
    }
}
