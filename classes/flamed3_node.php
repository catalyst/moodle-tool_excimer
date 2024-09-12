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
 * A node in a flame d3 tree.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flamed3_node {

    /** @var string Node name */
    public $name;
    /** @var int Node value */
    public $value;
    /** @var array Node's children */
    public $children = [];

    /**
     * Create the node.
     *
     * @param string $name
     * @param int $value
     * @param array $children
     */
    public function __construct(string $name, int $value = 0, array $children = []) {
        $this->name = $name;
        $this->value = $value;
        $this->children = $children;
    }

    /**
     * Converts a trace tail to flamed3_nodes.
     *
     * @param array $tail
     * @param int $eventcount the count of events
     */
    public function add_excimer_trace_tail(array $tail, int $eventcount): void {

        // Increases the base event size by the sum of its events.
        $this->value += $eventcount;

        if (count($tail)) {
            $child = end($this->children);
            $fname = self::extract_name_from_trace($tail[0]);
            if ($child === false || $child->name != $fname) {
                $child = new flamed3_node($fname);
                $this->children[] = $child;
            }

            $child->add_excimer_trace_tail(array_slice($tail, 1), $eventcount);
        }
    }

    /**
     * Extracts data from Excimer log entries and converts to a flame node tree, compatible with
     * d3-flame-graph.
     *
     * @param iterable $entries
     * @return flamed3_node
     */
    public static function from_excimer_log_entries(iterable $entries): flamed3_node {
        $root = new flamed3_node('root');
        foreach ($entries as $entry) {
            $eventcount = $entry->getEventCount();
            $trace = array_reverse($entry->getTrace());
            $root->add_excimer_trace_tail($trace, $eventcount);
        }
        return $root;
    }

    /**
     * Loads data from an import. This will generally be a stdClass that has been loaded from json.
     *
     * @param \stdClass $data loaded from json
     * @return flamed3_node|null flamed3_node if valid, null if any part of the import is invalid
     */
    public static function from_import(\stdClass $data): ?flamed3_node {
        // Validate whether the stdClass is a flamed3_node.
        if (!isset($data->name) || !isset($data->value) || !isset($data->children)) {
            return null;
        }

        // Map children to flamenode instances.
        $children = [];
        foreach ($data->children as $child) {
            $node = self::from_import($child);
            if (empty($node)) {
                return null;
            }
            $children[] = $node;
        }

        return new flamed3_node($data->name, $data->value, $children);
    }

    /**
     * Returns a name to represent the call in the trace node.
     *
     * @param array $tracenode Associative array containing info about the function
     * @return string Either the filename, if not in a function, a bare function name, or a class::function combo.
     */
    public static function extract_name_from_trace(array $tracenode): string {
        global $CFG;

        if (isset($tracenode['file'])) {
            $tracenode['file'] = str_replace($CFG->dirroot . DIRECTORY_SEPARATOR, '', $tracenode['file']);
        }
        if (isset($tracenode['closure_line'])) {
            return '{closure:' . $tracenode['file'] . '(' . $tracenode['closure_line'] . ')}';
        }
        if (!isset($tracenode['function'])) {
            return $tracenode['file'];
        }
        if (isset($tracenode['class'])) {
            $clname = $tracenode['class'] . '::';
        } else {
            $clname = '';
        }
        return $clname . $tracenode['function'];
    }
}
