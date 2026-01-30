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
 * Task sample set.
 *
 * @package    tool_excimer
 * @author     Dustin Huynh <dustinhuynh@catalyst-au.net>
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class excimer_sample_set extends sample_set {
    /** @var int The maximum stack depth. */
    public $maxstackdepth = 0;

    /**
     * Return the stack depth for this set.
     *
     * @return int
     */
    public function get_stack_depth(): int {
        return (int) $this->maxstackdepth;
    }

    /**
     * Processing given samples before adding into the sample set.
     *
     * @param array|\ExcimerLogEntry $sample
     */
    public function process_samples($sample) {
        // It will count the number of total events processed instead.
        // Each time a sample is added, recalculate the maxstackdepth for this set.
        $eventcount = $sample->getEventCount();
        $trace = $sample->getTrace();
        $this->totaladded += $eventcount;
        if ($trace) {
            $this->maxstackdepth = max($this->maxstackdepth, count($trace));
        }
        // We create a new dict to store the useful information.
        // This will be used in the merge functions because ExcimerLogEntry is immutable.
        // See https://github.com/wikimedia/php-excimer/blob/master/stubs/ExcimerLogEntry.php.
        $this->samplepool[] = [
            'eventcount' => $eventcount,
            'trace' => $trace,
        ];
    }

    /**
     * Merge excimer sample set.
     * @param array $samples
     * @param int $chunksize
     * @return array merged sample set.
     */
    public function merge_sample_set(array $samples, int $chunksize = 2) {
        $newsamples = [];
        $count = count($samples);
        for ($i = 0; $i < $count; $i += $chunksize) {
            // We keep stacktrace of the bigger sample as it contains the information of long-running task.
            $trace = null;
            $maxeventcount = 0;
            $sumeventcount = 0;
            $end = min($i + $chunksize, $count);
            $chunkcount = $end - $i;
            for ($j = $i; $j < $end; $j++) {
                if ($maxeventcount <= $samples[$j]['eventcount']) {
                    $trace = $samples[$j]['trace'];
                    $maxeventcount = $samples[$j]['eventcount'];
                }
                // Conduct sum and count for averaging.
                $sumeventcount += $samples[$j]['eventcount'];
            }
            $newsamples[] = [
                'eventcount' => $sumeventcount / $chunkcount,
                'trace' => $trace,
            ];
        }
        return $newsamples;
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
        if ($count > 0) {
            $count = array_reduce($this->samples, function ($acc, $sample) {
                $acc += $sample["eventcount"];
                return $acc;
            }, 0);
        }
        return (int) $count;
    }
}
