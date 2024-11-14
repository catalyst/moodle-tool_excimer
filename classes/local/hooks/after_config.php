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

namespace tool_excimer\local\hooks;

use tool_excimer\manager;

/**
 * Class after_config
 *
 * @package   tool_excimer
 * @author    Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @copyright 2024, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_config {

    /**
     * Hook to be run after initial site config.
     *
     * This allows the plugin to selectively activate the ExcimerProfiler while
     * having access to the database. If the site does not have the MDL-75014
     * available then the timer will be started at this point. It means that
     * the initialisation of the request up to this point will not be captured
     * by the profiler. This eliminates the need for an
     * auto_prepend_file/auto_append_file.
     *
     * See also https://docs.moodle.org/dev/Login_callbacks#after_config.
     *
     * @param \core\hook\after_config $hook
     * return void
     */
    public static function callback(\core\hook\after_config $hook): void {

        global $CFG;

        if (during_initial_install() || isset($CFG->upgraderunning)) {
            // Do nothing during installation or upgrade.
            return;
        }

        try {
            // Start processor.
            $manager = manager::get_instance();
            $manager->start_processor();
        } catch (\Exception $exception) {
            debugging('tool_excimer_after_config error',
            DEBUG_DEVELOPER, $exception->getTrace());
        }

    }
}
