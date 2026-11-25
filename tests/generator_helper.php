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
 * Synthetic study planner records for tests.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Create one record in every personal table for a user.
 *
 * @param int $userid
 * @return array<string, int> Table => id.
 */
function local_studyplanner_test_fill_user(int $userid): array {
    global $DB;
    $now = time();
    $ids = [];
    $base = ['userid' => $userid, 'timecreated' => $now, 'timemodified' => $now];
    $ids['local_studyplanner_preferences'] = $DB->insert_record('local_studyplanner_preferences', (object) $base);
    $ids['local_studyplanner_availability'] = $DB->insert_record(
        'local_studyplanner_availability',
        (object) ($base + ['dayofweek' => 1])
    );
    $planid = $DB->insert_record(
        'local_studyplanner_plans',
        (object) ($base + ['periodstart' => $now, 'periodend' => $now + WEEKSECS])
    );
    $ids['local_studyplanner_plans'] = $planid;
    $itemid = $DB->insert_record('local_studyplanner_plan_items', (object) ($base + ['planid' => $planid,
        'title' => 'Session of ' . $userid, 'plannedstart' => $now, 'plannedend' => $now + HOURSECS,
        'source' => 'personal_task']));
    $ids['local_studyplanner_plan_items'] = $itemid;
    $ids['local_studyplanner_personal_tasks'] = $DB->insert_record(
        'local_studyplanner_personal_tasks',
        (object) ($base + ['title' => 'Task of ' . $userid])
    );
    $ids['local_studyplanner_reschedule_log'] = $DB->insert_record(
        'local_studyplanner_reschedule_log',
        (object) ['planitemid' => $itemid, 'userid' => $userid, 'oldstart' => $now, 'oldend' => $now,
        'reason' => 'missed',
        'timecreated' => $now]
    );
    $ids['local_studyplanner_reminders'] = $DB->insert_record(
        'local_studyplanner_reminders',
        (object) ($base + ['remindertype' => 'daily'])
    );
    return $ids;
}
