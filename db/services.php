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
 * External function / AJAX service definitions for local_studyplanner.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_studyplanner_mark_complete' => [
        'classname' => '\local_studyplanner\external\plan_item_actions',
        'methodname' => 'mark_complete',
        'description' => 'Marks a study plan item as completed.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_studyplanner_skip' => [
        'classname' => '\local_studyplanner\external\plan_item_actions',
        'methodname' => 'skip',
        'description' => 'Marks a study plan item as skipped.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_studyplanner_reschedule' => [
        'classname' => '\local_studyplanner\external\plan_item_actions',
        'methodname' => 'reschedule',
        'description' => 'Reschedules a missed study plan item into the next available slot.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
