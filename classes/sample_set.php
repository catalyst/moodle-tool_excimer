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

    /** @var array $samples An array of \ExcimerLogEntry objects. */
    public $samples = [];

    /** @var ?int $samplelimit */
    public $samplelimit;
    public $maxstackdepth = 0;

    /** @var int If $filterrate is R, then only each Rth sample is recorded. */
    private $filterrate = 1;

    /** @var int Internal counter to help with filtering. */
    private $counter = 0;

    /** @var int Internal counter of how many samples were added (regardless of how many are currently held). */
    private $totaladded = 0;

    /**
     * Constructs the sample set.
     *
     * @param string $name
     * @param float $starttime
     * @param ?int $samplelimit
     */
    public function __construct(string $name, float $starttime, ?int $samplelimit = null) {
        $this->name = $name;
        $this->starttime = $starttime;
        $this->samplelimit = is_null($samplelimit) ? script_metadata::get_sample_limit() : $samplelimit;
    }

    /**
     * Return the stack depth for this set.
     *
     * @return int
     */
    public function get_stack_depth() : int {
        return (int) $this->maxstackdepth;
    }

    /**
     * Add a sample to the sample store, applying any filters.
     *
     * @param array|\ExcimerLogEntry $sample
     */
    public function add_sample($sample) {
        $trace = false;
        if (count($this->samples) === $this->samplelimit) {
            $this->apply_doubling();
        }
        $this->counter += 1;
        if ($this->counter === $this->filterrate) {
            $this->samples[] = $sample;
            $this->counter = 0;
        }
        // If this is a log entry, it will count the number of total events
        // processed instead.
        // Each time a sample is added, recalculate the maxstackdepth for this set.
        if ($sample instanceof \ExcimerLogEntry) {
            $this->totaladded += $sample->getEventCount();
            $trace = $sample->getTrace();
            if ($trace) {
                $this->maxstackdepth = max($this->maxstackdepth, count($trace));
            }
            return;
        }
        $this->totaladded++;
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

    /**
     * Number of samples that have gone through the add_sample method
     *
     * @return int number of samples added
     */
    public function total_added() {
        return $this->totaladded;
    }

    /**
     * Number of real samples, that is currently in possession.
     *
     * This is the total sum of events. Noting that the filtering, if required,
     * will have a reduced amount when compared to the totaladded count.
     *
     * @return int count of $this->samples
     */
    public function count() {
        if (isset($this->samples[0]) instanceof \ExcimerLogEntry) {
            $count = array_reduce($this->samples, function($acc, $sample) {
                $acc += $sample->getEventCount();
                return $acc;
            }, 0);
            return $count;
        }

        return count($this->samples);
    }

    /**
     * Returns the filter rate to calculate the real sampling rate
     *
     * @return int
     */
    public function filter_rate() {
        return $this->filterrate;
    }
}
