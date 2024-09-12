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
 * D3.js flamegraph of excimer profiling data.
 *
 * @package   tool_excimer
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>, Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\profile;
use tool_excimer\helper;
use tool_excimer\output\tabs;

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

/** Report section - recent - lists the most recent profiles first */
const REPORT_SECTION_RECENT = 'recent';

/** Report section - slowest web - lists profile groups for web pages. */
const REPORT_SECTION_SLOWEST_WEB = 'slowest_web';

/** Report section - slowest other - lists profile groups for tasks, CLI and WS scripts. */
const REPORT_SECTION_SLOWEST_OTHER = 'slowest_other';

/** Report section - unfinished - lists profiles of scripts that did not finish */
const REPORT_SECTION_UNFINISHED = 'unfinished';

/** Report sections */
const REPORT_SECTIONS = [
    REPORT_SECTION_RECENT,
    REPORT_SECTION_SLOWEST_WEB,
    REPORT_SECTION_SLOWEST_OTHER,
    REPORT_SECTION_UNFINISHED,
];

$profileid = required_param('id', PARAM_INT);

$params = ['id' => $profileid];
$url = new \moodle_url('/admin/tool/excimer/profile.php', $params);
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url($url);

$returnurl = get_local_referer(false);

// The page's breadcrumb will include a link to the top level report pages that are defined by the tabs.
// Handling here prevents things links from other pages and paginated listings
// from breaking the output of this page.
$report = explode('.php', basename($returnurl, '.php'))[0] ?? null;
$report = in_array($report, REPORT_SECTIONS) ? $report : REPORT_SECTION_SLOWEST_WEB;
admin_externalpage_setup('tool_excimer_report_' . $report);

$output = $PAGE->get_renderer('tool_excimer');

$lockform = new \tool_excimer\form\lock_reason_form($url);
if ($lockdata = $lockform->get_data()) {
    $DB->update_record('tool_excimer_profiles', ['id' => $profileid, 'lockreason' => $lockdata->lockreason]);
} else {
    $lockform->set_data(['lockreason' => $DB->get_field('tool_excimer_profiles', 'lockreason', ['id' => $profileid])]);
}

$pluginname = get_string('pluginname', 'tool_excimer');

$url = new moodle_url("/admin/tool/excimer/index.php");

$profile = new profile($profileid);

$prevurl = new moodle_url('/admin/tool/excimer/' . $report. '.php', ['group' => $profile->get('scriptgroup')]);
$PAGE->navbar->add($profile->get('scriptgroup'), $prevurl);

$PAGE->navbar->add($profile->get('request') . $profile->get('pathinfo'));
$PAGE->set_title($pluginname);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pluginname);

$PAGE->requires->css('/admin/tool/excimer/css/d3-flamegraph.css');
$PAGE->requires->css('/admin/tool/excimer/css/memory-usage-graph.css');

$PAGE->requires->js('/admin/tool/excimer/lib/d3/dist/d3.min.js', true);
$PAGE->requires->js('/admin/tool/excimer/lib/d3-flame-graph/dist/d3-flamegraph.min.js', true);
$PAGE->requires->js('/admin/tool/excimer/lib/d3-flame-graph/dist/d3-flamegraph-tooltip.min.js', true);

$user = $DB->get_record('user', ['id' => $profile->get('userid')]);

$deleteurl = new \moodle_url('/admin/tool/excimer/delete.php', ['deleteid' => $profileid, 'returnurl' => $returnurl]);
$deletebutton = new \single_button($deleteurl, get_string('deleteprofile', 'tool_excimer'));
$deletebutton->add_confirm_action(get_string('deleteprofilewarning', 'tool_excimer'));
if ($profile->get('lockreason') != '') {
    $deletebutton->disabled = true;
}

$deleteallurl = new \moodle_url('/admin/tool/excimer/delete.php',
        ['script' => $profile->get('request'), 'returnurl' => $returnurl]);
$deleteallbutton = new \single_button($deleteallurl, get_string('deleteprofiles_script', 'tool_excimer'));
$deleteallbutton->add_confirm_action(get_string('deleteprofiles_script_warning', 'tool_excimer'));

$lockprofileurl = new \moodle_url('/admin/tool/excimer/lock_profile.php', ['profileid' => $profileid]);
$lockprofilebutton = new \single_button($lockprofileurl, get_string('edit_lock', 'tool_excimer'), 'GET');

$exporturl = new \moodle_url('/admin/tool/excimer/export.php', ['exportid' => $profileid]);
$exportbutton = new \single_button($exporturl, get_string('export_profile', 'tool_excimer'), 'GET');

$data = (array) $profile->to_record();

// Totara doesn't like userdate being called within mustache.
$data['created'] = userdate($data['created']);
$data['finished'] = userdate($data['finished']);

$duration = $data['duration'];
$data['duration'] = helper::duration_display_text($duration, true);
if (isset($data['lockwait'])) {
    $lockwait = $data['lockwait'];
    $data['lockwait'] = helper::duration_display_text($lockwait, true);
    $data['lockheld'] = helper::duration_display_text($data['lockheld'], true);
    $data['lockwaiturl'] = helper::lockwait_display_link($data['lockwaiturl'], $lockwait);
    $data['lockwaiturlhelp'] = helper::lockwait_display_help($OUTPUT, $data['lockwaiturl']);

    $processing = $duration - $lockwait;
    if (($processing / $duration) < 0.1 && $processing < 10) {
        \core\notification::warning(get_string('lockwaitnotification', 'tool_excimer'));
        $data['waitnotification'] = true;
    }
} else {
    $data['lockwaiturl'] = '-';
}

$data['request'] = helper::full_request($profile->to_record());

// If GET request then it should be reproducable as a idempotent request (readonly).
if ($profile->get('method') === 'GET') {
    $requesturl = new \moodle_url('/' . $profile->get('request') .
            $profile->get('pathinfo') . '?' . htmlentities($profile->get('parameters')));
    $data['request'] = \html_writer::link(
        $requesturl,
        urldecode($data['request']),
        [
            'rel' => 'noreferrer noopener',
            'target' => '_blank',
        ]
    );
}

$data['script_type_display'] = function ($text, $render) {
    return helper::script_type_display((int) $render($text));
};
$data['reason_display'] = function ($text, $render) {
    return helper::reason_display((int) $render($text));
};

$data['datasize'] = display_size($profile->get('datasize'));
$data['memoryusagemax'] = display_size($profile->get('memoryusagemax'));
$data['delete_button'] = $output->render($deletebutton);
$data['delete_all_button'] = $output->render($deleteallbutton);
$data['profile_lock_button'] = $output->render($lockprofilebutton);
$data['export_button'] = $output->render($exportbutton);

$data['responsecode'] = helper::status_display($profile->get('scripttype'), $profile->get('responsecode'));

$decsep = get_string('decsep', 'langconfig');
$thousandssep = get_string('thousandssep', 'langconfig');

// Totara doesn't like the mustache string helper being called with variables.
$data['numsamples_str'] = get_string(
    'field_numsamples_value',
    'tool_excimer',
    [
        'samples' => number_format($data['numsamples'], 0, $decsep, $thousandssep),
        'samplerate' => $data['samplerate'],
    ]
);
$data['dbreads'] = number_format($data['dbreads'], 0, $decsep, $thousandssep);
$data['dbwrites'] = number_format($data['dbwrites'], 0, $decsep, $thousandssep);
$data['dbreplicareads'] = number_format($data['dbreplicareads'], 0, $decsep, $thousandssep);

if ($user) {
    $data['userlink'] = new moodle_url('/user/profile.php', ['id' => $user->id]);
    $data['fullname'] = fullname($user);
} else {
    $data['userlink'] = null;
    $data['fullname'] = '-';
}
$data['lockreason'] = format_text($data['lockreason']);
$tabs = new tabs($url);

// JavaScript locale string. Arguably "localecldr/langconfig" would be a better
// choice, but it's not present in Totara langpacks.
$data['locale'] = get_string('iso6391', 'langconfig');

$data['course'] = helper::course_display_link($data['courseid']);

echo $output->header();
echo $output->render_tabs($tabs);
echo $output->render_from_template('tool_excimer/profile', $data);
echo $output->render_from_template('tool_excimer/flamegraph', $data);
echo $output->render_from_template('tool_excimer/memoryusagegraph', $data);
echo $output->footer();
