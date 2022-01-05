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

use tool_excimer\converter;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the flamed3_node class.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_excimer_flamed3_node_test  extends advanced_testcase {

    const TEST_DATA1 = "a;b;c 3\na;t;c 2";
    const TEST_DATA2 = "a;b 5\na;x 7\nb;z;y 10\nb;z;a 1";

    public function test_find_first_subnode() {
        $n1 = converter::process(self::TEST_DATA1);
        $n2 = $n1->find_first_subnode('b');
        $this->assertEquals('b', $n2->name);
        $this->assertEquals(3, $n2->value);
        $this->assertEquals(1, count($n2->children));

        $n1 = converter::process(self::TEST_DATA2);
        $n2 = $n1->find_first_subnode('x');
        $this->assertEquals('x', $n2->name);
        $this->assertEquals(7, $n2->value);
        $this->assertEquals(0, count($n2->children));

        $n2 = $n1->find_first_subnode('z');
        $this->assertEquals('z', $n2->name);
        $this->assertEquals(11, $n2->value);
        $this->assertEquals(2, count($n2->children));

        $n2 = $n2->find_first_subnode('a');
        $this->assertEquals('a', $n2->name);
        $this->assertEquals(1, $n2->value);
        $this->assertEquals(0, count($n2->children));
    }
}
