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
}
