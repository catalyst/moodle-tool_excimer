<?php
// This file is part of Moodle - http://moodle.org/
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
 * Functions relevant to profiles.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile {

    /** Request's fallback value for when the $SCRIPT is null */
    const REQUEST_UNKNOWN = 'UNKNOWN';

    /** Report section - recent - lists the most recent profiles first */
    const REPORT_SECTION_RECENT = 'recent';

    /** Report section - slowest - lists the slowest profiles first */
    const REPORT_SECTION_SLOWEST = 'slowest';

    /** Report section - unfinished - lists profiles of scripts that did not finish */
    const REPORT_SECTION_UNFINISHED = 'unfinished';

    /** Report sections */
    const REPORT_SECTIONS = [
        self::REPORT_SECTION_RECENT,
        self::REPORT_SECTION_SLOWEST,
        self::REPORT_SECTION_UNFINISHED,
    ];

    const SCRIPTTYPE_AJAX = 0;
    const SCRIPTTYPE_CLI = 1;
    const SCRIPTTYPE_WEB = 2;
    const SCRIPTTYPE_WS = 3;
    const SCRIPTTYPE_TASK = 4;

    const DENYLIST = [
        manager::MANUAL_PARAM_NAME,
        manager::FLAME_ON_PARAM_NAME,
        manager::FLAME_OFF_PARAM_NAME,
    ];

    const REDACTLIST = [
        'sesskey',
    ];

    /**
     * Stores the ID of a saved profile, to indicate that it should be overwritten.
     *
     * @var int
     */
    public static $partialsaveid = 0;

    /**
     * Removes any parameter on profile::DENYLIST.
     *
     * @param array $parameters
     * @return array
     */
    public static function stripparameters(array $parameters): array {
        $parameters = array_filter(
            $parameters,
            function($i) {
                return !in_array($i, self::DENYLIST);
            },
            ARRAY_FILTER_USE_KEY
        );

        foreach ($parameters as $i => &$v) {
            if (in_array($i, self::REDACTLIST)) {
                $v = '';
            }
        }

        return $parameters;
    }

    /**
     * Gets a single profile, including data.
     *
     * @param $id
     * @return object
     * @throws \dml_exception
     */
    public static function getprofile($id): object {
        global $DB;
        return $DB->get_record('tool_excimer_profiles', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * (stub) Gets a URL link for the profile.
     *
     * @param $id
     * @return string
     */
    public static function getaslink($id): string {
        return '';
    }

    /**
     * (stub) Gets a cURL command for the profile.
     *
     * @param $id
     * @return string
     */
    public static function getascurl($id): string {
        return '';
    }

    /**
     * (stub) Gets HAR data for the profile.
     *
     * @param $id
     * @return string
     */
    public static function getashar($id): string {
        return '';
    }

    public static function get_num_profiles(): int {
        global $DB;
        return $DB->count_records('tool_excimer_profiles', []);
    }

    /**
     * Gets the script type of the request.
     *
     * @return int
     */
    private static function get_script_type(): int {
        if (manager::is_cron()) {
            return self::SCRIPTTYPE_TASK;
        } else if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return self::SCRIPTTYPE_CLI;
        } else if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            return self::SCRIPTTYPE_AJAX;
        } else if (defined('WS_SERVER') && WS_SERVER) {
            return self::SCRIPTTYPE_WS;
        }
        return self::SCRIPTTYPE_WEB;
    }

    /**
     * Obtains the parameters given to the request.
     *
     * @param int $type - The type of call (cli, web, etc)
     * @return string For non-cli requests, the parameters are returned in a url query string.
     *               For cli requests, the arguments are returned in a space sseparated list.
     */
    private static function get_parameters(int $type): string {
        if ($type == self::SCRIPTTYPE_TASK) {
            return '';
        } else if ($type == self::SCRIPTTYPE_CLI) {
            return implode(' ', array_slice($_SERVER['argv'], 1));
        } else {
            $parameters = [];
            parse_str($_SERVER['QUERY_STRING'], $parameters);
            return http_build_query(self::stripparameters($parameters), '', '&');
        }
    }


    /**
     * Saves a snaphot of the profile into the database.
     *
     * @param flamed3_node $node The profile data.
     * @param int $reason Why the profile is being saved.
     * @param int $created Timestamp of when the profile was started.
     * @param float $duration The total time of the profiling, in seconds.
     * @param int $finished Timestamp of when the profile finished, or zero if only partial.
     * @return int The ID of the database entry.
     *
     * @throws \dml_exception
     */
    public static function save(string $request, flamed3_node $node, int $reason,
            int $created, float $duration, int $finished = 0): int {
        global $DB, $USER, $CFG;

        $numsamples = $node->value;
        $flamedatad3json = json_encode($node);
        $flamedatad3gzip = gzcompress($flamedatad3json);
        $datasize = strlen($flamedatad3gzip);

        // Get DB ops (reads/writes).
        $dbreads = $DB->perf_get_reads();
        $dbwrites = $DB->perf_get_writes();
        $dbreplicareads = 0;
        if ($DB->want_read_slave()) {
            $dbreplicareads = $DB->perf_get_reads_slave();
        }

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

        if (self::$partialsaveid === 0) {
            $type = self::get_script_type();
            $parameters = self::get_parameters($type);
            $method = $_SERVER['REQUEST_METHOD'] ?? '';

            // If set, it will trim off the leading '/' to normalise web & cli requests.
            $pathinfo = $_SERVER['PATH_INFO'] ?? '';

            list($contenttypevalue, $contenttypekey, $contenttypecategory) = helper::resolve_content_type($request, $pathinfo);

            $id = $db2->insert_record('tool_excimer_profiles', [
                'sessionid' => substr(session_id(), 0, 10),
                'reason' => $reason,
                'pathinfo' => $pathinfo,
                'scripttype' => $type,
                'userid' => $USER ? $USER->id : 0,
                'method' => $method,
                'created' => $created,
                'finished' => $finished,
                'duration' => $duration,
                'request' => $request,
                'parameters' => $parameters,
                'cookies' => !defined('NO_MOODLE_COOKIES') || !NO_MOODLE_COOKIES,
                'buffering' => !defined('NO_OUTPUT_BUFFERING') || !NO_OUTPUT_BUFFERING,
                'responsecode' => http_response_code(),
                'referer' => $_SERVER['HTTP_REFERER'] ?? '',
                'pid' => getmypid(),
                'hostname' => gethostname(),
                'useragent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'versionhash' => $CFG->allversionshash,
                'datasize' => $datasize,
                'numsamples' => $numsamples,
                'flamedatad3' => $flamedatad3gzip,
                'contenttypevalue' => $contenttypevalue,
                'contenttypekey' => $contenttypekey,
                'contenttypecategory' => $contenttypecategory,
                'dbreads' => $dbreads,
                'dbwrites' => $dbwrites,
                'dbreplicareads' => $dbreplicareads,
            ]);
        } else {
            $db2->update_record('tool_excimer_profiles', (object) [
                'id' => self::$partialsaveid,
                'reason' => $reason,
                'responsecode' => http_response_code(),
                'finished' => $finished,
                'duration' => $duration,
                'datasize' => $datasize,
                'numsamples' => $numsamples,
                'flamedatad3' => $flamedatad3gzip,
                'dbreads' => $dbreads,
                'dbwrites' => $dbwrites,
                'dbreplicareads' => $dbreplicareads,
            ]);
            $id = self::$partialsaveid;
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
        if ($reason & manager::REASON_SLOW) {
            manager::get_min_duration_for_request_and_reason($request, manager::REASON_SLOW, false);
            manager::get_min_duration_for_reason(manager::REASON_SLOW, false);
        }

        return $id;
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
            $combinedreasons = manager::REASON_NONE;
            foreach ($reasons as $reason) {
                $combinedreasons |= $reason;
            }
            manager::clear_min_duration_cache_for_reason($combinedreasons);
        }

        // Purge the profiles older than this time as they are no longer
        // relevant.
        $DB->delete_records_select(
            'tool_excimer_profiles',
            'created < :cutoff',
            ['cutoff' => $cutoff]
        );

    }

    /**
     * Remove the reason bitmask on profiles given a list of ids and a reason
     * that should be removed.
     *
     * @param array  $profiles list of profiles to remove the reason for
     * @param int    $reason the reason ( manager::REASON_* )
     */
    public static function remove_reason(array $profiles, int $reason): void {
        global $DB;
        $idstodelete = [];
        $updateordelete = false;
        foreach ($profiles as $profile) {
            // Ensuring we only remove a reason that exists on the profile provided.
            if ($profile->reason & $reason) {
                $profile->reason ^= $reason; // Remove the reason.
                if ($profile->reason === manager::REASON_NONE) {
                    $idstodelete[] = $profile->id;
                    continue;
                }
                $DB->update_record('tool_excimer_profiles', $profile, true);
                $updateordelete = true;
            }
        }

        // Remove profiles where the reason (after updating) would be
        // REASON_NONE, as they no longer have a reason to exist.
        if (!empty($idstodelete)) {
            list($insql, $inparams) = $DB->get_in_or_equal($idstodelete);
            $DB->delete_records_select('tool_excimer_profiles', 'id ' . $insql, $inparams);
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

        $purgablereasons = $DB->sql_bitand('reason', manager::REASON_SLOW);
        $records = $DB->get_records_sql(
            "SELECT id, request, reason
               FROM {tool_excimer_profiles}
              WHERE $purgablereasons != ?
           ORDER BY duration ASC
               ", [manager::REASON_NONE, $numtokeep]
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
        self::remove_reason($profilestoremovereason, manager::REASON_SLOW);
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
        $purgablereasons = $DB->sql_bitand('reason', manager::REASON_SLOW);
        $records = $DB->get_records_sql(
            "SELECT id, reason
               FROM {tool_excimer_profiles}
              WHERE $purgablereasons != ?
           ORDER BY duration DESC", [manager::REASON_NONE], $numtokeep);

        if (!empty($records)) {
            self::remove_reason($records, manager::REASON_SLOW);
        }
    }

}
