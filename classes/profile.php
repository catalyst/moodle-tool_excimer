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

use core\persistent;

/**
 * Profile saving/loading and manipulation.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile extends persistent {
    const TABLE = 'tool_excimer_profiles';


    /** Reason - NONE - Default fallback reason value, this will not be stored. */
    const REASON_NONE = 0b0000;

    /** Reason - MANUAL - Profiles are manually stored for the request using FLAMEME as a page param. */
    const REASON_FLAMEME   = 0b0001;

    /** Reason - SLOW - Set when conditions are met and these profiles are automatically stored. */
    const REASON_SLOW     = 0b0010;

    /** Reason - FLAMEALL - Toggles profiling for all subsequent pages, until FLAMEALLSTOP param is passed as a page param. */
    const REASON_FLAMEALL = 0b0100;


    /** Reasons for profiling (bitmask flags). NOTE: Excluding the NONE option intentionally. */
    const REASONS = [
        self::REASON_FLAMEME,
        self::REASON_SLOW,
        self::REASON_FLAMEALL,
    ];

    const REASON_STR_MAP = [
        self::REASON_FLAMEME => 'manual',
        self::REASON_SLOW => 'slowest',
        self::REASON_FLAMEALL => 'flameall',
    ];

    const SCRIPTTYPE_AJAX = 0;
    const SCRIPTTYPE_CLI = 1;
    const SCRIPTTYPE_WEB = 2;
    const SCRIPTTYPE_WS = 3;
    const SCRIPTTYPE_TASK = 4;

    private static $runningprofile = null;

    // TODO: try to find a way to eliminate the need for this function.
    public static function get_num_profiles(): int {
        global $DB;
        return $DB->count_records(self::TABLE, []);
    }

    /**
     * Gets the profile that is being used to save the data as execution is running.
     * Creates a new one if it doesn't yet exist.
     *
     * @return profile
     */
    public static function get_running_profile(): profile {
        if (!isset(self::$runningprofile)) {
            self::$runningprofile = new profile();
        }
        return self::$runningprofile;
    }

    /**
     * Custom setter to set the flame data.
     *
     * @param flamed3_node $node
     * @throws \coding_exception
     */
    protected function set_flamedatad3(flamed3_node $node): void {
        $flamedata = gzcompress(json_encode($node));
        $this->raw_set('flamedatad3', $flamedata);
        $this->raw_set('numsamples',  $node->value);
        $this->raw_set('datasize', strlen($flamedata));
    }

    /**
     * Special getter to obtain the flame data JSON.
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_flamedatad3json(): string {
        return gzuncompress($this->raw_get('flamedatad3'));
    }

    public function add_env(string $request): void {
        global $USER, $CFG;

        $this->raw_set('request', $request);
        $this->raw_set('sessionid', substr(session_id(), 0, 10));
        $this->raw_set('scripttype', script_metadata::get_script_type());
        $this->raw_set('userid', $USER ? $USER->id : 0);
        $this->raw_set('pid', getmypid());
        $this->raw_set('hostname', gethostname());
        $this->raw_set('versionhash', $CFG->allversionshash);

        $this->raw_set('method', $_SERVER['REQUEST_METHOD'] ?? '');
        $this->raw_set('pathinfo', $_SERVER['PATH_INFO'] ?? '');
        $this->raw_set('useragent', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $this->raw_set('referer', $_SERVER['HTTP_REFERER'] ?? '');
        $this->raw_set('cookies', !defined('NO_MOODLE_COOKIES') || !NO_MOODLE_COOKIES);
        $this->raw_set('buffering', !defined('NO_OUTPUT_BUFFERING') || !NO_OUTPUT_BUFFERING);
        $this->raw_set('parameters', script_metadata::get_parameters($this->get('scripttype')));
        $this->raw_set('groupby', script_metadata::get_groupby_value($this));

        list($contenttypevalue, $contenttypekey, $contenttypecategory) = script_metadata::resolve_content_type($this);
        $this->raw_set('contenttypevalue', $contenttypevalue);
        $this->raw_set('contenttypekey', $contenttypekey);
        $this->raw_set('contenttypecategory', $contenttypecategory);
    }

    /**
     * Saves the record to the database. Any transaction is bypassed.
     * Additional information is obtained and inserted into the profile before recording.
     *
     * Note: save_record() should only get called in the process of making a profile. For other manipulation,
     * use save().
     *
     * @return int The database ID of the record.
     * @throws \dml_exception
     */
    public function save_record(): int {
        global $DB, $USER;

        // Get DB ops (reads/writes).
        $this->raw_set('dbreads', $DB->perf_get_reads());
        $this->raw_set('dbwrites', $DB->perf_get_writes());
        $this->raw_set('dbreplicareads', $DB->want_read_slave() ? $DB->perf_get_reads_slave() : 0);

        $this->raw_set('responsecode', http_response_code());

        $intrans = $DB->is_transaction_started();

        if ($intrans) {
            $cfg = $DB->export_dbconfig();
            $db2 = \moodle_database::get_driver_instance($cfg->dbtype, $cfg->dblibrary);
            try {
                $db2->connect($cfg->dbhost, $cfg->dbuser, $cfg->dbpass, $cfg->dbname, $cfg->prefix, $cfg->dboptions);
            } catch (\moodle_exception $e) {
                // Rather than engage with complex error handling, we choose to simply not record, and move on.
                debugging('tool_excimer: failed to open second db connection when saving profile: ' . $e->getMessage());
                return 0;
            }
        } else {
            $db2 = $DB;
        }

        $now = time();
        $this->raw_set('timemodified', $now);
        $this->raw_set('usermodified', $USER->id);

        if ($this->raw_get('id') <= 0) {
            $this->raw_set('timecreated', $now);
            $id = $db2->insert_record(self::TABLE, $this->to_record());
            $this->raw_set('id', $id);
        } else {
            $db2->update_record(self::TABLE, $this->to_record());
        }

        if ($intrans) {
            $db2->dispose();
        }

        // NOTE: Does clearing the cache on partial saves make sense? The cache
        // currently sets the min duration for how long a profile should go for
        // before it gets stored, for other reasons later on, it might be
        // pushing on additional constraints. In either case, clearing the cache
        // here assumes a few things: 1 - quota has been reached, 2 - minimum duration
        // will have changed, typically higher. 3 - every partial save will
        // cause some sort of reordering and potentially the cached items won't
        // hold correct values.

        // Updates the request_metadata and per reason cache with more recent values.
        if ($this->get('reason') & self::REASON_SLOW) {
            manager::get_min_duration_for_group_and_reason($this->get('groupby'), self::REASON_SLOW, false);
            manager::get_min_duration_for_reason(self::REASON_SLOW, false);
        }

        return (int) $this->raw_get('id');
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
        $reasons = $DB->get_fieldset_sql(
            "SELECT DISTINCT reason
               FROM {tool_excimer_profiles}
              WHERE created < :cutoff",
            ['cutoff' => $cutoff]
        );

        // Clears the request_metadata cache for the specific groups and
        // affected reasons.
        if (!empty($groups)) {
            $cache = \cache::make('tool_excimer', 'request_metadata');
            $cache->delete_many($groups);
        }
        if ($reasons) {
            $combinedreasons = self::REASON_NONE;
            foreach ($reasons as $reason) {
                $combinedreasons |= $reason;
            }
            manager::clear_min_duration_cache_for_reason($combinedreasons);
        }

        // Purge the profiles older than this time as they are no longer
        // relevant.
        $DB->delete_records_select(
            self::TABLE,
            'created < :cutoff',
            ['cutoff' => $cutoff]
        );

    }

    /**
     * Remove the reason bitmask on profiles given a list of ids and a reason
     * that should be removed.
     *
     * @param array  $profiles list of profiles to remove the reason for
     * @param int    $reason the reason ( self::REASON_* )
     */
    public static function remove_reason(array $profiles, int $reason): void {
        global $DB;
        $idstodelete = [];
        $updateordelete = false;
        foreach ($profiles as $profile) {
            // Ensuring we only remove a reason that exists on the profile provided.
            if ($profile->reason & $reason) {
                $profile->reason ^= $reason; // Remove the reason.
                if ($profile->reason === self::REASON_NONE) {
                    $idstodelete[] = $profile->id;
                    continue;
                }
                $DB->update_record(self::TABLE, $profile, true);
                $updateordelete = true;
            }
        }

        // Remove profiles where the reason (after updating) would be
        // REASON_NONE, as they no longer have a reason to exist.
        if (!empty($idstodelete)) {
            list($insql, $inparams) = $DB->get_in_or_equal($idstodelete);
            $DB->delete_records_select(self::TABLE, 'id ' . $insql, $inparams);
            $updateordelete = true;
        }

        if ($updateordelete) {
            // Clear the request_metadata cache on insert/updates for affected profile requests.
            $cache = \cache::make('tool_excimer', 'request_metadata');
            $requests = array_column($profiles, 'request');
            // Note: Slightly faster than array_unique since the values can be used as keys.
            $uniquerequests = array_flip(array_flip($requests));
            $cache->delete_many($uniquerequests);
            manager::clear_min_duration_cache_for_reason($reason);
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

        $purgablereasons = $DB->sql_bitand('reason', self::REASON_SLOW);
        $records = $DB->get_records_sql(
            "SELECT id, groupby, reason
               FROM {tool_excimer_profiles}
              WHERE $purgablereasons != ?
           ORDER BY duration ASC
               ", [self::REASON_NONE, $numtokeep]
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
        self::remove_reason($profilestoremovereason, self::REASON_SLOW);
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
        $purgablereasons = $DB->sql_bitand('reason', self::REASON_SLOW);
        $records = $DB->get_records_sql(
            "SELECT id, reason
               FROM {tool_excimer_profiles}
              WHERE $purgablereasons != ?
           ORDER BY duration DESC", [self::REASON_NONE], $numtokeep);

        if (!empty($records)) {
            self::remove_reason($records, self::REASON_SLOW);
        }
    }

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'reason' => ['type' => PARAM_INT],
            'scripttype' => ['type' => PARAM_INT],
            'method' => ['type' => PARAM_ALPHA, 'default' => ''],
            'created' => ['type' => PARAM_INT, 'default' => 0],
            'finished' => ['type' => PARAM_INT, 'default' => 0],
            'duration' => ['type' => PARAM_FLOAT, 'default' => 0],
            'request' => ['type' => PARAM_TEXT, 'default' => ''],
            'groupby' => ['type' => PARAM_TEXT, 'default' => ''],
            'pathinfo' => ['type' => PARAM_SAFEPATH, 'default' => ''],
            'parameters' => ['type' => PARAM_TEXT, 'default' => ''],
            'sessionid' => ['type' => PARAM_ALPHANUM, 'default' => ''],
            'userid' => ['type' => PARAM_INT, 'default' => 0],
            'cookies' => ['type' => PARAM_BOOL],
            'buffering' => ['type' => PARAM_BOOL],
            'responsecode' => ['type' => PARAM_INT, 'default' => 0],
            'referer' => ['type' => PARAM_URL, 'default' => ''],
            'pid' => ['type' => PARAM_INT, 'default' => 0],
            'hostname' => ['type' => PARAM_TEXT, 'default' => ''],
            'useragent' => ['type' => PARAM_TEXT, 'default' => ''],
            'versionhash' => ['type' => PARAM_TEXT, 'default' => ''],
            'datasize' => ['type' => PARAM_INT, 'default' => 0],
            'numsamples' => ['type' => PARAM_INT, 'default' => 0],
            'flamedatad3' => ['type' => PARAM_RAW],
            'contenttypecategory' => ['type' => PARAM_TEXT, 'default' => ''],
            'contenttypekey' => ['type' => PARAM_TEXT],
            'contenttypevalue' => ['type' => PARAM_TEXT],
            'dbreads' => ['type' => PARAM_INT, 'default' => 0],
            'dbwrites' => ['type' => PARAM_INT, 'default' => 0],
            'dbreplicareads' => ['type' => PARAM_INT, 'default' => 0],
        ];
    }
}
