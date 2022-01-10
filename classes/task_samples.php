<?php
// This file is part of Moodle - http://moodle.org/  <--change
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
 * Class for storing samples copied over from the profiler to match a current task.
 *
 * @package    tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2021 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class task_samples {
    public $name;
    public $samples = [];
    public $starttime;

    public function __construct($name, $starttime) {
        $this->name = $name;
        $this->starttime = $starttime;
    }

    /**
     * Add a sample the sample store.
     *
     * @param array $sample
     */
    public function add_sample(\ExcimerLogEntry $sample): void {
        $this->samples[] = $sample;
    }

    /**
     * Processes the stored samples to create a profile (if eligible).
     *
     * @param float $finishtime
     * @throws \dml_exception
     */
    public function process(float $finishtime): void {
        $duration = $finishtime - $this->starttime;

        $reasons = manager::get_reasons($this->name, $duration);
        // TODO: move this to inside if loop before full release.
        $node = flamed3_node::from_excimer_log_entries($this->samples);
        if ($reasons !== manager::REASON_NONE) {
            profile::save($this->name, $node, $reasons, (int) $this->starttime, $duration, $finishtime);
        }
        // TODO: Keep the following line for alpha release. Remove before full release.
        mtrace("++ task: $this->name, $node->value, $reasons, $this->starttime, $duration, $finishtime");
    }
}

