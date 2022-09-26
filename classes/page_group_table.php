<?php
// This file is part of Moodle - https://moodle.org/
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
 * Table for displaying data from the page group table.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_group_table extends \table_sql {

    /** Columns to be displayed. */
    const COLUMNS = [
        'name',
        'fuzzycount',
        'fuzzydurationcounts',
        'fuzzydurationsum',
    ];

    /** @var int The month to display in YYYYMM format. */
    private $month = null;

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
        $this->column_class('duration', 'text-right');
        $this->define_headers($headers);
    }

    /**
     * returns the columns defined for the table.
     *
     * @return string[]
     */
    protected function get_columns(): array {
        $columns = self::COLUMNS;
        return $columns;
    }

    /**
     * Sets the month to display the records for.
     *
     * @param int $month Month in YYYYMM format.
     */
    public function set_month(int $month) {
        $this->month = $month;
    }

    /**
     * Overrides flexible_table::setup() to do some extra setup.
     *
     * @return false|\type|void
     * @throws \moodle_exception
     */
    public function setup() {
        if (is_null($this->month)) {
            throw new \moodle_exception(get_string('no_month_in_page_group_table', 'tool_excimer'));
        }
        $this->set_sql(
            '*',
            '{' . page_group::TABLE . '}',
            'month = ?',
            [$this->month]
        );
        $retvalue = parent::setup();
        $this->set_attribute('class', $this->attributes['class'] . ' table-sm');
        return $retvalue;
    }

    /**
     * Name column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_name(\stdClass $record): string {
        $link = new \moodle_url('/admin/tool/excimer/page_group.php', ['id' => $record->id]);
        return \html_writer::link($link, $record->name);
    }

    /**
     * Month column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_month(\stdClass $record): string {
        return helper::monthint_formatted($record->month);
    }

    /**
     * Fuzzy count column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_fuzzycount(\stdClass $record): string {
        return pow(2, $record->fuzzycount - 1) . ' - ' . pow(2, $record->fuzzycount);
    }

    /**
     * Fuzzy duration counts column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_fuzzydurationcounts(\stdClass $record): string {
        $histogram = helper::make_histogram($record);
        $lines = [];
        foreach ($histogram as $rec) {
            $lines[] = get_string('fuzzydurationrange', 'tool_excimer', $rec) . ': '. $rec['value'];
        }
        return implode(\html_writer::empty_tag('br'), $lines);
    }

    /**
     * Fuzzy duration sum column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_fuzzydurationsum(\stdClass $record): string {
        return pow(2, $record->fuzzydurationsum);
    }
}
