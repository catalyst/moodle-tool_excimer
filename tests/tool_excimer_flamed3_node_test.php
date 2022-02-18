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

use tool_excimer\flamed3_node;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . "/excimer_testcase.php"); // This is needed. File will not be automatically included.

/**
 * Tests the flamed3_node class.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_excimer_flamed3_node_test extends excimer_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_add_excimer_trace_tail(): void {
        $trace = [];
        $node = new flamed3_node('root', 0);
        $node->add_excimer_trace_tail($trace);
        $this->assertEquals(1, $node->value);
        $this->assertEquals(0, count($node->children));

        $trace = [['function' => 'a'], ['function' => 'b'], ['function' => 'c']];
        $node->add_excimer_trace_tail($trace);
        $this->assertEquals(2, $node->value);
        $this->assertEquals(1, count($node->children));
        $this->assertEquals('a', $node->children[0]->name);
        $this->assertEquals(1, $node->children[0]->value);
        $this->assertEquals('b', $node->children[0]->children[0]->name);
        $this->assertEquals('c', $node->children[0]->children[0]->children[0]->name);

        $trace = [['function' => 'a'], ['function' => 'd'], ['function' => 'e']];
        $node->add_excimer_trace_tail($trace);
        $this->assertEquals(3, $node->value);
        $this->assertEquals(1, count($node->children));
        $this->assertEquals('a', $node->children[0]->name);
        $this->assertEquals(2, $node->children[0]->value);
        $this->assertEquals('b', $node->children[0]->children[0]->name);
        $this->assertEquals('c', $node->children[0]->children[0]->children[0]->name);
        $this->assertEquals('a', $node->children[0]->name);
        $this->assertEquals('d', $node->children[0]->children[1]->name);
        $this->assertEquals('e', $node->children[0]->children[1]->children[0]->name);

        $trace = [['function' => 'm'], ['function' => 'n'], ['function' => 'e']];
        $node->add_excimer_trace_tail($trace);
        $this->assertEquals(4, $node->value);
        $this->assertEquals(2, count($node->children));
        $this->assertEquals('a', $node->children[0]->name);
        $this->assertEquals(2, $node->children[0]->value);
        $this->assertEquals('b', $node->children[0]->children[0]->name);
        $this->assertEquals('c', $node->children[0]->children[0]->children[0]->name);
        $this->assertEquals('a', $node->children[0]->name);
        $this->assertEquals('d', $node->children[0]->children[1]->name);
        $this->assertEquals('e', $node->children[0]->children[1]->children[0]->name);
        $this->assertEquals('m', $node->children[1]->name);
        $this->assertEquals('n', $node->children[1]->children[0]->name);
        $this->assertEquals('e', $node->children[1]->children[0]->children[0]->name);
    }

    public function test_from_excimer_log_entries(): void {
        $entries = [
            $this->get_log_entry_stub(['c::a', 'b', 'c']),
            $this->get_log_entry_stub(['c::a', 'd', 'e']),
            $this->get_log_entry_stub(['m', 'n', 'e']),
            $this->get_log_entry_stub(['M::m', 'n', 'e']),
            $this->get_log_entry_stub(['M::m', 'l;12']),
        ];

        $node = flamed3_node::from_excimer_log_entries($entries);
        $this->assertEquals(5, $node->value);
        $this->assertEquals(3, count($node->children));
        $this->assertEquals('c::a', $node->children[0]->name);
        $this->assertEquals(2, $node->children[0]->value);
        $this->assertEquals('b', $node->children[0]->children[0]->name);
        $this->assertEquals('c', $node->children[0]->children[0]->children[0]->name);
        $this->assertEquals('c::a', $node->children[0]->name);
        $this->assertEquals('d', $node->children[0]->children[1]->name);
        $this->assertEquals('e', $node->children[0]->children[1]->children[0]->name);
        $this->assertEquals('m', $node->children[1]->name);
        $this->assertEquals(1, $node->children[1]->value);
        $this->assertEquals('n', $node->children[1]->children[0]->name);
        $this->assertEquals('e', $node->children[1]->children[0]->children[0]->name);
        $this->assertEquals('M::m', $node->children[2]->name);
        $this->assertEquals('n', $node->children[2]->children[0]->name);
        $this->assertEquals('e', $node->children[2]->children[0]->children[0]->name);
        $this->assertEquals('{closure:l(12)}', $node->children[2]->children[1]->name);
    }

    public function test_from_excimer_log_entries_counts(): void {
        $entries = [
            $this->get_log_entry_stub(['c::a', 'b']),
            $this->get_log_entry_stub(['c::a', 'b']),
            $this->get_log_entry_stub(['c::a', 'd']),
            $this->get_log_entry_stub(['m']),
            $this->get_log_entry_stub(['m']),
            $this->get_log_entry_stub(['m']),
        ];

        $node = flamed3_node::from_excimer_log_entries($entries);

        $this->assertEquals(6, $node->value);
        $this->assertEquals(2, count($node->children));

        $this->assertEquals('c::a', $node->children[0]->name);
        $this->assertEquals(3, $node->children[0]->value);
        $this->assertEquals(2, count($node->children[0]->children));

        $this->assertEquals('b', $node->children[0]->children[0]->name);
        $this->assertEquals(2, $node->children[0]->children[0]->value);
        $this->assertEquals(0, count($node->children[0]->children[0]->children));

        $this->assertEquals('m', $node->children[1]->name);
        $this->assertEquals(3, $node->children[1]->value);
        $this->assertEquals(0, count($node->children[1]->children));
    }
}
