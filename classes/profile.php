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

    const SCRIPTTYPE_AJAX = 0;
    const SCRIPTTYPE_CLI = 1;
    const SCRIPTTYPE_WEB = 2;
    const SCRIPTTYPE_WS = 3;

    const DENYLIST = [
        'sesskey',
        manager::MANUAL_PARAM_NAME
    ];

    /**
     * Removes any parameter on profile::DENYLIST.
     *
     * @param array $parameters
     * @return array
     */
    public static function stripparameters(array $parameters): array {
        return array_filter(
            $parameters,
            function($i) {
                return !in_array($i, profile::DENYLIST);
            },
            ARRAY_FILTER_USE_KEY
        );
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

    /**
     * @return int
     * @throws \dml_exception
     */
    public static function get_num_auto_profiles(): int {
        global $DB;
        return $DB->get_field_sql(
            "SELECT count(*) FROM {tool_excimer_profiles} WHERE reason = ?",
            [ manager::REASON_AUTO ]
        );
    }

    /**
     * @return object
     * @throws \dml_exception
     */
    public static function get_fastest_auto_profile(): object {
        global $DB;
        $sql = "SELECT id, duration
                  FROM {tool_excimer_profiles}
                 WHERE reason = ?
              ORDER BY duration ASC limit 1";
        return $DB->get_record_sql($sql, [ manager::REASON_AUTO ]);
    }

    /**
     * @param int $numtopurge
     * @throws \dml_exception
     */
    public static function purge_fastest_auto_profiles(int $numtopurge): void {
        global $DB;

        $ids = array_keys($DB->get_records('tool_excimer_profiles',
                ['reason' => manager::REASON_AUTO ], 'duration ASC', 'id', 0, $numtopurge));
        $inclause = $DB->get_in_or_equal($ids);
        $DB->delete_records_select('tool_excimer_profiles', 'id ' . $inclause[0], $inclause[1]);
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
        global $DB;
        $flamedata = trim(str_replace("\n;", "\n", $log->formatCollapsed()));
        $flamedatad3 = json_encode(converter::process($flamedata));
        $type = self::get_script_type();
        $parameters = self::get_parameters($type);
        return $DB->insert_record('tool_excimer_profiles', [
            'sessionid' => session_id(),
            'reason' => $reason,
            'scripttype' => $type,
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'created' => $created,
            'duration' => $duration,
            'request' => $_SERVER['PHP_SELF'] ?? 'UNKNOWN',
            'parameters' => $parameters,
            'cookies' => !defined(NO_MOODLE_COOKIES),
            'buffering' => !defined(NO_OUTPUT_BUFFERING),
            'responsecode' => http_response_code(),
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'flamedata' => $flamedata,
            'flamedatad3' => $flamedatad3
        ]);
    }
}
