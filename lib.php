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
 * Hook to be run after initial site config.
 *
 * This allows the plugin to selectively activate the ExcimerProfiler while
 * having access to the database. It means that the initialisation of the
 * request up to this point will not be captured by the profiler. This
 * eliminates the need for an auto_prepend_file/auto_append_file.
 */
function tool_excimer_after_config(): void {
    // TODO Temp ref: https://docs.moodle.org/dev/Login_callbacks#after_config
    // TODO Do we want to check if in upgrade/install etc.
    if (manager::is_profiling()) {
        manager::init();
    }
}

function tool_excimer_performance_checks(): array {
    return [new slowest()];
}
