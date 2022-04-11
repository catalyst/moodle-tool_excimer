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

use tool_excimer\profile;

require_once(__DIR__ . '/../../../config.php');

require_once($CFG->libdir.'/adminlib.php');
require_admin();

$profileid = required_param('profileid', PARAM_INT);

$profile = new profile($profileid);

header('Content-Type: application/json; charset: utf-8');

$jsondata = $profile->get_uncompressed_json('memoryusagedatad3');
if (!empty($jsondata)) {
    echo $jsondata;
    die;
}
// Debugging - dummy data
echo json_encode([
['value' => 1],
['value' => 2],
['value' => 3],
['value' => 4],
['value' => 5],
['value' => 6],
['value' => 7],
['value' => 8],
['value' => 9],
['value' => 10],
['value' => 11],
['value' => 12],
['value' => 13],
['value' => 14],
['value' => 15],
['value' => 16],
['value' => 17],
['value' => 18],
['value' => 19],
['value' => 20],
['value' => 21],
['value' => 22],
['value' => 23],
['value' => 24],
['value' => 25],
['value' => 26],
['value' => 27],
['value' => 28],
['value' => 29],
['value' => 30],
['value' => 31],
['value' => 32],
['value' => 33],
['value' => 34],
['value' => 35],
['value' => 36],
['value' => 37],
['value' => 38],
['value' => 39],
['value' => 40],
['value' => 41],
['value' => 42],
['value' => 43],
['value' => 44],
['value' => 45],
['value' => 46],
['value' => 47],
['value' => 48],
['value' => 49],
['value' => 50],
['value' => 51],
['value' => 52],
['value' => 53],
['value' => 54],
['value' => 55],
['value' => 56],
['value' => 57],
['value' => 58],
['value' => 59],
['value' => 60],
['value' => 61],
['value' => 62],
['value' => 63],
['value' => 64],
['value' => 65],
['value' => 66],
['value' => 67],
['value' => 68],
['value' => 69],
['value' => 70],
['value' => 71],
['value' => 72],
['value' => 73],
['value' => 74],
['value' => 75],
['value' => 76],
['value' => 77],
['value' => 78],
['value' => 79],
['value' => 80],
]);
die;

