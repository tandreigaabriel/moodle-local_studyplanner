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
 * Ad-hoc task that (re)generates one student's weekly study plan.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\task;

/**
 * Regenerates a single user's plan. Queued per-user by refresh_student_plans
 * so a slow user's plan generation never blocks the others, and can be
 * queued on-demand whenever preferences, availability or a due date change.
 */
class build_user_plan extends \core\task\adhoc_task {
    /**
     * Returns the task's display name, shown in the admin ad-hoc task queue.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:builduserplan', 'local_studyplanner');
    }

    /**
     * Generates the plan for the user id passed via custom data.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $userid = (int) ($data->userid ?? 0);

        if ($userid <= 0) {
            mtrace('build_user_plan: missing userid in custom data, skipping.');
            return;
        }

        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            mtrace("build_user_plan: user {$userid} not found, skipping.");
            return;
        }

        // Day/week boundaries are computed in the current $USER's timezone, so this
        // task must impersonate the target student rather than run as the generic
        // cron user (whose timezone defaults to the site setting) - otherwise a
        // student in a different timezone gets tasks bucketed onto the wrong day.
        \core\cron::setup_user($user);

        mtrace("build_user_plan: generating plan for user {$userid}.");
        \local_studyplanner\local\plan_generator::generate_for_user($userid);
        mtrace("build_user_plan: plan generated for user {$userid}.");

        \core\cron::reset_user_cache();
    }
}
