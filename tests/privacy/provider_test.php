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

namespace local_studyplanner\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_studyplanner\local\ical_token;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/studyplanner/tests/generator_helper.php');

/**
 * Privacy provider tests.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_studyplanner\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var string[] Tables keyed by userid. */
    private const TABLES = ['local_studyplanner_preferences', 'local_studyplanner_availability', 'local_studyplanner_plans',
        'local_studyplanner_plan_items', 'local_studyplanner_personal_tasks', 'local_studyplanner_reschedule_log',
        'local_studyplanner_reminders'];

    /**
     * Every column of every personal table is described, and each description string exists.
     */
    public function test_metadata_describes_every_column(): void {
        global $DB;
        $collection = provider::get_metadata(new collection('local_studyplanner'));
        $described = [];
        foreach ($collection->get_collection() as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $described[$item->get_name()] = array_keys($item->get_privacy_fields());
            }
            foreach ((array) $item->get_privacy_fields() as $string) {
                $this->assertTrue(get_string_manager()->string_exists($string, 'local_studyplanner'), $string);
            }
        }
        foreach (self::TABLES as $table) {
            $columns = array_diff(array_keys($DB->get_columns($table)), ['id', 'planid', 'planitemid', 'personaltaskid',
                'modname', 'source', 'segmentindex', 'segmentcount', 'generatedby']);
            $this->assertEqualsCanonicalizing($columns, array_intersect($columns, $described[$table] ?? []), $table);
        }
    }

    /**
     * Contexts, users, export, and deletion that leaves other students untouched.
     */
    public function test_export_and_delete_only_touch_one_user(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        local_studyplanner_test_fill_user($a->id);
        local_studyplanner_test_fill_user($b->id);
        ical_token::get($a);
        $context = \context_user::instance($a->id);

        $this->assertEquals([$context->id], array_map('intval', provider::get_contexts_for_userid($a->id)->get_contextids()));
        $userlist = new userlist($context, 'local_studyplanner');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$a->id], array_map('intval', $userlist->get_userids()));

        provider::export_user_data(new approved_contextlist($a, 'local_studyplanner', [$context->id]));
        $this->assertTrue(writer::with_context($context)->has_any_data());
        provider::export_user_preferences($a->id);
        $exported = writer::with_context(\context_system::instance())->get_user_preferences('local_studyplanner');
        $secret = get_user_preferences(ical_token::PREFERENCE, null, $a->id);
        $this->assertNotEmpty($secret);
        $this->assertStringNotContainsString($secret, json_encode($exported));

        provider::delete_data_for_user(new approved_contextlist($a, 'local_studyplanner', [$context->id]));
        foreach (self::TABLES as $table) {
            $this->assertFalse($DB->record_exists($table, ['userid' => $a->id]), $table);
            $this->assertTrue($DB->record_exists($table, ['userid' => $b->id]), $table);
        }
        $this->assertNull(get_user_preferences(ical_token::PREFERENCE, null, $a->id));
    }

    /**
     * Deleting a list of users in a user context, and a whole user context.
     */
    public function test_delete_for_users_and_context(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        local_studyplanner_test_fill_user($a->id);
        local_studyplanner_test_fill_user($b->id);

        $context = \context_user::instance($a->id);
        provider::delete_data_for_users(new approved_userlist($context, 'local_studyplanner', [$a->id]));
        provider::delete_data_for_all_users_in_context(\context_user::instance($b->id));
        foreach (self::TABLES as $table) {
            $this->assertSame(0, $DB->count_records($table), $table);
        }
    }
}
