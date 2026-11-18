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
 * Filters plan items to activities the student can still see.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\local;

/**
 * Plan items keep a copy of the activity title. After an unenrolment, or when a teacher hides an
 * activity, the copy must not be shown until the nightly refresh removes the session.
 */
class item_visibility {
    /**
     * Keep personal tasks, and Moodle activity sessions only while the activity is visible to the student.
     *
     * @param \stdClass[] $items Plan item records, all for $userid.
     * @param int $userid
     * @return \stdClass[] The visible items, keys preserved.
     */
    public static function filter(array $items, int $userid): array {
        $enrolled = null;
        $modinfos = [];
        $visible = [];
        foreach ($items as $key => $item) {
            if ($item->source !== 'moodle_activity') {
                $visible[$key] = $item;
                continue;
            }
            if ($enrolled === null) {
                $enrolled = array_map('intval', array_keys(enrol_get_users_courses($userid, true, 'id')));
            }
            $courseid = (int) $item->courseid;
            if (!in_array($courseid, $enrolled, true)) {
                continue;
            }
            if (!isset($modinfos[$courseid])) {
                $modinfos[$courseid] = get_fast_modinfo($courseid, $userid);
            }
            $cms = $modinfos[$courseid]->get_cms();
            $cm = $cms[(int) $item->cmid] ?? null;
            if ($cm && $cm->uservisible) {
                $visible[$key] = $item;
            }
        }
        return $visible;
    }
}
