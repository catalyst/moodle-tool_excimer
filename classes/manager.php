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

    const MANUAL_PARAM_NAME = 'FLAMEME';
    const FLAME_ON_PARAM_NAME = 'FLAMEALL';
    const FLAME_OFF_PARAM_NAME = 'FLAMEALLSTOP';

    // Reason for profiling.
    const REASON_MANUAL = 0;
    const REASON_AUTO = 1;
    const REASON_FLAMEALL = 2;

    const EXCIMER_LOG_LIMIT = 10000;
    const EXCIMER_PERIOD = 0.01;  // Default in seconds; used if config is out of sensible range.
    const EXCIMER_TRIGGER = 0.01; // Default in seconds; used if config is out of sensible range.

    /**
     * Checks if the given flag is set
     *
     * @param string $flag Name of the flag
     * @return bool
     */
    public static function is_flag_set(string $flag): bool {
        return !empty(getenv($flag)) ||
                isset($_COOKIE[$flag]) ||
                isset($_POST[$flag]) ||
                isset($_GET[$flag]);
    }

    /**
     * Checks flame on/off flags and sets the session value.
     *
     * @return bool True if we have flame all set.
     */
    protected static function is_flame_all(): bool {
        if (self::is_flag_set(self::FLAME_OFF_PARAM_NAME)) {
            unset($_SESSION[self::FLAME_ON_PARAM_NAME]);
        } else if (self::is_flag_set(self::FLAME_ON_PARAM_NAME)) {
            $_SESSION[self::FLAME_ON_PARAM_NAME] = 1;
        }
        return isset($_SESSION[self::FLAME_ON_PARAM_NAME]);
    }

    /**
     * Returns true if the profiler is currently set to be used.
     *
     * @return bool
     * @throws \dml_exception
     */
    public static function is_profiling(): bool {
        return  self::is_flame_all() ||
                self::is_flag_set(self::MANUAL_PARAM_NAME) ||
                (get_config('tool_excimer', 'excimeranableauto'));
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
     *
     * @throws \dml_exception
     */
    public static function init(): void {
        $samplems = (int)get_config('tool_excimer', 'excimersample_ms');
        $hassensiblerange = $samplems > 10 && $samplems < 10000;
        $sampleperiod = $hassensiblerange ? round($samplems / 1000, 3) : self::EXCIMER_PERIOD;

        $prof = new \ExcimerProfiler();
        $prof->setPeriod($sampleperiod);

        $started = microtime(true);

        // TODO: a setting to determine if logs are saved locally or sent to an external process.

        // Call self::on_flush whenever the logs get flushed.
        $onflush = function(\ExcimerLog $log) use ($started) {
            manager::on_flush($log, $started);
        };
        $prof->setFlushCallback($onflush, self::EXCIMER_LOG_LIMIT);

        // Stop the profiler as a part of the shutdown sequence.
        \core_shutdown_manager::register_function(
            function() use ($prof) {
                $prof->stop(); $prof->flush();
            }
        );

        $prof->start();
    }

    /**
     * Called when the Excimer log flushes.
     *
     * @param \ExcimerLog $log
     * @param float $started
     * @throws \dml_exception
     */
    public static function on_flush(\ExcimerLog $log, float $started): void {
        $stopped = microtime(true);
        $duration = $stopped - $started;

        if (self::is_flag_set(self::MANUAL_PARAM_NAME)) {
            $reason = self::REASON_MANUAL;
            $dowesave = true;
        } else if (isset($_SESSION[self::FLAME_ON_PARAM_NAME])) {
            $reason = self::REASON_FLAMEALL;
            $dowesave = true;
        } else {
            $reason = self::REASON_AUTO;
            $dowesave = ($duration * 1000) >= (int) get_config('tool_excimer', 'excimertrigger_ms');
            if ($dowesave) {
                $numrecorded = profile::get_num_auto_profiles();
                if ($numrecorded >= (int) get_config('tool_excimer', 'excimernum_slowest')) {
                    if ($duration <= profile::get_fastest_auto_profile()->duration) {
                        $dowesave = false;
                    } else {
                        profile::purge_fastest_auto_profiles(1);
                    }
                }
            }
        }
        if ($dowesave) {
            profile::save($log, $reason, (int) $started, $duration);
        }
    }

    /**
     *  Callback for when the 'excimernum_slowest' setting is changed.
     *
     * @param string $name
     * @throws \dml_exception
     */
    public static function on_num_slow_setting_change(string $name) {
        $numtokeep = (int) get_config('tool_excimer', 'excimernum_slowest');
        $numkept = profile::get_num_auto_profiles();
        if ($numtokeep < $numkept) {
            profile::purge_fastest_auto_profiles($numkept - $numtokeep);
        }
    }
}
