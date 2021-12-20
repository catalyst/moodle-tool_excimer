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
 * Table for displaying grouped profile lists.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grouped_profile_table extends profile_table {

    const COLUMNS = [
        'request',
        'requestcount',
        'maxcreated',
        'mincreated',
        'maxduration',
        'minduration',
        'actions',
    ];

    protected function put_sql(): void {
        global $DB;

        $this->set_sql(
            'request, count(request) as requestcount, scripttype, max(created) as maxcreated, min(created) as mincreated,
             max(duration) as maxduration, min(duration) as minduration',
            '{tool_excimer_profiles}',
            '1=1 GROUP BY request, scripttype'
        );
        $this->set_count_sql("SELECT count(distinct request) FROM {tool_excimer_profiles}");
    }

    protected function get_columns(): array {
        return self::COLUMNS;
    }

    public function col_request(object $record): string {
        $displayedrequest = $record->request;

        return \html_writer::link(
                new \moodle_url('/admin/tool/excimer/slowest.php', ['script' => $record->request]),
                shorten_text($displayedrequest, 100, true, '…'),
                ['title' => $displayedrequest, 'style' => 'word-break: break-all']);
    }

    public function col_maxcreated(object $record): string {
        return userdate($record->mincreated, get_string('strftime_datetime', 'tool_excimer'));
    }
    public function col_mincreated(object $record): string {
        return userdate($record->mincreated, get_string('strftime_datetime', 'tool_excimer'));
    }
    public function col_maxduration(object $record): string {
        return helper::duration_display($record->maxduration);
    }
    public function col_minduration(object $record): string {
        return helper::duration_display($record->minduration);
    }

    public function col_actions(object $record) {
        global $OUTPUT;
        $deleteurl = new \moodle_url('/admin/tool/excimer/delete.php', ['script' => $record->request, 'sesskey' => sesskey()]);
        $confirmaction = new \confirm_action(get_string('deleteprofiles_script_warning', 'tool_excimer'));
        $deleteicon = new \pix_icon('t/delete', get_string('deleteprofiles_script', 'tool_excimer'));
        $link = new \action_link($deleteurl, '', $confirmaction, null,  $deleteicon);
        return $OUTPUT->render($link);
    }

}
