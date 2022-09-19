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
 * @package    tool_excimer
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class excimer_testcase extends \advanced_testcase {

    /**
     * Creates a stub for the ExcimerLogEntry class for testing purposes.
     *
     * Each element of $stacktrace defines a function. Either
     * - x;12 - defines a closure file 'x', line 12.
     * - m::n - defines a class method m::n
     * - n    - defines a function
     *
     * @param array $stacktrace A stack trace, ordered topmost to local.
     * @param float $timestamp A timestamp to be returned by getTimestamp().
     * @param int $eventcount To be returned by getEventCount().
     * @return \ExcimerLogEntry|mixed|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function get_log_entry_stub(array $stacktrace, float $timestamp = 0, int $eventcount = 1) {
        $newtrace = [];
        // ExcimerLogEntry stores the trace from most local to topmost.
        foreach (array_reverse($stacktrace) as $fn) {
            // Convert the trace node to the format used by ExcimerLogEntry.
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

        $stub->method('getEventCount')
            ->willReturn($eventcount);

        $stub->method('getTimestamp')
            ->willReturn($timestamp);

        return $stub;
    }

    /**
     * Creates a stub for the ExcimerLog class for testing purposes.
     *
     * @param array $stacktraces A set of stack traces, representing samples.
     * @param float $period A time interval used to create timestamps.
     * @param float $starttime Starting time for timestamps.
     * @return \ExcimerLog|mixed|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function get_log_stub(array $stacktraces, float $period = 0, float $starttime = 0.0) {
        $logentries = [];
        $time = $starttime;
        foreach ($stacktraces as $stacktrace) {
            $time += $period;
            $logentries[] = $this->get_log_entry_stub($stacktrace, $time);
        }
        $logentries = new \ArrayObject($logentries);
        $iterator = $logentries->getIterator();

        $stub = $this->getMockBuilder(\ExcimerLog::class)
            ->disableOriginalConstructor()
            ->getMock();

        // These methods proxy the function names to $iterator for stubbing.
        foreach (get_class_methods("\Iterator") as $methodname) {
            $stub->method($methodname)->willReturnCallback(function () use ($iterator, $methodname) {
                return $iterator->$methodname();
            });
        }

        $stub->method('count')
            ->willReturnCallback(function () use ($logentries) {
                return $logentries->count();
            });

        // Don't use reflection here because not all methods need to be overridden.
        foreach (['offsetExists', 'offsetGet'] as $methodname) {
            $stub->method($methodname)->willReturnCallback(function ($offset) use ($logentries, $methodname) {
                return $logentries->$methodname($offset);
            });
        }

        return $stub;
    }

    /**
     * Creates a stub for the ExcimerProfiler class for testing purposes.
     *
     * @param array $stacktraces A set of stack traces, representing samples.
     * @param float $period A time interval used to create timestamps.
     * @param float $starttime
     * @return \ExcimerProfiler|mixed|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function get_profiler_stub(array $stacktraces, float $period = 0, float $starttime = 0.0) {
        $stub = $this->getMockBuilder(\ExcimerProfiler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $logstub = $this->get_log_stub($stacktraces, $period, $starttime);

        $stub->method('flush')
            ->willReturn($logstub);

        $stub->method('getLog')
            ->willReturn($logstub);

        return $stub;
    }

    /**
     * Creates a stub for the manager class for testing purposes.
     *
     * @param processor $processor
     * @param \ExcimerProfiler $profiler
     * @param \ExcimerTimer $timer
     * @param float $starttime
     * @return \ExcimerProfiler|mixed|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function get_manager_stub(processor $processor, \ExcimerProfiler $profiler, \ExcimerTimer $timer, float $starttime) {

        $stub = $this->getMockBuilder(manager::class)
            ->setConstructorArgs([$processor])
            ->getMock();

        $stub->method('get_profiler')
            ->willReturn($profiler);
        $stub->method('get_timer')
            ->willReturn($timer);
        $stub->method('get_starttime')
            ->willReturn($starttime);
        $stub->method('get_reasons')
            ->willReturn(profile::REASON_FLAMEME);

        return $stub;
    }
}
