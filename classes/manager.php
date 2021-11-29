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

namespace tool_excimer;

defined('MOODLE_INTERNAL') || die();

/**
 * Primary controller class for handling Excimer profiling.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    const EXCIMER_LOG_LIMIT = 10000;
    const EXCIMER_PERIOD = 0.01;  // Default in seconds; used if config is out of sensible range.
    const EXCIMER_TRIGGER = 0.01; // Default in seconds; used if config is out of sensible range.

    /**
     * Checks if the given flag is set
     *
     * @param $flag Name of the flag
     * @return bool if the flag is set or not.
     */
    static function isflagset(string $flag): bool {
        return !empty(getenv($flag)) ||
                isset($_COOKIE[$flag]) ||
                isset($_POST[$flag]) ||
                isset($_GET[$flag]);
    }

    /**
     * Returns true if the profiler is currently set to be used.
     *
     * @return bool
     */
    static function isprofileon(): bool {
        return self::isflagset('FLAMEME') || (bool)get_config('tool_excimer', 'excimerenable');
    }

    /**
     * Get the list of stored profiles. Does not return the data.
     *
     * @return array
     */
    public static function getprofiles(): object {
        global $DB;
        $sql = "
            SELECT id, request, created
              FROM {tool_excimer_profiles}
          ORDER BY created DESC
        ";
        return $DB->get_recordset_sql($sql);
    }

    /**
     * Initialises the profiler and also sets up the shutdown callback.
     */
    public static function init(): void {
        $samplems = (int)get_config('tool_excimer', 'excimersample_ms');
        $hassensiblerange = $samplems > 10 && $samplems < 10000;
        $sampleperiod = $hassensiblerange ? round($samplems / 1000, 3) : self::EXCIMER_PERIOD;

        $prof = new \ExcimerProfiler();
        $prof->setPeriod($sampleperiod);

        $started = microtime(true);

        // TODO: a setting to determine if logs are saved locally or sent to an external process.

        // Call self::saveprofile whenever the logs get flushed.
        $spool = function(\ExcimerLog $log) use ($started) {
            profile::save($log, $started);
        };
        $prof->setFlushCallback($spool, self::EXCIMER_LOG_LIMIT);
        $prof->start();

        // Stop the profiler as a part of the shutdown sequence.
        \core_shutdown_manager::register_function(function() use ($prof) { $prof->stop(); $prof->flush(); });
    }
}
