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
 * Helpers for displaying stuff.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

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
     * Returns a printable string for the profiling reason.
     *
     * @param int $reason
     * @return string
     * @throws \coding_exception
     */
    public static function reason_display(int $reason): string {
        switch ($reason) {
            case manager::REASON_MANUAL:
                return get_string('reason_manual', 'tool_excimer');
            case manager::REASON_AUTO:
                return get_string('reason_auto', 'tool_excimer');
            case manager::REASON_FLAMEALL:
                return get_string('reason_flameall', 'tool_excimer');
            default:
                return (string) $reason;
        }
    }
}

