<?php
// This file is part of Moodle - https://moodle.org/
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

use tool_excimer\task\purge_page_groups;

/**
 * <insertdescription>
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_excimer_purge_page_group_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_purge() {
        global $DB;

        $cutoff = 4;
        $months = [
            (int) userdate(time(), '%Y%m'),
            (int) userdate(strtotime('1 month ago'), '%Y%m'),
            (int) userdate(strtotime('2 months ago'), '%Y%m'),
            (int) userdate(strtotime('3 months ago'), '%Y%m'),
            (int) userdate(strtotime('4 months ago'), '%Y%m'),
            (int) userdate(strtotime('5 months ago'), '%Y%m'),
            (int) userdate(strtotime('6 months ago'), '%Y%m'),
            (int) userdate(strtotime('7 months ago'), '%Y%m'),
        ];
        foreach ($months as $month) {
            $DB->insert_record(page_group::TABLE, (object) ['month' => $month, 'fuzzydurationcounts' => '']);
        }

        $count = $DB->count_records(page_group::TABLE);
        $this->assertEquals(count($months), $count); // Sanity check.

        // Test no value set, should do no purging.
        set_config('expiry_fuzzy_counts', '', 'tool_excimer');
        $task = new purge_page_groups();
        $task->execute();
        $count = $DB->count_records(page_group::TABLE);
        $this->assertEquals(count($months), $count);

        // Test a value of $cutoff, all but $cutoff + 1 rows should be purged.
        set_config('expiry_fuzzy_counts', $cutoff, 'tool_excimer');
        $task = new purge_page_groups();
        $task->execute();

        $records = $DB->get_records(page_group::TABLE);

        // Check that the number of records remaining is the correct number.
        $this->assertEquals($cutoff + 1, count($records));

        // Check that no month stored is earlier than the cutoff month.
        $cutoffmonth = (int) userdate(strtotime(($cutoff + 1) . ' months ago'), '%Y%m');
        foreach ($records as $record) {
            $this->assertGreaterThan($cutoffmonth, (int) $record->month);
        }
    }
}
