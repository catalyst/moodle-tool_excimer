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
 * D3.js flamegraph of excimer profiling data.
 *
 * @package   tool_excimer
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\profile_table;

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');

$download = optional_param('download', '', PARAM_ALPHA);

$context = context_system::instance();
$url = new moodle_url("/admin/tool/excimer/index.php");

$PAGE->set_context($context);
$PAGE->set_url($url);

admin_externalpage_setup('tool_excimer_report');

$pluginname = get_string('pluginname', 'tool_excimer');

$table = new profile_table('profile_table');
$table->is_downloading($download, 'profile', 'profile_record');

if (!$table->is_downloading()) {
    $PAGE->set_title($pluginname);
    $PAGE->set_pagelayout('admin');
    $PAGE->set_heading($pluginname);
    echo $OUTPUT->header();
}


$table->define_baseurl($url);

$table->out(40, true); // TODO replace with a value from settings.

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
