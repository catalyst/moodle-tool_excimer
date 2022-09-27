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
 * Version.
 *
 * @package   tool_excimer
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2022092800;
$plugin->release = 2022092800;
$plugin->requires = 2017051500;    // Moodle 3.3 for Totara support.
$plugin->supported = [35, 401];     // Supports Moodle 3.5 or later.
// TODO $plugin->incompatible = ;  // Available as of Moodle 3.9.0 or later.

$plugin->component = 'tool_excimer';
$plugin->maturity  = MATURITY_STABLE;

$plugin->dependencies = [];
