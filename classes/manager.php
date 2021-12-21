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
    const NO_FLAME_PARAM_NAME = 'DONTFLAMEME';

    /** Reason - MANUAL - Profiles are manually stored for the request using FLAMEME as a page param. */
    const REASON_MANUAL   = 0b0001;

    /** Reason - AUTO - Set when conditions are met and these profiles are automatically stored. */
    const REASON_AUTO     = 0b0010;

    /** Reason - FLAMEALL - Toggles profiling for all subsequent pages, until FLAMEALLSTOP param is passed as a page param. */
    const REASON_FLAMEALL = 0b0100;

    /** Reason - NONE - Default fallback reason value, this will not be stored. */
    const REASON_NONE = 0b0000;

    /** Reasons for profiling (bitmask flags). NOTE: Excluding the NONE option intentionally. */
    const REASONS = [
        self::REASON_MANUAL,
        self::REASON_AUTO,
        self::REASON_FLAMEALL,
    ];

    const EXCIMER_LOG_LIMIT = 10000;
    const EXCIMER_PERIOD = 0.01;  // Default in seconds; used if config is out of sensible range.

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
        global $SESSION;
        if (self::is_flag_set(self::FLAME_OFF_PARAM_NAME)) {
            unset($SESSION->toolexcimerflameall);
        } else if (self::is_flag_set(self::FLAME_ON_PARAM_NAME)) {
            $SESSION->toolexcimerflameall = true;
        }
        return isset($SESSION->toolexcimerflameall);
    }

    /**
     * Returns true if the profiler is currently set to be used.
     *
     * @return bool
     * @throws \dml_exception
     */
    public static function is_profiling(): bool {
        return  !self::is_flag_set(self::NO_FLAME_PARAM_NAME) && (
                    self::is_flame_all() ||
                    self::is_flag_set(self::MANUAL_PARAM_NAME) ||
                    (get_config('tool_excimer', 'enable_auto'))
                );
    }

    /**
     * Initialises the profiler and also sets up the shutdown callback.
     *
     * @throws \dml_exception
     */
    public static function init(): void {
        $samplems = (int)get_config('tool_excimer', 'sample_ms');
        $hassensiblerange = $samplems > 10 && $samplems < 10000;
        $sampleperiod = $hassensiblerange ? round($samplems / 1000, 3) : self::EXCIMER_PERIOD;

        $longinterval = get_config('tool_excimer', 'long_interval_s');

        $prof = new \ExcimerProfiler();
        $prof->setPeriod($sampleperiod);

        $timer = new \ExcimerTimer();
        $timer->setPeriod($longinterval);

        $started = microtime(true);

        $oninterval = function($s) use ($prof, $started) {
            manager::on_interval($prof, $started);
        };
        $timer->setCallback($oninterval);

        // TODO: a setting to determine if logs are saved locally or sent to an external process.

        // Call self::on_flush whenever the logs get flushed.
        $onflush = function(\ExcimerLog $log) use ($started) {
            manager::on_flush($log, $started);
        };
        $prof->setFlushCallback($onflush, self::EXCIMER_LOG_LIMIT);

        // Stop the profiler as a part of the shutdown sequence.
        \core_shutdown_manager::register_function(
            function() use ($prof, $timer) {
                $timer->stop();
                $prof->stop(); $prof->flush();
            }
        );

        $prof->start();
        $timer->start();
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

        $reason = self::get_reasons($duration);
        if ($reason !== self::REASON_NONE) {
            profile::save($log, $reason, (int) $started, $duration);
        }
    }

    /**
     * Retrieves all the reasons for saving a profile.
     *
     * @param float $duration The duration of the script so far.
     * @return int Reasons as bit flags.
     * @throws \dml_exception
     */
    public static function get_reasons(float $duration): int {
        global $SESSION;

        $reason = self::REASON_NONE;
        if (self::is_flag_set(self::MANUAL_PARAM_NAME)) {
            $reason |= self::REASON_MANUAL;
        }
        if (isset($SESSION->toolexcimerflameall)) {
            $reason |= self::REASON_FLAMEALL;
        }
        if (($duration * 1000) >= (int) get_config('tool_excimer', 'trigger_ms')) {
            $reason |= self::REASON_AUTO;
        }
        return $reason;
    }

    /**
     * Called when an Excimer timer event is triggered.
     *
     * @param \ExcimerProfiler $profile
     * @param float $started
     * @throws \dml_exception
     */
    public static function on_interval(\ExcimerProfiler $profile, float $started): void {
        $current = microtime(true);
        $duration = $current - $started;

        $reason = self::get_reasons($duration);
        if ($reason === self::REASON_NONE) {
            return;
        }

        // TODO - may need to suspend profiling while getting the log.
        // See https://github.com/wikimedia/php-excimer/blob/8dd7a62856866f942b52733d7a7075242ce5483e/stubs/ExcimerProfiler.php#L87.
        $log = $profile->getLog();
        $id = profile::save($log, $reason, (int) $started, $duration);
        profile::$partialsaveid = $id;
    }
}
