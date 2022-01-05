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
 * Manages the profiling of cron runs.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_manager {
    /**
     * Sets callbacks to handle cron profiling.
     *
     * @param \ExcimerProfiler $profiler
     * @param \ExcimerTimer $timer
     * @param float $started
     * @throws \dml_exception
     */
    public static function set_callbacks(\ExcimerProfiler $profiler, \ExcimerTimer $timer, float $started): void {
        $timer->setCallback(function($s) use ($profiler, $started) {
            $log = $profiler->flush();
            cron_manager::process($log, $started);
        });

        \core_shutdown_manager::register_function(
            function() use ($profiler, $timer, $started) {
                $timer->stop();
                $profiler->stop();
                $log = $profiler->flush();
                cron_manager::process($log, $started);
            }
        );
    }

    /**
     * Process a batch of Excimer logs.
     *
     * @param \ExcimerLog $log
     * @param float $started
     * @throws \dml_exception
     */
    protected static function process(\ExcimerLog $log, float $started): void {
        $current = microtime(true);
        $duration = $current - $started;
        $reasons = manager::get_reasons($duration);
        if ($reasons) {
            $threshold = (int)get_config('tool_excimer', 'cron_sample_threshold');
            if ($threshold < 1) {
                $threshold = 2;
            }

            $flamed3node = flamed3_node::from_excimer($log);
            $nodes = self::extract_task_nodes($flamed3node);
            foreach ($nodes as $node) {
                if ($node->value >= $threshold) {
                    profile::save($node, $reasons, (int) $started, $duration, $current);
                }
            }
        }
    }

    /**
     * Extracts nodes from a flame tree that represent cron tasks.
     *
     * @param flamed3_node $node
     * @return array
     */
    public static function extract_task_nodes(flamed3_node $node): array {
        $tasknodes = [];
        $crstnode = $node->find_first_subnode('cron_run_scheduled_tasks');
        if ($crstnode) {
            foreach ($crstnode->children as $crstchild) {
                if ($crstchild->name == 'cron_run_inner_scheduled_task') {
                    foreach ($crstchild->children as $innernode) {
                        if (strpos($innernode->name, '::execute') !== false) {
                            $tasknodes[] = $innernode;
                        }
                    }
                }
            }
        }
        $cratnode = $node->find_first_subnode('cron_run_adhoc_tasks');
        if ($cratnode) {
            foreach ($cratnode->children as $cratchild) {
                if ($cratchild->name == 'cron_run_inner_adhoc_task') {
                    foreach ($cratchild->children as $innernode) {
                        if (strpos($innernode->name, '::execute') !== false) {
                            $tasknodes[] = $innernode;
                        }
                    }
                }
            }
        }
        return $tasknodes;
    }
}
