<?php
// This file is part of Moodle - http://moodle.org/  <--change
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

require_once(__DIR__ . "/excimer_testcase.php");

/**
 * Defines names of plugin types and some strings used at the plugin managment
 *
 * @package    tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  2022 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_excimer_sample_set_test extends excimer_testcase {
    /**
     * Tests adding samples to the object.
     *
     * @covers \tool_excimer\sample_set::add_many_samples
     */
    public function test_add_sample(): void {
        $samples = [
            $this->from_log_entry_to_sample($this->get_log_entry_stub(['a'])),
            $this->from_log_entry_to_sample($this->get_log_entry_stub(['b'])),
            $this->from_log_entry_to_sample($this->get_log_entry_stub(['c'])),
        ];

        $set = new sample_set('a', 0, 1024);

        $set->add_many_samples($samples);

        $this->assertEquals($samples, $set->samples);
    }

    /**
     * Tests the effect of merging memory usage sample set while adding samples.
     *
     * @covers \tool_excimer\sample_set::add_many_samples
     * @covers \tool_excimer\sample_set::apply_doubling
     * @covers \tool_excimer\sample_set::merge_memory_usage_sample_set
     */
    public function test_merge_memory_usage_sample_set(): void {
        $samples = [
            ['sampleindex' => 0, 'value' => 100000],
            ['sampleindex' => 1, 'value' => 200000],
            ['sampleindex' => 2, 'value' => 300000],
            ['sampleindex' => 3, 'value' => 400000],
            ['sampleindex' => 5, 'value' => 350000],
        ];
        $expected1 = [
            ['sampleindex' => 0, 'value' => 150000],
            ['sampleindex' => 2, 'value' => 350000],
            ['sampleindex' => 5, 'value' => 350000],
        ];

        $expected2 = [
            ['sampleindex' => 0, 'value' => 250000],
            ['sampleindex' => 5, 'value' => 350000],
        ];

        $expected3 = [
            ['sampleindex' => 0, 'value' => 300000],
        ];

        $set = new sample_set('a', 0, 1024);
        $set->add_many_samples($samples);

        $set->apply_doubling(true);
        $this->assertEquals($expected1, $set->samples);

        $set->apply_doubling(true);
        $this->assertEquals($expected2, $set->samples);

        $set->apply_doubling(true);
        $this->assertEquals($expected3, $set->samples);
    }

    /**
     * Tests the effect of merging memory usage sample set while adding samples.
     *
     * @covers \tool_excimer\sample_set::add_many_samples
     * @covers \tool_excimer\sample_set::apply_doubling
     * @covers \tool_excimer\sample_set::merge_trace_sample_set
     */
    public function test_merge_trace_sample_set(): void {
        $samples = [
            $this->get_log_entry_stub(['a'], 0, 10),
            $this->get_log_entry_stub(['b'], 0, 100),
            $this->get_log_entry_stub(['c'], 0, 100),
            $this->get_log_entry_stub(['d'], 0, 20),
            $this->get_log_entry_stub(['e'], 0, 50),
        ];
        $expected1 = [
            ['eventcount' => 110, 'trace' => [['function' => 'b']]],
            ['eventcount' => 120, 'trace' => [['function' => 'c']]],
            ['eventcount' => 50, 'trace' => [['function' => 'e']]],
        ];

        $expected2 = [
            ['trace' => [['function' => 'c']], 'eventcount' => 230],
            ['trace' => [['function' => 'e']], 'eventcount' => 50],
        ];

        $expected3 = [
            ['trace' => [['function' => 'c']], 'eventcount' => 280],
        ];

        $set = new sample_set('a', 0, 1024);
        $set->add_many_samples($samples);

        $set->apply_doubling(false);
        $this->assertEquals($expected1, $set->samples);

        $set->apply_doubling(false);
        $this->assertEquals($expected2, $set->samples);

        $set->apply_doubling(false);
        $this->assertEquals($expected3, $set->samples);
    }

    /**
     * Tests the invoking of apply_doubling from within add_sample.
     *
     * @covers \tool_excimer\sample_set::add_many_samples
     */
    public function test_automatic_doubling_when_adding_samples(): void {
        $samples1 = [
            $this->get_log_entry_stub(['a'], 0, 10),
            $this->get_log_entry_stub(['b'], 0, 100),
            $this->get_log_entry_stub(['c'], 0, 100),
            $this->get_log_entry_stub(['d'], 0, 20),
            $this->get_log_entry_stub(['e'], 0, 50),
        ];
        $expected1 = [
            ['eventcount' => 110, 'trace' => [['function' => 'b']]],
            ['eventcount' => 120, 'trace' => [['function' => 'c']]],
            ['eventcount' => 50, 'trace' => [['function' => 'e']]],
        ];

        $samples2 = [
            $this->get_log_entry_stub(['g'], 0, 10),
            $this->get_log_entry_stub(['h'], 0, 20),
            $this->get_log_entry_stub(['i'], 0, 10),
        ];

        $expected2 = [
            ['eventcount' => 290, 'trace' => [['function' => 'c']]],
            ['eventcount' => 30, 'trace' => [['function' => 'h']]],
        ];

        $set = new sample_set('a', 0, 4);

        $set->add_many_samples($samples1);

        // By this point apply_doubling should have been invoked once.
        $this->assertEquals($expected1, $set->samples);

        $set->add_many_samples($samples2);

        // By this point apply_doubling should have been invoked two more times.
        $this->assertEquals($expected2, $set->samples);
    }

    /**
     * Tests event count
     *
     * @covers \tool_excimer\sample_set::add_many_samples
     */
    public function test_event_count(): void {
        script_metadata::init();
        $eventcounts = [1, 1, 4, 1, 2, 1];
        $samples1 = [
            $this->get_log_entry_stub(['a'], 0, $eventcounts[0]),
            $this->get_log_entry_stub(['b'], 1, $eventcounts[1]),
            $this->get_log_entry_stub(['c'], 2, $eventcounts[2]),
            $this->get_log_entry_stub(['d'], 3, $eventcounts[3]),
            $this->get_log_entry_stub(['e'], 4, $eventcounts[4]),
            $this->get_log_entry_stub(['f'], 5, $eventcounts[5]),
        ];

        $set = new sample_set('a', 0);
        $set->add_many_samples($samples1);
        $this->assertEquals(array_sum($eventcounts), $set->count());
    }
}
