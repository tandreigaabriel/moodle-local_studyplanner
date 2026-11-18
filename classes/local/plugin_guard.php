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
 * Enforces the site-wide enable/disable admin setting on every entry page.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\local;

/**
 * A tiny guard called by every page controller, right after require_login()
 * and the capability check, so an admin can take the whole planner offline
 * without uninstalling it.
 */
class plugin_guard {
    /**
     * Throws if the plugin has been disabled by an administrator.
     *
     * @return void
     */
    public static function require_enabled(): void {
        if (!get_config('local_studyplanner', 'enabled')) {
            throw new \moodle_exception('plugindisabled', 'local_studyplanner');
        }
    }
}
