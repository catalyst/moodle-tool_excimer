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

/**
 * Helper functions for Excimer data formatting
 *
 * @package   tool_excimer
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_excimer;

use ExcimerLogEntry;

defined('MOODLE_INTERNAL') || die();

class excimer_helper {

    /**
     * Format a call graph path for an Excimer log.
     *
     * @param ExcimerLogEntry $entry
     * @return str e.g. '/var/www/site/index.php|my_class::my_function|my_function_2'
     *
     */
    public static function get_graph_path(ExcimerLogEntry $entry) {
        $trace = $entry->getTrace();
        $stack = array_map(fn($call) => self::format_call($call), $trace);
        return join('|', array_reverse($stack));
    }

    /**
     * Format an individual function call in a excimer log entry trace:
     *
     * @param array $call An a associative array of ['file', 'class', 'function'] (any).
     * @return string of e.g. '/var/www/site/index.php', 'my_class::my_function', 'my_function'
     */
    public static function format_call($call) {
        if (isset($call['function'])) {
            if (isset($call['class'])) {
                return $call['class'] . '::' . $call['function'];
            } else {
                return $call['function'];
            }
        } else {
            if (isset($call['file'])) {
                return $call['file'];
            } else {
                return 'UNKNOWN';
            }
        }
    }

    /**
     * Render D3 tree stucture as HTML OL tag.
     *
     * (Degugging function, unused)
     *
     * @param array $ol Associative array of ['name', 'value', 'children'].
     * @return str HTML ordered list
     */
    public static function ol($ol) {
        echo "<ol>";
        foreach ($ol as $li) {
            echo "<li>" . $li['name'] . " (" . $li['value'] . ")</li>";
            if (isset($li['children'])) {
                echo self::ol($li['children']);
            }
        }
        echo "</ol>";
    }

}
