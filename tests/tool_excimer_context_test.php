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

use tool_excimer\context;

defined('MOODLE_INTERNAL') || die();

/**
 * Units tests for the context class.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_excimer_context_test extends advanced_testcase {

    public function test_stripparamters(): void {
        $param = ['a' => '1', 'b' => 2, 'c' => 3];
        $paramexpect = $param;
        $this->assertEquals($paramexpect, context::stripparameters($param));

        $param = ['a' => '1', 'sesskey' => 2, 'c' => 3];
        $paramexpect = ['a' => '1', 'sesskey' => '', 'c' => 3];
        $this->assertEquals($paramexpect, context::stripparameters($param));

        $param = ['a' => '1', 'sesskey' => 2, 'FLAMEME' => 3];
        $paramexpect = ['a' => '1', 'sesskey' => ''];
        $this->assertEquals($paramexpect, context::stripparameters($param));
    }
}