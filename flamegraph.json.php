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
 * D3.js flamegraph data in JSON format.
 *
 * @package   tool_excimer
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\excimer_call;

require_once(__DIR__ . '/../../../config.php');

require_once($CFG->dirroot.'/admin/tool/excimer/lib.php');
require_once($CFG->libdir.'/adminlib.php');

admin_externalpage_setup('tool_excimer_report');

$paramprofile = optional_param('profile', null, PARAM_INT);
$paramday = optional_param('day', null, PARAM_INT);
$paramhour = $paramday !== null
    ? optional_param('hour', null, PARAM_INT)
    : null;

if ($paramday === null || $paramprofile === null) {
    return json_encode(['error' => 500]);
} else if ($paramday !== null) {
    $data = excimer_call::get_time_based_data($paramday, $paramhour);
} else if ($paramprofile !== null) {
    $data = excimer_call::get_profile_data($paramprofile);
} else {
    $data = []; // Not possible.
}

header('Content-Type: application/json; charset: utf-8');
$tree = excimer_call::tree_data($data);
echo json_encode($tree);
