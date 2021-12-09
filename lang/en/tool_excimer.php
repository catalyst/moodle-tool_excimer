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
$string['general_settings'] = 'General settings';
$string['general_settings_desc'] = 'Settings related to all profiles';
$string['auto_settings'] = 'Auto profiling settings';
$string['auto_settings_desc'] = 'Settings related to automatic profiling';
$string['enable_auto'] = 'Enable auto profiling';
$string['enable_auto_desc'] = 'Any page will be automatically profiled if they exceed the miniumum duration.';
$string['expiry_s'] = 'Log expiry (days)';
$string['expiry_s_desc'] = 'Remove profiles after this long.';
$string['num_slowest'] = 'Max to save';
$string['num_slowest_desc'] = 'Only the N slowest profiles will be kept.';
$string['task_expire_logs'] = 'Expire excimer logs';
$string['task_purge_fastest'] = 'Purge fastest excimer profiles';
$string['period_ms'] = 'Sampling period (milliseconds)';
$string['period_ms_desc'] = 'Frequency of sampling.';
$string['request_ms'] = 'Minimum request duration (milliseconds)';
$string['request_ms_desc'] = 'Record a profile only if it runs at least this long.';
$string['num_slowest_by_page'] = 'Max to save by page';
$string['num_slowest_by_page_desc'] = 'Only the N slowest profiles will be kept for each script page.';

// Profile table.
$string['field_id'] = 'ID';
$string['field_scripttype'] = 'Type';
$string['field_reason'] = 'Reason';
$string['field_created'] = 'Created';
$string['field_user'] = 'User';
$string['field_duration'] = 'Duration';
$string['field_request'] = 'Request';
$string['field_explanation'] = 'Explanation';
$string['field_parameters'] = 'Parameters';
$string['field_responsecode'] = 'Response Code';
$string['field_sessionid'] = 'Session ID';
$string['field_referer'] = 'Referer';
$string['field_cookies'] = 'Cookies Enabled';
$string['field_buffering'] = 'Buffering Enabled';

// Terminology.
$string['term_profile'] = 'Profile';

// Script types.
$string['scripttype_web'] = 'Web';
$string['scripttype_cli'] = 'CLI';
$string['scripttype_ajax'] = 'Ajax';
$string['scripttype_ws'] = 'Service';

// Log reasons.
$string['reason_manual'] = 'Manual';
$string['reason_auto'] = 'Auto';
$string['reason_flameall'] = 'Flame All';

