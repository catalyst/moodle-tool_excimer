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
    /** Collect profile for the current script. */
    const FLAME_ME_PARAM_NAME = 'FLAMEME';
    /** Collect profiles for all scripts. */
    const FLAME_ON_PARAM_NAME = 'FLAMEALL';
    /** Stop collecting profiles for all scripts. */
    const FLAME_OFF_PARAM_NAME = 'FLAMEALLSTOP';
    /** Don't collect profile for current scripts. */
    const NO_FLAME_PARAM_NAME = 'DONTFLAMEME';
    /** The size of a PHP integer in bits (64 on Linux x86_64, 32 on Windows). */
    const PHP_INT_SIZE_BITS = 8 * PHP_INT_SIZE;
    /** Wiki URL for the approximate counting algorithm. */
    const APPROX_ALGO_WIKI_URL = 'https://en.wikipedia.org/wiki/Approximate_counting_algorithm';

    /** @var processor */
    private $processor;
    /** @var \ExcimerProfiler */
    private $profiler;
    /** @var \ExcimerTimer */
    private $timer;
    /** @var float */
    private $starttime;
    /** @var manager */
    private static $instance;

    /**
     * Generates the samples for the script.
     *
     * @return \ExcimerProfiler
     */
    public function get_profiler(): \ExcimerProfiler {
        return $this->profiler;
    }

    /**
     * Timer to create events to process samples generated so far.
     *
     * @return \ExcimerTimer
     */
    public function get_timer(): \ExcimerTimer {
        return $this->timer;
    }

    /**
     * Start time for the script.
     *
     * @return float
     */
    public function get_starttime(): float {
        return $this->starttime;
    }

    /**
     * Get manager instance.
     *
     * @return manager
     * @throws \dml_exception
     */
    public static function get_instance(): manager {
        if (!self::$instance) {
            self::create();
        }
        return self::$instance;
    }

    /**
     * Constructs the manager.
     *
     * @param processor $processor The object that processes the samples generated.
     */
    public function __construct(processor $processor) {
        $this->processor = $processor;
    }

    /**
     * Initialises the manager. Creates and starts the profiler and timer.
     *
     * @throws \dml_exception
     */
    public function init() {
        if (!self::is_testing()) {
            $sampleperiod = script_metadata::get_sampling_period();
            $timerinterval = script_metadata::get_timer_interval();

            $this->profiler = new \ExcimerProfiler();
            $this->profiler->setPeriod($sampleperiod);

            $this->timer = new \ExcimerTimer();
            $this->timer->setPeriod($timerinterval);

            $this->starttime = microtime(true);

            $this->profiler->start();
            $this->timer->start();
        }
    }

    /**
     * Starts processor if not unit testing.
     *
     */
    public function start_processor() {
        if (!self::is_testing()) {
            $this->processor->init($this);
        }
    }

    /**
     * Creates the manager object using the appropriate processor.
     *
     */
    public static function create() {
        if (self::is_cron()) {
            self::$instance = new manager(new cron_processor());
        } else {
            self::$instance = new manager(new web_processor());
        }
    }

    /**
     * Is this a unit test.
     * @throws \dml_exception
     */
    public static function is_testing(): bool {
        if (!PHPUNIT_TEST && self::is_profiling()) {
            return false;
        }
        return true;
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
        if ((int) $profile->get('maxstackdepth') > (int) script_metadata::get_stack_limit()) {
            $reason |= profile::REASON_STACK;
        }
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
     * @param profile $profile
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
        $minduration = profile_helper::get_min_duration_for_reason(profile::REASON_SLOW);
        if ($minduration && $duration <= $minduration) {
            return false;
        }

        // This is reached if the duration provided should be checked with the
        // request minimum.
        // If a min duration exists, it means the quota is filled, and only
        // profiles slower than the fastest stored profile should be stored.
        $requestminduration = profile_helper::get_min_duration_for_group_and_reason($profile->get('groupby'), profile::REASON_SLOW);
        if ($requestminduration && $duration <= $requestminduration) {
            return false;
        }

        // By this stage, the duration provided should have exceeded the min
        // requirements for all the different timing types (if they exist).
        return true;
    }

    /**
     * Increments a counter using the approximate counting algorithm.
     * Can handle up to PHP_INT_SIZE_BITS counts (2^PHP_INT_SIZE_BITS events).
     *
     * See https://en.wikipedia.org/wiki/Approximate_counting_algorithm
     *
     * @param int $current The current count.
     * @return int The new count.
     */
    public static function approximate_increment(int $current): int {
        // If the number of events is ever expected to be more than 2 billion, a refactor may be needed.

        $bits = random_int(PHP_INT_MIN, PHP_INT_MAX);

        // This gives us a number of bits equal to the current count. The rest are all zeroed.
        $bits = $bits << (self::PHP_INT_SIZE_BITS - $current);

        // If the bits are all zero (equiv to all coin tosses = tails), then we increment the counter.
        if ($bits == 0) {
            return $current + 1;
        }

        return $current;
    }
}
