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

namespace tool_excimer\task;

use tool_excimer\monthint;
use tool_excimer\page_group;

/**
 * Purge old profile group data.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_page_groups extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_purge_page_groups', 'tool_excimer');
    }

    /**
     * Execute the scheduled task.
     *
     * @param int|null $now Optional timestamp for testing
     */
    public function execute(?int $now = null) {
        $this->purge($now ?? time());
    }

    /**
     * Purge page group records older than the retention period.
     *
     * @param int $now Current timestamp (injectable for tests)
     */
    public function purge(int $now): void {
        global $DB;

        $keepmonths = (int)get_config('tool_excimer', 'expiry_fuzzy_counts');

        if (!empty($keepmonths) && $keepmonths > 0) {
            $cutoff = $this->calculate_cutoff_month($now, $keepmonths);

            // Delete all page group records less than or equal our cutoff month.
            $DB->delete_records_select(page_group::TABLE, 'month <= ' . $cutoff);
        }
    }

    /**
     * Calculate the cutoff month number based on $now and months to keep.
     *
     * @param int $now Current timestamp
     * @param int $keepmonths Number of months to retain
     * @return int Cutoff month number (for comparison in DB)
     */
    public function calculate_cutoff_month(int $now, int $keepmonths): int {
        // Because we want to keep n full months of data (n = $keepmonths).
        // We add one month ($keepmonths += 1) to include the current month.
        $keepmonths += 1;

        $date = new \DateTime();
        $date->setTimestamp($now);
        $date->modify('first day of this month');
        $date->modify("-{$keepmonths} months");

        // Return a custom from_timestamp year/month number (e.g., 202508).
        // We can call this custom timestamp the "cutoff month".
        return monthint::from_timestamp($date->getTimestamp());
    }

}
