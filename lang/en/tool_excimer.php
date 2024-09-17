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

$string['pluginname'] = 'Excimer profiler';
$string['reportname'] = 'Profiler reports';
$string['adminname'] = 'Excimer profiler';

// Admin Tree.
$string['report_slowest'] = 'Slowest profiles';
$string['report_slowest_grouped'] = 'Slowest scripts';
$string['report_slowest_web'] = 'Slow web pages';
$string['report_slowest_other'] = 'Slow tasks / CLI / WS';
$string['report_session_locks'] = 'Long session locks';
$string['report_recent'] = 'Recently profiled';
$string['report_unfinished'] = 'Currently profiling';
$string['report_page_groups'] = 'Page Group Metadata';
$string['report_page_slow_course'] = 'Slow by course';

// Check API.
$string['checkslowest'] = 'Excimer profiles';
$string['checkslowest:none'] = 'No profiles recorded.';
$string['checkslowest:action'] = 'Slowest profiles';
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
$string['enable_fuzzy_count'] = 'Enable fuzzy counting';
$string['enable_fuzzy_count_desc'] = 'This will cause the plugin to maintain an approximate count of page runs using the {$a}. Automatic profiling must also be enabled.';
$string['enable_partial_save'] = 'Enable partial save';
$string['enable_partial_save_desc'] = 'This will save partial profiles of slow web processes every processing interval. This
    provides information about these processes while they are still running or if a container gets reaped, but the extra writes
    can be problematic when experiencing large scale database issues.';
$string['expiry_s'] = 'Log expiry (days)';
$string['expiry_s_desc'] = 'Remove profiles after this long.';
$string['num_slowest'] = 'Max to save';
$string['num_slowest_desc'] = 'Only the N slowest profiles will be kept.';
$string['period_ms'] = 'Sampling period (milliseconds)';
$string['period_ms_desc'] = 'Frequency of sampling. Minimum is {$a->min}, maximum is {$a->max}.';
$string['request_ms'] = 'Minimum request duration (milliseconds)';
$string['request_ms_desc'] = 'Record a profile only if it runs at least this long.';
$string['num_slowest_by_page'] = 'Max to save by page';
$string['num_slowest_by_page_desc'] = 'Only the N slowest profiles will be kept for each script page.';
$string['noexcimerprofiler'] = 'ExcimerProfiler class does not exist so profiling cannot continue. Please check the installation instructions {$a}.';
$string['long_interval_s'] = 'Processing interval (seconds)';
$string['long_interval_s_desc'] = 'Checks the current status of long running tasks every N seconds and processes as required.
    This includes saving profiles of finished cron tasks and partial saves of ongoing web processes if enabled.';
$string['task_min_duration'] = 'Task min duration (seconds)';
$string['task_min_duration_desc'] = 'For scheduled and ad-hoc tasks, the minimum approx duration, in seconds.';
$string['samplelimit'] = 'Sample limit';
$string['samplelimit_desc'] = 'The maximum number of samples that will be recorded. This works by filtering the recording of
    samples. Each time the limit is reached, the samples recorded so far are stripped of every second sample. Also, the filter rate
    doubles, so that only every Nth sample is recorded at filter rate N. This has the same effect as adjusting the sampling period
    so that the total number of samples never exceeds the limit.';
$string['stacklimit'] = 'Stack limit';
$string['stacklimit_desc'] = 'The maximum permitted recursion or stack depth before the task is flagged.';
$string['expiry_fuzzy_counts'] = 'Months to keep aproximate count data.';
$string['expiry_fuzzy_counts_desc'] = 'The number of full months worth of data to keep. Leave blank to keep indefinitely.';
$string['redact_params'] = 'Paramters to be redacted';
$string['redact_params_desc'] = 'These parameters (one per line) will have their values removed before their profile is saved.
    Include in this list, any paramters that are potentially sensitive, such as keys, tokens and nonces. Comments, C style (/\*..\*/)
    and bash style (#), and blank lines will be ignored.<br/>
    Redacting of parameters {$a} is builtin, and will always be done.';

// Tasks.
$string['task_expire_logs'] = 'Expire excimer logs';
$string['task_purge_fastest'] = 'Purge fastest excimer profiles';
$string['task_purge_page_groups'] = 'Purge page group approximate count data';

// Tabs.
$string['slowest_grouped'] = 'Slowest scripts';
$string['recent'] = 'Recent';
$string['slowest'] = 'Slowest';
$string['tab_slowest_web'] = 'Slow web pages';
$string['tab_slowest_other'] = 'Slow tasks / CLI / WS';
$string['tab_session_locks'] = 'Long session locks';
$string['unfinished'] = 'Unfinished';
$string['tab_page_groups'] = 'Page Groups';
$string['tab_page_course'] = 'Courses';
$string['tab_import'] = 'Import';

// Month Selector.
$string['displaying_month'] = 'Displaying month';
$string['to_current_month'] = 'To current month';
$string['previous_month'] = 'Previous month';
$string['next_month'] = 'Next month';

// Profile table.
$string['field_id'] = 'ID';
$string['field_type'] = 'Type';
$string['field_scripttype'] = 'Script Type';
$string['field_contenttype'] = 'Content Type';
$string['field_contenttypecategory'] = 'Content Type (category)';
$string['field_contenttypekey'] = 'Content Type (extension/key)';
$string['field_contenttypevalue'] = 'Content Type (actual value)';
$string['field_reason'] = 'Reason';
$string['field_scriptgroup'] = 'Script group';
$string['field_created'] = 'Created';
$string['field_finished'] = 'Finished';
$string['field_userid'] = 'User';
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
$string['field_numsamples_value'] = '{$a->samples} samples @ ~{$a->samplerate}ms';
$string['field_dbreadwrites'] = 'DB reads/writes';
$string['field_dbreplicareads'] = 'DB reads from replica';
$string['field_datasize'] = 'Size of profile data';
$string['field_memoryusagemax'] = 'Max Memory Used';
$string['field_maxcreated'] = 'Latest';
$string['field_mincreated'] = 'Earliest';
$string['field_maxduration'] = 'Slowest';
$string['field_minduration'] = 'Fastest';
$string['field_maxlockheld'] = 'Longest';
$string['field_minlockheld'] = 'Shortest';
$string['field_requestcount'] = 'Num profiles';
$string['field_pid'] = 'Process ID';
$string['field_hostname'] = 'Host name';
$string['field_useragent'] = 'User agent';
$string['field_versionhash'] = 'Version Hash';
$string['field_usermodified'] = 'User modified';
$string['field_timecreated'] = 'Time created';
$string['field_timemodified'] = 'Time modified';
$string['field_lockreason'] = 'Lock reason';
$string['field_courseid'] = 'Course';
$string['field_lockheld'] = 'Session held';
$string['field_lockwait'] = 'Session wait';
$string['field_lockwaiturl'] = 'Locking URL';
$string['field_lockwaiturl_help'] = 'Information about this page may only be available when $CFG->debugsessionlock is set.';

// Page count table.
$string['field_name'] = 'Name';
$string['field_month'] = 'Month';
$string['field_fuzzycount'] = 'Approx. count';
$string['field_fuzzydurationcounts'] = 'Histogram';
$string['field_fuzzydurationsum'] = 'Approx. total duration (s)';

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
$string['reason_stack'] = 'Recursion';
$string['reason_import'] = 'Import';

// Lock reason form.
$string['lock_profile'] = 'Lock Profile';
$string['locked'] = 'Profile is locked';
$string['lockedinfo'] = 'Locked by {$a->user} on {$a->date}';
$string['lockreason'] = 'Lock Profile Reason';
$string['lockreason_help'] = 'Submitting text will prevent this profile from being deleted.
    It will not be purged during cleanup tasks, nor can it be deleted manually (will also be excluded from group deletes).
    Typically you would provide a reason why you want to keep this profile. Clearing this box will allow the profile to be deleted.';
$string['profile_updated'] = 'Profile updated';

// Time formats.
$string['strftime_datetime'] = '%d %b %Y, %H:%M';
$string['strftime_monyear'] = '%b %Y';

// Privacy.
$string['privacy:metadata:tool_excimer_profiles'] = 'Excimer';

// Approx counting.
$string['approx_count_algorithm'] = 'approximate counting algorithm';
$string['fuzzydurationrange'] = '{$a->low} - {$a->high}s';

// Page count.
$string['months_to_display'] = 'Months to display';
$string['histogram_history'] = 'Histogram history';

// Import/export profiles.
$string['export_profile'] = 'Export profile';
$string['import_profile'] = 'Import profile';
$string['import_success'] = 'Profile imported successfully.';
$string['import_error'] = 'Error saving the imported file contents.';
$string['no_profile_file'] = 'No profile file found.';
$string['profile_file'] = 'Profile file';

// Miscellaneous.
$string['cachedef_request_metadata'] = 'Excimer request metadata cache';
$string['cachedef_page_group_metadata'] = 'Excimer page group metadata cache';
$string['deleteallwarning'] = 'This will remove ALL stored profiles. Continue?<br/><i>Locked profiles will not be removed.</i>';
$string['deleteprofile'] = 'Delete profile';
$string['deleteprofilewarning'] = 'This will remove the profile. Continue?';
$string['allprofilesdeleted'] = 'All profiles have been deleted.';
$string['profiledeleted'] = 'Profile has been deleted.';
$string['deleteprofiles_script_warning'] = 'This will remove all stored profiles for the script. Continue?<br/><i>Locked profiles will not be removed.</i>';
$string['deleteprofiles_script'] = 'Delete all profiles for script';
$string['deleteprofiles_course'] = 'Delete all profiles for course';
$string['deleteprofiles_course_warning'] = 'This will remove all stored profiles for the course. Continue?<br/><i>Locked profiles will not be removed.</i>';
$string['profilesdeleted'] = 'Profiles have been deleted';
$string['didnotfinish'] = 'Did not finish';
$string['deleteprofiles_filter_warning'] = 'This will remove all stored profiles that match this filter. Continue?<br/><i>Locked profiles will not be removed.</i>';
$string['deleteprofiles_filter'] = 'Delete all profiles for this filter';
$string['edit_lock'] = 'Edit lock';
$string['samples'] = 'samples';
$string['duration'] = 'duration';
$string['no_month_in_page_group_table'] = 'Month value not set in page group table.';
$string['deletedcourse'] = 'Deleted course id: {$a}';
$string['unknown'] = 'Unknown';
$string['link'] = 'Direct link';
$string['lockwaitnotification'] = 'The majority of the duration was spent waiting for a session lock, the page may not be slow.';
