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
 * Display table for profile report index page.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_table extends \table_sql {

    /**
     * Display values for 'reason' column entries.
     *
     * @param object $record
     * @return string
     * @throws \coding_exception
     */
    public function col_reason(object $record): string {
        return helper::reason_display($record->reason);
    }

    /**
     * Display value for 'type' column entries.
     *
     * @param object $record
     * @return string
     * @throws \coding_exception
     */
    public function col_scripttype(object $record): string {
        return helper::script_type_display($record->scripttype);
    }

    /**
     * Display value for 'request' column entries.
     *
     * @param object $record
     * @return string
     */
    public function col_request(object $record): string {
        if ($this->is_downloading()) {
            return $record->request;
        } else {
            return \html_writer::link(
                new \moodle_url('/admin/tool/excimer/profile.php', ['id' => $record->id]),
                $record->request
            );
        }
    }

    /**
     * Display value for 'duration' column entries.
     *
     * @param object $record
     * @return string
     */
    public function col_duration(object $record): string {
        return format_time($record->duration);
    }

    /**
     * Display value for 'created' column entries.
     *
     * @param object $record
     * @return string
     * @throws \coding_exception
     */
    public function col_created(object $record): string {
        return userdate($record->created, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * Display value for 'parameters' column entries.
     *
     * @param object $record
     * @return string
     */
    public function col_parameters(object $record): string {
        return htmlentities($record->parameters);
    }
}
