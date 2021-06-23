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
 * Delete logs that have expired
 *
 * @package   tool_excimer
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_excimer\task;

use tool_excimer\excimer_log;

/**
 * Delete logs that are past date...
 *
 */
class expire_logs extends \core\task\scheduled_task {

    public function get_name() {
        return 'tool_excimer: Expire logs';
    }

    public function execute() {

        if (!get_config('tool_excimer', 'excimerenable')) {
            return;
        }

        $expiry = (int)get_config('tool_excimer', 'excimerexpiry_s');
        $cutoff = time() - $expiry;
        excimer_log::delete_before_epoch_time($cutoff);
    }

}
