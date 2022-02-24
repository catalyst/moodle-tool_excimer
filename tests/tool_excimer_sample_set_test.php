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

    public function test_add_sample() {
        $entries = [
            $this->get_log_entry_stub(['a']),
            $this->get_log_entry_stub(['b']),
            $this->get_log_entry_stub(['c']),
        ];

        $set = new sample_set('a', 0);

        foreach ($entries as $entry) {
            $set->add_sample(($entry));
        }

        $this->assertEquals($entries, $set->samples);
    }

    public function test_filtering() {
        $entries = [
            $this->get_log_entry_stub(['a']),
            $this->get_log_entry_stub(['b']),
            $this->get_log_entry_stub(['c']),
            $this->get_log_entry_stub(['d']),
        ];

        $set = new sample_set('a', 0);
        $set->apply_doubling();

        foreach ($entries as $entry) {
            $set->add_sample(($entry));
        }

        $this->assertEquals([$entries[1], $entries[3]], $set->samples);

        $set = new sample_set('a', 0);
        $set->apply_doubling();
        $set->apply_doubling();

        foreach ($entries as $entry) {
            $set->add_sample(($entry));
        }

        $this->assertEquals([$entries[3]], $set->samples);
    }

    public function test_stripping() {
        $entries = [
            $this->get_log_entry_stub(['a']),
            $this->get_log_entry_stub(['b']),
            $this->get_log_entry_stub(['c']),
            $this->get_log_entry_stub(['d']),
        ];

        $set = new sample_set('a', 0);

        foreach ($entries as $entry) {
            $set->add_sample(($entry));
        }

        $set->apply_doubling();
        $this->assertEquals([$entries[1], $entries[3]], $set->samples);

        $set->apply_doubling();
        $this->assertEquals([$entries[3]], $set->samples);
    }

    public function test_doubling() {
        $entries1 = [
            $this->get_log_entry_stub(['a']),
            $this->get_log_entry_stub(['b']),
            $this->get_log_entry_stub(['c']),
            $this->get_log_entry_stub(['d']),
            $this->get_log_entry_stub(['e']),
            $this->get_log_entry_stub(['f']),
        ];
        $entries2 = [
            $this->get_log_entry_stub(['g']),
            $this->get_log_entry_stub(['h']),
            $this->get_log_entry_stub(['i']),
            $this->get_log_entry_stub(['j']),
            $this->get_log_entry_stub(['k']),
            $this->get_log_entry_stub(['l']),
        ];

        $set = new sample_set('a', 0, 4);

        foreach ($entries1 as $entry) {
            $set->add_sample(($entry));
        }

        $this->assertEquals([$entries1[1], $entries1[3], $entries1[5]], $set->samples);

        foreach ($entries2 as $entry) {
            $set->add_sample(($entry));
        }

        $this->assertEquals([$entries1[3], $entries2[1], $entries2[5]], $set->samples);
    }
}
