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
 * Makes one profile per run, with partial saving if the scripts runs long enough.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class web_processor implements processor {

    /** @var profile $profile The profile object for the run. */
    protected $profile;

    /** @var sample_set */
    protected $sampleset;
    protected $memoryusagesampleset;

    /**
     * Initialises the processor
     *
     * @param manager $manager The profiler manager object
     */
    public function init(manager $manager) {
        // Record and set initial memory usage at this point.
        $memoryusage = memory_get_usage();

        $request = script_metadata::get_request();
        $starttime = (int) $manager->get_starttime();
        $this->sampleset = new sample_set($request, $starttime);

        // Add sampleset for memory usage - this sets the baseline for the profile.
        $this->memoryusagesampleset = new sample_set($request, $starttime);
        $this->memoryusagesampleset->add_sample(['sampleindex' => 0, 'value' => $memoryusage]);

        $this->profile = new profile();
        $this->profile->add_env($this->sampleset->name);
        $this->profile->set('created', $this->sampleset->starttime);

        $manager->get_timer()->setCallback(function () use ($manager) {
            $this->process($manager, false);
        });

        \core_shutdown_manager::register_function(
            function () use ($manager) {
                $manager->get_timer()->stop();
                $manager->get_profiler()->stop();
                $this->process($manager, true);
            }
        );
    }

    /**
     * Gets the minimum duration required for a profile to be saved, as seconds.
     *
     * @return float
     */
    public function get_min_duration(): float {
        return (float) get_config('tool_excimer', 'trigger_ms') / 1000.0;
    }

    /**
     * Process a batch of Excimer logs.
     *
     * @param manager $manager
     * @param bool $isfinal
     * @throws \dml_exception
     */
    public function process(manager $manager, bool $isfinal) {
        $log = $manager->get_profiler()->flush();
        $this->sampleset->add_many_samples($log);

        $this->memoryusagesampleset->add_sample([
            'sampleindex' => $this->sampleset->total_added() + $this->memoryusagesampleset->count() - 1,
            'value' => memory_get_usage()
        ]);
        $current = microtime(true);
        $this->profile->set('duration', $current - $manager->get_starttime());
        $this->profile->set('maxstackdepth', $this->sampleset->get_stack_depth());
        $reason = $manager->get_reasons($this->profile);
        if ($reason !== profile::REASON_NONE) {
            $this->profile->set('reason', $reason);
            $this->profile->set('finished', $isfinal ? (int) $current : 0);
            $this->profile->set('memoryusagedatad3', $this->memoryusagesampleset->samples);
            $this->profile->set('flamedatad3', flamed3_node::from_excimer_log_entries($this->sampleset->samples));
            $this->profile->set('numsamples', $this->sampleset->count());
            $this->profile->set('samplerate', $this->sampleset->filter_rate() * get_config('tool_excimer', 'sample_ms'));
            $this->profile->save_record();
        }
    }
}

