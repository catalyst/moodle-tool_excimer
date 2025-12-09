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
final class tool_excimer_helper_test extends \advanced_testcase {
    /**
     * Tests course_display_name function
     */
    public function test_course_display_name(): void {
        $this->resetAfterTest(true);

        // Test with real course.
        $course = $this->getDataGenerator()->create_course();
        $this->assertEquals($course->fullname, helper::course_display_name($course->id));

        // Test with course that does not exist.
        $this->assertEquals(
            get_string('deletedcourse', 'tool_excimer', $course->id + 1),
            helper::course_display_name($course->id + 1)
        );
    }

    /**
     * Tests course_display_link function
     */
    public function test_course_display_link(): void {
        $this->resetAfterTest(true);

        // Test with real course.
        $course = $this->getDataGenerator()->create_course();
        $this->assertNotEmpty(helper::course_display_link($course->id));

        // Test with null (should return empty string).
        $this->assertEquals('', helper::course_display_link());
    }

    /**
     * Providor for test_timeval_to_float
     *
     * @return array[]
     */
    public static function timeval_to_float_providor(): array {
        return [
            [ 'seconds' => 10, 'microseconds' => 687655, 'expected' => 10.687655 ],
            [ 'seconds' => 3, 'microseconds' => 256, 'expected' => 3.000256 ],
            [ 'seconds' => 0, 'microseconds' => 130000, 'expected' => 0.13 ],
        ];
    }

    /**
     * Tests timeval_to_float function.
     *
     * @param int $seconds
     * @param int $microseconds
     * @param float $expected
     * @dataProvider timeval_to_float_providor
     * @return void
     */
    public function test_timeval_to_float(int $seconds, int $microseconds, float $expected): void {
        $ru = [
            'ru_utime.tv_usec' => $microseconds,
            'ru_utime.tv_sec' => $seconds,
        ];
        $time = helper::timeval_to_float($ru, 'ru_utime');
        $this->assertEquals($expected, round($time, 6));
    }

    /**
     * Providor function for test_rusage_timediff
     *
     * @return array[]
     */
    public static function rusage_timediff_providor(): array {
        return [
            [
                'startusertimes' => ['seconds' => 5, 'microseconds' => 787560 ],
                'startkerneltimes' => [ 'seconds' => 2, 'microseconds' => 54378 ],
                'finishusertimes' => [ 'seconds' => 10, 'microseconds' => 665000 ],
                'finishkerneltimes' => [ 'seconds' => 3, 'microseconds' => 156705],
                'expecteduser' => (10.665000 - 5.787560),
                'expectedsystem' => (3.156705 - 2.054378),
            ],
            [
                'startusertimes' => ['seconds' => 16, 'microseconds' => 87639 ],
                'startkerneltimes' => [ 'seconds' => 3, 'microseconds' => 281734 ],
                'finishusertimes' => [ 'seconds' => 22, 'microseconds' => 96728 ],
                'finishkerneltimes' => [ 'seconds' => 10, 'microseconds' => 156705],
                'expecteduser' => (22.096728 - 16.087639),
                'expectedsystem' => (10.156705 - 3.281734),
            ],
        ];
    }

    /**
     * Test get_rusage_timediff function.
     *
     * @param array $startusertimes
     * @param array $startkerneltimes
     * @param array $finishusertimes
     * @param array $finishkerneltimes
     * @param float $expecteduser
     * @param float $expectedsystem
     * @dataProvider rusage_timediff_providor
     * @return void
     */
    public function test_rusage_timediff(
        array $startusertimes,
        array $startkerneltimes,
        array $finishusertimes,
        array $finishkerneltimes,
        float $expecteduser,
        float $expectedsystem
    ): void {
        $startru = [
            'ru_utime.tv_usec' => $startusertimes['microseconds'],
            'ru_utime.tv_sec' => $startusertimes['seconds'],
            'ru_stime.tv_usec' => $startkerneltimes['microseconds'],
            'ru_stime.tv_sec' => $startkerneltimes['seconds'],
        ];

        $finishru = [
            'ru_utime.tv_usec' => $finishusertimes['microseconds'],
            'ru_utime.tv_sec' => $finishusertimes['seconds'],
            'ru_stime.tv_usec' => $finishkerneltimes['microseconds'],
            'ru_stime.tv_sec' => $finishkerneltimes['seconds'],
        ];

        $times = helper::get_rusage_timediff($startru, $finishru);
        $this->assertEquals(round($expecteduser, 6), round($times['user'], 6));
        $this->assertEquals(round($expectedsystem, 6), round($times['system'], 6));
    }
}
