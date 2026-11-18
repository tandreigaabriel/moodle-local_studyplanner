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

namespace local_studyplanner\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/studyplanner/tests/generator_helper.php');

/**
 * Plan item web service tests.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_studyplanner\external\plan_item_actions
 */
final class plan_item_actions_test extends \advanced_testcase {
    /**
     * A student cannot change another student's session; the owner can.
     */
    public function test_only_owner_can_change_item(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $itemid = local_studyplanner_test_fill_user($a->id)['local_studyplanner_plan_items'];
        $before = $DB->get_field('local_studyplanner_plan_items', 'status', ['id' => $itemid]);

        $this->setUser($b);
        foreach (['mark_complete', 'skip', 'reschedule'] as $method) {
            try {
                plan_item_actions::$method($itemid);
                $this->fail($method . ' changed another student\'s item');
            } catch (\moodle_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
        $this->assertSame($before, $DB->get_field('local_studyplanner_plan_items', 'status', ['id' => $itemid]));

        $this->setUser($a);
        plan_item_actions::mark_complete($itemid);
        $this->assertSame('completed', $DB->get_field('local_studyplanner_plan_items', 'status', ['id' => $itemid]));
    }
}
