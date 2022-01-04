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

defined('MOODLE_INTERNAL') || die();

/**
 * A node in a flame d3 tree
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flamed3_node {

    public $name;
    public $value;
    public $children = [];

    public function __construct(string $name, $value, array $children = []) {
        $this->name = $name;
        $this->value = $value;
        $this->children = $children;
    }

    /**
     * Finds the first subnode with the given name, using depth first search.
     *
     * @param string $name
     * @return flamed3_node|null
     */
    public function find_first_subnode(string $name): ?flamed3_node {
        foreach ($this->children as $child) {
            if ($child->name == $name) {
                return $child;
            }
            $node = $child->find_first_subnode($name);
            if ($node) {
                return $node;
            }
        }
        return null;
    }

    /**
     * Extracts data from an Excimer log and converts it into a flame node tree.
     *
     * @param \ExcimerLog $log
     * @return flamed3_node
     */
    public static function from_excimer(\ExcimerLog $log): flamed3_node {
        global $CFG;

        // Some adjustments to work around a bug in Excimer. See https://phabricator.wikimedia.org/T296514.
        $flamedata = trim(str_replace("\n;", "\n", $log->formatCollapsed()));

        // Remove full pathing to dirroot and only keep pathing from site root (non-issue in most sane cases).
        $flamedata = str_replace($CFG->dirroot . DIRECTORY_SEPARATOR, '', $flamedata);

        return converter::process($flamedata);
    }
}
