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

use core_filetypes;

/**
 * Helpers for displaying stuff.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Maps HTTP status codes to css badges.
     */
    const STATUS_BADGES = [
        2 => 'badge-success',
        3 => 'badge-secondary',
        4 => 'badge-warning',
        5 => 'badge-danger',
    ];

    /**
     * Returns a printable string for a script type value.
     *
     * @param int $type
     * @return string
     * @throws \coding_exception
     */
    public static function script_type_display(int $type): string {
        switch ($type) {
            case profile::SCRIPTTYPE_WEB:
                return get_string('scripttype_web', 'tool_excimer');
            case profile::SCRIPTTYPE_CLI:
                return get_string('scripttype_cli', 'tool_excimer');
            case profile::SCRIPTTYPE_AJAX:
                return get_string('scripttype_ajax', 'tool_excimer');
            case profile::SCRIPTTYPE_WS:
                return get_string('scripttype_ws', 'tool_excimer');
            default:
                return (string) $type;
        }
    }

    /**
     * Returns a printable string for the profiling reasons.
     *
     * @param int $reason
     * @return string
     * @throws \coding_exception
     */
    public static function reason_display(int $reason): string {
        $reasonsmatched = [];
        if ($reason & manager::REASON_AUTO) {
            $reasonsmatched[] = get_string('reason_auto', 'tool_excimer');
        }
        if ($reason & manager::REASON_FLAMEALL) {
            $reasonsmatched[] = get_string('reason_flameall', 'tool_excimer');
        }
        if ($reason & manager::REASON_MANUAL) {
            $reasonsmatched[] = get_string('reason_manual', 'tool_excimer');
        }
        return implode(',', $reasonsmatched);
    }

    /**
     * Returns a formatted time duration in m:s.ms format.
     * @param float $duration
     * @return string
     * @throws \Exception
     */
    public static function duration_display(float $duration): string {
        $ms = round($duration * 1000, 0) % 1000;
        $s = (int) $duration;
        $m = $s / 60;
        $s = $s % 60;
        return sprintf('%d:%02d<small>.%03d</small>', $m, $s, $ms);
    }

    /**
     * Returns CLI script return status as a badge.
     *
     * @param int $status
     * @return string
     */
    public static function cli_return_status_display(int $status): string {
        $spanclass = 'badge ' . ($status ? 'badge-danger' : 'badge-success');
        return \html_writer::tag('span', $status, ['class' => $spanclass]);
    }

    /**
     * Returns HTTP status as a badge.
     *
     * @param int $status
     * @return string
     */
    public static function http_status_display(int $status): string {
        $spanclass = 'badge ' . self::STATUS_BADGES[$status / 100];
        return \html_writer::tag('span', $status, ['class' => $spanclass]);
    }

    /**
     * Checks the current response headers and tries to resolve the content type
     * e.g. to store as part of the profile.
     *
     * @param      string $request the request of the profile to be stored.
     * @param      string $pathinfo the pathinfo of the profile to be stored.
     * @return     array containing [value, key, category]
     *                   Where:
     *                   - value is the raw content type detected,
     *                   - key is the resolved filetype key or if not found, the
     *                     detected extension.
     *                   - category the general group it should fall under.
     * @author     Kevin Pham <kevinpham@catalyst-au.net>
     * @copyright  Catalyst IT, 2021
     */
    public static function resolve_content_type(string $request, string $pathinfo) {
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
