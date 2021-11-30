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
        'FLAMEME'
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
     * Gets the script type of th request.
     *
     * @return int
     */
    private static function getscripttype(): int {
        if (defined(CLI_SCRIPT)) {
            return self::SCRIPTTYPE_CLI;
        } else if (defined(AJAX_SCRIPT)) {
            return self::SCRIPTTYPE_AJAX;
        } else if (defined(WS_SERVER)) {
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
    private static function getparameters(int $type): string {
        if ($type == self::SCRIPTTYPE_CLI) {
            return implode(' ', array_slice($_SERVER['argv'], 1));
        } else {
            $parameters = [];
            parse_str($_SERVER['QUERY_STRING'], $parameters);
            return http_build_query(self::stripparameters($parameters), '', '&');
        }
    }

    /**
     * Saves a snaphot of the logs into the database.
     *
     * @param \ExcimerLog $log
     * @param float $started
     * @return int The database ID of the inserted profile.
     */
    public static function save(\ExcimerLog $log, float $started): int {
        global $DB;
        $stopped  = microtime(true);
        $flamedata = trim(str_replace("\n;", "\n", $log->formatCollapsed()));
        $flamedatad3 = json_encode(converter::process($flamedata));
        $type = self::getscripttype();
        $parameters = self::getparameters($type);

        return $DB->insert_record('tool_excimer_profiles', [
            'sessionid' => session_id(),
            'type' => $type,
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'created' => (int)$started,
            'duration' => $stopped - $started,
            'request' => $_SERVER['PHP_SELF'] ?? 'UNKNOWN',
            'parameters' => $parameters,
            'cookies' => !defined(NO_MOODLE_COOKIES),
            'buffering' => !defined(NO_OUTPUT_BUFFERING),
            'responsecode' => http_response_code(),
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'explanation' => '', // TODO support this.
            'flamedata' => $flamedata,
            'flamedatad3' => $flamedatad3
        ]);
    }
}
