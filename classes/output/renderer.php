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
 * Output renderer for local_studyplanner.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\output;

/**
 * Renders the plugin's renderable pages from their mustache templates.
 */
class renderer extends \plugin_renderer_base {
    /**
     * Renders the "Today" plan view.
     *
     * @param today_page $page The page data.
     * @return string HTML.
     */
    public function render_today_page(today_page $page): string {
        $data = $page->export_for_template($this);

        return $this->render_from_template('local_studyplanner/today', $data);
    }

    /**
     * Renders the "This Week" plan view.
     *
     * @param week_page $page The page data.
     * @return string HTML.
     */
    public function render_week_page(week_page $page): string {
        $data = $page->export_for_template($this);

        return $this->render_from_template('local_studyplanner/week', $data);
    }
}
