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
 * Admin settings for local_studyplanner.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_studyplanner', get_string('pluginname', 'local_studyplanner'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_studyplanner/enabled',
        get_string('settingenabled', 'local_studyplanner'),
        get_string('settingenabled_desc', 'local_studyplanner'),
        1
    ));

    $settings->add(new admin_setting_heading(
        'local_studyplanner/durationsheading',
        get_string('settingdurationsheading', 'local_studyplanner'),
        get_string('settingdurationsheading_desc', 'local_studyplanner')
    ));

    $moduletypes = ['assign', 'quiz', 'choice', 'workshop', 'lesson'];
    foreach ($moduletypes as $moduletype) {
        $settings->add(new admin_setting_configtext(
            'local_studyplanner/defaultduration_' . $moduletype,
            get_string('settingdefaultduration' . $moduletype, 'local_studyplanner'),
            get_string('settingdefaultduration' . $moduletype . '_desc', 'local_studyplanner'),
            get_string('settingdefaultduration' . $moduletype . '_default', 'local_studyplanner'),
            PARAM_INT
        ));
    }
}
