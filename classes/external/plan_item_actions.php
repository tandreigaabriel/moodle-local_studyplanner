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
 * AJAX-callable actions a student can take on their own plan items.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_studyplanner\local\rescheduling_service;

/**
 * Mark complete, skip, and reschedule actions for a student's own plan items.
 *
 * Every method enforces ownership: a student may only act on plan items that
 * belong to them, regardless of the itemid passed in.
 */
class plan_item_actions extends external_api {
    /**
     * Describes the parameters for mark_complete().
     *
     * @return external_function_parameters
     */
    public static function mark_complete_parameters(): external_function_parameters {
        return new external_function_parameters([
            'itemid' => new external_value(PARAM_INT, 'Plan item id'),
        ]);
    }

    /**
     * Marks one of the current user's own plan items as completed.
     *
     * @param int $itemid The plan item id.
     * @return array{success: bool, message: string}
     */
    public static function mark_complete(int $itemid): array {
        $params = self::validate_parameters(self::mark_complete_parameters(), ['itemid' => $itemid]);
        self::validate_context(\context_system::instance());
        require_capability('local/studyplanner:manageownplan', \context_system::instance());

        $item = self::get_own_item($params['itemid']);

        return self::apply_status($item, 'completed');
    }

    /**
     * Describes the return structure for mark_complete().
     *
     * @return external_single_structure
     */
    public static function mark_complete_returns(): external_single_structure {
        return self::result_structure();
    }

    /**
     * Describes the parameters for skip().
     *
     * @return external_function_parameters
     */
    public static function skip_parameters(): external_function_parameters {
        return new external_function_parameters([
            'itemid' => new external_value(PARAM_INT, 'Plan item id'),
        ]);
    }

    /**
     * Marks one of the current user's own plan items as skipped.
     *
     * @param int $itemid The plan item id.
     * @return array{success: bool, message: string}
     */
    public static function skip(int $itemid): array {
        $params = self::validate_parameters(self::skip_parameters(), ['itemid' => $itemid]);
        self::validate_context(\context_system::instance());
        require_capability('local/studyplanner:manageownplan', \context_system::instance());

        $item = self::get_own_item($params['itemid']);

        return self::apply_status($item, 'skipped');
    }

    /**
     * Describes the return structure for skip().
     *
     * @return external_single_structure
     */
    public static function skip_returns(): external_single_structure {
        return self::result_structure();
    }

    /**
     * Describes the parameters for reschedule().
     *
     * @return external_function_parameters
     */
    public static function reschedule_parameters(): external_function_parameters {
        return new external_function_parameters([
            'itemid' => new external_value(PARAM_INT, 'Plan item id'),
        ]);
    }

    /**
     * Moves one of the current user's own missed/planned items into the next available slot.
     *
     * @param int $itemid The plan item id.
     * @return array{success: bool, message: string}
     */
    public static function reschedule(int $itemid): array {
        $params = self::validate_parameters(self::reschedule_parameters(), ['itemid' => $itemid]);
        self::validate_context(\context_system::instance());
        require_capability('local/studyplanner:manageownplan', \context_system::instance());

        // Ownership check only; reschedule_item() reloads and updates the item itself.
        self::get_own_item($params['itemid']);
        $moved = rescheduling_service::reschedule_item($params['itemid'], 'manual');

        return [
            'success' => $moved,
            'message' => get_string($moved ? 'itemrescheduled' : 'itemrescheduledfailed', 'local_studyplanner'),
        ];
    }

    /**
     * Describes the return structure for reschedule().
     *
     * @return external_single_structure
     */
    public static function reschedule_returns(): external_single_structure {
        return self::result_structure();
    }

    /**
     * Loads a plan item and confirms it belongs to the currently logged-in user.
     *
     * @param int $itemid The plan item id.
     * @return \stdClass The plan item record.
     */
    private static function get_own_item(int $itemid): \stdClass {
        global $DB, $USER;

        $item = $DB->get_record('local_studyplanner_plan_items', ['id' => $itemid], '*', MUST_EXIST);
        if ((int) $item->userid !== (int) $USER->id) {
            throw new \moodle_exception('nopermissions', 'error', '', 'study plan item');
        }

        return $item;
    }

    /**
     * Applies a new status to a plan item.
     *
     * @param \stdClass $item The plan item record.
     * @param string $status The new status value.
     * @return array{success: bool, message: string}
     */
    private static function apply_status(\stdClass $item, string $status): array {
        global $DB;

        $item->status = $status;
        $item->timemodified = time();
        $DB->update_record('local_studyplanner_plan_items', $item);

        return [
            'success' => true,
            'message' => get_string('itemstatusupdated', 'local_studyplanner'),
        ];
    }

    /**
     * Builds the shared return structure used by every action in this class.
     *
     * @return external_single_structure
     */
    private static function result_structure(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the action succeeded'),
            'message' => new external_value(PARAM_TEXT, 'Human readable result message'),
        ]);
    }
}
