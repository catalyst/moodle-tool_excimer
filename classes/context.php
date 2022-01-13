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
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>, Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class context {

    /** Request's fallback value for when the $SCRIPT is null */
    const REQUEST_UNKNOWN = 'UNKNOWN';


    /** List of parameters that are not to be recorded at all. */
    const DENYLIST = [
        manager::MANUAL_PARAM_NAME,
        manager::FLAME_ON_PARAM_NAME,
        manager::FLAME_OFF_PARAM_NAME,
    ];

    /** List of paramteres that are to be recorded in redacted form. */
    const REDACTLIST = [
        'sesskey',
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
}
