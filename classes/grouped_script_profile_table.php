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

/**
 * Table for displaying profiles grouped by script.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grouped_script_profile_table extends grouped_profile_table {
    /**
     * Group by 'scriptgroup'
     */
    protected function get_group_by(): string {
        return 'scriptgroup';
    }

    /**
     * Profile group column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_scriptgroup(\stdClass $record): string {
        $displayedvalue = $record->scriptgroup;

        if ($this->is_downloading()) {
            return $displayedvalue;
        } else {
            $url = clone $this->urlpath;
            $url->param('group', $record->scriptgroup);
            return \html_writer::link(
                $url,
                shorten_text($displayedvalue, 100, true, '…'),
                ['title' => $displayedvalue, 'style' => 'word-break: break-all']
            );
        }
    }
}
