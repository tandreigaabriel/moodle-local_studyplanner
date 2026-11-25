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
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\local;

/**
 * Calendar feed subscriptions have no logged-in session, so access is proven with a
 * token instead. The token is derived, not stored, and changes automatically if the
 * user's password changes, the same approach core's own calendar export uses.
 */
class ical_token {
    /** User preference holding the per-user secret mixed into the token. */
    public const PREFERENCE = 'local_studyplanner_icalsecret';

    /**
     * Returns the calendar feed token for a user.
     *
     * Same idea as core calendar export tokens (user id + password hash + site identifier),
     * plus a per-user random secret so the student can revoke a leaked link without
     * changing their password. Changing the password also invalidates the link.
     *
     * @param \stdClass $user The user record. Must include id.
     * @return string The token.
     */
    public static function get(\stdClass $user): string {
        global $CFG, $DB;

        $password = $DB->get_field('user', 'password', ['id' => $user->id]);
        $secret = get_user_preferences(self::PREFERENCE, null, $user->id);
        if ($secret === null || $secret === '') {
            $secret = self::reset((int) $user->id);
        }

        return sha1($user->id . $password . $CFG->siteidentifier . 'local_studyplanner' . $secret);
    }

    /**
     * Checks a token in constant time.
     *
     * @param \stdClass $user The user record. Must include id.
     * @param string $token The token from the request.
     * @return bool True when the token is valid.
     */
    public static function verify(\stdClass $user, string $token): bool {
        $secret = get_user_preferences(self::PREFERENCE, null, $user->id);
        if ($secret === null || $secret === '') {
            return false;
        }
        return hash_equals(self::get($user), $token);
    }

    /**
     * Replaces the per-user secret, which invalidates every previously issued feed link.
     *
     * @param int $userid The user id.
     * @return string The new secret.
     */
    public static function reset(int $userid): string {
        $secret = random_string(32);
        set_user_preference(self::PREFERENCE, $secret, $userid);
        return $secret;
    }
}
