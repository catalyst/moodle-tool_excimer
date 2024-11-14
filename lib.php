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
 * D3.js flamegraph of excimer profiling data.
 *
 * @package   tool_excimer
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>, Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\manager;
use tool_excimer\check\slowest;

/**
 * Hook to run plugin before session start.
 *
 * This is to get the timer started for installations that have the MDL-75014 fix (4.1 or later). Otherwise
 * the timer will be started as a part of tool_excimer_after_config().
 */
function tool_excimer_before_session_start() {
    // Start plugin.
    $manager = manager::get_instance();
}

/**
 * Hook to obtain a list of perfomence checks supplied by the plugin.
 *
 * @return \core\check\check[]
 */
function tool_excimer_performance_checks(): array {
    return [new slowest()];
}
