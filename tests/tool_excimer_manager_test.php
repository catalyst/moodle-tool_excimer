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
 * Units tests for the manager class.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_excimer_manager_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test is_profiling().
     *
     * @covers \tool_excimer\manager::is_profiling
     */
    public function test_is_profiling(): void {
        // Do not assume any config is set or unset.
        set_config('enable_auto', 0, 'tool_excimer');
        unset($_REQUEST[manager::FLAME_ME_PARAM_NAME]);
        unset($_REQUEST[manager::FLAME_ON_PARAM_NAME]);
        unset($_REQUEST[manager::FLAME_OFF_PARAM_NAME]);

        $this->assertFalse(manager::is_profiling());

        $_REQUEST[manager::FLAME_ME_PARAM_NAME] = 1;
        $this->assertTrue(manager::is_profiling());

        unset($_REQUEST[manager::FLAME_ME_PARAM_NAME]);
        $this->assertFalse(manager::is_profiling());

        $_REQUEST[manager::FLAME_ON_PARAM_NAME] = 1;
        $this->assertTrue(manager::is_profiling());

        unset($_REQUEST[manager::FLAME_ON_PARAM_NAME]);
        $this->assertTrue(manager::is_profiling());

        $_REQUEST[manager::FLAME_OFF_PARAM_NAME] = 1;
        $this->assertFalse(manager::is_profiling());

        unset($_REQUEST[manager::FLAME_OFF_PARAM_NAME]);
        $this->assertFalse(manager::is_profiling());

        set_config('enable_auto', 1, 'tool_excimer');
        $this->assertTrue(manager::is_profiling());

        set_config('enable_auto', 0, 'tool_excimer');
        $this->assertFalse(manager::is_profiling());
    }

    /**
     * Covers approximate_increment
     *
     * @covers \tool_excimer\manager::approximate_increment
     */
    public function test_approximate_increment() {
        // Run tests for the first portion of expected counts.
        for ($expectedcount = 0; $expectedcount <= 10; $expectedcount++) {
            $current = 0;
            $sequence = $this->get_even_distribution_sequence($current, $expectedcount);

            // Increment the count for each psuedo random number in the sequence.
            foreach ($sequence as $number) {
                $current = manager::approximate_increment($current, 1, $number);
            }

            // Assert that the final count is exactly equal to the expected count.
            $this->assertEquals($expectedcount, $current);

            // Test higher increments from all possible starting points.
            for ($start = 0; $start <= $expectedcount; $start++) {
                // To exactly match the expected count, the increment needs to differ depending on the start.
                // This can be based off the number of items in the even distribution sequence.
                $increment = count($this->get_even_distribution_sequence($start, $expectedcount));

                // Assert that incrementing by the same amount will return the same expected count.
                $current = manager::approximate_increment($start, $increment);
                $this->assertEquals($expectedcount, $current);
            }
        }
    }

    /**
     * Returns an even distribution of non random numbers to get a perfect result.
     * If starting at 0, this would return 1/1, 1/2, 2/2, 1/4, 2/4, 3/4, 4/4, 1/8...
     *
     * @param int $start
     * @param int $expectedcount
     * @return array
     */
    private function get_even_distribution_sequence(int $start, int $expectedcount): array {
        $sequence = [];
        for ($i = $start; $i < $expectedcount; $i++) {
            $approx = 2 ** $i;
            for ($j = 1; $j <= $approx; $j++) {
                $sequence[] = $j / $approx;
            }
        }
        return $sequence;
    }
}
