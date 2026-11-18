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
 * Rule-based priority scoring for study plan tasks.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\local;

/**
 * Computes a simple, explainable priority score for a task.
 *
 * Deliberately rule-based rather than predictive, so the scheduling engine
 * stays swappable behind this interface if a paid AI-assisted mode is added
 * later. See docblock references to duedate proximity and importance below.
 */
class priority_calculator {
    /** @var int Priority added when a task's due date has already passed. */
    private const SCORE_OVERDUE = 100;

    /** @var int Priority added when a task is due within the next 24 hours. */
    private const SCORE_DUE_24H = 80;

    /** @var int Priority added when a task is due within the next 3 days. */
    private const SCORE_DUE_3DAYS = 60;

    /** @var int Priority added for an activity flagged essential by the teacher. */
    private const SCORE_ESSENTIAL = 30;

    /** @var int Priority added for an activity with no importance flag set. */
    private const SCORE_RECOMMENDED = 10;

    /** @var int Priority subtracted for an activity flagged optional by the teacher. */
    private const SCORE_OPTIONAL = -20;

    /**
     * Calculates a priority score from a task's due date and importance.
     *
     * @param int $duedate Unix timestamp the task is due, or 0 for no due date.
     * @param string $importance One of "essential", "recommended" or "optional".
     * @param int $now Reference "now" timestamp; 0 uses the current time.
     * @return int The calculated priority score. Higher schedules first.
     */
    public static function calculate(int $duedate, string $importance, int $now = 0): int {
        if ($now === 0) {
            $now = time();
        }

        $score = 0;

        if ($duedate > 0) {
            if ($duedate < $now) {
                $score += self::SCORE_OVERDUE;
            } else if ($duedate <= $now + DAYSECS) {
                $score += self::SCORE_DUE_24H;
            } else if ($duedate <= $now + (3 * DAYSECS)) {
                $score += self::SCORE_DUE_3DAYS;
            }
        }

        switch ($importance) {
            case 'essential':
                $score += self::SCORE_ESSENTIAL;
                break;
            case 'optional':
                $score += self::SCORE_OPTIONAL;
                break;
            default:
                $score += self::SCORE_RECOMMENDED;
        }

        return $score;
    }
}
