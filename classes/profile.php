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

    const DENYLIST = [
        'sesskey',
        'FLAMEME'
    ];

    static function stripparameters(array $parameters): array {
        return array_filter(
            $parameters,
            function($i) { return !in_array($i, profile::DENYLIST); },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Gets a single profile, including data.
     * @param $id
     * @return object
     */
    public static function getprofile($id): object {
        global $DB;
        return $DB->get_record('tool_excimer_profiles', ['id' => $id],'*', MUST_EXIST);
    }

    /**
     * (stub) Gets a URL link for the profile.
     *
     * @param $id
     * @return string
     */
    static function getaslink($id): string { return ''; }

    /**
     * (stub) Gets a cURL command for the profile.
     *
     * @param $id
     * @return string
     */
    static function getascurl($id): string { return ''; }

    /**
     * (stub) Gets HAR data for the profile.
     *
     * @param $id
     * @return string
     */
    static function getashar($id): string { return ''; }

    /**
     * Gets the request type (web, cli, ...) and the parameters of the request.
     *
     * @return array A tuple [type, parameters].
     */
    static function gettypeandparams(): array {
        if (php_sapi_name() == 'cli') {
            // Our setup lacks $argv even though register_argc_argv is On; use
            // $_SERVER['argv'] instead.
            $type = 'cli';
            $parameters = array_slice($_SERVER['argv'], 1);
        } else {
            // Web request: split API calls later.
            $type = 'web';
            $parameters = [];
            parse_str($_SERVER['QUERY_STRING'], $parameters);
            $parameters = self::stripparameters($parameters);
        }

        return [$type, $parameters];
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
        list($type, $parameters) = self::gettypeandparams();

        return $DB->insert_record('tool_excimer_profiles', [
                'type' => $type,
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'created' => (int)$started,
                'duration' => $stopped - $started,
                'request' => $_SERVER['PHP_SELF'] ?? 'UNKNOWN',
                'parameters' => serialize($parameters),
                'responsecode' => http_response_code(),
                'referer' => $_SERVER['HTTP_REFERER'] ?? '',
                'explanation' => '', // TODO support this
                'flamedata' => $flamedata,
                'flamedatad3' => $flamedatad3
        ]);
    }
}
