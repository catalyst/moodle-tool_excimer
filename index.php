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

$context = context_system::instance();
$url = new moodle_url("/admin/tool/excimer/index.php");

$PAGE->set_context($context);
$PAGE->set_url($url);

admin_externalpage_setup('tool_excimer_report');

$pluginname = get_string('pluginname', 'tool_excimer');

// TODO support downloading.

$table = new profile_table('uniqueid');
$table->is_downloading(false, 'profile', 'profile record');

if (!$table->is_downloading()) {
    // Only print headers if not asked to download data.
    // Print the page header.
    $PAGE->set_title($pluginname);
    $PAGE->set_pagelayout('admin');
    $PAGE->set_heading($pluginname);
    echo $OUTPUT->header();
}

$columns = [
    'request',
    'type',
    'created',
    'duration',
    'parameters',
    'responsecode',
    'referer'
];

$headers = [
    get_string('excimerfield_request', 'tool_excimer'),
    get_string('excimerfield_type', 'tool_excimer'),
    get_string('excimerfield_created', 'tool_excimer'),
    get_string('excimerfield_duration', 'tool_excimer'),
    get_string('excimerfield_parameters', 'tool_excimer'),
    get_string('excimerfield_responsecode', 'tool_excimer'),
    get_string('excimerfield_referer', 'tool_excimer'),
];

// Work out the sql for the table.
$table->set_sql('id, type, request, created, duration, parameters, responsecode, referer', '{tool_excimer_profiles}', '1=1');
$table->define_columns($columns);
$table->define_headers($headers);

$table->define_baseurl($url);

$table->out(40, true); // TODO replace with a value from settings.

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
