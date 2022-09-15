<?php
// This file is part of Moodle - https://moodle.org/
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

use core\persistent;

/**
 * Metadata for profile groups.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_group extends persistent {

    /** The name of the database table. */
    public const TABLE = 'tool_excimer_page_groups';

    /**
     * Returns the fuzzy counts for duration.
     *
     * @return array
     */
    protected function get_fuzzydurationcounts(): array {
        $raw = $this->raw_get('fuzzydurationcounts');
        if (empty($raw)) {
            return [];
        }

        return json_decode($raw, true);
    }

    /**
     * Sets the fuzzy duration counts.
     *
     * @param array $data
     */
    protected function set_fuzzydurationcounts(array $data) {
        $this->raw_set('fuzzydurationcounts', json_encode($data, JSON_FORCE_OBJECT));
    }

    /**
     * Records fuzzy count metadata about a page group.
     *
     * @param profile $profile The profile to pull the information from.
     */
    public static function record_fuzzy_counts(profile $profile) {

        // Get the profile group record, creating a new one if one does not yet exist.
        $month = userdate(time() - 360, '%Y%m'); // YYYYMM format.
        $pagegroup = self::get_record(['name' => $profile->get('groupby'), 'month' => $month]);
        if ($pagegroup === false) {
            $pagegroup = new page_group(0, (object) ['name' => $profile->get('groupby'), 'month' => $month]);
        }

        $existing = $pagegroup->to_record();

        // Fuzzy increment the count.
        $fuzzycount = manager::approximate_increment($pagegroup->get('fuzzycount'));
        $pagegroup->set('fuzzycount', $fuzzycount);

        // Fuzzy increment count for the duration slice.
        $duration = $profile->get('duration');
        $fuzzydurationcounts = $pagegroup->get('fuzzydurationcounts');
        $exp = ceil(log($duration, 2));
        if ($exp < 0) {
            $exp = 0;
        }
        $fuzzydurationcounts[$exp] = manager::approximate_increment($fuzzydurationcounts[$exp] ?? 0);
        $pagegroup->set('fuzzydurationcounts', $fuzzydurationcounts);

        // Add the duration to the fuzzy sum, treating each second as an event for counting.
        $fuzzydurationsum = $pagegroup->get('fuzzydurationsum');
        $duration = (int) round($duration);
        for ($i = 0; $i < $duration; ++$i) {
            $fuzzydurationsum = manager::approximate_increment($fuzzydurationsum);
        }
        $pagegroup->set('fuzzydurationsum', $fuzzydurationsum);

        if ($existing != $pagegroup->to_record()) {
            $pagegroup->save();
        }
    }

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'name' => ['type' => PARAM_TEXT, 'default' => ''],
            'month' => ['type' => PARAM_INT],
            'fuzzycount' => ['type' => PARAM_INT, 'default' => 0],
            // Fuzzydurationcounts is an assoc array (stored as JSON) of the approximate counts based on time taken.
            // The index is the base 2 log of the upper limit of the time period. So '0' is for 0-1 seconds. '1' is for
            // 1-2 seconds, '2' is for 2-4 seconds, '3' for 4-8 seconds etc.
            'fuzzydurationcounts' => ['type' => PARAM_TEXT, 'default' => '{}'],
            // Estimate of total time spent (in seconds) serving this group using fuzzy counts.
            'fuzzydurationsum' => ['type' => PARAM_INT, 'default' => 0],
        ];
    }
}
