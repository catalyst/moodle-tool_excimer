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
 * Strings for availability conditions options.
 *
 * @package   tool_excimer
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Excimer sampling profiler';

// Admin.
$string['excimerenable'] = 'Enable profiler';
$string['excimerexpiry_s'] = 'Log expiry (days)';
$string['excimertask_expire_logs'] = 'Expire excimer logs';
$string['excimerperiod_ms'] = 'Sampling period (milliseconds)';
$string['excimerrequest_ms'] = 'Minimum request duration (milliseconds)';
$string['excimeruri_contains'] = 'URI must contain';
$string['excimeruri_not_contains'] = 'URI must NOT contain';
$string['excimeruri_patterns_help'] = 'One pattern per line; * for wilcards';

// Profile table.
$string['excimerfield_id'] = 'ID';
$string['excimerfield_type'] = 'Type';
$string['excimerfield_created'] = 'Created';
$string['excimerfield_duration'] = 'Duration';
$string['excimerfield_request'] = 'Request';
$string['excimerfield_explanation'] = 'Explanation';
$string['excimerfield_parameters'] = 'Parameters';
$string['excimerfield_responsecode'] = 'Response Code';
$string['excimerfield_referer'] = 'Referer';

// Terminology.
$string['excimerterm_profile'] = 'Profile';

// Profile types.
$string['excimertype_web'] = 'Web';
$string['excimertype_cli'] = 'CLI';

// Time format used in profile table
$string['excimertimeformat'] = 'jS M Y H:i:s';
