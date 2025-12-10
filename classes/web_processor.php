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
 * Processor for web script profiling.
 *
 * Makes one profile per run, with partial saving if the script runs long enough.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class web_processor implements processor {
    /** @var profile The profile object for the run. */
    protected $profile;

    /** @var sample_set */
    protected $sampleset;
    /** @var sample_set */
    protected $memoryusagesampleset;

    /** @var int */
    protected $minduration;
    /** @var int */
    protected $samplingperiod;
    /** @var int */
    protected $samplelimit;
    /** @var int */
    protected $maxsamples;
    /** @var int */
    protected $logcount = 0;
    /** @var bool */
    protected $partialsave;
    /** @var bool */
    protected static $alreadyprofiling = false;
    /** @var bool  */
    protected $hasoverlapped = false;

    /** @var array */
    protected static $logs = [];
    /**
     * Construct the web processor.
     */
    public function __construct() {
        // Preload config values to avoid DB access during processing. See manager::get_altconnection() for more information.
        $this->minduration = (float) get_config('tool_excimer', 'trigger_ms') / 1000.0;
        $this->partialsave = get_config('tool_excimer', 'enable_partial_save');
    }

    /**
     * Initialises the processor
     *
     * @param manager $manager The profiler manager object
     */
    public function init(manager $manager) {
        // Record and set initial memory usage at this point.
        $memoryusage = memory_get_usage();

        global $ME, $SCRIPT;

        $request = script_metadata::get_normalised_relative_script_path($ME, $SCRIPT);
        $starttime = (int) $manager->get_starttime();
        $this->sampleset = new sample_set($request, $starttime);

        // Add sampleset for memory usage - this sets the baseline for the profile.
        $this->memoryusagesampleset = new sample_set($request, $starttime);
        $this->memoryusagesampleset->add_sample(['sampleindex' => 0, 'value' => $memoryusage]);

        $this->profile = new profile();
        $this->profile->add_env($this->sampleset->name);
        $this->profile->set('created', $this->sampleset->starttime);
        $this->samplingperiod = script_metadata::get_sampling_period();
        $this->samplelimit = script_metadata::get_sample_limit();
        $this->maxsamples = script_metadata::get_max_samples();

        if ($this->partialsave) {
            $manager->get_profiler()->setFlushCallback(function ($log) use ($manager) {
                // Once overlapping has happened once, we prevent all future partial saving.
                if (!$this->hasoverlapped) {
                    $this->process($log, $manager);
                }
            }, $this->maxsamples);
        }

        \core_shutdown_manager::register_function(
            function () use ($manager) {
                $manager->get_profiler()->stop();
                if (!$this->partialsave) {
                    $log = $manager->get_profiler()->flush();
                    $this->process($log, $manager, true);
                }
                page_group::record_fuzzy_counts($this->profile);
            }
        );
    }

    /**
     * Gets the minimum duration required for a profile to be saved, as seconds.
     *
     * @return float
     */
    public function get_min_duration(): float {
        return $this->minduration;
    }

    /**
     * Doubling the sampling period when we reach the samples limit
     *
     * @param manager $manager
     */
    public function on_reach_limit(manager $manager) {
        $this->samplingperiod *= 2;
        // This will take effect the next time start() is called.
        $manager->get_profiler()->setPeriod($this->samplingperiod);
        $manager->get_profiler()->start();
    }

    /**
     * Process a batch of Excimer logs.
     *
     * @param \ExcimerLog $log
     * @param manager $manager
     * @param bool $isfinal
     * @throws \dml_exception
     */
    public function process($log, manager $manager, bool $isfinal = false) {
        // We want to prevent overlapping of processing, so skip if an existing process is still executing.
        // The profile logs will be kept and processed the next time.
        self::$logs[] = $log;

        // Doubling sampling period if it reaches the limit.
        $this->logcount += $log->count();
        if ($this->partialsave && $this->logcount >= $this->samplelimit) {
            $this->on_reach_limit($manager);
            $this->logcount = $this->logcount - $this->samplelimit;
        }

        if (self::$alreadyprofiling) {
            $this->hasoverlapped = true;
            debugging('tool_excimer: starting web_processor::process when previous process has not yet finished');
            if ($isfinal || $log->count() < $this->samplelimit) {
                // This should never happen.
                debugging('tool_excimer: alreadyprofiling is true during final process.');
            }
            return;
        }
        self::$alreadyprofiling = true;
        foreach (self::$logs as $log) {
            $memoryusage = memory_get_usage();
            $this->sampleset->add_many_samples($log);

            $this->memoryusagesampleset->add_sample([
                'sampleindex' => $this->sampleset->total_added() - 1,
                'value' => $memoryusage,
            ]);
            $current = microtime(true);
            $currentrusage = getrusage();
            $this->profile->set('duration', $current - $manager->get_starttime());
            $cpuduration = helper::get_rusage_timediff($manager->get_startrusage(), $currentrusage);
            $this->profile->set('usercpuduration', $cpuduration['user']);
            $this->profile->set('systemcpuduration', $cpuduration['system']);
            $this->profile->set('maxstackdepth', $this->sampleset->get_stack_depth());
            $reason = $manager->get_reasons($this->profile);
            if ($reason !== profile::REASON_NONE) {
                $this->profile->set('reason', $reason);
                $this->profile->set('finished', $isfinal ? (int)$current : 0);
                $this->profile->set('memoryusagedatad3', $this->memoryusagesampleset->samples);
                $this->profile->set('flamedatad3', flamed3_node::from_sample_set_samples($this->sampleset->samples));
                $this->profile->set('numsamples', count($this->sampleset->samples));
                $this->profile->set('numevents', $this->sampleset->count());
                $this->profile->set('samplerate', $this->samplingperiod * 1000);
                foreach (script_metadata::get_lock_info() as $field => $value) {
                    $this->profile->set($field, $value);
                }
                $this->profile->save_record();
            }
        }
        self::$logs = [];
        self::$alreadyprofiling = false;
    }
}
