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
 * Lists a course's activities and saves teacher-set duration/importance estimates.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\local;

/**
 * Backs the teacher-facing course estimates page (courseestimates.php).
 */
class activity_estimate_manager {
    /**
     * Lists every visible activity in a course, with its current estimate if one exists.
     *
     * @param \stdClass $course The course record.
     * @return \stdClass[] Rows: cmid, name, modname, currentestimate, currentimportance.
     */
    public static function list_course_activities(\stdClass $course): array {
        global $DB;

        $modinfo = get_fast_modinfo($course);
        $existing = $DB->get_records('local_studyplanner_activity_estimates', ['courseid' => $course->id]);
        $byidcm = [];
        foreach ($existing as $row) {
            $byidcm[(int) $row->cmid] = $row;
        }

        $rows = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            $override = $byidcm[$cm->id] ?? null;
            $rows[] = (object) [
                'cmid' => (int) $cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
                'currentestimate' => $override ? (int) $override->estimatedminutes : duration_estimator::estimate_minutes(
                    (int) $cm->id,
                    $cm->modname
                ),
                'currentimportance' => $override ? $override->importance : 'recommended',
            ];
        }

        return $rows;
    }

    /**
     * Saves submitted activity_estimate_form data for a course.
     *
     * @param int $courseid The course id.
     * @param \stdClass $data Submitted form data.
     * @param int[] $cmids The course module ids the form was built for.
     * @return void
     */
    public static function save(int $courseid, \stdClass $data, array $cmids): void {
        global $DB;

        $now = time();

        foreach ($cmids as $cmid) {
            $estimatefield = 'estimate_' . $cmid;
            $importancefield = 'importance_' . $cmid;

            $existing = $DB->get_record('local_studyplanner_activity_estimates', ['cmid' => $cmid]);
            $record = (object) [
                'cmid' => $cmid,
                'courseid' => $courseid,
                'estimatedminutes' => (int) ($data->$estimatefield ?? 0),
                'importance' => $data->$importancefield ?? 'recommended',
                'timemodified' => $now,
            ];

            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('local_studyplanner_activity_estimates', $record);
            } else {
                $record->timecreated = $now;
                $DB->insert_record('local_studyplanner_activity_estimates', $record);
            }
        }
    }
}
