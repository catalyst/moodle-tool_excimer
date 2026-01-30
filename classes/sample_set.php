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
abstract class sample_set {
    /** @var string name of the sample set. */
    public $name;
    /** @var float Starting time of the sample set. */
    public $starttime;

    /** @var array An array of sample objects. It could contain memory usage samples or tasks samples  */
    public $samples = [];

    /** @var array An array of sample objects. It is used to store sample upto the filterrate  */
    public $samplepool = [];

    /** @var int Sample limit. */
    public $samplelimit;

    /** @var int If is R, then only each Rth sample is recorded. */
    protected $filterrate = 1;

    /** @var int Internal counter of how many samples were added (regardless of how many are currently held). */
    protected $totaladded = 0;

    /**
     * Constructs the sample set.
     *
     * @param string $name
     * @param float $starttime
     * @param int|null $samplelimit
     */
    public function __construct(string $name, float $starttime, ?int $samplelimit = null) {
        $this->name = $name;
        $this->starttime = $starttime;
        $this->samplelimit = is_null($samplelimit) ? script_metadata::get_sample_limit() : $samplelimit;
    }

    /**
     * Add a sample to the sample store, applying any filters.
     *
     * @param array|\ExcimerLogEntry $sample
     */
    public function add_sample($sample) {
        if (count($this->samples) === $this->samplelimit) {
            $this->apply_doubling();
        }
        $this->process_samples($sample);
        if (count($this->samplepool) === $this->filterrate) {
            $this->samples[] = $this->get_sample_from_pool();
        }
    }

    /**
     * Processing given samples before adding into the sample set.
     *
     * @param array|\ExcimerLogEntry $sample
     */
    abstract public function process_samples($sample);

    /**
     * Merging sample set
     *
     * @param array $samples
     * @param int $chunksize
     */
    abstract public function merge_sample_set(array $samples, int $chunksize = 2);

    /**
     * Get a significant sample from pool.
     */
    private function get_sample_from_pool(): array {
        $mergedsample = $this->merge_sample_set($this->samplepool, $this->filterrate);
        $this->samplepool = [];
        return reset($mergedsample);
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
     * Merge samples to increase storage
     * Called when the sample limit is reached.
     */
    public function apply_doubling(): void {
        $this->filterrate *= 2;
        // Instead of dropping a half of samples, we merge them together to keep useful information.
        // The merge logic is different for each type of sample set.
        $this->samples = $this->merge_sample_set($this->samples);
    }

    /**
     * Number of samples that have gone through the add_sample method
     *
     * @return int number of samples added
     */
    public function total_added(): int {
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
    abstract public function count(): int;

    /**
     * Returns the filter rate to calculate the real sampling rate
     *
     * @return int
     */
    public function filter_rate() {
        return $this->filterrate;
    }
}
