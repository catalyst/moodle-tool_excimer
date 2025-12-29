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
    /** @var string name of the sample set. */
    public $name;
    /** @var float Starting time of the sample set. */
    public $starttime;

    /** @var array An array of sample objects. It could contain memory usage samples or tasks samples  */
    public $samples = [];

    /** @var int Sample limit. */
    public $samplelimit;
    /** @var int The maximum stack depth. */
    public $maxstackdepth = 0;

    /** @var int Internal counter of how many samples were added (regardless of how many are currently held). */
    private $totaladded = 0;

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
     * Return the stack depth for this set.
     *
     * @return int
     */
    public function get_stack_depth(): int {
        return (int) $this->maxstackdepth;
    }

    /**
     * Add a sample to the sample store, applying any filters.
     *
     * @param array|\ExcimerLogEntry $sample
     */
    public function add_sample($sample) {
        $ismemory = false;
        // If this is a log entry, it will count the number of total events processed instead.
        // Each time a sample is added, recalculate the maxstackdepth for this set.
        // The sample set can be either a tasksampleset (ExcimerLogEntry) or a memoryusagesampleset.
        if ($sample instanceof \ExcimerLogEntry) {
            $eventcount = $sample->getEventCount();
            $trace = $sample->getTrace();
            $this->totaladded += $eventcount;
            if ($trace) {
                $this->maxstackdepth = max($this->maxstackdepth, count($trace));
            }
            // We create a new dict to store the useful information.
            // This will be used in the merge functions because ExcimerLogEntry is immutable.
            // See https://github.com/wikimedia/php-excimer/blob/master/stubs/ExcimerLogEntry.php
            $this->samples[] = [
                'eventcount' => $eventcount,
                'trace' => $trace,
            ];
        } else {
            $this->samples[] = $sample;
            $this->totaladded++;
            $ismemory = true;
        }
        if (count($this->samples) >= $this->samplelimit) {
            $this->apply_doubling($ismemory);
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
     * Merge samples to increase storage
     * Called when the sample limit is reached.
     * @param bool $ismemory true if the dataset is a memory usage sample set.
     */
    public function apply_doubling($ismemory) {
        // Instead of dropping a half of samples, we merge them together to keep useful information.
        // The merge logic is different for each type of sample set
        $ismemory ? $this->merge_memory_usage_sample_set() : $this->merge_excimer_sample_set();
    }

    /**
     * Merge memory usage sample set
     *
     */
    private function merge_memory_usage_sample_set() {
        $newsamples = [];
        for ($i = 0; $i < count($this->samples); $i += 2) {
            // For the case the number of sample is odd.
            // We keep the last one as is.
            if ($i == count($this->samples) - 1) {
                $newsamples[] = $this->samples[$i];
            } else {
                // The final value should be the higher value which can highlight the considerable memory usage.
                $newsamples[] = [
                    'sampleindex' => $this->samples[$i]['sampleindex'],
                    'value' => max($this->samples[$i]['value'], $this->samples[$i + 1]['value']),
                ];
            }
        }
        $this->samples = $newsamples;
    }

    /**
     * Merge excimer sample set
     */
    private function merge_excimer_sample_set() {
        $newsamples = [];
        for ($i = 0; $i < count($this->samples); $i += 2) {
            // For the case the number of sample is odd.
            // We keep the last one as is.
            if ($i == count($this->samples) - 1) {
                $newsamples[] = $this->samples[$i];
            } else {
                // We keep stacktrace of the bigger sample as it contains the information of long-running task.
                if ($this->samples[$i]['eventcount'] >= $this->samples[$i + 1]['eventcount']) {
                    $trace = $this->samples[$i]['trace'];
                } else {
                    $trace = $this->samples[$i + 1]['trace'];
                }
                $newsamples[] = [
                    'eventcount' => (int) round(($this->samples[$i]['eventcount'] + $this->samples[$i + 1]['eventcount']) / 2),
                    'trace' => $trace,
                ];
            }
        }
        $this->samples = $newsamples;
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
    public function count(): int {
        $count = count($this->samples);
        if ($count > 0 && array_key_exists("eventcount", $this->samples[0])) {
            $count = array_reduce($this->samples, function ($acc, $sample) {
                $acc += $sample["eventcount"];
                return $acc;
            }, 0);
        }

        return $count;
    }
}
