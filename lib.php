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
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\excimer_log;

defined('MOODLE_INTERNAL') || die();

const EXCIMER_LOG_LIMIT = 10000;
const EXCIMER_PERIOD = 0.01;  // Default in seconds; used if config is out of sensible range.
const EXCIMER_TRIGGER = 0.01; // Default in seconds; used if config is out of sensible range.

// Global var to store logs as they are generated; add entries here for now.

$excimerlogentries = [];


/**
 * Hook to be run after initial site config.
 *
 * This allows the plugin to selectively activate the ExcimerProfiler while
 * having access to the database. It means that the initialisation of the
 * request up to this point will not be captured by the profiler. This
 * eliminates the need for an auto_prepend_file/auto_append_file.
 */
function tool_excimer_after_config() {

    static $prof;  // Stay in scope.

    $isenabled = (bool)get_config('tool_excimer', 'excimerenable');
    if ($isenabled) {

        $samplems = (int)get_config('tool_excimer', 'excimersample_ms');
        $hassensiblerange = $samplems > 10 && $samplems < 10000;
        $sampleperiod = $hassensiblerange ? round($samplems / 1000, 3) : EXCIMER_PERIOD;

        $prof = new ExcimerProfiler();
        $prof->setPeriod($sampleperiod);
        $prof->setFlushCallback(fn($log) => tool_excimer_spool($log), EXCIMER_LOG_LIMIT);
        $prof->start();

        $started = microtime($ms = true);
        core_shutdown_manager::register_function('tool_excimer_shutdown', [$prof, $started]);
    }
}

/**
 * Callback function to push log entries to in-memory storage; saved to disk by
 * shutdown function.
 *
 * IMPORTANT: See performance note in tool_excimer_shutdown.
 *
 * @param ExcimerLog $log The excimer log of the current request.
 * @return void
 */
function tool_excimer_spool(ExcimerLog $log) {
    global $excimerlogentries;
    foreach ($log as $entry) {
        $excimerlogentries[] = $entry;
    }
}

/**
 * Calback function to save log entries on shutdown.
 *
 * IMPORTANT: This has a performance cost to the system, obvoiusly. For minimal
 * load in production systems, this should run on a different system and
 * tool_excimer_spool should send it UDP packets.
 *
 * @param ExcimerProfiler $prof The profiler instance created in
 *      tool_excimer_after_config
 * @param float $started Time in epoch milliseconds
 * @return void
 */
function tool_excimer_shutdown(ExcimerProfiler $prof, $started) {
    global $excimerlogentries;

    $stopped  = microtime($ms = true);
    $elapsedms = ($stopped - $started) * 1000;

    $triggerms = (int)get_config('tool_excimer', 'excimertrigger_ms');
    $hassensiblerange = $triggerms > 0 && $triggerms < 600000;  // 10 mins max
    $triggervalue = $hassensiblerange ? $triggerms : EXCIMER_TRIGGER;

    if ($elapsedms > $triggervalue) {
        $prof->stop();
        $prof->flush();
        if (is_iterable($excimerlogentries)) {
            foreach ($excimerlogentries as $entry) {
                excimer_log::save_entry($entry);
            }
        }
    }
}

