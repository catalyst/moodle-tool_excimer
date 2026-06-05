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
 * Page group meta data.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\chart_bar;
use core\chart_series;
use tool_excimer\helper;
use tool_excimer\monthint;
use tool_excimer\output\tabs;
use tool_excimer\page_group;

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$expiry = get_config('tool_excimer', 'expiry_fuzzy_counts');
$pagegroupid = required_param('id', PARAM_INT);
$monthstodisplay = optional_param('monthstodisplay', $expiry, PARAM_INT);

$url = new \moodle_url('/admin/tool/excimer/page_group.php', ['id' => $pagegroupid]);
$pagesgroupsurl = new \moodle_url('/admin/tool/excimer/page_groups.php');
$context = context_system::instance();

$pagegroup = new page_group($pagegroupid);

$PAGE->set_context($context);
$PAGE->set_url($url);

admin_externalpage_setup('tool_excimer_report_page_groups');

$PAGE->navbar->add(get_string('tab_page_groups', 'tool_excimer'), $pagesgroupsurl);
$PAGE->navbar->add($pagegroup->get('name'));

$output = $PAGE->get_renderer('tool_excimer');

$data = $pagegroup->to_record();

// Data for table.
$data->month = helper::monthint_formatted($data->month);
$data->approxcount = pow(2, $data->fuzzycount - 1) . ' - ' . pow(2, $data->fuzzycount);
$data->approxduration = pow(2, $data->fuzzydurationsum);
$data->histogram = helper::make_histogram($data);
// Add estimated duration (count × upper bound) to each histogram row.
foreach ($data->histogram as &$row) {
    $row['duration'] = $row['value'] * $row['high'];
}
unset($row);

// Data for charts.
// Each duration range forms its own line on the chart.

$firstmonth = monthint::from_timestamp(strtotime("$monthstodisplay months ago"));
// We do the following to ensure we include the current month.
$firstmonth = monthint::increment_month($firstmonth);

$history = $DB->get_records_select(
    page_group::TABLE,
    "name = ? and month >= ?",
    [$data->name, $firstmonth],
    '',
    'month, id, fuzzydurationcounts'
);

$highest = 0;

// Each of these is an array holding counts for each month for a particular duration range.
$durationseries = [];

$histograms = [];
// We make a list of histograms, while finding the highest duration range that a histogram has a count for.
foreach ($history as $month => $record) {
    $histograms[$month] = helper::make_histogram($record);
    $highest = max($highest, count($histograms[$month]));
}

// Labels for the axis.
$labels = [];

// Label for each line.
$linelabels = [];

// Prepare values and labels for the chart.
$month = $firstmonth;
// We use a for loop to include months that have no record in the database.
for ($i = 0; $i < $monthstodisplay; ++$i) {
    $labels[] = helper::monthint_formatted($month);
    // For each histogram, the duration counts are stored in a simple array. We count from 0 to highest here to
    // ensure that we have the same number of entries for each month.
    for ($rangeindex = 0; $rangeindex < $highest; ++$rangeindex) {
        $value = 0;
        if (isset($histograms[$month][$rangeindex])) {
            $entry = $histograms[$month][$rangeindex];
            // Weight count by upper bound of duration range to represent total seconds.
            $value = $entry['value'] * $entry['high'];
            $linelabels[$rangeindex] = get_string('fuzzydurationrange', 'tool_excimer', $entry);
        }
        $durationseries[$rangeindex][] = $value;
    }
    $month = monthint::increment_month($month);
}

$chart = new chart_bar();
$chart->set_stacked(true);
$chart->set_labels($labels);

// Colour stops from green to yellow to orange to red to purple.
$colorstops = [
    [0x2ecc71, 0], // Green.
    [0xf1c40f, 25], // Yellow.
    [0xe67e22, 50], // Orange.
    [0xe74c3c, 75], // Red.
    [0x8e44ad, 100], // Purple.
];

// Interpolate a hex colour along the defined stops given a 0-100 position.
$interpolatecolor = function (float $pct) use ($colorstops): string {
    for ($i = 0; $i < count($colorstops) - 1; $i++) {
        [$cola, $posa] = $colorstops[$i];
        [$colb, $posb] = $colorstops[$i + 1];
        if ($pct <= $posb) {
            $t = ($posb === $posa) ? 0 : ($pct - $posa) / ($posb - $posa);
            $r = (int) round((($cola >> 16) & 0xff) * (1 - $t) + (($colb >> 16) & 0xff) * $t);
            $g = (int) round((($cola >> 8) & 0xff) * (1 - $t) + (($colb >> 8) & 0xff) * $t);
            $b = (int) round(($cola & 0xff) * (1 - $t) + ($colb & 0xff) * $t);
            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }
    }
    [$lastcol] = end($colorstops);
    return sprintf('#%02x%02x%02x', ($lastcol >> 16) & 0xff, ($lastcol >> 8) & 0xff, $lastcol & 0xff);
};

// Add each duration range to the chart, but only those that have non-zero counts.
foreach ($durationseries as $idx => $series) {
    if (max($series) != 0) {
        // Map colour by log base 16 of duration: each factor of 16 = one colour stop.
        // idx is the duration exponent (high = 2^idx), so 4 idx steps = factor of 16.
        // Colour stops: idx 0 = green, 4 = yellow, 8 = orange, 12 = red, 16+ = purple.
        $pct = min(100.0, $idx * 100.0 / 16.0);
        $s = new chart_series($linelabels[$idx], $series);
        $s->set_color($interpolatecolor($pct));
        $chart->add_series($s);
    }
}

// Select element to choose how many months in the past to show in the chart.
$monthstodisplayselect = new \single_select(
    $url,
    'monthstodisplay',
    // We do the following line to get a 1 to $expiry array with matching indexes.
    array_slice(range(0, $expiry), 1, null, true),
    $monthstodisplay
);
$monthstodisplayselect->set_label(get_string('months_to_display', 'tool_excimer'));

$pluginname = get_string('pluginname', 'tool_excimer');

$PAGE->set_title($pluginname);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pluginname);

echo $output->header();
$tabs = new tabs($pagesgroupsurl);
echo $output->render_tabs($tabs);
echo $output->render_from_template('tool_excimer/page_group', $data);
echo html_writer::tag('h3', get_string('histogram_history', 'tool_excimer'));
echo $output->render($monthstodisplayselect);
echo $output->render($chart);
echo $output->footer();
