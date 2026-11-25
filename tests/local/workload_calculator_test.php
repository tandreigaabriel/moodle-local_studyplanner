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

namespace local_studyplanner\local;

/**
 * Study time is never planned in the past.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_studyplanner\local\workload_calculator
 * @covers     \local_studyplanner\local\plan_generator
 */
final class workload_calculator_test extends \advanced_testcase {
    /**
     * A student who can study 90 minutes every day from 18:00.
     *
     * @return int User id.
     */
    private function student(): int {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        for ($dow = 0; $dow <= 6; $dow++) {
            $DB->insert_record('local_studyplanner_availability', (object) [
                'userid' => $user->id, 'dayofweek' => $dow, 'availableminutes' => 90,
                'preferredstarttime' => 18 * 60, 'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        return (int) $user->id;
    }

    /**
     * Moments in the week of Monday 16 November 2026 (Europe/London) and the expected capacity.
     *
     * @return array
     */
    public static function moments_provider(): array {
        // Expected [minutes, start in minutes after midnight] for Monday..Sunday; start is irrelevant at 0 minutes.
        $full = [90, 1080];
        $none = [0, null];
        return [
            'Monday 09:00: whole week' => ['2026-11-16 09:00', [$full, $full, $full, $full, $full, $full, $full]],
            'Wednesday 12:00: Mon, Tue gone' => ['2026-11-18 12:00', [$none, $none, $full, $full, $full, $full, $full]],
            'Wednesday 18:30: today starts now' => ['2026-11-18 18:30', [$none, $none, [60, 1110], $full, $full, $full, $full]],
            'Wednesday 18:32: rounded up to 18:35' => ['2026-11-18 18:32', [$none, $none, [55, 1115], $full, $full, $full, $full]],
            'Wednesday 20:48: today gone' => ['2026-11-18 20:48', [$none, $none, $none, $full, $full, $full, $full]],
            'Sunday 23:00: nothing left this week' => ['2026-11-22 23:00', [$none, $none, $none, $none, $none, $none, $none]],
        ];
    }

    /**
     * Past days get no capacity and today's window starts no earlier than now.
     *
     * @dataProvider moments_provider
     * @param string $moment Local time, Europe/London.
     * @param array $expected [minutes, start] per day, Monday first.
     */
    public function test_capacity_map_skips_time_already_gone(string $moment, array $expected): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/London');
        $userid = $this->student();
        $this->setUser($userid);

        $now = (new \DateTime($moment, new \DateTimeZone('Europe/London')))->getTimestamp();
        $weekstart = workload_calculator::get_week_start($now);
        $map = workload_calculator::build_capacity_map($userid, $weekstart, [], $now);

        $actual = array_map(
            fn(array $day): array => $day['minutes'] === 0 ? [0, null] : [$day['minutes'], $day['start']],
            array_values($map)
        );
        $this->assertSame($expected, $actual);
    }

    /**
     * A plan generated now contains no session that starts in the past.
     */
    public function test_generated_plan_has_no_sessions_in_the_past(): void {
        global $DB;
        $this->resetAfterTest();
        $userid = $this->student();
        $this->setUser($userid);
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($userid, $course->id, 'student');
        $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'duedate' => time() + 3 * DAYSECS]);

        plan_generator::generate_for_user($userid);

        $items = $DB->get_records('local_studyplanner_plan_items', ['userid' => $userid, 'status' => 'planned']);
        foreach ($items as $item) {
            $this->assertGreaterThanOrEqual(time() - MINSECS, (int) $item->plannedstart);
        }
    }
}
