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
 * Memory sample set.
 *
 * @package    tool_excimer
 * @author     Dustin Huynh <dustinhuynh@catalyst-au.net>
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class memory_sample_set extends sample_set {
    /**
     * Processing given samples before adding into the sample set.
     *
     * @param array|\ExcimerLogEntry $sample
     */
    public function process_samples($sample) {
        $this->samplepool[] = $sample;
        $this->totaladded++;
    }

    /**
     * Merge memory usage sample set
     * @param array $samples
     * @param int $chunksize
     * @return array merged sample set.
     */
    public function merge_sample_set(array $samples, int $chunksize = 2) {
        $newsamples = [];
        $count = count($samples);
        for ($i = 0; $i < $count; $i += $chunksize) {
            // The final value should be the higher value which can highlight the considerable memory usage.
            $max = $samples[$i]['value'];
            $end = min($i + $chunksize, $count);
            for ($j = $i + 1; $j < $end; $j++) {
                $max = max($max, $samples[$j]['value']);
            }
            $newsamples[] = [
                'sampleindex' => $samples[$i]['sampleindex'],
                'value' => $max,
            ];
        }
        return $newsamples;
    }

    /**
     * Number of real samples, that is currently in possession.
     *
     * @return int count of $this->samples
     */
    public function count(): int {
        return count($this->samples);
    }
}
