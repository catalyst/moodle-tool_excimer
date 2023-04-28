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
 * Profile aggregate functions.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_helper {

    /** @var string Name used for group of all */
    public const ALL_GROUP_CACHE_KEY = '__all__';

    /** @var array Limits for storing profiles of a group. */
    protected static $groupquotas;
    /** @var array Limits for storing profiles. */
    protected static $quotas;

    /**
     * Preload config values to avoid DB access during processing. See manager::get_altconnection() for more information.
     */
    public static function init() {
        self::$groupquotas = [];
        self::$quotas = [];
        foreach (profile::REASON_STR_MAP as $reason => $reasonstr) {
            self::$groupquotas[$reason] = (int) get_config('tool_excimer', 'num_' . $reasonstr . '_by_page');
            self::$quotas[$reason] = (int) get_config('tool_excimer', "num_$reasonstr");
        }
    }

    /**
     * The number of profiles stored on disk.
     *
     * @return int
     * @throws \dml_exception
     */
    public static function get_num_profiles(): int {
        global $DB;
        return $DB->count_records(profile::TABLE, []);
    }

    /**
     * Returns the minimum duration for profiles matching this reason and page/request.
     *
     * Cost: 1 cache read (ideally)
     * Otherwise: 1 cache read, 1 DB read and 1 cache write.
     *
     * @author Kevin Pham <kevinpham@catalyst-au.net>
     *
     * @param string $group
     * @param int $reason
     * @param bool $usecache
     * @return float
     */
    public static function get_min_duration_for_group_and_reason(string $group, int $reason, bool $usecache = true): float {
        $pagequota = self::$groupquotas[$reason];

        // Grab the fastest profile for this page/request, and use that as
        // the lower boundary for any new profiles of this page/request.
        $cachekey = $group;
        $cachefield = 'min_duration_for_reason_' . $reason . '_s';
        $cache = \cache::make('tool_excimer', 'request_metadata');
        $result = $cache->get($cachekey) ?: array();

        if (!$usecache || $result === false || !isset($result[$cachefield])) {
            // NOTE: Opting to query this way instead of using MIN due to
            // the fact valid profiles will be added and the limits will be
            // breached for 'some time'. This will keep the constraints as
            // correct as possible.
            $db = manager::get_altconnection();
            if ($db === false) {
                debugging('tool_excimer: Alt DB connection failed.');
                return 0;
            }
            $reasons = $db->sql_bitand('reason', $reason);
            $sql = "SELECT duration as min_duration
                      FROM {tool_excimer_profiles}
                     WHERE $reasons != ?
                           AND groupby = ?
                  ORDER BY duration DESC
                     ";
            $resultset = $db->get_records_sql($sql, [
                profile::REASON_NONE,
                $group,
            ], $pagequota - 1, 1); // Will fetch the Nth item based on the quota.
            // Cache the results in milliseconds (avoids recalculation later).
            $minduration = (end($resultset)->min_duration ?? 0.0);
            // Updates the cache value if the calculated value is different.
            if (!isset($result[$cachefield]) || $result[$cachefield] !== $minduration) {
                $result[$cachefield] = $minduration;
                $cache->set($cachekey, $result);
            }
        }
        return (float) $result[$cachefield];
    }

    /**
     * Returns the minimum duration for profiles matching this reason.
     *
     * Cost: Should be free as long as the cache exists in the config.
     * Otherwise: 1 DB read, 1 cache write
     *
     * @author Kevin Pham <kevinpham@catalyst-au.net>
     *
     * @param  int $reason the profile type or REASON_*
     * @param  bool $usecache whether or not to even bother with caching. This allows for a forceful cache update.
     * @return float duration (as seconds) of the fastest profile for a given reason.
     */
    public static function get_min_duration_for_reason(int $reason, bool $usecache = true): float {
        $quota = self::$quotas[$reason];

        $cachefield = 'profile_type_' . $reason . '_min_duration_s';
        $cache = \cache::make('tool_excimer', 'request_metadata');
        $result = $cache->get(self::ALL_GROUP_CACHE_KEY) ?: array();

        if (!$usecache || $result === false || !isset($result[$cachefield])) {
            // Get and set cache.
            $db = manager::get_altconnection();
            if ($db === false) {
                debugging('tool_excimer: Alt DB connection failed.');
                return 0;
            }
            $reasons = $db->sql_bitand('reason', $reason);
            $sql = "SELECT duration as min_duration
                      FROM {tool_excimer_profiles}
                     WHERE $reasons != ?
                  ORDER BY duration DESC
                     ";
            $resultset = $db->get_records_sql($sql, [
                profile::REASON_NONE,
            ], $quota - 1, 1); // Will fetch the Nth item based on the quota.
            // Cache the results in (avoids recalculation later).
            $newvalue = (end($resultset)->min_duration ?? 0.0);
            // Updates the cache value if the calculated value is different.
            if (!isset($result[$cachefield]) || $result[$cachefield] !== $newvalue) {
                $result[$cachefield] = $newvalue;
                $cache->set(self::ALL_GROUP_CACHE_KEY, $result);
            }
        }
        return (float) $result[$cachefield];
    }

    /**
     * Returns the slowest profile on record.
     *
     * @return false|mixed The slowest profile, or false if no profiles are stored.
     * @throws \dml_exception
     */
    public static function get_slowest_profile() {
        global $DB;
        return $DB->get_record_sql(
            "SELECT id, request, duration, pathinfo, parameters, scripttype
                FROM {tool_excimer_profiles}
            ORDER BY duration DESC
               LIMIT 1"
        );
    }

    /**
     * Delete profiles created earlier than a given time.
     *
     * @author Kevin Pham <kevinpham@catalyst-au.net>
     *
     * @param int $cutoff Epoch seconds
     * @return void
     */
    public static function purge_profiles_before_epoch_time(int $cutoff): void {
        global $DB;

        // Fetch unique groupby and reasons that will be purged by the cutoff
        // datetime, so that we can selectively clear the cache.
        $groups = $DB->get_fieldset_sql(
            "SELECT DISTINCT groupby
               FROM {tool_excimer_profiles}
              WHERE created < :cutoff",
            ['cutoff' => $cutoff]
        );
        $groups[] = self::ALL_GROUP_CACHE_KEY;

        // Clears the request_metadata cache for the specific groups and
        // affected reasons.
        $cache = \cache::make('tool_excimer', 'request_metadata');
        $cache->delete_many($groups);

        // Purge the profiles older than this time as they are no longer
        // relevant, but keep any locked profiles.
        $DB->delete_records_select(
            profile::TABLE,
            "created < :cutoff and lockreason = ''",
            ['cutoff' => $cutoff]
        );

    }

    /**
     * Remove the reason bitmask on profiles given a list of ids and a reason
     * that should be removed.
     *
     * @author Kevin Pham <kevinpham@catalyst-au.net>
     *
     * @param array  $profiles list of profiles to remove the reason for
     * @param int    $reason the reason ( profile::REASON_* )
     */
    public static function remove_reason(array $profiles, int $reason): void {
        global $DB;
        $idstodelete = [];
        $updateordelete = false;
        foreach ($profiles as $profile) {
            // Do not change any profile that has been locked.
            if ($profile->lockreason != '') {
                continue;
            }
            // Ensuring we only remove a reason that exists on the profile provided.
            if ($profile->reason & $reason) {
                $profile->reason ^= $reason; // Remove the reason.
                if ($profile->reason === profile::REASON_NONE) {
                    $idstodelete[] = $profile->id;
                    continue;
                }
                $DB->update_record(profile::TABLE, $profile, true);
                $updateordelete = true;
            }
        }

        // Remove profiles where the reason (after updating) would be
        // REASON_NONE, as they no longer have a reason to exist.
        if (!empty($idstodelete)) {
            list($insql, $inparams) = $DB->get_in_or_equal($idstodelete);
            $DB->delete_records_select(profile::TABLE, 'id ' . $insql, $inparams);
            $updateordelete = true;
        }

        if ($updateordelete) {
            // Clear the request_metadata cache on insert/updates for affected profile requests.
            $cache = \cache::make('tool_excimer', 'request_metadata');
            $requests = array_column($profiles, 'request');
            // Note: Slightly faster than array_unique since the values can be used as keys.
            $uniquerequests = array_flip(array_flip($requests));
            $uniquerequests[] = self::ALL_GROUP_CACHE_KEY;
            $cache->delete_many($uniquerequests);
        }
    }

    /**
     * Removes excess REASON_SLOW profiles keep only up to $numtokeep records
     * per page/request.
     *
     * @param int $numtokeep Number of profiles per request to keep.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function purge_fastest_by_group(int $numtokeep): void {
        global $DB;

        $purgablereasons = $DB->sql_bitand('reason', profile::REASON_SLOW);
        $records = $DB->get_records_sql(
            "SELECT id, groupby, reason, lockreason
               FROM {tool_excimer_profiles}
              WHERE $purgablereasons != ?
           ORDER BY duration ASC
               ", [profile::REASON_NONE, $numtokeep]
        );

        // Group profiles by request / page.
        $groupedprofiles = array_reduce($records, function ($acc, $record) {
            $acc[$record->groupby] = $acc[$record->groupby] ?? [
                'count' => 0,
                'profiles' => [],
            ];
            $acc[$record->groupby]['count']++;
            $acc[$record->groupby]['profiles'][] = $record;
            return $acc;
        }, []);

        // For the requests found, loop through the aggregated ids, and remove
        // the ones to keep from the final list, based on the provided
        // $numtokeep.
        $profilestoremovereason = [];
        foreach ($groupedprofiles as $groupedprofile) {
            if ($groupedprofile['count'] <= $numtokeep) {
                continue;
            }
            $profiles = $groupedprofile['profiles'];
            $remaining = array_splice($profiles, 0, -$numtokeep);
            array_push($profilestoremovereason, ...$remaining);
        }

        // This will remove the REASON_SLOW bitmask on the record, and if the
        // final record is REASON_NONE, it will do a final purge of all the
        // affected records.
        self::remove_reason($profilestoremovereason, profile::REASON_SLOW);
    }

    /**
     * Removes excess REASON_SLOW profiles to keep only up to $numtokeep
     * profiles with this reason.
     *
     * Typically runs after purging records by request/page grouping first.
     *
     * @param int $numtokeep Overall number of profiles to keep.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function purge_fastest(int $numtokeep): void {
        global $DB;
        // Fetch all profiles with the reason REASON_SLOW and keep the number
        // under $numtokeep by flipping the order, and making the offset start
        // from the records after $numtokeep.
        $purgablereasons = $DB->sql_bitand('reason', profile::REASON_SLOW);
        $records = $DB->get_records_sql(
            "SELECT id, reason, lockreason
               FROM {tool_excimer_profiles}
              WHERE $purgablereasons != ?
           ORDER BY duration DESC", [profile::REASON_NONE], $numtokeep);

        if (!empty($records)) {
            self::remove_reason($records, profile::REASON_SLOW);
        }
    }
}
