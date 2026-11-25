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
 * Reads upcoming graded/dated activities across a student's enrolled courses.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\local;

/**
 * Discovers Moodle activities with a due/close date that count towards a student's workload.
 */
class activity_provider {
    /**
     * Module table field that stores each supported module type's due/close date.
     *
     * @var array<string, string>
     */
    private const DUEDATE_FIELDS = [
        'assign' => 'duedate',
        'quiz' => 'timeclose',
        'choice' => 'timeclose',
        'workshop' => 'submissionend',
        'lesson' => 'deadline',
    ];

    /**
     * Returns dated activities from all of a user's active course enrolments.
     *
     * Activities already marked complete for the user are excluded. Activities
     * with no due date, or a due date beyond the horizon, are excluded too.
     * Overdue activities (due date already in the past) are always included,
     * since a missed deadline still needs to appear as work to catch up on.
     *
     * @param int $userid The user to collect activities for.
     * @param int $windowdays How many days ahead of now to look for due dates.
     * @return \stdClass[] Activity records: cmid, courseid, coursename, title, duedate, modname, url, overdue.
     */
    public static function get_upcoming_activities(int $userid, int $windowdays = 21): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $now = time();
        $horizon = $now + ($windowdays * DAYSECS);
        $activities = [];

        $courses = enrol_get_users_courses($userid, true, ['id', 'fullname', 'shortname']);

        foreach ($courses as $course) {
            $modinfo = get_fast_modinfo($course, $userid);
            $completion = new \completion_info($course);

            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible || !isset(self::DUEDATE_FIELDS[$cm->modname])) {
                    continue;
                }

                $duedate = self::get_activity_duedate($cm);
                if ($duedate <= 0 || $duedate > $horizon) {
                    continue;
                }

                // Pass wholecourse = true so completion state for every cm in this course is
                // preloaded once, rather than running one completion query per activity.
                if ($completion->is_enabled($cm)) {
                    $data = $completion->get_data($cm, true, $userid);
                    if (in_array($data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)) {
                        continue;
                    }
                }

                $activities[] = (object) [
                    'cmid' => (int) $cm->id,
                    'courseid' => (int) $course->id,
                    'coursename' => $course->fullname,
                    'title' => $cm->name,
                    'duedate' => $duedate,
                    'modname' => $cm->modname,
                    'url' => (new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false),
                    'overdue' => $duedate < $now,
                ];
            }
        }

        return $activities;
    }

    /**
     * Looks up a single course module's due/close date from its module table.
     *
     * @param \cm_info $cm The course module.
     * @return int The due date as a unix timestamp, or 0 if none is set.
     */
    private static function get_activity_duedate(\cm_info $cm): int {
        global $DB;

        $field = self::DUEDATE_FIELDS[$cm->modname];
        $instance = $DB->get_record($cm->modname, ['id' => $cm->instance], "id, $field");

        if (!$instance || empty($instance->$field)) {
            return 0;
        }

        return (int) $instance->$field;
    }
}
