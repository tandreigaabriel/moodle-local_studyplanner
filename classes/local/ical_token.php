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
 * Generates and verifies the per-user token used to authenticate calendar feed requests.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\local;

/**
 * Calendar feed subscriptions have no logged-in session, so access is proven with a
 * token instead. The token is derived, not stored, and changes automatically if the
 * user's password changes, the same approach core's own calendar export uses.
 */
class ical_token {
    /**
     * Returns the calendar feed token for a user.
     *
     * The password is looked up fresh from the database rather than read off the
     * passed-in user object, because the session $USER has its password field
     * stripped out for security.
     *
     * @param \stdClass $user The user record. Must include id.
     * @return string The token.
     */
    public static function get(\stdClass $user): string {
        global $CFG, $DB;

        $password = $DB->get_field('user', 'password', ['id' => $user->id]);

        return sha1($user->id . $password . $CFG->siteidentifier . 'local_studyplanner');
    }
}
