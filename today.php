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
 * Today's study plan view.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_studyplanner\local\plan_generator;
use local_studyplanner\local\plugin_guard;
use local_studyplanner\output\today_page;

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/studyplanner:manageownplan', $context);
plugin_guard::require_enabled();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studyplanner/today.php'));
$PAGE->set_title(get_string('today', 'local_studyplanner'));
$PAGE->set_heading(get_string('pluginname', 'local_studyplanner'));
$PAGE->set_pagelayout('standard');

// Cheap for a single user; cohort-wide regeneration always goes through the ad-hoc queue instead.
plan_generator::ensure_current_plan((int) $USER->id);

$renderer = $PAGE->get_renderer('local_studyplanner');
$page = new today_page((int) $USER->id);

echo $OUTPUT->header();
echo $renderer->render_today_page($page);
echo $OUTPUT->footer();
