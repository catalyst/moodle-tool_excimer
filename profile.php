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
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>, Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\manager;
use tool_excimer\profile;

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$profileid = required_param('id', PARAM_INT);

$params = [ 'id' => $profileid ];
$url = new \moodle_url('/admin/tool/excimer/profile.php', $params);
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url($url);

admin_externalpage_setup('tool_excimer_report');

$returnurl = new \moodle_url('/admin/tool/excimer/profile.php');

$pluginname = get_string('pluginname', 'tool_excimer');

$url = new moodle_url("/admin/tool/excimer/index.php");

$PAGE->set_title($pluginname);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pluginname);

$PAGE->requires->css('/admin/tool/excimer/css/d3-flamegraph.css');

$data = [
    'dataurl' => '/admin/tool/excimer/flamegraph.json.php?profileid=' . $profileid,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('tool_excimer/flamegraph', $data);
echo $OUTPUT->footer();
