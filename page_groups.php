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
 * Display a table of page group metadata.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\page_group_table;
use tool_excimer\page_group;
use tool_excimer\helper;
use tool_excimer\output\tabs;

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$download = optional_param('download', '', PARAM_ALPHA);
$month = optional_param('month', null, PARAM_INT);

$url = new \moodle_url('/admin/tool/excimer/page_groups.php');
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url($url);

admin_externalpage_setup('tool_excimer_report_page_groups');

$output = $PAGE->get_renderer('tool_excimer');

$pluginname = get_string('pluginname', 'tool_excimer');

$table = new page_group_table('page_group_table');

$currentmonth = page_group::get_current_month();
if (empty($month)) {
    $month = $currentmonth;
}

$table->set_month($month);
$table->sortable(true, 'fuzzydurationsum', SORT_DESC);
$table->is_downloading($download, 'page_groups', 'group');
$table->define_baseurl($url);
$table->make_columns();

if (!$table->is_downloading()) {
    $PAGE->set_title($pluginname);
    $PAGE->set_pagelayout('admin');
    $PAGE->set_heading($pluginname);
    echo $output->header();

    $tabs = new tabs($url);
    echo $output->render_tabs($tabs);

    $button = null;
    if ($month !== $currentmonth) {
        $button = $output->render(new \single_button($url, get_string('to_current_month', 'tool_excimer')));
    }

    // Using an integer in YYYYMM format makes it possible to use arithmetic to manipulute the month.
    // However, if you want to go from Dec to Jan, you need to add 89. To go from Jan to Dec, subtract 89.
    $prevmonth = $month - 1;
    if ($prevmonth % 100 === 0) { // Before January.
        $prevmonth -= 88; // Subtract 1 year, but add 12 months.
    }
    $nextmonth = $month + 1;
    if ($nextmonth % 100 === 13) { // After December.
        $nextmonth += 88; // Add one year, but subtract 12 months.
    }

    $data = [
        'prevurl' => page_group::record_exists_for_month($prevmonth) ?
            new moodle_url('/admin/tool/excimer/page_groups.php', ['month' => $prevmonth]) : null,
        'month' => userdate(strtotime($month . '01'), '%b %Y'),
        'nexturl' => page_group::record_exists_for_month($nextmonth) ?
            new moodle_url('/admin/tool/excimer/page_groups.php', ['month' => $nextmonth]) : null,
        'button' => $button,
    ];
    echo $output->render_month_selector($data);
}

$table->out(40, true); // TODO replace with a value from settings.

if (!$table->is_downloading()) {
    echo $output->footer();
}
