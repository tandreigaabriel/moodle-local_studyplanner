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
 * Resolves how many minutes a given activity is expected to take to study.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\local;

/**
 * Resolves estimated study duration and importance for a course activity.
 *
 * Resolution order: a teacher-set override in local_studyplanner_activity_estimates,
 * then a site-configured default for the module type, then a built-in
 * fallback default.
 */
class duration_estimator {
    /**
     * Fallback duration estimates in minutes, keyed by module type.
     *
     * Limited to the module types activity_provider actually recognises as
     * having a due/close date (see activity_provider::DUEDATE_FIELDS) --
     * other module types never reach this lookup.
     *
     * @var array<string, int>
     */
    private const DEFAULTS = [
        'assign' => 120,
        'quiz' => 30,
        'choice' => 5,
        'workshop' => 90,
        'lesson' => 45,
    ];

    /**
     * Returns the estimated study duration for an activity, in minutes.
     *
     * @param int $cmid The course module id.
     * @param string $modname The module type, for example "assign" or "quiz".
     * @return int Estimated minutes.
     */
    public static function estimate_minutes(int $cmid, string $modname): int {
        global $DB;

        $override = $DB->get_record('local_studyplanner_activity_estimates', ['cmid' => $cmid], 'estimatedminutes');
        if ($override && (int) $override->estimatedminutes > 0) {
            return (int) $override->estimatedminutes;
        }

        $configured = get_config('local_studyplanner', 'defaultduration_' . $modname);
        if ($configured !== false && (int) $configured > 0) {
            return (int) $configured;
        }

        return self::DEFAULTS[$modname] ?? 30;
    }

    /**
     * Returns the teacher-set importance flag for an activity.
     *
     * @param int $cmid The course module id.
     * @return string One of "essential", "recommended" or "optional".
     */
    public static function get_importance(int $cmid): string {
        global $DB;

        $record = $DB->get_record('local_studyplanner_activity_estimates', ['cmid' => $cmid], 'importance');

        return $record ? $record->importance : 'recommended';
    }
}
