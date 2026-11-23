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
 * Tests course activity estimates.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_studyplanner\local\activity_estimate_manager
 */
final class activity_estimate_manager_test extends \advanced_testcase {
    /**
     * Saving and updating estimates affects only the supplied activities.
     */
    public function test_save_and_list(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cmid = (int) $page->cmid;
        $data = (object) ["estimate_$cmid" => 45, "importance_$cmid" => 'essential'];
        activity_estimate_manager::save($course->id, $data, [$cmid]);
        $rows = activity_estimate_manager::list_course_activities($course);
        $this->assertCount(1, $rows);
        $this->assertSame(45, $rows[0]->currentestimate);
        $this->assertSame('essential', $rows[0]->currentimportance);
        $data->{"estimate_$cmid"} = 90;
        $data->{"importance_$cmid"} = 'optional';
        activity_estimate_manager::save($course->id, $data, [$cmid]);
        $this->assertEquals(1, $DB->count_records('local_studyplanner_activity_estimates', ['cmid' => $cmid]));
        $rows = activity_estimate_manager::list_course_activities($course);
        $this->assertSame(90, $rows[0]->currentestimate);
        $this->assertSame('optional', $rows[0]->currentimportance);
    }
}
