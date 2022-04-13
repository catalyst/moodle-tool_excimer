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
    public $sampleset;

    /**
     * Initialises the processor
     *
     * @param manager $manager The profiler manager object
     */
    public function init(manager $manager) {
        $this->sampleset = new sample_set(
            script_metadata::get_request(),
            (int) $manager->get_starttime(),
            script_metadata::get_sample_limit()
        );

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
        $reasonstack = 0;
        $log = $manager->get_profiler()->flush();
        $this->sampleset->add_many_samples($log);

        $current = microtime(true);
        $this->profile->set('duration', $current - $manager->get_starttime());
        if ($this->sampleset->get_stack_depth() > script_metadata::get_stack_limit()) {
            $reasonstack = profile::REASON_STACK;
        }
        $reason = $manager->get_reasons($this->profile) + $reasonstack;
        if ($reason !== profile::REASON_NONE) {
            $this->profile->set('reason', $reason);
            $this->profile->set('finished', $isfinal ? (int) $current : 0);
            $this->profile->set('flamedatad3', flamed3_node::from_excimer_log_entries($this->sampleset->samples));
            $this->profile->save_record();
        }
    }
}

