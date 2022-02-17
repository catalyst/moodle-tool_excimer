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

use PHPUnit\Framework\TestCase;

/**
 * Intermediary class to provide stubs for Excimer classes.
 *
 * @package    tool_eximer
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class excimer_mockery extends \advanced_testcase {

    /**
     * Creates a stub for the ExcimerLogEntry class for testing purposes.
     *
     * Each element of tracedef defines a function. Either
     * - x;12 - defines a closure file 'x', line 12.
     * - m::n - defines a class method m::n
     * - n    - defines a function
     *
     * @param array $tracedef
     * @return \ExcimerLogEntry|mixed|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function get_log_entry_stub(array $tracedef) {
        $newtrace = [];
        foreach (array_reverse($tracedef) as $fn) {
            $node = [];
            if (strpos($fn, ';') !== false) {
                $fn = explode(';', $fn);
                $node['file'] = $fn[0];
                $node['closure_line'] = $fn[1];
            } else {
                $fn = explode('::', $fn);
                if (isset($fn[1])) {
                    $node['class'] = $fn[0];
                    $node['function'] = $fn[1];
                } else {
                    $node['function'] = $fn[0];
                }
            }
            $newtrace[] = $node;
        }

        $stub = $this->getMockBuilder(\ExcimerLogEntry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $stub->method('getTrace')
            ->willReturn($newtrace);

        return $stub;
    }

    /**
     * Creates a stub for the ExcimerLog class for testing purposes.
     *
     * @param array $tracedefs
     * @return \ExcimerLog|mixed|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function get_log_stub(array $tracedefs) {

        $logentries = [];
        foreach ($tracedefs as $tracedef) {
            $logentries[] = $this->get_log_entry_stub($tracedef);
        }
        $logentries = new \ArrayObject($logentries);
        $iterator = $logentries->getIterator();

        $stub = $this->getMockBuilder(\ExcimerLog::class)
            ->disableOriginalConstructor()
            ->getMock();

        $stub->method('current')
            ->willReturnCallback(function() use($iterator) {
                return $iterator->current();
            });

        $stub->method('key')
            ->willReturnCallback(function() use($iterator) {
                return $iterator->key();
            });

        $stub->method('next')
            ->willReturnCallback(function() use($iterator) {
                return $iterator->next();
            });

        $stub->method('rewind')
            ->willReturnCallback(function() use($iterator) {
                return $iterator->rewind();
            });

        $stub->method('valid')
            ->willReturnCallback(function() use($iterator) {
                return $iterator->valid();
            });

        $stub->method('count')
            ->willReturnCallback(function() use($logentries) {
                return $logentries->count();
            });

        $stub->method('offsetExists')
            ->willReturnCallback(function($offset) use($logentries) {
                return $logentries->offsetExists($offset);
            });

        $stub->method('offsetGet')
            ->willReturnCallback(function($offset) use($logentries) {
                return $logentries->offsetGet($offset);
            });

        return $stub;
    }


    /**
     * Creates a stub for the ExcimerProfiler class for testing purposes.
     *
     * @param array $tracedefs
     * @return \ExcimerProfiler|mixed|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function get_profiler_stub(array $tracedefs) {
        $stub = $this->getMockBuilder(\ExcimerProfiler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $stub->method('flush')
            ->willReturn($this->get_log_stub($tracedefs));

        $stub->method('getLog')
            ->willReturn($this->get_log_stub($tracedefs));

        return $stub;
    }
}
