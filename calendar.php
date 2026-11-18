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
 * iCalendar subscription feed of a student's study plan (token in the URL, revocable).
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

use local_studyplanner\local\ical_token;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/bennu/bennu.inc.php');

$userid = required_param('userid', PARAM_INT);
$token = required_param('token', PARAM_ALPHANUM);

$user = \core_user::get_user($userid);
$valid = $user && !$user->deleted && !$user->suspended && ical_token::verify($user, $token)
    && has_capability('local/studyplanner:manageownplan', context_system::instance(), $user);
if (!$valid) {
    die('Invalid authentication');
}

if (empty(get_config('local_studyplanner', 'enabled'))) {
    die('Study planner is disabled');
}

$items = $DB->get_records_select(
    'local_studyplanner_plan_items',
    'userid = :userid AND status <> :cancelled AND plannedstart > :since',
    ['userid' => $userid, 'cancelled' => 'cancelled', 'since' => time() - (30 * DAYSECS)],
    'plannedstart ASC'
);
$items = \local_studyplanner\local\item_visibility::filter($items, (int) $userid);

$hostaddress = preg_replace('#^https?://#', '', $CFG->wwwroot);

$ical = new iCalendar();
$ical->add_property('method', 'PUBLISH');
$ical->add_property('prodid', '-//Moodle Pty Ltd//NONSGML Study Planner ' . $CFG->version . '//EN');

foreach ($items as $item) {
    $ev = new iCalendar_event();
    $ev->add_property('uid', 'studyplanner-' . $item->id . '@' . $hostaddress);
    $ev->add_property('summary', format_string($item->title));
    $ev->add_property('class', 'PRIVATE');
    $ev->add_property('last-modified', Bennu::timestamp_to_datetime((int) $item->timemodified));
    $ev->add_property('dtstamp', Bennu::timestamp_to_datetime());
    $ev->add_property('dtstart', Bennu::timestamp_to_datetime((int) $item->plannedstart));
    $ev->add_property('dtend', Bennu::timestamp_to_datetime((int) $item->plannedend));
    $ical->add_component($ev);
}

$serialized = $ical->serialize();

header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
header('Pragma: no-cache');
header('Content-disposition: inline; filename=studyplan.ics');
header('Content-length: ' . strlen($serialized));
header('Content-type: text/calendar; charset=utf-8');

echo $serialized;
