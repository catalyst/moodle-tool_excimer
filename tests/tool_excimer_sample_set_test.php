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

/**
 * Defines names of plugin types and some strings used at the plugin managment
 *
 * @package    tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  2022 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_excimer_sample_set_test extends excimer_testcase {

    /**
     * Tests adding samples to the object.
     */
    public function test_add_sample() {
        $samples = [
            $this->get_log_entry_stub(['a']),
            $this->get_log_entry_stub(['b']),
            $this->get_log_entry_stub(['c']),
        ];

        $set = new sample_set('a', 0, 1024);

        $set->add_many_samples($samples);

        $this->assertEquals($samples, $set->samples);
    }

    /**
     * Tests the effect of filtering while adding samples.
     */
    public function test_filtering() {
        $samples = [
            $this->get_log_entry_stub(['a']),
            $this->get_log_entry_stub(['b']),
            $this->get_log_entry_stub(['c']),
            $this->get_log_entry_stub(['d']),
        ];
        // This is every 2nd element of $samples.
        $expected1 = [
            $samples[1],
            $samples[3]
        ];
        // This is every 4th element of $samples.
        $expected2 = [
            $samples[3]
        ];

        $set = new sample_set('a', 0, 1024);

        // Each time this is called, the filter rate is doubled.
        $set->apply_doubling();

        // Filter rate should be 2, thus, only every 2nd sample should be recorded in sample set.
        $set->add_many_samples($samples);

        // Only every 2nd sample should be recorded in sample set.
        $this->assertEquals($expected1, $set->samples);

        $set = new sample_set('a', 0, 1024);
        $set->apply_doubling();
        $set->apply_doubling();

        // Filter rate should be 4, thus, only every 4th sample should be recorded in sample set.
        $set->add_many_samples($samples);

        $this->assertEquals($expected2, $set->samples);
    }

    /**
     * Tests stripping existing samples when calling apply_doubling.
     */
    public function test_stripping() {
        $samples = [
            $this->get_log_entry_stub(['a']),
            $this->get_log_entry_stub(['b']),
            $this->get_log_entry_stub(['c']),
            $this->get_log_entry_stub(['d']),
        ];
        // This is $samples ofter being stripped once.
        $expected1 = [
            $samples[1],
            $samples[3]
        ];
        // This is $samples ofter being stripped twice.
        $expected2 = [
            $samples[3]
        ];

        $set = new sample_set('a', 0, 1024);

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
     */
    public function test_doubling() {
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
            $samples1[1],
            $samples1[3],
            $samples1[5]
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
            $samples1[3],
            $samples2[1],
            $samples2[5]
        ];

        $set = new sample_set('a', 0, 4);

        $set->add_many_samples($samples1);

        // By this point apply_doubling should have been invoked once.
        $this->assertEquals($expected1, $set->samples);

        $set->add_many_samples($samples2);

        // By this point apply_doubling should have been invoked a second time.
        $this->assertEquals($expected2, $set->samples);
    }
}
