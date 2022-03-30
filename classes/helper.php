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
 * Helpers for displaying stuff.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
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
            case profile::SCRIPTTYPE_TASK:
                return get_string('scripttype_task', 'tool_excimer');
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
        if ($reason & profile::REASON_SLOW) {
            $reasonsmatched[] = get_string('reason_slow', 'tool_excimer');
        }
        if ($reason & profile::REASON_FLAMEALL) {
            $reasonsmatched[] = get_string('reason_flameall', 'tool_excimer');
        }
        if ($reason & profile::REASON_FLAMEME) {
            $reasonsmatched[] = get_string('reason_flameme', 'tool_excimer');
        }
        return implode(',', $reasonsmatched);
    }

    /**
     * Returns a formatted time duration in m:s.ms format.
     * @param float $duration
     * @param bool $markup If true, then use markup on the result.
     * @return string
     * @throws \Exception
     */
    public static function duration_display(float $duration, bool $markup = true): string {
        $str = format_time(intval($duration));
        return ($str === get_string('now') ? '' : $str);
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
     * Returns status as a badge.
     *
     * @param int $status
     * @return string
     */
    public static function status_display(string $scripttype, int $responsecode): string {
        if ($scripttype == profile::SCRIPTTYPE_TASK) {
            // TODO: A better way needs to be found to determine which kind of response code is being returned.
            if ($responsecode < 100) {
                return self::cli_return_status_display($responsecode);
            } else {
                return self::http_status_display($responsecode);
            }
        } else if ($scripttype == profile::SCRIPTTYPE_CLI) {
            return self::cli_return_status_display($responsecode);
        } else {
            return self::http_status_display($responsecode);
        }
    }


    public static function full_request(object $profile): string {
        $displayedrequest = $profile->request . $profile->pathinfo;
        if (!empty($profile->parameters)) {
            if ($profile->scripttype == profile::SCRIPTTYPE_CLI) {
                // For CLI scripts, request should look like `command.php --flag=value` as an example.
                $separator = ' ';
                $profile->parameters = escapeshellcmd($profile->parameters);
            } else {
                // For GET requests, request should look like `myrequest.php?myparam=1` as an example.
                $separator = '?';
                $profile->parameters = urldecode($profile->parameters);
            }
            $displayedrequest .= $separator . $profile->parameters;
        }
        return $displayedrequest;
    }
}
