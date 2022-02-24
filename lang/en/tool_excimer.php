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
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Excimer sampling profiler';

// Admin Tree.
$string['report_slowest'] = 'Profile list - slowest';
$string['report_slowest_grouped'] = 'Profile list - slowest, grouped by request';
$string['report_recent'] = 'Profile list - recent';
$string['report_unfinished'] = 'Profile list - unfinished';

// Check API.
$string['checkslowest'] = 'Excimer profiles';
$string['checkslowest:none'] = 'No profiles recorded.';
$string['checkslowest:action'] = 'Slowest profiles list';
$string['checkslowest:summary'] = 'Slowest profile is "{$a->request}" at {$a->duration}';
$string['checkslowest:details'] = 'The longest running Excimer profile recorded is for the script/task "{$a->request}" at {$a->duration}.';

// Settings.
$string['here'] = 'here';
$string['general_settings'] = 'General settings';
$string['general_settings_desc'] = 'Settings related to all profiles.';
$string['auto_settings'] = 'Auto profiling settings';
$string['auto_settings_desc'] = 'Settings related to automatic profiling.';
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
$string['noexcimerprofiler'] = 'ExcimerProfiler class does not exist so profiling cannot continue. Please check the installation instructions {$a}.';
$string['long_interval_s'] = 'Partial save interval (seconds)';
$string['long_interval_s_desc'] = 'For long running taks, save a partial profile every N seconds.';
$string['task_min_duration'] = 'Task min duration (seconds)';
$string['task_min_duration_desc'] = 'For scheduled and ad-hoc tasks, the minimum approx duration, in seconds.';
$string['doublerate'] = 'Doubling rate';
$string['doublerate_desc'] = 'The number of samples at which the rate of filtering doubles.';

// Tabs.
$string['slowest_grouped'] = 'Slowest - grouped';
$string['recent'] = 'Recent';
$string['slowest'] = 'Slowest';
$string['unfinished'] = 'Unfinished';

// Profile table.
$string['field_id'] = 'ID';
$string['field_type'] = 'Type';
$string['field_scripttype'] = 'Script Type';
$string['field_contenttype'] = 'Content Type';
$string['field_contenttypecategory'] = 'Content Type (category)';
$string['field_contenttypekey'] = 'Content Type (extension/key)';
$string['field_contenttypevalue'] = 'Content Type (actual value)';
$string['field_reason'] = 'Reason';
$string['field_group'] = 'Group';
$string['field_created'] = 'Created';
$string['field_finished'] = 'Finished';
$string['field_user'] = 'User';
$string['field_duration'] = 'Duration';
$string['field_request'] = 'Request';
$string['field_pathinfo'] = 'Pathinfo';
$string['field_explanation'] = 'Explanation';
$string['field_parameters'] = 'Parameters';
$string['field_responsecode'] = 'Code';
$string['field_sessionid'] = 'Session ID';
$string['field_referer'] = 'Referer';
$string['field_cookies'] = 'Cookies enabled';
$string['field_buffering'] = 'Buffering enabled';
$string['field_numsamples'] = 'Number of samples';
$string['field_dbreadwrites'] = 'DB reads/writes';
$string['field_dbreplicareads'] = 'DB reads from replica';
$string['field_datasize'] = 'Size of profile data';
$string['field_maxcreated'] = 'Latest';
$string['field_mincreated'] = 'Earliest';
$string['field_maxduration'] = 'Slowest';
$string['field_minduration'] = 'Fastest';
$string['field_requestcount'] = 'Num profiles';
$string['field_pid'] = 'Process ID';
$string['field_hostname'] = 'Host name';
$string['field_useragent'] = 'User agent';
$string['field_versionhash'] = 'Version Hash';

// Note: This is needed as the headers for the profile table are added in a loop.
$string['field_actions'] = 'Actions';

// Terminology.
$string['term_profile'] = 'Profile';

// Script types.
$string['scripttype_web'] = 'Web';
$string['scripttype_cli'] = 'CLI';
$string['scripttype_ajax'] = 'Ajax';
$string['scripttype_ws'] = 'Service';
$string['scripttype_task'] = 'Task';

// Log reasons.
$string['reason_flameme'] = 'Flame Me';
$string['reason_auto'] = 'Auto';
$string['reason_slow'] = 'Slow';
$string['reason_flameall'] = 'Flame All';

// Time formats.
$string['strftime_datetime'] = '%d %b %Y, %H:%M';

// Privacy.
$string['privacy:metadata:tool_excimer_profiles'] = 'Excimer';

// Miscellaneous.
$string['cachedef_request_metadata'] = 'Excimer request metadata cache';
$string['deleteallwarning'] = 'This will remove ALL stored profiles. Continue?';
$string['deleteprofile'] = 'Delete profile';
$string['deleteprofilewarning'] = 'This will remove the profile. Continue?';
$string['allprofilesdeleted'] = 'All profiles have been deleted.';
$string['profiledeleted'] = 'Profile has been deleted.';
$string['deleteprofiles_script_warning'] = 'This will remove all stored profiles for the script. Continue?';
$string['deleteprofiles_script'] = 'Delete all profiles for script';
$string['profilesdeleted'] = 'Profiles have been deleted';
$string['didnotfinish'] = 'Did not finish';
$string['deleteprofiles_filter_warning'] = 'This will remove all stored profiles that match this filter. Continue?';
$string['deleteprofiles_filter'] = 'Delete all profiles for this filter';
