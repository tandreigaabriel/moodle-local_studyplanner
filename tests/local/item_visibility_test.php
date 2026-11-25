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

namespace local_studyplanner\local;

/**
 * Plan items for activities the student can no longer see are not shown.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_studyplanner\local\item_visibility
 */
final class item_visibility_test extends \advanced_testcase {
    /**
     * A plan item record as stored by the generator.
     *
     * @param int $userid
     * @param string $source
     * @param int $courseid
     * @param int $cmid
     * @return \stdClass
     */
    private function item(int $userid, string $source, int $courseid = 0, int $cmid = 0): \stdClass {
        global $DB;
        $now = time();
        $id = $DB->insert_record('local_studyplanner_plan_items', (object) ['planid' => 1, 'userid' => $userid,
            'courseid' => $courseid, 'cmid' => $cmid, 'title' => 'Secret exam title', 'plannedstart' => $now,
            'plannedend' => $now + HOURSECS, 'source' => $source, 'timecreated' => $now, 'timemodified' => $now]);
        return $DB->get_record('local_studyplanner_plan_items', ['id' => $id]);
    }

    /**
     * Unenrolment, a hidden activity and a deleted activity remove the session; personal tasks stay.
     */
    public function test_filter(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $course = $gen->create_course();
        $other = $gen->create_course();
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($student->id, $other->id, 'student');
        $visible = $gen->create_module('assign', ['course' => $course->id]);
        $hidden = $gen->create_module('assign', ['course' => $course->id]);
        $deleted = $gen->create_module('assign', ['course' => $course->id]);
        $unenrolled = $gen->create_module('assign', ['course' => $other->id]);

        $items = [
            'visible' => $this->item($student->id, 'moodle_activity', $course->id, $visible->cmid),
            'hidden' => $this->item($student->id, 'moodle_activity', $course->id, $hidden->cmid),
            'deleted' => $this->item($student->id, 'moodle_activity', $course->id, $deleted->cmid),
            'unenrolled' => $this->item($student->id, 'moodle_activity', $other->id, $unenrolled->cmid),
            'personal' => $this->item($student->id, 'personal_task'),
        ];
        $this->assertEqualsCanonicalizing(array_keys($items), array_keys(item_visibility::filter($items, $student->id)));

        set_coursemodule_visible($hidden->cmid, 0);
        course_delete_module($deleted->cmid);
        $plugin = enrol_get_plugin('manual');
        $instance = array_values(enrol_get_instances($other->id, true))[0];
        $plugin->unenrol_user($instance, $student->id);
        \course_modinfo::clear_instance_cache();

        $this->assertEqualsCanonicalizing(['visible', 'personal'], array_keys(item_visibility::filter($items, $student->id)));
    }
}
