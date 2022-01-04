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
use tool_excimer\flamed3_node;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the converter class.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_excimer_converter_test extends advanced_testcase {

    const TEST_DATA = [
        [
            "a;b;c 3\na;t;c 2",
            [ 'name' => 'root', 'value' => 5, 'children' => [
                [ "name" => "a", "value" => 5, 'children' => [
                    [ "name" => "b", "value" => 3, 'children' => [
                        [ "name" => "c", "value" => 3, 'children' => [] ],
                    ]],
                    [ "name" => "t", "value" => 2, 'children' => [
                        [ "name" => "c", "value" => 2, 'children' => [] ],
                    ]]
                ]]
            ]]
        ],
        [
            "a;b 5\na;x 7\nb;x;y 10\nb;x;a 1",
            [ 'name' => 'root', 'value' => 23, 'children' => [
                [ 'name' => 'a', 'value' => 12, 'children' => [
                    [ 'name' => 'b', 'value' => 5, 'children' => [] ],
                    [ 'name' => 'x', 'value' => 7, 'children' => [] ]
                ]],
                [ 'name' => 'b', 'value' => 11, 'children' => [
                    [ 'name' => 'x', 'value' => 11, 'children' => [
                        [ 'name' => 'y', 'value' => 10, 'children' => [] ],
                        [ 'name' => 'a', 'value' => 1, 'children' => [] ]
                    ]]
                ]]
            ]]
        ]
    ];

    /**
     * Test converter::process().
     */
    public function test_process(): void {
        foreach (self::TEST_DATA as $testdata) {
            $this->assertEquals($this->from_array($testdata[1]), converter::process($testdata[0]));
        }
    }

    public function from_array(array $nodedata): flamed3_node {
        $node = new flamed3_node($nodedata['name'], $nodedata['value']);
        $children = [];
        foreach ($nodedata['children'] as $child) {
            $children[] = self::from_array($child);
        }
        $node->children = $children;
        return $node;
    }
}
