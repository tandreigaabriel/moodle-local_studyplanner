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
 * Loads and saves a student's preferences_form data to and from storage.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\local;

/**
 * Bridges preferences_form's flat field-per-weekday layout and the
 * normalised local_studyplanner_preferences / local_studyplanner_availability tables.
 */
class preferences_manager {
    /**
     * Loads a user's current preferences into preferences_form's expected shape.
     *
     * @param int $userid The user id.
     * @return array Form data, suitable for moodleform::set_data().
     */
    public static function load_form_data(int $userid): array {
        global $DB;

        $preferences = $DB->get_record('local_studyplanner_preferences', ['userid' => $userid]);
        $data = [
            'maxsessionminutes' => $preferences ? (int) $preferences->maxsessionminutes : 60,
            'breakminutes' => $preferences ? (int) $preferences->breakminutes : 10,
            'intensity' => $preferences ? $preferences->intensity : 'normal',
            'remindersenabled' => $preferences ? (int) $preferences->remindersenabled : 1,
            'remindertimehour' => $preferences ? (int) floor(((int) $preferences->remindertime) / 60) : 8,
        ];

        $availability = $DB->get_records('local_studyplanner_availability', ['userid' => $userid]);
        $byday = [];
        foreach ($availability as $row) {
            $byday[(int) $row->dayofweek] = $row;
        }

        for ($dow = 0; $dow <= 6; $dow++) {
            $row = $byday[$dow] ?? null;
            $data['minutes_' . $dow] = $row ? (int) $row->availableminutes : 0;
            $data['start_' . $dow] = $row ? (int) floor(((int) $row->preferredstarttime) / 60) : 18;
        }

        return $data;
    }

    /**
     * Saves submitted preferences_form data back to storage.
     *
     * @param int $userid The user id.
     * @param \stdClass $data Submitted form data.
     * @return void
     */
    public static function save(int $userid, \stdClass $data): void {
        global $DB;

        $now = time();

        $preferences = $DB->get_record('local_studyplanner_preferences', ['userid' => $userid]);
        $record = (object) [
            'userid' => $userid,
            'maxsessionminutes' => (int) $data->maxsessionminutes,
            'breakminutes' => (int) $data->breakminutes,
            'intensity' => $data->intensity,
            'remindersenabled' => (int) $data->remindersenabled,
            'remindertime' => (int) $data->remindertimehour * 60,
            'timemodified' => $now,
        ];

        if ($preferences) {
            $record->id = $preferences->id;
            $DB->update_record('local_studyplanner_preferences', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_studyplanner_preferences', $record);
        }

        for ($dow = 0; $dow <= 6; $dow++) {
            self::save_availability_day($userid, $dow, $data, $now);
        }
    }

    /**
     * Saves one weekday's availability row.
     *
     * @param int $userid The user id.
     * @param int $dow The weekday number, 0 (Sunday) to 6 (Saturday).
     * @param \stdClass $data Submitted form data.
     * @param int $now The current timestamp.
     * @return void
     */
    private static function save_availability_day(int $userid, int $dow, \stdClass $data, int $now): void {
        global $DB;

        $minutesfield = 'minutes_' . $dow;
        $startfield = 'start_' . $dow;

        $existing = $DB->get_record('local_studyplanner_availability', ['userid' => $userid, 'dayofweek' => $dow]);
        $record = (object) [
            'userid' => $userid,
            'dayofweek' => $dow,
            'availableminutes' => (int) ($data->$minutesfield ?? 0),
            'preferredstarttime' => (int) ($data->$startfield ?? 18) * 60,
            'timemodified' => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_studyplanner_availability', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_studyplanner_availability', $record);
        }
    }
}
