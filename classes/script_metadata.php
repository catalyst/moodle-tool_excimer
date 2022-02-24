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

use core_filetypes;

/**
 * Functions that extract information from the execution environment.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class script_metadata {

    /** Request's fallback value for when the $SCRIPT is null */
    const REQUEST_UNKNOWN = 'UNKNOWN';


    /** List of parameters that are not to be recorded at all. */
    const DENYLIST = [
        manager::FLAME_ME_PARAM_NAME,
        manager::FLAME_ON_PARAM_NAME,
        manager::FLAME_OFF_PARAM_NAME,
    ];

    /** List of paramteres that are to be recorded in redacted form. */
    const REDACTLIST = [
        'sesskey',
    ];

    /**
     * List of script names that requires more infor for grouping.
     * TODO: This list is incomplete.
     */
    const SCRIPT_NAMES_FOR_GROUP_REFINING = [
        'admin/index.php',
        'admin/settings.php',
        'admin/search.php',
        'admin/category.php',
        'lib/javascript.php',
        'lib/ajax/service.php',
        'pluginfile.php',
        'webservice/pluginfile.php',
        'tokenpluginfile.php',
    ];

    /**
     * Gets the script type of the request.
     *
     * @return int
     */
    public static function get_script_type(): int {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return profile::SCRIPTTYPE_CLI;
        } else if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            return profile::SCRIPTTYPE_AJAX;
        } else if (defined('WS_SERVER') && WS_SERVER) {
            return profile::SCRIPTTYPE_WS;
        }
        return profile::SCRIPTTYPE_WEB;
    }

    /**
     * Gets the parameters given to the request.
     *
     * @param int $type - The type of call (cli, web, etc)
     * @return string For non-cli requests, the parameters are returned in a url query string.
     *               For cli requests, the arguments are returned in a space sseparated list.
     */
    public static function get_parameters(int $type): string {
        if ($type == profile::SCRIPTTYPE_CLI) {
            return implode(' ', array_slice($_SERVER['argv'], 1));
        } else {
            $parameters = [];
            parse_str($_SERVER['QUERY_STRING'], $parameters);
            return http_build_query(self::stripparameters($parameters), '', '&');
        }
    }

    /**
     * Removes any parameter on DENYLIST.
     * Redacts any parameters on REDACTLIST.
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
     * Gets the name of the script.
     *
     * @return string the request path for this profile.
     */
    public static function get_request(): string {
        global $SCRIPT;
        // If set, it will trim off the leading '/' to normalise web & cli requests.
        $request = isset($SCRIPT) ? ltrim($SCRIPT, '/') : self::REQUEST_UNKNOWN;
        return $request;
    }

    /**
     * Checks the current response headers and tries to resolve the content type
     * e.g. to store as part of the profile.
     *
     * @param      profile $profile The profile being stored.
     * @return     array containing [value, key, category]
     *                   Where:
     *                   - value is the raw content type detected,
     *                   - key is the resolved filetype key or if not found, the
     *                     detected extension.
     *                   - category the general group it should fall under.
     * @author     Kevin Pham <kevinpham@catalyst-au.net>
     * @copyright  Catalyst IT, 2021
     */
    public static function resolve_content_type(profile $profile) {
        $request = $profile->get('request');
        $pathinfo = $profile->get('pathinfo');
        $contenttypevalue = null;
        $contenttypekey = null;
        $contenttypecategory = null;

        // Get 'Content-Type' header to perform further checks.
        $headers = headers_list();
        if (!empty($headers)) {
            foreach ($headers as $header) {
                $index = strpos(strtolower($header), 'content-type');
                if ($index === 0) {
                    list($contenttypewhole) = explode(';', $header, 2);
                    list(, $contenttypevalue) = explode(': ', $contenttypewhole, 2);
                    break;

                }
            }
        }

        // If there is no Content-Type header detected, then bail since we
        // aren't sure - it could be coming from CLI and thus needs no response
        // headers, but the content type could vary (text/image) and there is no
        // other checks for it at the moment.
        // TODO: Should this check and prefill CLI based requests?

        // Compare the value of 'Content-Type' to known values, to determine the content type fields.
        $allfiletypes = core_filetypes::get_types();
        // NOTE: This will stop on the FIRST match based on this list. It
        // will also use the 'key' if the 'string' is not available.
        foreach ($allfiletypes as $key => $filetype) {
            if ($filetype['type'] === $contenttypevalue) {
                $contenttypekey = $key;
                $contenttypecategory = $filetype['string'] ?? $key;
                break;
            }
        }

        // If it cannot be determined via the core_filetypes, determine it based
        // on known groups e.g. font.php, javascript.php, handlers, etc.
        if (empty($contenttypekey)) {
            $requestbasename = basename($request);
            if (
                $contenttypevalue === 'application/javascript' // Common, but not in filetypes as this exactly.
                || $requestbasename === 'javascript.php'
            ) {
                $contenttypekey = 'js';
                $contenttypecategory = 'js';
            } else if ($requestbasename === 'font.php') {
                list(, $trailingpathinfo) = explode('.', $pathinfo, 2);
                list($extension) = explode('?', $trailingpathinfo, 2);

                // Use the extension of the request to determine the 'key' (more
                // or less analogous to the expected file extension anyways).
                $contenttypekey = $extension;
                $contenttypecategory = 'font';
            }
        }

        return [$contenttypevalue, $contenttypekey, $contenttypecategory];
    }

    const PLUGINFILE_SCRIPTS = [
        'pluginfile.php',
        'webservice/pluginfile.php',
        'tokenpluginfile.php',
    ];

    /**
     * @param profile $profile
     * @return string
     * @throws \coding_exception
     */
    public static function get_groupby_value(profile $profile): string {
        $request = $profile->get('request');
        if (in_array($request, self::SCRIPT_NAMES_FOR_GROUP_REFINING)) {
            $pathinfo = $profile->get('pathinfo');
            $val = $request;
            if ($pathinfo) {
                if (in_array($request, self::PLUGINFILE_SCRIPTS)) {
                    $val .= self::redact_pluginfile_pathinfo($pathinfo);
                } else {
                    $val .= self::redact_pathinfo($pathinfo);
                }
            }
            $params = $profile->get('parameters');
            if ($params != '') {
                $val .= '?' . self::redact_parameters($params);
            }
            return $val;
        } else {
            return $request;
        }
    }

    /**
     * Redacts values from a query string.
     *
     * @param string $parameters
     * @return string
     */
    public static function redact_parameters(string $parameters): string {
        $parms = explode('&', $parameters);
        foreach ($parms as &$v) {
            $v = preg_replace('/=.*$/', '=', $v);
        }
        return implode('&', $parms);
    }

    /**
     * Redacts values from a pathinfo.
     *
     * @param string $pathinfo
     * @return string
     */
    public static function redact_pathinfo(string $pathinfo): string {
        $segments = explode('/', ltrim($pathinfo, '/'));
        foreach ($segments as &$v) {
            if (ctype_digit($v)) {
                $v = 'x';
            }
        }
        return '/' . implode('/', $segments);
    }

    /**
     * Redacts values for a pathinfo. Specificly for pluginfile like scripts.
     *
     * @param string $pathinfo
     * @return string
     */
    public static function redact_pluginfile_pathinfo(string $pathinfo): string {
        $segments = explode('/', ltrim($pathinfo, '/'), 4);
        $segments[0] = 'x';
        $segments[3] = 'xxx';
        return '/' . implode('/', $segments);
    }

    const TIMER_INTERVAL_MIN = 1;
    const TIMER_INTERVAL_DEFAULT = 10;

    /**
     * Get the timer interval from config, and return it as seconds.
     *
     * @return float
     * @throws \dml_exception
     */
    public static function get_timer_interval(): float {
        $interval = (float) get_config('tool_excimer', 'long_interval_s');
        if ($interval < self::TIMER_INTERVAL_MIN) {
            return self::TIMER_INTERVAL_DEFAULT;
        }
        return $interval;
    }

    const SAMPLING_PERIOD_MIN = 0.01;
    const SAMPLING_PERIOD_MAX = 1.0;
    const SAMPLING_PERIOD_DEFAUILT = 0.1;
    /**
     * Get the sampling period, and return it as seconds.
     *
     * @return float
     * @throws \dml_exception
     */
    public static function get_sampling_period(): float {
        $period = get_config('tool_excimer', 'sample_ms') / 1000;
        $insensiblerange = $period >= self::SAMPLING_PERIOD_MIN && $period <= self::SAMPLING_PERIOD_MAX;
        return round($insensiblerange ? $period : self::SAMPLING_PERIOD_DEFAUILT, 3);
    }

    /**
     * Returns the sampling double rate.
     *
     * @return int
     * @throws \dml_exception
     */
    public static function get_sampling_doublerate(): int {
        $rate = (int)get_config('tool_excimer', 'doublerate');
        $insensiblerange = $rate >= 0;
        return $insensiblerange ? $rate : 1024;
    }
}
