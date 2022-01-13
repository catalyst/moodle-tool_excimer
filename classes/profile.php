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

defined('MOODLE_INTERNAL') || die();

/**
 * Profile saving/loading and manipulation.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>, Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile {
    const TABLE = 'tool_excimer_profiles';


    /** Reason - MANUAL - Profiles are manually stored for the request using FLAMEME as a page param. */
    const REASON_MANUAL   = 0b0001;

    /** Reason - SLOW - Set when conditions are met and these profiles are automatically stored. */
    const REASON_SLOW     = 0b0010;

    /** Reason - FLAMEALL - Toggles profiling for all subsequent pages, until FLAMEALLSTOP param is passed as a page param. */
    const REASON_FLAMEALL = 0b0100;

    /** Reason - NONE - Default fallback reason value, this will not be stored. */
    const REASON_NONE = 0b0000;

    /** Reasons for profiling (bitmask flags). NOTE: Excluding the NONE option intentionally. */
    const REASONS = [
        self::REASON_MANUAL,
        self::REASON_SLOW,
        self::REASON_FLAMEALL,
    ];

    const REASON_STR_MAP = [
        self::REASON_MANUAL => 'manual',
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
     * Loads a profile from disk.
     *
     * @param int $id The ID of the profile.
     * @return profile
     * @throws \dml_exception
     */
    public static function get_profile(int $id) {
        global $DB;
        $profile = new profile();
        $profile->record = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        return $profile;
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

    protected $record;

    public function __construct() {
        $this->record = new \stdClass();
    }

    /**
     * Gets the raw data.
     * TODO: There should probably be a better way to get a data dump.
     *
     * @return object
     */
    public function as_object(): object {
        return $this->record;
    }

    /**
     * Custom setter to set the flame data.
     *
     * @param flamed3_node $node
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
     */
    public function get_flamedatad3json(): string {
        return gzuncompress($this->raw_get('flamedatad3'));
    }

    public function add_env(): void {
        global $USER, $CFG;

        $this->set('sessionid', substr(session_id(), 0, 10));
        $this->set('scripttype', context::get_script_type());
        $this->set('userid', $USER ? $USER->id : 0);
        $this->set('pid', getmypid());
        $this->set('hostname', gethostname());
        $this->set('versionhash', $CFG->allversionshash);

        $this->set('method', $_SERVER['REQUEST_METHOD'] ?? '');
        $this->set('pathinfo', $_SERVER['PATH_INFO'] ?? '');
        $this->set('useragent', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $this->set('referer', $_SERVER['HTTP_REFERER'] ?? '');
        $this->set('cookies', !defined('NO_MOODLE_COOKIES') || !NO_MOODLE_COOKIES);
        $this->set('buffering', !defined('NO_OUTPUT_BUFFERING') || !NO_OUTPUT_BUFFERING);
        $this->set('parameters', context::get_parameters($this->get('scripttype')));

        list($contenttypevalue, $contenttypekey, $contenttypecategory) = context::resolve_content_type($this);
        $this->set('contenttypevalue', $contenttypevalue);
        $this->set('contenttypekey', $contenttypekey);
        $this->set('contenttypecategory', $contenttypecategory);
    }

    /**
     * Saves the record to the database. Any transaction is bypassed.
     * Additional information is obtained and inserted into the profile before recording.
     *
     * @return int The database ID of the record.
     * @throws \dml_exception
     */
    public function save_record(): int {
        global $DB, $USER, $CFG;

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

        if (!isset($this->record->id)) {
            $this->add_env();
            $this->record->id = $db2->insert_record(self::TABLE, $this->record);
        } else {
            $db2->update_record(self::TABLE, $this->record);
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
            manager::get_min_duration_for_request_and_reason($this->get('request'), self::REASON_SLOW, false);
            manager::get_min_duration_for_reason(self::REASON_SLOW, false);
        }

        return $this->raw_get('id');
    }

    /**
     * Mimics persitent::set()
     *
     * @param string $property
     * @param $value
     * @return $this
     */
    final public function set(string $property, $value): profile {
        $methodname = 'set_' . $property;
        if (method_exists($this, $methodname)) {
            $this->$methodname($value);
            return $this;
        } else {
            $this->record->$property = $value;
        }
        return $this;
    }

    /**
     * Mimics persistent::get()
     *
     * @param string $property
     * @return mixed
     */
    final public function get(string $property) {
        $methodname = 'get_' . $property;
        if (method_exists($this, $methodname)) {
            return $this->$methodname();
        }
        return $this->record->$property;
    }

    /**
     * Mimics persistent::raw_set()
     *
     * @param string $property
     * @param $value
     * @return $this
     */
    public function raw_set(string $property, $value): profile {
        $this->record->$property = $value;
        return $this;
    }

    /**
     * Mimics persistent::raw_get()
     *
     * @param string $property
     * @return mixed
     */
    public function raw_get(string $property) {
        return $this->record->$property;
    }

    /**
     * Delete profiles created earlier than a given time.
     *
     * @param int $cutoff Epoch seconds
     * @return void
     */
    public static function purge_profiles_before_epoch_time(int $cutoff): void {
        global $DB;

        // Fetch unique requets and reasons that will be purged by the cutoff
        // datetime, so that we can selectively clear the cache.
        $requests = $DB->get_fieldset_sql(
            "SELECT DISTINCT request
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

        // Clears the request_metadata cache for the specific request and
        // affected reasons.
        if (!empty($requests)) {
            $cache = \cache::make('tool_excimer', 'request_metadata');
            $cache->delete_many($requests);
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
    public static function purge_fastest_by_page(int $numtokeep): void {
        global $DB;

        $purgablereasons = $DB->sql_bitand('reason', self::REASON_SLOW);
        $records = $DB->get_records_sql(
            "SELECT id, request, reason
               FROM {tool_excimer_profiles}
              WHERE $purgablereasons != ?
           ORDER BY duration ASC
               ", [self::REASON_NONE, $numtokeep]
        );

        // Group profiles by request / page.
        $groupedprofiles = array_reduce($records, function ($acc, $record) {
            $acc[$record->request] = $acc[$record->request] ?? [
                'count' => 0,
                'profiles' => [],
            ];
            $acc[$record->request]['count']++;
            $acc[$record->request]['profiles'][] = $record;
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
}
