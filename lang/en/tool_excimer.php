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
$string['excimergeneral_settings'] = 'General settings';
$string['excimergeneral_settings_desc'] = 'Settings related to all profiles';
$string['excimerauto_settings'] = 'Auto profiling settings';
$string['excimerauto_settings_desc'] = 'Settings related to automatic profiling';
$string['excimeranableauto'] = 'Enable auto profiling';
$string['excimeranableauto_desc'] = 'Any page will be automatically profiled if they exceed the miniumum duration.';
$string['excimerexpiry_s'] = 'Log expiry (days)';
$string['excimerexpiry_s_desc'] = 'Remove profiles after this long.';
$string['excimernum_slowest'] = 'Number of saves';
$string['excimernum_slowest_desc'] = 'Only the N slowest profiles will be kept.';
$string['excimertask_expire_logs'] = 'Expire excimer logs';
$string['excimerperiod_ms'] = 'Sampling period (milliseconds)';
$string['excimerperiod_ms_desc'] = 'Frequency of sampling.';
$string['excimerrequest_ms'] = 'Minimum request duration (milliseconds)';
$string['excimerrequest_ms_desc'] = 'Record a profile only if it runs at least this long.';
$string['excimeruri_contains'] = 'URI must contain';
$string['excimeruri_not_contains'] = 'URI must NOT contain';
$string['excimeruri_patterns_help'] = 'One pattern per line; * for wildcards';

// Profile table.
$string['excimerfield_id'] = 'ID';
$string['excimerfield_scripttype'] = 'Type';
$string['excimerfield_reason'] = 'Reason';
$string['excimerfield_created'] = 'Created';
$string['excimerfield_user'] = 'User';
$string['excimerfield_duration'] = 'Duration';
$string['excimerfield_request'] = 'Request';
$string['excimerfield_explanation'] = 'Explanation';
$string['excimerfield_parameters'] = 'Parameters';
$string['excimerfield_responsecode'] = 'Response Code';
$string['excimerfield_sessionid'] = 'Session ID';
$string['excimerfield_referer'] = 'Referer';
$string['excimerfield_cookies'] = 'Cookies Enabled';
$string['excimerfield_buffering'] = 'Buffering Enabled';

// Terminology.
$string['excimerterm_profile'] = 'Profile';

// Script types.
$string['excimertype_web'] = 'Web';
$string['excimertype_cli'] = 'CLI';
$string['excimertype_ajax'] = 'Ajax';
$string['excimertype_ws'] = 'Service';

// Log methods.
$string['excimerreason_manual'] = 'Manual';
$string['excimerreason_auto'] = 'Auto';
$string['excimerreason_flameall'] = 'Flame All';

