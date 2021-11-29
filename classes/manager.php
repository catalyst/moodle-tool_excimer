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
     * Gets a single profile, including data.
     * @param $id
     * @return object
     */
    public static function getprofile($id): object {
        global $DB;
        return $DB->get_record('tool_excimer_profiles', ['id' => $id],'*', MUST_EXIST);
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
            manager::saveprofile($log, $started);
        };
        $prof->setFlushCallback($spool, self::EXCIMER_LOG_LIMIT);
        $prof->start();

        // Stop the profiler as a part of the shutdown sequence.
        \core_shutdown_manager::register_function(function() use ($prof) { $prof->stop(); $prof->flush(); });
    }

    /**
     * Gets the request type (web, cli, ...) and the parameters of the request.
     *
     * @return array A tuple [type, parameters].
     */
    // TODO strip out FLAMEME parameter?

    function gettypeandparams() {
        if (php_sapi_name() == 'cli') {
            // Our setup lacks $argv even though register_argc_argv is On; use
            // $_SERVER['argv'] instead.
            $type = 'cli';
            $parameters = join(' ', array_slice($_SERVER['argv'], 1));
        } else {
            // Web request: split API calls later.
            $type = 'web';
            $parameters = $_SERVER['QUERY_STRING'];
        }

        return [$type, $parameters];
    }

    /**
     * Saves a snaphot of the logs into the database.
     *
     * @param \ExcimerLog $log
     * @param float $started
     */
    public static function saveprofile(\ExcimerLog $log, float $started): void {
        global $DB;
        $stopped  = microtime(true);
        $flamedata = trim(str_replace("\n;", "\n", $log->formatCollapsed()));
        $flamedatad3 = json_encode(converter::process($flamedata));
        list($type, $parameters) = self::gettypeandparams();

        $id = $DB->insert_record('tool_excimer_profiles', [
            'type' => $type,
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'created' => (int)$started,
            'duration' => $stopped - $started,
            'request' => $_SERVER['PHP_SELF'] ?? 'UNKNOWN',
            'parameters' => $parameters,
            'responsecode' => http_response_code(),
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'explanation' => '', // TODO support this
            'flamedata' => $flamedata,
            'flamedatad3' => $flamedatad3
        ]);
    }
}
