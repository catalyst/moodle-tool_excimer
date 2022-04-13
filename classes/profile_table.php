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
 * Display table for profile report index page.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_table extends \table_sql {

    const COLUMNS = [
        'duration',
        'responsecode',
        'request',
        'reason',
        'type',
        'created',
        'userid',
    ];

    const NOSORT_COLUMNS = [
        'actions',
    ];

    protected $filters = []; // Where clause filters.

    protected $scripttypes = []; // Filter by specific and/or multiple scripttypes.

    /**
     * Add a filter to limit the profiles being listed.
     *
     * @param string $field
     * @param mixed $value
     */
    public function add_filter(string $field, $value): void {
        $this->filters[$field] = $value;
    }

    /**
     * Returns the filters being applied to the list.
     *
     * @return array
     */
    public function get_filters(): array {
        return $this->filters;
    }

    /**
     * Add specific scripttypes to filter
     *
     * @param array $arr
     */
    public function set_scripttypes(array $arr): void {
        $this->scripttypes = $arr;
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

        foreach (self::NOSORT_COLUMNS as $column) {
            $this->no_sorting($column);
        }

        $this->define_columns($columns);
        $this->column_class('duration', 'text-right');
        $this->column_class('responsecode', 'text-right');
        $this->define_headers($headers);
    }

    /**
     * returns the columns defined for the table.
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
     * Sets the SQL for the table.
     */
    protected function put_sql(): void {
        global $DB;

        $filterstring = '';
        $filter = [];
        $filterparams = [];
        if (count($this->filters)) {
            foreach ($this->filters as $i => $v) {
                $filter[] = $i . ' = ?';
                $filterparams[] = $v;
            }
            $filterstring = implode(' and ', $filter);
        } else {
            $filterstring = '1=1';
        }

        if ($this->scripttypes) {
            list($query, $params) = $DB->get_in_or_equal($this->scripttypes);
            $filterstring .= " and scripttype $query";
            $filterparams = array_merge($filterparams, $params);
        }

        $fields = [
            '{tool_excimer_profiles}.id as id',
            'reason',
            'scripttype',
            'contenttypecategory',
            'method',
            'request',
            'pathinfo',
            'created',
            'duration',
            'parameters',
            'responsecode',
            'referer',
            'userid',
            'lang',
            'firstname',
            'lastname',
            'firstnamephonetic',
            'lastnamephonetic',
            'middlename',
            'alternatename',
        ];
        $fieldsstr = implode(',', $fields);

        $this->set_sql(
            $fieldsstr,
            '{tool_excimer_profiles} LEFT JOIN {user} ON {user}.id = {tool_excimer_profiles}.userid',
            $filterstring,
            $filterparams
        );
    }

    /**
     * Overrides felxible_table::setup() to do some extra setup.
     *
     * @return false|\type|void
     */
    public function setup() {
        $this->put_sql();
        $retvalue = parent::setup();
        $this->set_attribute('class', $this->attributes['class'] . ' table-sm');
        return $retvalue;
    }

    /**
     * Display values for 'reason' column entries.
     *
     * @param \stdClass $record
     * @return string
     * @throws \coding_exception
     */
    public function col_reason(\stdClass $record): string {
        return helper::reason_display($record->reason);
    }

    /**
     * Display value for 'type' column entries.
     *
     * @param \stdClass $record
     * @return string
     * @throws \coding_exception
     */
    public function col_type(\stdClass $record): string {
        $scripttype = helper::script_type_display($record->scripttype);
        $contenttype = $record->contenttypecategory;

        // Wrap fields in span which more accurately describes them on hover.
        if (!$this->is_downloading()) {
            $str = \html_writer::span(
                    $scripttype,
                    '',
                    ['title' => get_string('field_scripttype', 'tool_excimer')]);
            if ($contenttype) {
                $str .= ' - ' . \html_writer::span(
                        $contenttype,
                        '',
                        ['title' => get_string('field_contenttypecategory', 'tool_excimer')]);
            }
        } else {
            $str = $scripttype;
            if ($contenttype) {
                $str .= ' - ' . $contenttype;
            }
        }

        return $str;
    }

    /**
     * Display value for 'request' column entries.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_request(\stdClass $record): string {
        $displayedrequest = helper::full_request($record);

        // Return plaintext for download table response format.
        if ($this->is_downloading()) {
            return $record->method . ' ' . $displayedrequest;
        }

        // Return the web format.
        return $record->method . ' ' . \html_writer::link(
                new \moodle_url('/admin/tool/excimer/profile.php', ['id' => $record->id]),
                shorten_text($displayedrequest, 100, true, '…'),
                ['title' => $displayedrequest, 'style' => 'word-break: break-all']);
    }

    /**
     * Display value for 'duration' column entries.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_duration(\stdClass $record): string {
        return helper::duration_display($record->duration, !$this->is_downloading());
    }

    /**
     * Display value for 'created' column entries.
     *
     * @param \stdClass $record
     * @return string
     * @throws \coding_exception
     */
    public function col_created(\stdClass $record): string {
        return userdate($record->created, get_string('strftime_datetime', 'tool_excimer'));
    }

    /**
     * Displays the full name of the user.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_userid(\stdClass $record): string {
        if ($record->userid == 0) {
            return '-';
        } else {
            $fullname = fullname($record);
            if ($this->is_downloading()) {
                return $fullname;
            } else {
                return \html_writer::link(new \moodle_url('/user/profile.php', ['id' => $record->userid]), $fullname);
            }
        }
    }

    /**
     * Displays the 'responsecode' column entries
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_responsecode(\stdClass $record): string {
        if ($this->is_downloading()) {
            return $record->responsecode;
        } else {
            return helper::status_display($record->scripttype, $record->responsecode);
        }
    }

    /**
     * Display for the action icons
     *
     * @param \stdClass $record
     * @return mixed
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function col_actions(\stdClass $record) {
        if ($this->is_downloading()) {
            return '';
        }
        global $OUTPUT;
        $deleteurl = new \moodle_url('/admin/tool/excimer/delete.php', ['deleteid' => $record->id, 'sesskey' => sesskey()]);
        $confirmaction = new \confirm_action(get_string('deleteprofilewarning', 'tool_excimer'));
        $deleteicon = new \pix_icon('t/delete', get_string('deleteprofile', 'tool_excimer'));
        $link = new \action_link($deleteurl, '', $confirmaction, null,  $deleteicon);
        return $OUTPUT->render($link);
    }
}
