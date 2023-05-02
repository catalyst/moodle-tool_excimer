<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_excimer;

/**
 * Units tests for the helper class.
 *
 * @package   tool_excimer
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_excimer\helper
 */
class tool_excimer_helper_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests course_display_name function
     */
    public function test_course_display_name() {
        // Test with real course.
        $course = $this->getDataGenerator()->create_course();
        $this->assertEquals($course->fullname, helper::course_display_name($course->id));

        // Test with course that does not exist.
        $this->assertEquals(get_string('deletedcourse', 'tool_excimer', $course->id + 1),
            helper::course_display_name($course->id + 1));
    }

    /**
     * Tests course_display_link function
     */
    public function test_course_display_link() {
         // Test with real course.
         $course = $this->getDataGenerator()->create_course();
         $this->assertNotEmpty(helper::course_display_link($course->id));

         // Test with null (should return empty string).
         $this->assertEquals('', helper::course_display_link());
    }
}
