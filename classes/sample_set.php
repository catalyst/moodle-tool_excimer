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
    public $starttime;

    public $samples = [];

    public $samplelimit;

    /** @var int If $filterrate is R, then only each Rth sample is recorded. */
    private $filterrate = 1;

    /** @var int Internal counter to help with filtering. */
    private $counter = 0;

    /**
     * Constructs the sample set.
     *
     * @param string $name
     * @param float $starttime
     * @param int $samplelimit
     */
    public function __construct(string $name, float $starttime, int $samplelimit) {
        $this->name = $name;
        $this->starttime = $starttime;
        $this->samplelimit = $samplelimit;
    }

    /**
     * Add a sample to the sample store, applying any filters.
     *
     * @param array $sample
     */
    public function add_sample(\ExcimerLogEntry $sample) {
        if (count($this->samples) === $this->samplelimit) {
            $this->apply_doubling();
        }
        $this->counter += 1;
        if ($this->counter === $this->filterrate) {
            $this->samples[] = $sample;
            $this->counter = 0;
        }
    }

    /**
     * Add a number of samples.
     *
     * @param iterable $samples
     */
    public function add_many_samples(iterable $samples) {
        foreach ($samples as $sample) {
            $this->add_sample($sample);
        }
    }

    /**
     * Doubles the filter rate, and strips every second sample from the set.
     * Called when the sample limit is reached.
     *
     * We have two options here. Either double the sampling period, or apply a filter to record only the
     * Nth sample passed to sample_set. By using a filter and keeping the sampling period the same, we avoid
     * spilling over into the next task.
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
