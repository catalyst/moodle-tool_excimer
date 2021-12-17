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

    /** Report sections */
    const REPORT_SECTIONS = [
        self::REPORT_SECTION_RECENT,
        self::REPORT_SECTION_SLOWEST,
    ];

    const SCRIPTTYPE_AJAX = 0;
    const SCRIPTTYPE_CLI = 1;
    const SCRIPTTYPE_WEB = 2;
    const SCRIPTTYPE_WS = 3;

    const DENYLIST = [
        manager::MANUAL_PARAM_NAME,
        manager::FLAME_ON_PARAM_NAME,
        manager::FLAME_OFF_PARAM_NAME,
    ];

    const REDACTLIST = [
        'sesskey',
    ];

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
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
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
        if ($type == self::SCRIPTTYPE_CLI) {
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
     * @param \ExcimerLog $log The profile data.
     * @param int $reason Why the profile is being saved.
     * @param int $created Timestamp of when the profile was started.
     * @param float $duration The total time of the profiling, in seconds.
     * @return int The ID of the database entry.
     *
     * @throws \dml_exception
     */
    public static function save(\ExcimerLog $log, int $reason, int $created, float $duration): int {
        global $DB, $USER, $CFG, $SCRIPT;

        // Some adjustments to work around a bug in Excimer. See https://phabricator.wikimedia.org/T296514.
        $flamedata = trim(str_replace("\n;", "\n", $log->formatCollapsed()));

        // Remove full pathing to dirroot and only keep pathing from site root (non-issue in most sane cases).
        $flamedata = str_replace($CFG->dirroot . DIRECTORY_SEPARATOR, '', $flamedata);

        $flamedatad3 = converter::process($flamedata);
        $numsamples = $flamedatad3['value'];
        $flamedatad3json = json_encode($flamedatad3);
        $datasize = strlen($flamedatad3json);
        $type = self::get_script_type();
        $parameters = self::get_parameters($type);

        // If set, it will trim off the leading '/' to normalise web & cli requests.
        $request = isset($SCRIPT) ? ltrim($SCRIPT, '/') : self::REQUEST_UNKNOWN;
        $pathinfo = $_SERVER['PATH_INFO'] ?? '';

        return $DB->insert_record('tool_excimer_profiles', [
            'sessionid' => substr(session_id(), 0, 10),
            'reason' => $reason,
            'pathinfo' => $pathinfo,
            'scripttype' => $type,
            'userid' => $USER ? $USER->id : 0,
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'created' => $created,
            'duration' => $duration,
            'request' => $request,
            'parameters' => $parameters,
            'cookies' => !defined('NO_MOODLE_COOKIES') || !NO_MOODLE_COOKIES,
            'buffering' => !defined('NO_OUTPUT_BUFFERING') || !NO_OUTPUT_BUFFERING,
            'responsecode' => http_response_code(),
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'datasize' => $datasize,
            'numsamples' => $numsamples,
            'flamedatad3' => $flamedatad3json,
        ]);
    }


    /**
     * Delete profiles created earlier than a given time.
     *
     * @param int $cutoff Epoch seconds
     * @return void
     */
    public static function purge_profiles_before_epoch_time(int $cutoff): void {
        global $DB;

        $DB->delete_records_select(
            'tool_excimer_profiles',
            'created < :cutoff',
            [ 'cutoff' => $cutoff ]
        );
    }

    /**
     * Removes auto profiles from the database so as to keep only the $numtokeep slowest for each script page.
     *
     * @param int $numtokeep Number of profiles per request to keep.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function purge_fastest_by_page(int $numtokeep): void {
        global $DB;

        // TODO optimisation suggestions welcome.
        $records = $DB->get_records_sql(
            "SELECT COUNT(*) AS num, request
               FROM {tool_excimer_profiles}
              WHERE reason = ?
           GROUP BY request
             HAVING COUNT(*) > ?",
            [manager::REASON_AUTO, $numtokeep]
        );
        foreach ($records as $record) {
            $ids = array_keys($DB->get_records('tool_excimer_profiles',
                    ['reason' => manager::REASON_AUTO, 'request' => $record->request ],
                    'duration ASC', 'id', 0, $record->num - $numtokeep));
            $inclause = $DB->get_in_or_equal($ids);
            $DB->delete_records_select('tool_excimer_profiles', 'id ' . $inclause[0], $inclause[1]);
        }
    }

    /**
     * Removes auto profiles from the database so as to keep only the $numtokeep slowest. Typically run after purge_fastest_by_page.
     *
     * @param int $numtokeep Overall number of profiles to keep.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function purge_fastest(int $numtokeep): void {
        global $DB;
        $numtopurge = $DB->count_records('tool_excimer_profiles', ['reason' => manager::REASON_AUTO ]) - $numtokeep;
        if ($numtopurge > 0) {
            $ids = array_keys($DB->get_records('tool_excimer_profiles',
                    ['reason' => manager::REASON_AUTO ], 'duration ASC', 'id', 0, $numtopurge));
            $inclause = $DB->get_in_or_equal($ids);
            $DB->delete_records_select('tool_excimer_profiles', 'id ' . $inclause[0], $inclause[1]);
        }
    }

}
