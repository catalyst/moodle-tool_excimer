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

    const COLUMNS = [
        'responsecode',
        'request',
        'reason',
        'scripttype',
        'created',
        'duration',
        'user',
        'actions',
    ];

    const NOSORT_COLUMNS = [
        'actions',
    ];

    const TIME_FORMAT = '%d %b %Y, %H:%M';

    protected $filters = []; // Where clause filters.

    public function __construct($uniqueid, $filter = '1=1') {
        global $DB;

        parent::__construct($uniqueid);

        $headers = [];
        foreach ($this->get_columns() as $column) {
            $headers[] = get_string('field_' . $column, 'tool_excimer');
        }

        foreach (self::NOSORT_COLUMNS as $column) {
            $this->no_sorting($column);
        }

        $this->define_columns($this->get_columns());
        $this->column_class('duration', 'text-right');
        $this->column_class('responsecode', 'text-right');
        $this->define_headers($headers);
    }

    /**
     * Add a filter to limit the profiles eing listed.
     *
     * @param string $field
     * @param mixed $value
     */
    public function add_filter(string $field, $value): void {
        $this->filters[$field] = $value;
    }

    /**
     * returns the columns defined for the table.
     *
     * @return string[]
     */
    protected function get_columns(): array {
        return self::COLUMNS;
    }

    /**
     * Sets the SQL for the table.
     */
    protected function put_sql(): void {
        $filter = [];
        $filterparams = [];
        if (count($this->filters)) {
            foreach ($this->filters as $i => $v) {
                $filter[] = $i . ' = ?';
                $filterparams[] = $v;
            }
            $filter = implode(' and ', $filter);
        } else {
            $filter = '1=1';
        }

        $fields = [
            '{tool_excimer_profiles}.id as id',
            'reason',
            'scripttype',
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
            $filter,
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
        $displayedrequest = $record->request . $record->pathinfo;
        if (!empty($record->parameters)) {
            if ($record->scripttype == profile::SCRIPTTYPE_CLI) {
                // For CLI scripts, request should look like `command.php --flag=value` as an example.
                $separator = ' ';
                $record->parameters = escapeshellcmd($record->parameters);
            } else {
                // For GET requests, request should look like `myrequest.php?myparam=1` as an example.
                $separator = '?';
                $record->parameters = urldecode($record->parameters);
            }
            $displayedrequest .= $separator . $record->parameters;
        }

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
     * @param object $record
     * @return string
     */
    public function col_duration(object $record): string {
        return helper::duration_display($record->duration);
    }

    /**
     * Display value for 'created' column entries.
     *
     * @param object $record
     * @return string
     * @throws \coding_exception
     */
    public function col_created(object $record): string {
        return userdate($record->created, self::TIME_FORMAT);
    }

    /**
     * Displays the full name of the user.
     *
     * @param object $record
     * @return string
     */
    public function col_user(object $record): string {
        if ($record->userid == 0) {
            return '-';
        } else {
            $fullname = fullname($record);
            if ($this->is_downloading()) {
                return $fullname;
            } else {
                return \html_writer::link('/user/profile.php?id=' . $record->userid, $fullname);
            }
        }
    }

    /**
     * Displays the 'responsecode' column entries
     *
     * @param object $record
     * @return string
     */
    public function col_responsecode(object $record): string {
        if ($this->is_downloading()) {
            return $record->responsecode;
        } else if ($record->scripttype == profile::SCRIPTTYPE_CLI) {
            return helper::cli_return_status_display($record->responsecode);
        } else {
            return helper::http_status_display($record->responsecode);
        }
    }

    protected function format_responsecode($responsecode, $scripttype) {
        if ($this->is_downloading()) {
            return $responsecode;
        } else if ($scripttype == profile::SCRIPTTYPE_CLI) {
            return helper::cli_return_status_display($responsecode);
        } else {
            return helper::http_status_display($responsecode);
        }
    }

    /**
     * Display for the action icons
     *
     * @param object $record
     * @return mixed
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function col_actions(object $record) {
        global $OUTPUT;
        $deleteurl = new \moodle_url('/admin/tool/excimer/delete.php', ['deleteid' => $record->id, 'sesskey' => sesskey()]);
        $confirmaction = new \confirm_action(get_string('deleteprofilewarning', 'tool_excimer'));
        $deleteicon = new \pix_icon('t/delete', get_string('deleteprofile', 'tool_excimer'));
        $link = new \action_link($deleteurl, '', $confirmaction, null,  $deleteicon);
        return $OUTPUT->render($link);
    }
}
