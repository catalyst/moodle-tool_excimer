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
 * Table for displaying grouped profile lists.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grouped_profile_table extends profile_table {

    /** Columns to be displayed. */
    const COLUMNS = [
        'maxduration',
        'group',
        'requestcount',
        'maxcreated',
        'mincreated',
        'minduration',
    ];

    /**
     * Sets the SQL.
     */
    protected function put_sql(): void {
        global $DB;

        $this->set_sql(
            'groupby, count(request) as requestcount, scripttype, max(created) as maxcreated, min(created) as mincreated,
             max(duration) as maxduration, min(duration) as minduration',
            '{tool_excimer_profiles}',
            '1=1 GROUP BY groupby, scripttype'
        );
        $this->set_count_sql("SELECT count(distinct request) FROM {tool_excimer_profiles}");
    }

    /**
     * Get the columns to be displayed.
     *
     * @return string[]
     */
    protected function get_columns(): array {
        $columns = self::COLUMNS;
        if (!$this->is_downloading()) {
            $columns[] = 'actions';
        }
        return $columns;
    }

    /**
     * Profile group column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_group(\stdClass $record): string {
        $displayedvalue = $record->groupby;

        if ($this->is_downloading()) {
            return $displayedvalue;
        } else {
            return \html_writer::link(
                    new \moodle_url('/admin/tool/excimer/slowest.php', ['group' => $record->groupby]),
                    shorten_text($displayedvalue, 100, true, '…'),
                    ['title' => $displayedvalue, 'style' => 'word-break: break-all']);
        }
    }

    /**
     * Max created column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_maxcreated(\stdClass $record): string {
        return userdate($record->maxcreated, get_string('strftime_datetime', 'tool_excimer'));
    }

    /**
     * Min created column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_mincreated(\stdClass $record): string {
        return userdate($record->mincreated, get_string('strftime_datetime', 'tool_excimer'));
    }

    /**
     * Max duration column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_maxduration(\stdClass $record): string {
        return helper::duration_display($record->maxduration, !$this->is_downloading());
    }

    /**
     * Min duration column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_minduration(\stdClass $record): string {
        return helper::duration_display($record->minduration, !$this->is_downloading());
    }

    /**
     * Actions column.
     *
     * @param \stdClass $record
     * @return bool|mixed|string
     */
    public function col_actions(\stdClass $record) {
        if ($this->is_downloading()) {
            return '';
        }
        global $OUTPUT;
        $deleteurl = new \moodle_url('/admin/tool/excimer/delete.php',
                ['filter' => json_encode(['groupby' => $record->groupby]), 'sesskey' => sesskey()]);
        $confirmaction = new \confirm_action(get_string('deleteprofiles_script_warning', 'tool_excimer'));
        $deleteicon = new \pix_icon('t/delete', get_string('deleteprofiles_script', 'tool_excimer'));
        $link = new \action_link($deleteurl, '', $confirmaction, null,  $deleteicon);
        return $OUTPUT->render($link);
    }
}
