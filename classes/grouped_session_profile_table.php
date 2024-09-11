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
 * Table for displaying session lock info from profiles grouped by script.
 *
 * @package   tool_excimer
 * @author    Benjamin Walker <benjaminwalker@catalyst-au.net>
 * @copyright 2024, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grouped_session_profile_table extends grouped_script_profile_table {

    /** Columns to be displayed.*/
    const COLUMNS = [
        'maxlockheld',
        'minlockheld',
        'requestcount',
        'maxcreated',
        'mincreated',
    ];

    /** @var int Only display maximum lockhelds above the threshold. */
    protected $lockheldthreshold;

    /**
     * Sets the minimum lockheld threshold.
     *
     * @param int $duration
     */
    public function set_lockheld_threshold(int $duration) {
        $this->lockheldthreshold = $duration;
    }

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

        if ($this->lockheldthreshold) {
            $filterstring .= " HAVING MAX(lockheld) > ?";
            $filterparams = array_merge($filterparams, [$this->lockheldthreshold]);
        }

        $this->set_sql(
            $groupby . ', COUNT(request) as requestcount, MAX(created) as maxcreated, MIN(created) as mincreated,
            MAX(lockheld) as maxlockheld, MIN(lockheld) as minlockheld',
           '{tool_excimer_profiles}',
           $filterstring,
           $filterparams
        );
    }

    /**
     * Defines the columns for this table.
     *
     * @throws \coding_exception
     */
    public function make_columns(): void {
        $headers = [];
        $columns = $this->get_columns();
        foreach ($columns as $column) {
            $headers[] = get_string('field_' . $column, 'tool_excimer');
        }

        $this->define_columns($columns);
        $this->column_class('maxlockheld', 'text-right');
        $this->column_class('minlockheld', 'text-right');
        $this->column_class('requestcount', 'text-center');
        $this->define_headers($headers);
    }

    /**
     * Max lockheld column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_maxlockheld(\stdClass $record): string {
        return helper::duration_display($record->maxlockheld, !$this->is_downloading());
    }

    /**
     * Min lockheld column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_minlockheld(\stdClass $record): string {
        return helper::duration_display($record->minlockheld, !$this->is_downloading());
    }
}
