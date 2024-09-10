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
 * Long session lock data in a table.
 *
 * @package   tool_excimer
 * @author    Benjamin Walker <benjaminwalker@catalyst-au.net>
 * @copyright 2024, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\grouped_session_profile_table;
use tool_excimer\profile_table_page;
use tool_excimer\session_profile_table;

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// The script can either be a URL, or a task name, or whatever may be used
// for a request name. So we need to accept TEXT input.
$script = optional_param('script', '', PARAM_TEXT);
$group = optional_param('group', '', PARAM_TEXT);

admin_externalpage_setup('tool_excimer_report_session_locks');

$url = new moodle_url("/admin/tool/excimer/session_locks.php");

if ($script || $group) {
    $table = new session_profile_table('profile_table_session_locks');
    $table->sortable(true, 'lockheld', SORT_DESC);
    if ($script) {
        $table->add_filter('request', $script);
        $url->params(['script' => $script]);
    }
    if ($group) {
        $table->add_filter('scriptgroup', $group);
        $url->params(['group' => $group]);
        $PAGE->navbar->add($group);
    }
} else {
    $table = new grouped_session_profile_table('profile_table_session_locks');
    $table->set_url_path($url);
    $table->set_lockheld_threshold(1);
    $table->sortable(true, 'maxlockheld', SORT_DESC);
}

profile_table_page::display($table, $url);
