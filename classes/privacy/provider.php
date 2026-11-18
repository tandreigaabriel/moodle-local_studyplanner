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
 * Privacy API provider for local_studyplanner.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\privacy;

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Every table this plugin writes to holds personal data scoped to one
 * student, regardless of which course or activity it references, so all of
 * it is modelled under that student's own user context (context_user) --
 * the same pattern core uses for private, non-course-scoped user data.
 *
 * local_studyplanner_activity_estimates is deliberately not declared here: it stores
 * teacher-authored duration/importance settings per activity with no userid
 * column, so it holds no personal data to export or delete.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Tables in this plugin that store personal data, all keyed by userid.
     *
     * @var string[]
     */
    private const PERSONAL_TABLES = [
        'local_studyplanner_preferences',
        'local_studyplanner_availability',
        'local_studyplanner_plans',
        'local_studyplanner_plan_items',
        'local_studyplanner_personal_tasks',
        'local_studyplanner_reschedule_log',
        'local_studyplanner_reminders',
    ];

    /**
     * Describes what personal data this plugin stores.
     *
     * @param collection $collection The initialised collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_studyplanner_preferences', [
            'maxsessionminutes' => 'privacy:metadata:local_studyplanner_preferences:maxsessionminutes',
            'breakminutes' => 'privacy:metadata:local_studyplanner_preferences:breakminutes',
            'intensity' => 'privacy:metadata:local_studyplanner_preferences:intensity',
            'remindersenabled' => 'privacy:metadata:local_studyplanner_preferences:remindersenabled',
            'remindertime' => 'privacy:metadata:local_studyplanner_preferences:remindertime',
        ], 'privacy:metadata:local_studyplanner_preferences');

        $collection->add_database_table('local_studyplanner_availability', [
            'dayofweek' => 'privacy:metadata:local_studyplanner_availability:dayofweek',
            'availableminutes' => 'privacy:metadata:local_studyplanner_availability:availableminutes',
            'preferredstarttime' => 'privacy:metadata:local_studyplanner_availability:preferredstarttime',
        ], 'privacy:metadata:local_studyplanner_availability');

        $collection->add_database_table('local_studyplanner_plans', [
            'periodstart' => 'privacy:metadata:local_studyplanner_plans:periodstart',
            'periodend' => 'privacy:metadata:local_studyplanner_plans:periodend',
            'status' => 'privacy:metadata:local_studyplanner_plans:status',
        ], 'privacy:metadata:local_studyplanner_plans');

        $collection->add_database_table('local_studyplanner_plan_items', [
            'title' => 'privacy:metadata:local_studyplanner_plan_items:title',
            'plannedstart' => 'privacy:metadata:local_studyplanner_plan_items:plannedstart',
            'plannedend' => 'privacy:metadata:local_studyplanner_plan_items:plannedend',
            'estimatedminutes' => 'privacy:metadata:local_studyplanner_plan_items:estimatedminutes',
            'actualminutes' => 'privacy:metadata:local_studyplanner_plan_items:actualminutes',
            'status' => 'privacy:metadata:local_studyplanner_plan_items:status',
            'duedate' => 'privacy:metadata:local_studyplanner_plan_items:duedate',
        ], 'privacy:metadata:local_studyplanner_plan_items');

        $collection->add_database_table('local_studyplanner_personal_tasks', [
            'title' => 'privacy:metadata:local_studyplanner_personal_tasks:title',
            'description' => 'privacy:metadata:local_studyplanner_personal_tasks:description',
            'duedate' => 'privacy:metadata:local_studyplanner_personal_tasks:duedate',
            'estimatedminutes' => 'privacy:metadata:local_studyplanner_personal_tasks:estimatedminutes',
            'status' => 'privacy:metadata:local_studyplanner_personal_tasks:status',
        ], 'privacy:metadata:local_studyplanner_personal_tasks');

        $collection->add_database_table('local_studyplanner_reschedule_log', [
            'oldstart' => 'privacy:metadata:local_studyplanner_reschedule_log:oldstart',
            'oldend' => 'privacy:metadata:local_studyplanner_reschedule_log:oldend',
            'newstart' => 'privacy:metadata:local_studyplanner_reschedule_log:newstart',
            'newend' => 'privacy:metadata:local_studyplanner_reschedule_log:newend',
            'reason' => 'privacy:metadata:local_studyplanner_reschedule_log:reason',
        ], 'privacy:metadata:local_studyplanner_reschedule_log');

        $collection->add_database_table('local_studyplanner_reminders', [
            'remindertype' => 'privacy:metadata:local_studyplanner_reminders:remindertype',
            'lastsentat' => 'privacy:metadata:local_studyplanner_reminders:lastsentat',
        ], 'privacy:metadata:local_studyplanner_reminders');

        return $collection;
    }

    /**
     * Returns the user context for a user, if they have any data stored.
     *
     * @param int $userid The user to search.
     * @return contextlist The list of contexts containing user data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        if (self::user_has_data($userid)) {
            $contextlist->add_user_context($userid);
        }

        return $contextlist;
    }

    /**
     * Adds the context's user to the userlist, if that user has any data stored.
     *
     * @param userlist $userlist The userlist to add users to.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof context_user) {
            return;
        }

        if (self::user_has_data((int) $context->instanceid)) {
            $userlist->add_user($context->instanceid);
        }
    }

    /**
     * Exports all personal data for the user context(s) in the approved list.
     *
     * @param approved_contextlist $contextlist The approved contexts to export.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || (int) $context->instanceid !== (int) $contextlist->get_user()->id) {
                continue;
            }

            $userid = (int) $context->instanceid;

            self::export_single_record(
                $context,
                $DB->get_record('local_studyplanner_preferences', ['userid' => $userid]),
                ['Study planner', 'Preferences']
            );

            self::export_multiple_records(
                $context,
                $DB->get_records('local_studyplanner_availability', ['userid' => $userid]),
                ['Study planner', 'Availability']
            );

            self::export_multiple_records(
                $context,
                $DB->get_records('local_studyplanner_plans', ['userid' => $userid]),
                ['Study planner', 'Plans']
            );

            self::export_multiple_records(
                $context,
                $DB->get_records('local_studyplanner_plan_items', ['userid' => $userid]),
                ['Study planner', 'Plan items']
            );

            self::export_multiple_records(
                $context,
                $DB->get_records('local_studyplanner_personal_tasks', ['userid' => $userid]),
                ['Study planner', 'Personal tasks']
            );

            self::export_multiple_records(
                $context,
                $DB->get_records('local_studyplanner_reschedule_log', ['userid' => $userid]),
                ['Study planner', 'Reschedule history']
            );

            self::export_multiple_records(
                $context,
                $DB->get_records('local_studyplanner_reminders', ['userid' => $userid]),
                ['Study planner', 'Reminders']
            );
        }
    }

    /**
     * Deletes all personal data for every user in a given context.
     *
     * @param context $context The context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if ($context instanceof context_user) {
            self::delete_user_data((int) $context->instanceid);
        }
    }

    /**
     * Deletes personal data for the user(s) in the approved contextlist.
     *
     * @param approved_contextlist $contextlist The approved contexts to delete data for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_user && (int) $context->instanceid === (int) $contextlist->get_user()->id) {
                self::delete_user_data((int) $context->instanceid);
            }
        }
    }

    /**
     * Deletes personal data for a list of users within a single context.
     *
     * @param approved_userlist $userlist The approved user list to delete data for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof context_user) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            if ((int) $userid === (int) $context->instanceid) {
                self::delete_user_data((int) $userid);
            }
        }
    }

    /**
     * Checks whether a user has any row in any of this plugin's personal data tables.
     *
     * @param int $userid The user id.
     * @return bool
     */
    private static function user_has_data(int $userid): bool {
        global $DB;

        foreach (self::PERSONAL_TABLES as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deletes every row belonging to one user across all personal data tables.
     *
     * @param int $userid The user id.
     * @return void
     */
    private static function delete_user_data(int $userid): void {
        global $DB;

        foreach (self::PERSONAL_TABLES as $table) {
            $DB->delete_records($table, ['userid' => $userid]);
        }
    }

    /**
     * Exports a single record (or does nothing if it does not exist) at a subcontext path.
     *
     * @param \context $context The user context being exported.
     * @param \stdClass|false $record The record to export.
     * @param string[] $subcontext The export subcontext path.
     * @return void
     */
    private static function export_single_record(\context $context, $record, array $subcontext): void {
        if (!$record) {
            return;
        }

        unset($record->id, $record->userid);
        writer::with_context($context)->export_data($subcontext, $record);
    }

    /**
     * Exports a set of records at a subcontext path, one export node per record id.
     *
     * @param \context $context The user context being exported.
     * @param \stdClass[] $records The records to export.
     * @param string[] $subcontext The export subcontext path.
     * @return void
     */
    private static function export_multiple_records(\context $context, array $records, array $subcontext): void {
        foreach ($records as $record) {
            $id = $record->id;
            unset($record->id, $record->userid);
            writer::with_context($context)->export_data(array_merge($subcontext, [(string) $id]), $record);
        }
    }
}
