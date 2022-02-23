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

    /**
     * @var profile $profile The profile object for the run.
     */
    protected $profile;

    /**
     * Initialises the processor
     *
     * @param manager $manager The profiler manager object
     */
    public function init(manager $manager) {
        $this->profile = new profile();
        $this->profile->add_env(script_metadata::get_request());
        $this->profile->set('created', (int) $manager->get_starttime());

        $manager->get_timer()->setCallback(function($s) use ($manager) {
            $this->process($manager, false);
        });

        \core_shutdown_manager::register_function(
            function() use ($manager) {
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
    public function process(manager $manager, bool $isfinal): void {
        $log = $manager->get_profiler()->getLog();
        $current = microtime(true);
        $this->profile->set('duration', $current - $manager->get_starttime());
        $reason = $manager->get_reasons($this->profile);
        if ($reason !== profile::REASON_NONE) {
            $this->profile->set('reason', $reason);
            $this->profile->set('finished', $isfinal ? (int) $current : 0);
            $this->profile->set('flamedatad3', flamed3_node::from_excimer_log_entries($log));
            $this->profile->save_record();
        }
    }
}

