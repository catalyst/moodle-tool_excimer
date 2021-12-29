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
        self::REASON_AUTO => 'slowest',
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
        if (($duration * 1000) >= self::get_min_duration_for_reason_slow()) {
            $reason |= self::REASON_SLOW;
        }
        return $reason;
    }

    /**
     * Returns the minimum duration for profiles matching this reason and page/request.
     *
     * @param  int $reason - the profile type or REASON_*
     * @return float duration (in milliseconds) of the fastest profile for a given reason and request/page.
     */
    public static function get_min_duration_for_request_and_reason(string $request, int $reason): float {
        global $DB;

        $reasonstr = self::REASON_STR_MAP[$reason];
        $pagequota = (int) get_config('tool_excimer', "num_' . $reasonstr . '_by_page");

        // Grab the fastest profile for this page/request, and use that as
        // the lower boundary for any new profiles of this page/request.
        $cachekey = "profile_type_$reason" . "_page_$request" . '_min_duration_ms';
        $cache = \cache::make('tool_excimer', 'timings');
        $result = $cache->get($cachekey);
        if ($result === false) {
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
            ], 0, $pagequota);
            // Cache the results in milliseconds (avoids recalculation later).
            $result = (end($resultset)->min_duration ?? 0) * 1000;
            $cache->set($cachekey, $result);
        }
        return $result;
    }

    /**
     * Returns the minimum duration for profiles matching this reason.
     *
     * @param  int $reason - the profile type or REASON_*
     * @return float duration (in milliseconds) of the fastest profile for a given reason.
     */
    public static function get_min_duration_for_reason(int $reason): float {
        global $DB;

        $reasonstr = self::REASON_STR_MAP[$reason];
        $quota = (int) get_config('tool_excimer', "num_$reasonstr");
        $cache = \cache::make('tool_excimer', 'timings');
        // Grab the fastest profile across the slow profiles, and use that
        // as the lower boundary for any new profiles.
        $cachekey = "profile_type_$reason" . '_min_duration_ms';
        $result = $cache->get($cachekey);
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
            ], 0, $quota);
            // Cache the results in milliseconds (avoids recalculation later).
            $result = (end($resultset)->min_duration ?? 0) * 1000;
            $cache->set($cachekey, $result);
        }
        return $result;
    }

    /**
     * Quota for this profile type (e.g. REASON_SLOW) has been reached.
     *
     * @param int reason
     * @return bool whether or not the quota is filled.
     */
    public static function has_filled_reason_quota(int $reason): bool {
        global $DB;

        $reasonstr = self::REASON_STR_MAP[$reason];
        $quota = (int) get_config('tool_excimer', "num_$reasonstr");

        // Get and set cache.
        $reasons = $DB->sql_bitand('reason', $reason);
        $sql = "SELECT count(*)
                  FROM {tool_excimer_profiles}
                 WHERE $reasons != ?";
        $count = $DB->count_records_sql($sql, [
            self::REASON_NONE,
        ]);
        return $count >= $quota;
    }

    /**
     * Quota for this page, for this profile type (e.g. REASON_SLOW) has been reached.
     *
     * @param int reason
     * @return bool whether or not the quota is filled.
     */
    public static function has_filled_page_and_reason_quota(string $request, int $reason): bool {
        global $DB;
        $reasonstr = self::REASON_STR_MAP[$reason];
        $quota = (int) get_config('tool_excimer', "num_' . $reasonstr . '_by_page");

        // Get and set cache.
        $reasons = $DB->sql_bitand('reason', $reason);
        $sql = "SELECT count(*)
                  FROM {tool_excimer_profiles}
                 WHERE $reasons != ?
                       AND request = ?";
        $count = $DB->count_records_sql($sql, [
            self::REASON_NONE,
            $request,
        ]);
        return $count >= $quota;
    }

    /**
     * Checks the quotas, and returns the best value for the min duration a
     * profile should be, before it should be saved.
     *
     * This will check quotass per page first, then check the more broad quotas, because it only
     * matters if the page quota has been exceeded, e.g. after the broad quota
     * has been reached.
     *
     * Assuming limits of page=5 and overall=10 (unlikely to be less than the
     * page quota). The following behaviour is based on this assumption, that
     * the page limit is less than the other limit (the reason limit or overall
     * limit in this example).
     *
     * With all examples, the check should look at the profile duration based on
     * the quota that's filled, and if both are filled then it should look at
     * the pagequota values before adding a new profile.
     *
     * Examples:
     * [quota filled, pagequota filled]:
     * Should look and add new profiles based on pagequota, as this is the limiting factor before it can be saved.
     *
     * [quota filled, pagequota notfilled]:
     * Should be based on (overall) quota filled.
     *
     * [quota notfilled, pagequota filled]:
     * Should be based on the page quota.
     *
     * [quota notfilled, pagequota notfilled]:
     * Should be based on configuration thresholds/limits.
     *
     * @return float the minimum duration required, for a profile to be stored with the REASON_AUTO reason.
     */
    public static function get_min_duration_for_reason_slow(): float {
        // Quota for this page, for this profile type (e.g. REASON_SLOW) has been reached.
        $request = profile::get_request();

        // Get the cached timings for the fastest of the stored profiles, to
        // ensure anything faster than this does not get stored iif the quota is
        // reached. This cache should be reset when, a new profile is
        // stored/deleted, or settings have changed.
        if (self::has_filled_page_and_reason_quota($request, self::REASON_AUTO)) {
            // Quota for this profile type (e.g. REASON_SLOW) for this page/request has been reached.
            $result = self::get_min_duration_for_request_and_reason($request, self::REASON_AUTO);
        } else if (self::has_filled_reason_quota(self::REASON_AUTO)) {
            // Quota for this profile type (e.g. REASON_SLOW) has been reached.
            $result = self::get_min_duration_for_reason(self::REASON_AUTO);
        }

        // Between the config and the fastest stored profile flagged for being
        // slow, grab the slower option as that is the new minimum (#106).
        $triggerms = (int) get_config('tool_excimer', 'trigger_ms');
        $minduration = max($triggerms, $result ?? 0);

        return $minduration;
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
