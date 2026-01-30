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
            $this->get_log_entry_stub(['a']),
            $this->get_log_entry_stub(['b']),
            $this->get_log_entry_stub(['c']),
        ];
        $expected = [
            $this->from_log_entry_to_sample($this->get_log_entry_stub(['a'])),
            $this->from_log_entry_to_sample($this->get_log_entry_stub(['b'])),
            $this->from_log_entry_to_sample($this->get_log_entry_stub(['c'])),
        ];

        $set = new excimer_sample_set('a', 0, 1024);

        $set->add_many_samples($samples);

        $this->assertEquals($expected, $set->samples);
    }

    /**
     * Tests the effect of filtering while adding samples.
     *
     * @covers \tool_excimer\sample_set::add_many_samples
     * @covers \tool_excimer\sample_set::apply_doubling
     */
    public function test_filtering(): void {
        $samples = [
            $this->get_log_entry_stub(['a']),
            $this->get_log_entry_stub(['b'], 0, 3),
            $this->get_log_entry_stub(['c'], 0, 7),
            $this->get_log_entry_stub(['d']),
        ];
        // This is every 2nd element of $samples.
        $expected1 = [
            $this->from_log_entry_to_sample($this->get_log_entry_stub(['b'], 0, 2)),
            $this->from_log_entry_to_sample($this->get_log_entry_stub(['c'], 0, 4)),
        ];
        // This is every 4th element of $samples.
        $expected2 = [$this->from_log_entry_to_sample($this->get_log_entry_stub(['c'], 0, 3))];

        $set = new excimer_sample_set('a', 0, 1024);

        // Each time this is called, the filter rate is doubled.
        $set->apply_doubling();

        // Filter rate should be 2, thus, only every 2nd sample should be recorded in sample set.
        $set->add_many_samples($samples);

        // Only every 2nd sample should be recorded in sample set.
        $this->assertEquals($expected1, $set->samples);

        $set = new excimer_sample_set('a', 0, 1024);
        $set->apply_doubling();
        $set->apply_doubling();

        // Filter rate should be 4, thus, only every 4th sample should be recorded in sample set.
        $set->add_many_samples($samples);

        $this->assertEquals($expected2, $set->samples);
    }

    /**
     * Tests stripping existing samples when calling apply_doubling.
     *
     * @covers \tool_excimer\sample_set::add_many_samples
     * @covers \tool_excimer\sample_set::apply_doubling
     */
    public function test_stripping(): void {
        $samples = [
            $this->get_log_entry_stub(['a']),
            $this->get_log_entry_stub(['b']),
            $this->get_log_entry_stub(['c']),
            $this->get_log_entry_stub(['d']),
        ];
        // This is $samples ofter being stripped once.
        $expected1 = [
            $this->from_log_entry_to_sample($samples[1]),
            $this->from_log_entry_to_sample($samples[3]),
        ];
        // This is $samples ofter being stripped twice.
        $expected2 = [ $this->from_log_entry_to_sample($samples[3])];

        $set = new excimer_sample_set('a', 0, 1024);

        $set->add_many_samples($samples);

        // Every 2nd sample should be stripped after doubling.
        $set->apply_doubling();
        $this->assertEquals($expected1, $set->samples);

        // Half of the samples should be stripped again, leaving every 4th from the original.
        $set->apply_doubling();
        $this->assertEquals($expected2, $set->samples);
    }

    /**
     * Tests the invoking of apply_doubling from within add_sample.
     *
     * @covers \tool_excimer\sample_set::add_many_samples
     */
    public function test_automatic_doubling_when_adding_samples(): void {
        $samples1 = [
            $this->get_log_entry_stub(['a']),
            $this->get_log_entry_stub(['b']),
            $this->get_log_entry_stub(['c']),
            $this->get_log_entry_stub(['d']),
            $this->get_log_entry_stub(['e']),
            $this->get_log_entry_stub(['f']),
        ];
        // This is every second element of $samples1.
        $expected1 = [
            $this->from_log_entry_to_sample($samples1[1]),
            $this->from_log_entry_to_sample($samples1[3]),
            $this->from_log_entry_to_sample($samples1[5]),
        ];

        $samples2 = [
            $this->get_log_entry_stub(['g']),
            $this->get_log_entry_stub(['h']),
            $this->get_log_entry_stub(['i']),
            $this->get_log_entry_stub(['j']),
            $this->get_log_entry_stub(['k']),
            $this->get_log_entry_stub(['l']),
        ];
        // This is every 4th element of $sample1 + $sample2.
        $expected2 = [
            $this->from_log_entry_to_sample($samples1[3]),
            $this->from_log_entry_to_sample($samples2[1]),
            $this->from_log_entry_to_sample($samples2[5]),
        ];

        $set = new excimer_sample_set('a', 0, 4);

        $set->add_many_samples($samples1);

        // By this point apply_doubling should have been invoked once.
        $this->assertEquals($expected1, $set->samples);

        $set->add_many_samples($samples2);

        // By this point apply_doubling should have been invoked a second time.
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

        $set = new excimer_sample_set('a', 0);
        $set->add_many_samples($samples1);
        $this->assertEquals(array_sum($eventcounts), $set->count());
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
            ['sampleindex' => 0, 'value' => 200000],
            ['sampleindex' => 2, 'value' => 400000],
            ['sampleindex' => 5, 'value' => 350000],
        ];

        $expected2 = [
            ['sampleindex' => 0, 'value' => 400000],
            ['sampleindex' => 5, 'value' => 350000],
        ];

        $expected3 = [
            ['sampleindex' => 0, 'value' => 400000],
        ];

        $set = new memory_sample_set('a', 0, 1024);
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
            ['eventcount' => 55, 'trace' => [['function' => 'b']]],
            ['eventcount' => 60, 'trace' => [['function' => 'c']]],
            ['eventcount' => 50, 'trace' => [['function' => 'e']]],
        ];

        $expected2 = [
            ['trace' => [['function' => 'c']], 'eventcount' => 57.5],
            ['trace' => [['function' => 'e']], 'eventcount' => 50],
        ];

        $expected3 = [
            ['trace' => [['function' => 'c']], 'eventcount' => 53.75],
        ];

        $set = new excimer_sample_set('a', 0, 1024);
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
    public function test_automatic_web_doubling_when_adding_samples(): void {
        $samples1 = [
            $this->get_log_entry_stub(['a'], 0, 10),
            $this->get_log_entry_stub(['b'], 0, 100),
            $this->get_log_entry_stub(['c'], 0, 100),
            $this->get_log_entry_stub(['d'], 0, 20),
            $this->get_log_entry_stub(['e'], 0, 50),
        ];
        $expected1 = [
            ['eventcount' => 55, 'trace' => [['function' => 'b']]],
            ['eventcount' => 60, 'trace' => [['function' => 'c']]],
        ];

        $samples2 = [
            $this->get_log_entry_stub(['g'], 0, 10),
            $this->get_log_entry_stub(['h'], 0, 20),
            $this->get_log_entry_stub(['i'], 0, 10),
            $this->get_log_entry_stub(['k'], 0, 10),
        ];

        $expected2 = [
            ['eventcount' => 57.5, 'trace' => [['function' => 'c']]],
            ['eventcount' => 22.5, 'trace' => [['function' => 'e']]],
        ];

        $set = new excimer_sample_set('a', 0, 4);

        $set->add_many_samples($samples1);

        // By this point apply_doubling should have been invoked once.
        $this->assertEquals($expected1, $set->samples);

        $set->add_many_samples($samples2);

        // By this point apply_doubling should have been invoked two more times.
        $this->assertEquals($expected2, $set->samples);
    }
}
