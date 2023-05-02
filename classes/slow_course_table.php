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
class slow_course_table extends profile_table {

    /** Columns to be displayed. */
    const COLUMNS = [
        'maxduration',
        'course',
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

        $filterstring .= " AND courseid IS NOT NULL GROUP BY courseid";
        $this->set_sql(
            'courseid, count(request) as requestcount, scripttype, max(created) as maxcreated, min(created) as mincreated,
            max(duration) as maxduration, min(duration) as minduration',
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
        $columns = self::COLUMNS;
        if (!$this->is_downloading()) {
            $columns[] = 'actions';
        }
        return $columns;
    }

    /**
     * Course column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_course(\stdClass $record): string {
        // This should always be set, but still double check.
        if (empty($record->courseid)) {
            return '';
        }

        // Create a drill-down url for this course instead of linking to the course itself.
        $url = new \moodle_url($this->urlpath, ['courseid' => $record->courseid]);
        return \html_writer::link($url, helper::course_display_name($record->courseid));
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
                ['filter' => json_encode(['courseid' => $record->courseid]), 'sesskey' => sesskey()]);
        $confirmaction = new \confirm_action(get_string('deleteprofiles_course_warning', 'tool_excimer'));
        $deleteicon = new \pix_icon('t/delete', get_string('deleteprofiles_course', 'tool_excimer'));
        $link = new \action_link($deleteurl, '', $confirmaction, null,  $deleteicon);
        return $OUTPUT->render($link);
    }
}
