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

    /** Name of the cache used in this class. */
    public const CACHE_NAME = 'page_group_metadata';

    /**
     * Returns the current month in YYYYMM format.
     * @return int
     */
    public static function get_current_month(): int {
        return monthint::from_timestamp(time());
    }

    /**
     * Gets the number of records stored for a paricular month.
     *
     * @param int $month
     * @return bool
     */
    public static function record_exists_for_month(int $month): bool {
        global $DB;
        return $DB->record_exists(self::TABLE, ['month' => $month]);
    }

    /**
     * Creates a page_group, either from the cache, or fresh.
     *
     * @param string $name The name of the page group
     * @param int $month The month, in YYYYMM format.
     * @return page_group
     */
    public static function get_page_group(string $name, int $month): page_group {
        // Get the cache and attempt to pull the pag group's record from it.
        $keydata = ['name' => $name, 'month' => $month];
        $cachekey = serialize($keydata);
        $cache = \cache::make('tool_excimer', self::CACHE_NAME);
        $record = $cache->get($cachekey);

        // The cached record exists, return it.
        if ($record !== false) {
            return new page_group(0, $record);
        }

        // Check if a matching DB record exists. If not, create it.
        $pagegroup = self::get_record($keydata);
        if ($pagegroup === false) {
            // No need to set the cache as it is guaranteed to be set later. So return early.
            return new page_group(0, (object) $keydata);
        }

        // The DB record should be cached for future calls.
        $cache->set($cachekey, $pagegroup->to_record());
        return $pagegroup;
    }

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
     * Called after the page group is created in the DB.
     */
    public function after_create() {
        $this->update_cache();
    }

    /**
     * Called after the DB is updated.
     *
     * @param bool $result Whether or not the update was successful.
     */
    public function after_update($result) {
        if ($result) {
            $this->update_cache();
        }
    }

    /**
     * Updates the cache with the current record.
     */
    protected function update_cache() {
        $keydata = [
            'name' => $this->get('name'),
            'month' => $this->get('month'),
        ];
        $cachekey = serialize($keydata);
        $cache = \cache::make('tool_excimer', self::CACHE_NAME);
        $cache->set($cachekey, $this->to_record());
    }

    /**
     * Records fuzzy count metadata about a page group.
     *
     * @param profile $profile The profile to pull the information from.
     * @param int|null $month The month to record the profile under, or null to use the current month.
     * @param bool $retry Whether to allow one retry for unique key errors.
     */
    public static function record_fuzzy_counts(profile $profile, ?int $month = null, $retry = true) {
        // Do this only if both auto profiling and fuzzy counting is set.
        if (!get_config('tool_excimer', 'enable_auto') ||
            !get_config('tool_excimer', 'enable_fuzzy_count')) {
            return;
        }

        // Get the profile group record, creating a new one if one does not yet exist.
        $month = $month ?? self::get_current_month();
        $pagegroup = self::get_page_group($profile->get('scriptgroup'), $month);

        $existing = $pagegroup->to_record();

        // Check if the page group existed before this call, can be determined by fuzzydurationcounts.
        $fuzzydurationcounts = $pagegroup->get('fuzzydurationcounts');
        $pagegroupexisted = !empty($fuzzydurationcounts);

        // Fuzzy increment the count.
        $fuzzycount = $pagegroupexisted ? manager::approximate_increment($pagegroup->get('fuzzycount')) : 0;
        $pagegroup->set('fuzzycount', $fuzzycount);

        // Fuzzy increment count for the duration slice.
        $duration = $profile->get('duration');
        $fuzzydurationcounts = $pagegroup->get('fuzzydurationcounts');
        $exp = ceil(log($duration, 2));
        if ($exp < 0) {
            $exp = 0;
        }
        $fuzzydurationexists = $pagegroupexisted && isset($fuzzydurationcounts[$exp]);
        $fuzzydurationcounts[$exp] = $fuzzydurationexists ? manager::approximate_increment($fuzzydurationcounts[$exp]) : 0;
        $pagegroup->set('fuzzydurationcounts', $fuzzydurationcounts);

        // Add the duration to the fuzzy sum, treating each second as an event for counting.
        $fuzzydurationsum = $pagegroup->get('fuzzydurationsum');
        $duration = (int) round($duration);
        $fuzzydurationsum = manager::approximate_increment($fuzzydurationsum, $duration);
        $pagegroup->set('fuzzydurationsum', $fuzzydurationsum);

        if ($existing != $pagegroup->to_record()) {
            try {
                $pagegroup->save();
            } catch (\dml_exception $e) {
                // We have a minor loss in data with concurrent updates that can cause duplicate rows.
                // When creating new page groups we can catch unique key errors and then retry an update.
                // Updates are harder to detect, but will only occur when fuzzycount is low, so can ignore.
                if (!$pagegroupexisted && $retry) {
                    // One retry should be enough to resolve unique key errors.
                    self::record_fuzzy_counts($profile, $month, false);
                }
            }
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
