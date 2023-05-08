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
 * Table for displaying profiles grouped by course.
 *
 * @package   tool_excimer
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grouped_courses_profile_table extends grouped_profile_table {
    /**
     * Group by courseid.
     */
    protected function get_group_by(): string {
        return 'courseid';
    }

    /**
     * Course column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_courseid(\stdClass $record): string {
        // This should always be set, but still double check.
        if (empty($record->courseid)) {
            return '';
        }

        // Create a drill-down url for this course instead of linking to the course itself.
        $url = new \moodle_url($this->urlpath, ['courseid' => $record->courseid]);
        return \html_writer::link($url, helper::course_display_name($record->courseid));
    }
}
