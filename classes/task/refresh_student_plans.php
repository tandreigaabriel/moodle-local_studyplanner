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
 * Nightly orchestrator that queues a plan refresh for every opted-in student.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\task;

/**
 * Queues one build_user_plan ad-hoc task per user who has planner
 * preferences saved. This scheduled task itself never touches plan data
 * directly and returns in seconds regardless of how many students use the
 * planner; the actual per-user work happens in the ad-hoc queue.
 */
class refresh_student_plans extends \core\task\scheduled_task {
    /**
     * Returns the task's display name, shown in the scheduled task admin list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:refreshstudentplans', 'local_studyplanner');
    }

    /**
     * Queues a build_user_plan ad-hoc task for every user with saved preferences.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $userids = $DB->get_fieldset_select('local_studyplanner_preferences', 'userid', '1 = 1');
        $queued = 0;

        foreach ($userids as $userid) {
            $task = new build_user_plan();
            $task->set_custom_data(['userid' => (int) $userid]);
            \core\task\manager::queue_adhoc_task($task, true);
            $queued++;
        }

        mtrace("refresh_student_plans: {$queued} build_user_plan tasks queued.");
    }
}
