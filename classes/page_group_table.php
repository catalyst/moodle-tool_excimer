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
        'month',
        'fuzzycount',
        'fuzzydurationcounts',
        'fuzzydurationsum',
    ];

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
     * Overrides felxible_table::setup() to do some extra setup.
     *
     * @return false|\type|void
     */
    public function setup() {
        $this->set_sql(
            '*',
            '{' . page_group::TABLE . '}',
            '1=1'
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
        return $record->name;
    }

    /**
     * Month column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_month(\stdClass $record): string {
        return userdate(strtotime($record->month . '01'), '%b %Y');
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
     * Construct a line of the fuzzy duration count column.
     * Line is of the form 2^(k-1) - 2^k : 2^(v-1).
     *
     * @param int $durationexponent The exponent (k) of the high end of the duration range.
     * @param int $count The fuzzy count (v), or zero if no value.
     * @return string
     */
    private function make_fuzzy_duration_line(int $durationexponent, int $count): string {
        $high = pow(2, $durationexponent);
        $low = ($high == 1) ? 0 : pow(2, $durationexponent - 1);
        $val = $count ? pow(2, $count - 1) : 0;
        return get_string('fuzzydurationcount_lines', 'tool_excimer', [
            'low'   => $low,
            'high'  => $high,
            'value' => $val,
        ]);
    }

    /**
     * Fuzzy duration counts column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_fuzzydurationcounts(\stdClass $record): string {
        $counts = json_decode($record->fuzzydurationcounts, true);
        ksort($counts);

        $lines = [];
        $durationexponent = 0;
        // Generate a line for each duration range up to the highest stored in the DB.
        foreach ($counts as $storeddurationexponent => $fuzzycount) {
            // Fill in lines that do not have stored values.
            while ($durationexponent < $storeddurationexponent) {
                $lines[] = $this->make_fuzzy_duration_line($durationexponent, 0);
                ++$durationexponent;
            }
            $lines[] = $this->make_fuzzy_duration_line($storeddurationexponent, $fuzzycount);
            ++$durationexponent; // Ensures this line is not printed twice.
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
