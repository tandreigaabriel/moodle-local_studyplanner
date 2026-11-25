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
 * Calendar feed token tests.
 *
 * @package    local_studyplanner
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_studyplanner\local\ical_token
 */
final class ical_token_test extends \advanced_testcase {
    /**
     * The token works only for its owner, and a reset revokes old links.
     */
    public function test_verify_and_reset(): void {
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();

        $this->assertFalse(ical_token::verify($a, 'anything'));
        $token = ical_token::get($a);
        $this->assertSame($token, ical_token::get($a));
        $this->assertTrue(ical_token::verify($a, $token));
        $this->assertFalse(ical_token::verify($b, $token));
        $this->assertFalse(ical_token::verify($a, ''));
        $this->assertFalse(ical_token::verify($a, strtoupper($token)));

        ical_token::reset($a->id);
        $this->assertFalse(ical_token::verify($a, $token));
        $this->assertTrue(ical_token::verify($a, ical_token::get($a)));
    }
}
