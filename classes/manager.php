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

/**
 * Manages Excimer profiling.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    const FLAME_ME_PARAM_NAME = 'FLAMEME';
    const FLAME_ON_PARAM_NAME = 'FLAMEALL';
    const FLAME_OFF_PARAM_NAME = 'FLAMEALLSTOP';
    const NO_FLAME_PARAM_NAME = 'DONTFLAMEME';

    private $processor;
    private $profiler;
    private $timer;
    private $starttime;

    public function get_profiler(): \ExcimerProfiler {
        return $this->profiler;
    }

    public function get_timer(): \ExcimerTimer {
        return $this->timer;
    }

    public function get_starttime(): float {
        return $this->starttime;
    }

    /**
     * Initialises the manager.
     *
     * @param processor $processor
     * @throws \coding_exception
     */
    public function __construct(processor $processor) {
        $this->processor = $processor;
    }

    public function init() {
        $sampleperiod = script_metadata::get_sampling_period();
        $timerinterval = script_metadata::get_timer_interval();

        $this->profiler = new \ExcimerProfiler();
        $this->profiler->setPeriod($sampleperiod);

        $this->timer = new \ExcimerTimer();
        $this->timer->setPeriod($timerinterval);

        $this->starttime = microtime(true);

        $this->processor->init($this);

        $this->profiler->start();
        $this->timer->start();
    }

    /**
     * Creates the manager object using the appropriate processor.
     *
     * @return manager
     * @throws \coding_exception
     */
    public static function create(): manager {
        if (self::is_cron()) {
            return new manager(new cron_processor());
        } else {
            return new manager(new regular_processor());
        }
    }

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
                    self::is_flag_set(self::FLAME_ME_PARAM_NAME) ||
                    (get_config('tool_excimer', 'enable_auto'))
                ) &&
                !moodle_needs_upgrading() &&
                class_exists('\ExcimerProfiler');
    }

    /**
     * True if running cron or adhoc_task scripts.
     *
     * @return bool
     */
    public static function is_cron(): bool {
        global $SCRIPT;
        return (
            strpos($SCRIPT, 'admin/cli/cron.php') !== false ||
            strpos($SCRIPT, 'admin/cli/adhoc_task.php') !== false ||
            strpos($SCRIPT, 'admin/cron.php') !== false
        );
    }

    /**
     * Retrieves all the reasons for saving a profile.
     *
     * @param profile $profile
     * @return int Reasons as bit flags.
     * @throws \dml_exception
     */
    public function get_reasons(profile $profile): int {
        global $SESSION;
        $reason = profile::REASON_NONE;
        if (self::is_flag_set(self::FLAME_ME_PARAM_NAME)) {
            $reason |= profile::REASON_FLAMEME;
        }
        if (isset($SESSION->toolexcimerflameall)) {
            $reason |= profile::REASON_FLAMEALL;
        }

        if ($this->is_considered_slow($profile)) {
            $reason |= profile::REASON_SLOW;
        }
        return $reason;
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
    public function is_considered_slow(profile $profile): bool {
        $duration = $profile->get('duration');

        // First, check against the overall minimum duration value to ensure it meets the
        // minimum required duration for the profile to be considered slow.
        if ($duration <= $this->processor->get_min_duration()) {
            return false;
        }

        // If a min duration exists, it means the quota is filled, and only
        // profiles slower than the fastest stored profile should be stored.
        $minduration = profile::get_min_duration_for_reason(profile::REASON_SLOW);
        if ($minduration && $duration <= $minduration) {
            return false;
        }

        // This is reached if the duration provided should be checked with the
        // request minimum.
        // If a min duration exists, it means the quota is filled, and only
        // profiles slower than the fastest stored profile should be stored.
        $requestminduration = profile::get_min_duration_for_group_and_reason($profile->get('groupby'), profile::REASON_SLOW);
        if ($requestminduration && $duration <= $requestminduration) {
            return false;
        }

        // By this stage, the duration provided should have exceeded the min
        // requirements for all the different timing types (if they exist).
        return true;
    }
}
