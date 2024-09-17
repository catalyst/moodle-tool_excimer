<?php
// This file is part of Moodle - https://moodle.org/
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
 * Form for locking a profile.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\manager;
use tool_excimer\profile;
use tool_excimer\helper;
use tool_excimer\output\tabs;

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$profileid = required_param('profileid', PARAM_INT);
$pluginname = get_string('pluginname', 'tool_excimer');

$params = ['profileid' => $profileid];
$url = new \moodle_url('/admin/tool/excimer/lock_profile.php', $params);
$context = context_system::instance();

$profile = new profile($profileid);

$PAGE->set_context($context);
$PAGE->set_url($url);

$returnurl = get_local_referer(false);

$reporttype = $profile->get('scripttype') == profile::SCRIPTTYPE_WEB ? 'slowest_web' : 'slowest_other';
admin_externalpage_setup('tool_excimer_report_' . $reporttype);

$form = new \tool_excimer\form\lock_reason_form($url);

if ($data = $form->get_data()) {
    $lockreason = trim($data->lockreason);
    $profile->update_lock($lockreason);
    redirect($data->returnurl);
} else {
    $form->set_data(['lockreason' => $DB->get_field('tool_excimer_profiles', 'lockreason', ['id' => $profileid])]);
}

$prevurl = new moodle_url('/admin/tool/excimer/' . $reporttype. '.php', ['group' => $profile->get('scriptgroup')]);
$PAGE->navbar->add($profile->get('scriptgroup'), $prevurl);

$profileurl = new \moodle_url('/admin/tool/excimer/profile.php', ['id' => $profileid]);
$PAGE->navbar->add($profile->get('request') . $profile->get('pathinfo'), $profileurl);

$PAGE->navbar->add(get_string('lock_profile', 'tool_excimer'));

// Display the page.
$PAGE->set_title($pluginname);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pluginname);
$output = $PAGE->get_renderer('tool_excimer');

echo $output->header();

$tabs = new tabs($url);
echo $output->render_tabs($tabs);

$responsecode = helper::status_display($profile->get('scripttype'), $profile->get('responsecode'));
$method = $profile->get('method');
$request = $profile->get('request');
echo html_writer::tag('h3', "$responsecode $method $request");

$form->display();

echo $output->footer();
