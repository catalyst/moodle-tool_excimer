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
 * Subclasses extending this class only need to implement the get_group_by() function.
 * This adds a column by the same name, and updates the SQL to group by this column in the tool_excimer_profiles table.
 * For more complex cases, subclasses can also overwrite put_sql()
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class grouped_profile_table extends profile_table {

    /** Columns to be displayed.*/
    const COLUMNS = [
        'maxduration',
        'requestcount',
        'maxcreated',
        'mincreated',
        'minduration',
    ];

    /** @var \moodle_url URL path to use for linking to profile groups. */
    protected $urlpath;

    /**
     * Set the URL path to use for linking to profile groups.
     *
     * @param \moodle_url $url
     */
    public function set_url_path(\moodle_url $url) {
        $this->urlpath = $url;
    }

    /**
     * Returns what column in the tool_excimer_profiles is used to group the profiles by.
     */
    abstract protected function get_group_by(): string;

    /**
     * Sets the SQL for the table.
     */
    protected function put_sql(): void {
        list($filterstring, $filterparams) = $this->get_filter_for_sql();

        $this->set_count_sql(
            "SELECT count(distinct request)
            FROM {tool_excimer_profiles}
            WHERE $filterstring",
            $filterparams
        );

        $groupby = $this->get_group_by();

        $filterstring .= " AND " . $groupby . " IS NOT NULL GROUP BY " . $groupby;
        $this->set_sql(
            $groupby . ', COUNT(request) as requestcount, MAX(created) as maxcreated, MIN(created) as mincreated,
            MAX(duration) as maxduration, MIN(duration) as minduration',
           '{tool_excimer_profiles}',
           $filterstring,
           $filterparams
        );
    }

    /**
     * Get the columns to be displayed.
     *
     * @return string[]
     */
    protected function get_columns(): array {
        $columns = array_merge(
            [$this->get_group_by()],
            $this::COLUMNS
        );

        if (!$this->is_downloading()) {
            $columns[] = 'actions';
        }
        return $columns;
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

        $groupby = $this->get_group_by();
        $filter = json_encode([$groupby => $record->$groupby]);

        $deleteurl = new \moodle_url('/admin/tool/excimer/delete.php',
                ['filter' => $filter, 'sesskey' => sesskey()]);
        $confirmaction = new \confirm_action(get_string('deleteprofiles_script_warning', 'tool_excimer'));
        $deleteicon = new \pix_icon('t/delete', get_string('deleteprofiles_script', 'tool_excimer'));
        $link = new \action_link($deleteurl, '', $confirmaction, null,  $deleteicon);
        return $OUTPUT->render($link);
    }
}
