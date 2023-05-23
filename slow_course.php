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

/**
 * Excimer profiling data grouped by course in a table.
 * @package   tool_excimer
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\profile_table;
use tool_excimer\profile_table_page;
use tool_excimer\grouped_courses_profile_table;

require_once(__DIR__.'/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);

admin_externalpage_setup('tool_excimer_report_page_slow_course');

$url = new moodle_url('/admin/tool/excimer/slow_course.php');

if ($courseid) {
    // If courseid given, show profiles only for this course.
    $table = new profile_table('profile_table_slow_course');
    $table->sortable(true, 'duration', SORT_DESC);
    $table->add_filter('courseid', $courseid);
    $url->params(['courseid' => $courseid]);
    $PAGE->navbar->add($courseid);
} else {
    // Else show profiles grouped by each course, 1 course per row.
    $table = new grouped_courses_profile_table('profile_table_slow_course');
    $table->set_url_path($url);
    $table->sortable(true, 'maxduration', SORT_DESC);
}

profile_table_page::display($table, $url);
