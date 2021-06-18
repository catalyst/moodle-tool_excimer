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

require_once(__DIR__ . '/../../../config.php');

header('Content-Type: application/json; charset: utf-8');

require_once($CFG->dirroot.'/admin/tool/excimer/lib.php');
require_once($CFG->libdir.'/adminlib.php');
require_login(null, false);

$paramDay = optional_param('day', null, PARAM_INT);
$paramHour = $paramDay !== null ? optional_param('hour', null, PARAM_INT) : null; // <-- only if day also

if ($paramDay === null) {
    return json_encode(['error' => 500]);
}

$tree = tool_excimer_tree_data($paramDay, $paramHour);

$total = array_sum(array_map(fn($node) => (int)$node['value'], $tree));
echo json_encode((object)[
    'name' => 'root',
    'value' => $total,
    'children' => $tree,
]);

die();
