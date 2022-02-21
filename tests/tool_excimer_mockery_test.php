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

require_once(__DIR__ . "/excimer_testcase.php"); // This is needed. File will not be automatically included.

/**
 * Tests the excimer_mockry class.
 *
 * @package    tool_excimer
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_excimer_mockery_test extends excimer_testcase {

    /**
     * Tests excimer_mockery::get_log_entry_stub()
     */
    public function test_log_entry(): void {
        $stub = $this->get_log_entry_stub(['c::a', 'b', 'c'], 100.3);
        $this->assertInstanceOf('\ExcimerLogEntry', $stub);

        $fns = $this->get_traces_from_entry($stub);
        $this->assertEquals(['c', 'b', 'c::a'], $fns);

        $this->assertEquals(100.3, $stub->getTimestamp());
    }

    /**
     * Tests excimer_testcase::get_log_stub()
     */
    public function test_log(): void {
        $log = $this->get_log_stub([
            ['c::a', 'b', 'c'],
            ['c::a', 'd', 'e'],
            ['m', 'n', 'f'],
            ['M::m', 'n', 'e'],
            ['M::m', 'l;12'],
        ]);
        $expected = [
            ['c', 'b', 'c::a'],
            ['e', 'd', 'c::a'],
            ['f', 'n', 'm'],
            ['e', 'n', 'M::m'],
            ['{closure:l(12)}', 'M::m'],
        ];

        $trace = $this->get_traces_from_entry($log[0]);
        $this->assertEquals($expected[0], $trace);
        $traces = $this->get_traces_from_log($log);
        $this->assertEquals($expected, $traces);
    }

    /**
     * Tests excimer_testcase::get_profiler_stub()
     */
    public function test_profiler(): void {
        $profiler = $this->get_profiler_stub([
            ['co::ab', 'b', 'c'],
            ['c::a', 'd', 'e'],
            ['m', 'n', 'e'],
            ['M::m', 'n', 'e'],
            ['M::x', 'l;12'],
        ]);
        $expected = [
            ['c', 'b', 'co::ab'],
            ['e', 'd', 'c::a'],
            ['e', 'n', 'm'],
            ['e', 'n', 'M::m'],
            ['{closure:l(12)}', 'M::x'],
        ];

        $traces = $this->get_traces_from_profile($profiler);
        $this->assertEquals($expected, $traces);
    }

    /**
     * Converts a trace node def to a string.
     * See excimer_testcase::get_log_entry_stub() for info about string format.
     *
     * @param array $tracenode An assoc. array containing a trace node as returned by ExcimerLogEntry::getTrace().
     * @return string
     */
    protected function extract_name_from_trace(array $tracenode): string {
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

    /**
     * Extracts traces from a profiler.
     *
     * @param \ExcimerProfiler $profile
     * @return array
     */
    protected function get_traces_from_profile(\ExcimerProfiler $profile): array {
        return $this->get_traces_from_log($profile->getLog());
    }

    /**
     * Extracts traces from a log.
     *
     * @param \ExcimerLog $log
     * @return array
     */
    protected function get_traces_from_log(\ExcimerLog $log): array {
        $ret = [];
        foreach ($log as $entry) {
            $ret[] = $this->get_traces_from_entry($entry);
        }
        return $ret;
    }

    /**
     * Extracts traces from a log entry.
     *
     * @param \ExcimerLogEntry $entry
     * @return array
     */
    protected function get_traces_from_entry(\ExcimerLogEntry $entry): array {
        $ret = [];
        $trace = $entry->getTrace();
        foreach ($trace as $fn) {
            $ret[] = $this->extract_name_from_trace($fn);
        }
        return $ret;
    }
}
