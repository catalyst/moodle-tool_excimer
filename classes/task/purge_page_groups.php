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
     * Do the job.
     */
    public function execute() {
        global $DB;

        // Because we want to keep n full months of data, we add one to include the current month.
        $months = get_config('tool_excimer', 'expiry_fuzzy_counts');
        // Only purge if a value is set.
        if (!empty($months)) {
            $month = userdate(strtotime(($months + 1) . ' months ago'), '%Y%m');
            $DB->delete_records_select(page_group::TABLE, 'month <= ' . $month);
        }
    }
}
