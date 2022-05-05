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
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT, 2022
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\profile;

require_once(__DIR__ . '/../../../config.php');

require_once($CFG->libdir.'/adminlib.php');
require_login(null, false);
require_capability('moodle/site:config', context_system::instance());

$profileid = required_param('profileid', PARAM_INT);

$profile = new profile($profileid);

header('Content-Type: application/json; charset: utf-8');

$jsondata = $profile->get_uncompressed_json('memoryusagedatad3');
if (!empty($jsondata)) {
    echo $jsondata;
}
