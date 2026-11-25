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
 * This Week's study plan view, with a per-day workload heatmap.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_studyplanner\local\plan_generator;
use local_studyplanner\local\plugin_guard;
use local_studyplanner\output\week_page;

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/studyplanner:manageownplan', $context);
plugin_guard::require_enabled();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studyplanner/week.php'));
$PAGE->set_title(get_string('thisweek', 'local_studyplanner'));
$PAGE->set_heading(get_string('pluginname', 'local_studyplanner'));
$PAGE->set_pagelayout('standard');

plan_generator::ensure_current_plan((int) $USER->id);

$renderer = $PAGE->get_renderer('local_studyplanner');
$page = new week_page((int) $USER->id);

echo $OUTPUT->header();
echo $renderer->render_week_page($page);
echo $OUTPUT->footer();
