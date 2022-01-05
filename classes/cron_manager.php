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
    public static function set_callbacks($prof, $timer, $started) {
        $timer->setCallback(function($s) use ($prof, $started) {
            $log = $prof->flush();
            cron_manager::process($log, $started);
        });

        \core_shutdown_manager::register_function(
            function() use ($prof, $timer, $started) {
                $timer->stop();
                $prof->stop();
                $log = $prof->flush();
                cron_manager::process($log, $started);
            }
        );
    }

    protected static function process($log, $started) {
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
                    profile::save_from_node($node, $reasons, (int) $started, $duration, $current);
                }
            }
        }
    }

    public static function extract_task_nodes(flamed3_node $profilenode): array {
        $tasknodes = [];
        $crstnode = $profilenode->find_first_subnode('cron_run_scheduled_tasks');
        if ($crstnode) {
            foreach ($crstnode->children as $node) {
                if ($node->name == 'cron_run_inner_scheduled_task') {
                    foreach ($node->children as $innernode) {
                        if (strpos($innernode->name, '::execute') !== false) {
                            $tasknodes[] = $innernode;
                        }
                    }
                }
            }
        }
        $cratnode = $profilenode->find_first_subnode('cron_run_adhoc_tasks');
        if ($cratnode) {
            foreach ($cratnode->children as $node) {
                if ($node->name == 'cron_run_inner_adhoc_task') {
                    foreach ($node->children as $innernode) {
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
