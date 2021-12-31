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

    /** Reason - SLOW - Set when conditions are met and these profiles are automatically stored. */
    const REASON_SLOW     = 0b0010;

    /** Reason - FLAMEALL - Toggles profiling for all subsequent pages, until FLAMEALLSTOP param is passed as a page param. */
    const REASON_FLAMEALL = 0b0100;

    /** Reason - NONE - Default fallback reason value, this will not be stored. */
    const REASON_NONE = 0b0000;

    /** Reasons for profiling (bitmask flags). NOTE: Excluding the NONE option intentionally. */
    const REASONS = [
        self::REASON_MANUAL,
        self::REASON_SLOW,
        self::REASON_FLAMEALL,
    ];

    const REASON_STR_MAP = [
        self::REASON_MANUAL => 'manual',
        self::REASON_SLOW => 'slowest',
        self::REASON_FLAMEALL => 'flameall',
    ];

    const EXCIMER_LOG_LIMIT = 10000;
    const EXCIMER_PERIOD = 0.01;  // Default in seconds; used if config is out of sensible range.
    const EXCIMER_LONG_PERIOD = 10; // Default period for partial saves.

    /**
     * Checks if the given flag is set
     *
     * @param string $flag Name of the flag
     * @return bool
     */
    public static function is_flag_set(string $flag): bool {
        return isset($_REQUEST[$flag]) ||
               isset($_COOKIE[$flag]) ||
               !empty(getenv($flag));
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
        return !self::is_flag_set(self::NO_FLAME_PARAM_NAME) && (
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

        $longinterval = (int)get_config('tool_excimer', 'long_interval_s');
        if ($longinterval < 1) {
            $longinterval = self::EXCIMER_LONG_PERIOD;
        }

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
                $prof->stop();
                $prof->flush();
            }
        );

        $prof->start();
        $timer->start();
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

        if (self::is_considered_slow($duration * 1000)) {
            $reason |= self::REASON_SLOW;
        }
        return $reason;
    }

    /**
     * Returns the minimum duration for profiles matching this reason and page/request.
     *
     * Cost: 1 cache read (ideally)
     * Otherwise: 1 cache read, 1 DB read and 1 cache write.
     *
     * @param  int $reason - the profile type or REASON_*
     * @return float duration (in milliseconds) of the fastest profile for a given reason and request/page.
     */
    public static function get_min_duration_for_request_and_reason(string $request, int $reason): float {
        global $DB;

        $reasonstr = self::REASON_STR_MAP[$reason];
        $pagequota = (int) get_config('tool_excimer', 'num_' . $reasonstr . '_by_page');

        // Grab the fastest profile for this page/request, and use that as
        // the lower boundary for any new profiles of this page/request.
        $cachekey = $request;
        $cachefield = "min_duration_for_reason_$reason";
        $cache = \cache::make('tool_excimer', 'request_metadata');
        $result = $cache->get($cachekey);
        if ($result === false || !isset($result[$cachefield])) {
            // NOTE: Opting to query this way instead of using MIN due to
            // the fact valid profiles will be added and the limits will be
            // breached for 'some time'. This will keep the constraints as
            // correct as possible.
            $reasons = $DB->sql_bitand('reason', $reason);
            $sql = "SELECT duration as min_duration
                      FROM {tool_excimer_profiles}
                     WHERE $reasons != ?
                           AND request = ?
                  ORDER BY duration DESC
                     ";
            $resultset = $DB->get_records_sql($sql, [
                self::REASON_NONE,
                $request,
            ], $pagequota - 1, 1); // Will fetch the Nth item based on the quota.
            // Cache the results in milliseconds (avoids recalculation later).
            $minduration = (end($resultset)->min_duration ?? 0) * 1000;
            $result[$cachefield] = $minduration;
            $cache->set($cachekey, $result);
        }
        return (float)$result[$cachefield];
    }

    /**
     * Returns the minimum duration for profiles matching this reason.
     *
     * Cost: Should be free as long as the cache exists in the config.
     * Otherwise: 1 DB read, 1 cache write
     *
     * @param  int $reason - the profile type or REASON_*
     * @return float duration (in milliseconds) of the fastest profile for a given reason.
     */
    public static function get_min_duration_for_reason(int $reason): float {
        global $DB;

        $reasonstr = self::REASON_STR_MAP[$reason];
        $quota = (int) get_config('tool_excimer', "num_$reasonstr");

        $cachekey = 'profile_type_' . $reason . '_min_duration_ms';
        $result = get_config('tool_excimer', $cachekey);
        if ($result === false) {
            // Get and set cache.
            $reasons = $DB->sql_bitand('reason', $reason);
            $sql = "SELECT duration as min_duration
                      FROM {tool_excimer_profiles}
                     WHERE $reasons != ?
                  ORDER BY duration DESC
                     ";
            $resultset = $DB->get_records_sql($sql, [
                self::REASON_NONE,
            ], $quota - 1, 1); // Will fetch the Nth item based on the quota.
            // Cache the results in milliseconds (avoids recalculation later).
            $result = (end($resultset)->min_duration ?? 0) * 1000;
            set_config($cachekey, $result, 'tool_excimer');
        }
        return (float)$result;
    }

    /**
     * Returns whether or not the profile should be stored based on the duration provided.
     *
     * The order in which items are checked are based on the cost of those
     * checks, with get_config related calls considered free, cache api being
     * slightly more expensive and DB calls being the most expensive.
     *
     * The order is also based on the fact most things should NOT be captured as
     * it should be harder to reach the minimums required for a new profile to
     * be stored once quotas are maxed.
     *
     * @param float duration of the current profile
     * @return bool whether or not the profile should stored with the REASON_SLOW reason.
     */
    public static function is_considered_slow(float $duration): bool {
        // First, check against the trigger_ms value to ensure it meets the
        // minimum required duration for the profile to be considered slow.
        $triggerms = get_config('tool_excimer', 'trigger_ms');
        if ($triggerms && $duration <= $triggerms) {
            return false;
        }

        // If a min duration exists, it means the quota is filled, and only
        // profiles slower than the fastest stored profile should be stored.
        $minduration = self::get_min_duration_for_reason(self::REASON_SLOW);
        if ($minduration && $duration <= $minduration) {
            return false;
        }

        // This is reached if the duration provided should be checked with the
        // request minimum.
        // If a min duration exists, it means the quota is filled, and only
        // profiles slower than the fastest stored profile should be stored.
        $request = profile::get_request();
        $requestminduration = self::get_min_duration_for_request_and_reason($request, self::REASON_SLOW);
        if ($requestminduration && $duration <= $requestminduration) {
            return false;
        }

        // By this stage, the duration provided should have exceeded the min
        // requirements for all the different timing types (if they exist).
        return true;
    }

    /**
     * Clears the plugin cache for keys used for the provided reasons
     *
     * @param int $reason bitmap of reason(s)
     */
    public static function clear_min_duration_cache_for_reason(int $reason): void {
        foreach (self::REASONS as $basereason) {
            if ($reason & $basereason) {
                // Clear the plugin config cache for this profile's reason.
                $cachekey = 'profile_type_' . $basereason . '_min_duration_ms';
                unset_config($cachekey, 'tool_excimer');
            }
        }
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
            profile::save($log, $reason, (int) $started, $duration, (int) $stopped);
        }
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
        if ($reason !== self::REASON_NONE) {
            // TODO - may need to suspend profiling while getting the log. See issue #116.
            $log = $profile->getLog();
            $id = profile::save($log, $reason, (int) $started, $duration);
            profile::$partialsaveid = $id;
        }
    }
}
