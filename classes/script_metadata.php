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
    const DENY_LIST = [
        manager::FLAME_ME_PARAM_NAME,
        manager::FLAME_OFF_PARAM_NAME,
        manager::FLAME_ON_PARAM_NAME,
    ];

    /** List of parameters that are to be recorded in redacted form. */
    const REDACT_LIST = [
        'authtoken',
        'key',
        'nonce',
        'sesskey',
        'wstoken',
    ];

    /**
     * List of script names that requires more info for grouping.
     * TODO: This list is incomplete.
     */
    const SCRIPT_NAMES_FOR_GROUP_REFINING = [
        'admin/category.php',
        'admin/index.php',
        'admin/settings.php',
        'admin/search.php',
        'lib/ajax/service.php',
        'lib/javascript.php',
        'pluginfile.php',
        'tokenpluginfile.php',
        'webservice/pluginfile.php',
    ];

    /** Minimum sampling period. */
    const SAMPLING_PERIOD_MIN = 0.01;
    /** Maximum sampling period. */
    const SAMPLING_PERIOD_MAX = 100.0;
    /** Default sampling period. */
    const SAMPLING_PERIOD_DEFAULT = 0.1;

    /** Minimum timer interval */
    const TIMER_INTERVAL_MIN = 1;
    /** Default timer interval */
    const TIMER_INTERVAL_DEFAULT = 10;

    /** Maximium stack depth. */
    const STACK_DEPTH_LIMIT = 1000;

    /** Default sample limit. */
    const SAMPLE_LIMIT_DEFAULT = 1024;

    /** @var int Stack limit config. */
    protected static $stacklimit;
    /** @var int Sample limit config. */
    protected static $samplelimit;
    /** @var int Sampling period */
    public static $samplems;
    /** @var string */
    protected static $redactparams;

    /**
     * Preload config values to avoid DB access during processing. See manager::get_altconnection() for more information.
     */
    public static function init() {
        self::$stacklimit = (int) get_config('tool_excimer', 'stacklimit');
        if (self::$stacklimit <= 0) {
            self::$stacklimit = self::STACK_DEPTH_LIMIT;
        }

        self::$samplelimit = (int) get_config('tool_excimer', 'samplelimit');
        if (self::$samplelimit <= 0) {
            self::$samplelimit = self::SAMPLE_LIMIT_DEFAULT;
        }
        self::$samplems = get_config('tool_excimer', 'sample_ms');
        self::$redactparams = get_config('tool_excimer', 'redact_params');
    }

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
        global $ME;

        if ($type == profile::SCRIPTTYPE_CLI) {
            return implode(' ', array_slice($_SERVER['argv'], 1));
        }

        if (isset($ME)) {
            $querystring = (new \moodle_url($ME))->get_query_string(false);
        } else if (isset($_SERVER['QUERY_STRING'])) {
            $querystring = $_SERVER['QUERY_STRING'];
        } else {
            return '';
        }
        $parameters = [];
        parse_str($querystring, $parameters);
        return http_build_query(self::strip_parameters($parameters), '', '&');
    }

    /**
     * Removes any parameter on DENY_LIST.
     * Redacts any parameters on REDACT_LIST.
     *
     * @param array $parameters
     * @return array
     */
    public static function strip_parameters(array $parameters): array {
        $parameters = array_filter(
            $parameters,
            function ($i) {
                return !in_array($i, self::DENY_LIST);
            },
            ARRAY_FILTER_USE_KEY
        );

        foreach ($parameters as $i => &$v) {
            if (in_array($i, self::get_redactable_param_names())) {
                $v = '';
            }
        }

        return $parameters;
    }

    /**
     * Gets a list of parameter names to be redacted, combining those from settings with
     * those builtin.
     *
     * @return string[]
     */
    public static function get_redactable_param_names(): array {
        // Strip C style comments.
        $setting = preg_replace('!/\*.*?\*/!s', '', self::$redactparams);

        $lines = explode(PHP_EOL, $setting);

        // Get the builtin list, and then add the setting list to it.
        $paramstoredact = self::REDACT_LIST;
        foreach ($lines as $line) {
            // Strip # comments, and trim.
            $line = trim(preg_replace('/#.*$/', '', $line));

            // Ignore empty lines.
            if ($line === '') {
                continue;
            }

            $paramstoredact[] = $line;
        }
        return $paramstoredact;
    }

    /**
     * Gets the name of the script.
     *
     * @return string the request path for this profile.
     */
    public static function get_request(): string {
        global $SCRIPT, $ME, $CFG;

        if (!isset($ME)) {
            // If set, it will trim off the leading '/' to normalise web & cli requests.
            $request = isset($SCRIPT) ? ltrim($SCRIPT, '/') : self::REQUEST_UNKNOWN;
            return $request;
        }

        $request = (new \moodle_url($ME))->out_omit_querystring();
        $request = str_replace($CFG->wwwroot, '', $request);
        $request = ltrim($request, '/');
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

    /** Script names for pluginfile. */
    const PLUGINFILE_SCRIPTS = [
        'pluginfile.php',
        'webservice/pluginfile.php',
        'tokenpluginfile.php',
    ];

    /**
     * Determine the group for the profile.
     *
     * @param profile $profile
     * @return string
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
            if (empty($val)) {
                // Must always have a groupby value.
                return '?';
            }
            return $val;
        }
        if (empty($request)) {
            // Must always have a groupby value.
            return 'index.php';
        }
        return $request;
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

    /**
     * Get the sampling period, and return it as seconds.
     *
     * @return float
     * @throws \dml_exception
     */
    public static function get_sampling_period(): float {
        $period = get_config('tool_excimer', 'sample_ms') / 1000;
        $insensiblerange = $period >= self::SAMPLING_PERIOD_MIN && $period <= self::SAMPLING_PERIOD_MAX;
        if (! $insensiblerange) {
            set_config('sample_ms', self::SAMPLING_PERIOD_DEFAULT * 1000, 'tool_excimer');
            $period = self::SAMPLING_PERIOD_DEFAULT;
        }
        return round($period, 3);
    }

    /**
     * Returns the sample limit. The maximum number of samples stored.
     *
     * This works by filtering the recording of samples. Each time the limit is reached, the samples that have
     * been recorded so far are stripped of every second sample. Also, the filter rate doubles, so that only
     * every Nth sample is recorded at filter rate N.
     *
     * This has the same effect as adjusting the sampling period so that the total number of samples never exceeds
     * the limit.
     *
     * See also sample_set class
     *
     * @return int
     * @throws \dml_exception
     */
    public static function get_sample_limit(): int {
        return self::$samplelimit;
    }

    /**
     * Returns the pre-configured stack (recursion) limit.
     *
     * @return integer
     */
    public static function get_stack_limit(): int {
        return self::$stacklimit;
    }
}
