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
 * Testing the purge page group scheduled task execution method
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_excimer_purge_page_group_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests purging of expired page group records.
     *
     * @covers \tool_excimer\task\purge_page_groups::execute
     */
    public function test_purge(): void {
        global $DB;

        // Set test static page group records for previous months.
        $months = [
            202507, // July 2025.
            202506, // June 2025.
            202505, // May 2025.
            202504, // April 2025.
            202503, // March 2025.
            202502, // February 2025.
            202501, // January 2025.
            202412, // December 2024.
        ];

        // Fixed "now" for an injectable static date test.
        // Testing 31st of July 2025, a date affected by datetime rollover issues.
        // If 31st July passes then all affected dates and normal dates will.
        $now = new \DateTime('2025-07-31');

        $this->populate_test_data($months);

        // Assertion 1: Sanity check that all records were inserted.
        $count = $DB->count_records(page_group::TABLE);
        $this->assertEquals(count($months), $count);

        // Assertion 2: No value set, should do no purging.
        set_config('expiry_fuzzy_counts', '', 'tool_excimer');
        $task = new purge_page_groups();
        $task->execute($now->getTimestamp());
        $count = $DB->count_records(page_group::TABLE);
        $this->assertEquals(count($months), $count);

        // Assertion 3: Keep more months than already exist, should do no purging.
        $keepmonths = 20;
        set_config('expiry_fuzzy_counts', $keepmonths, 'tool_excimer');
        $task = new purge_page_groups();
        $task->execute($now->getTimestamp());
        $this->assertEquals(count($months), $count);

        // Assertion 4: All except $keepmonths should be purged.
        $keepmonths = 5;
        set_config('expiry_fuzzy_counts', $keepmonths, 'tool_excimer');
        $task = new purge_page_groups();
        $task->execute($now->getTimestamp());
        $records = $DB->get_records(page_group::TABLE);
        // Addition equation here.
        // Ensures n full months + current month.
        $this->assertEquals($keepmonths + 1, count($records));

        // Assertion 5: Cutoff is expected
        $expectedcutoff = 202501;
        $cutoffmonth = $task->calculate_cutoff_month($now->getTimestamp(), $keepmonths);
        $this->assertEquals($expectedcutoff, $cutoffmonth);

        // Assertion 6: Ensure no month older than cutoff remains.
        $cutoffmonth = $task->calculate_cutoff_month($now->getTimestamp(), $keepmonths);
        foreach ($records as $record) {
            $this->assertGreaterThan($cutoffmonth, (int) $record->month);
        }
    }

    /**
     * Populate test data
     *
     * @param array $months Required test month data
     * @param bool|null $reset Optional to reset the data in the table
     */
    protected function populate_test_data(array $months, ?bool $reset = null): void {
        global $DB;

        if ($reset === true) {
            // Delete all test records.
            $DB->delete_records(page_group::TABLE);
        }

        // Insert the test months.
        foreach ($months as $month) {
            $DB->insert_record(page_group::TABLE, (object) [
                'month' => $month,
                'fuzzydurationcounts' => '',
            ]);
        }
    }
}
