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
 * Import profiles page
 *
 * @package   tool_excimer
 * @author    Benjamin Walker <benjaminwalker@catalyst-au.net>
 * @copyright 2024, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\profile;
use tool_excimer\form\import_form;

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();

require_login();
admin_externalpage_setup('tool_excimer_import_profile');

$context = context_system::instance();

// Check for caps.
require_capability('moodle/site:config', context_system::instance());

$overviewurl = new moodle_url('/admin/category.php?category=tool_excimer_reports');
$url = new moodle_url('/admin/tool/excimer/import.php');
$PAGE->set_url($url);

$customdata = [];
$form = new import_form($PAGE->url->out(false), $customdata);
if ($form->is_cancelled()) {
    redirect($overviewurl);
}

if (($data = $form->get_data())) {
    try {
        // Prepare and import the profile.
        $filecontent = $form->get_file_content('userfile');
        $id = profile::import($filecontent);

        if (empty($id)) {
            // Failed to save the imported profile.
            \core\notification::error(get_string('import_error', 'tool_excimer'));
            redirect($overviewurl);
        }

        // The import was a success, so redirect to the imported profile.
        \core\notification::success(get_string('import_success', 'tool_excimer'));
        $profile = new moodle_url('/admin/tool/excimer/profile.php', ['id' => $id]);
        redirect($profile);
    } catch (Exception $e) {
        \core\notification::error($e->getMessage() . html_writer::empty_tag('br') . $e->debuginfo);
    }
}

// Display the mandatory header and footer.
$heading = get_string('import_profile', 'tool_excimer');

$title = implode(': ', array_filter([
    get_string('pluginname', 'tool_excimer'),
    $heading,
]));
$PAGE->set_title($title);
$PAGE->set_heading(get_string('pluginname', 'tool_excimer'));
echo $OUTPUT->header();

// Output headings.
echo $OUTPUT->heading($heading);

// And display the form, and its validation errors if there are any.
$form->display();

// Display footer.
echo $OUTPUT->footer();
