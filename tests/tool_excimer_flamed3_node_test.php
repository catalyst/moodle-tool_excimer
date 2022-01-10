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

use tool_excimer\flamed3_node;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the flamed3_node class.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Emulates the ExcimerLogEntry class for testing purposes.
 */
class fake_log_entry {
    public $trace = [];
    public function __construct($trace) {
        foreach (array_reverse($trace) as $fn) {
            $fn = explode('::', $fn);
            $node = [];
            if (isset($fn[1])) {
                $node['class'] = $fn[0];
                $node['function'] = $fn[1];
            } else {
                $node['function'] = $fn[0];
            }
            $this->trace[] = $node;
        }
    }

    /**
     * Emulates the getTrace() function from ExcimerLogEntry.
     * @return array
     */
    public function gettrace(): array {
        return $this->trace;
    }
}

class tool_excimer_flamed3_node_test extends advanced_testcase {

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
            new fake_log_entry(['c::a', 'b', 'c']),
            new fake_log_entry(['c::a', 'd', 'e']),
            new fake_log_entry(['m', 'n', 'e']),
            new fake_log_entry(['M::m', 'n', 'e']),
        ];

        $node = flamed3_node::from_excimer_log_entries($entries);
        $this->assertEquals(4, $node->value);
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
    }
}
