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

/** Report section - slowest - lists the slowest profiles first */
const REPORT_SECTION_SLOWEST = 'slowest';

/** Report section - unfinished - lists profiles of scripts that did not finish */
const REPORT_SECTION_UNFINISHED = 'unfinished';

/** Report sections */
const REPORT_SECTIONS = [
    REPORT_SECTION_RECENT,
    REPORT_SECTION_SLOWEST,
    REPORT_SECTION_UNFINISHED,
];

$profileid = required_param('id', PARAM_INT);

$params = [ 'id' => $profileid ];
$url = new \moodle_url('/admin/tool/excimer/profile.php', $params);
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url($url);

$returnurl = get_local_referer(false);

// The page's breadcrumb will include a link to the reports for recent or slowest (default).
// Handling here prevents things links from other pages and paginated listings
// from breaking the output of this page.
$report = explode('.php', basename($returnurl, '.php'))[0] ?? null;
$report = in_array($report, REPORT_SECTIONS) ? $report : REPORT_SECTION_SLOWEST;
admin_externalpage_setup('tool_excimer_report_' . $report);

$output = $PAGE->get_renderer('tool_excimer');

$pluginname = get_string('pluginname', 'tool_excimer');

$url = new moodle_url("/admin/tool/excimer/index.php");

$profile = new profile($profileid);

$PAGE->navbar->add($profile->get('request') . $profile->get('pathinfo'));
$PAGE->set_title($pluginname);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pluginname);

$PAGE->requires->css('/admin/tool/excimer/css/d3-flamegraph.css');

$PAGE->requires->js('/admin/tool/excimer/lib/d3/dist/d3.min.js', true);
$PAGE->requires->js('/admin/tool/excimer/lib/d3-flame-graph/dist/d3-flamegraph.min.js', true);

$user = $DB->get_record('user', ['id' => $profile->get('userid')]);

$deleteurl = new \moodle_url('/admin/tool/excimer/delete.php', ['deleteid' => $profileid, 'returnurl' => $returnurl]);
$deletebutton = new \single_button($deleteurl, get_string('deleteprofile', 'tool_excimer'));
$deletebutton->add_confirm_action(get_string('deleteprofilewarning', 'tool_excimer'));

$deleteallurl = new \moodle_url('/admin/tool/excimer/delete.php',
        ['script' => $profile->get('request'), 'returnurl' => $returnurl]);
$deleteallbutton = new \single_button($deleteallurl, get_string('deleteprofiles_script', 'tool_excimer'));
$deleteallbutton->add_confirm_action(get_string('deleteprofiles_script_warning', 'tool_excimer'));

$data = (array) $profile->to_record();

// Totara doesn't like userdate being called within mustache.
$data['created'] = userdate($data['created']);
$data['finished'] = userdate($data['finished']);

$data['duration'] = format_time(round($data['duration'], 3));

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

$data['script_type_display'] = function($text, $render) {
    return helper::script_type_display((int)$render($text));
};
$data['reason_display'] = function($text, $render) {
    return helper::reason_display((int)$render($text));
};

$data['datasize'] = display_size($profile->get('datasize'));
$data['memoryusagemax'] = display_size($profile->get('memoryusagemax'));
$data['delete_button'] = $output->render($deletebutton);
$data['delete_all_button'] = $output->render($deleteallbutton);

$data['responsecode'] = helper::status_display($profile->get('scripttype'), $profile->get('responsecode'));
// Totara doesn't like the mustache string helper being called with varaibles.
$data['numsamples_str'] = get_string('field_numsamples_value', 'tool_excimer', ['samples' => $data['numsamples'], 'samplerate' => $data['samplerate']]);


if ($user) {
    $data['userlink'] = new moodle_url('/user/profile.php', ['id' => $user->id]);
    $data['fullname'] = fullname($user);
} else {
    $data['userlink'] = null;
    $data['fullname'] = '-';
}
$tabs = new tabs($url);

$data['tabs'] = $tabs->export_for_template($output)['tabs'];

echo $output->header();
echo $output->render_from_template('tool_excimer/profile', $data);
echo $output->render_from_template('tool_excimer/flamegraph', $data);
echo $output->render_from_template('tool_excimer/memoryusagegraph', $data);
echo $output->footer();
