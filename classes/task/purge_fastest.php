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

/**
 * Delete excess logs to keep only the slowest.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_excimer\task;

use tool_excimer\profile;

class purge_fastest  extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_purge_fastest', 'tool_excimer');
    }

    public function execute() {
        profile::purge_fastest_by_group((int) get_config('tool_excimer', 'num_slowest_by_page'));
        profile::purge_fastest((int) get_config('tool_excimer', 'num_slowest'));
    }
}
