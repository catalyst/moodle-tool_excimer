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

/**
 * Stores samples copied over from the profiler, to be used in a profile.
 *
 * @package    tool_excimer
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class sample_set {
    public $name;
    public $samples = [];
    public $starttime;

    public $limit;

    public $filterrate = 1;
    public $current = 0;

    /**
     * Constructs the sample set.
     *
     * @param string $name
     * @param float $starttime
     * @param float|null $limit
     */
    public function __construct(string $name, float $starttime, int $limit = null) {
        $this->name = $name;
        $this->starttime = $starttime;
        $this->limit = $limit;
    }

    /**
     * Add a sample to the sample store, applying any filters.
     *
     * @param array $sample
     */
    public function add_sample(\ExcimerLogEntry $sample): void {
        if (count($this->samples) === $this->limit) {
            $this->apply_doubling();
        }
        $this->current += 1;
        if ($this->current == $this->filterrate) {
            $this->samples[] = $sample;
            $this->current = 0;
        }
    }

    /**
     * Doubles the filter rate, and strips every second sample from the set.
     */
    public function apply_doubling() {
        $this->filterrate *= 2;
        $this->samples = array_values(
            array_filter(
                $this->samples,
                function($key) {
                    return ($key % 2);
                },
                ARRAY_FILTER_USE_KEY
            )
        );
    }
}
