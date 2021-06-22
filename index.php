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
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\excimer_log;

require_once('../../../config.php');
require_once($CFG->dirroot.'/admin/tool/excimer/lib.php');
require_once($CFG->libdir.'/adminlib.php');

//  [NC 2021-06-17] @todo: Lock down to admin user. 
//
require_login(null, false);

//  ----------------------------------------------------------------------
//  TODO: Just proof-of-concept now; add to third-party libraries.
//  ----------------------------------------------------------------------
//
//  $PAGE->requires->js('/admin/tool/excimer/amd/build/bundle.js');
$PAGE->requires->css('/admin/tool/excimer/css/d3-flamegraph.css');
$PAGE->requires->css('/admin/tool/excimer/css/style.css');

$pluginName = get_string('pluginname', 'tool_excimer');

$context = context_system::instance();
$url = new moodle_url("/admin/tool/excimer/index.php");

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title($pluginName);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pluginName);

$paramDay = optional_param('day', null, PARAM_INT);
$paramHour = $paramDay !== null ? optional_param('hour', null, PARAM_INT) : null; // <-- only if day also

if ($paramDay === null) {

    $count = excimer_log::count_unique_paths();
    $s = $count === 1 ? '' : 's';

    $summary = excimer_log::summarize(); 

    echo $OUTPUT->header();

?>

<h3 class="text-muted">Summary &gt; <?= $count ?> distinct graph path<?= $s ?></h3>

<p>[NC 2021-06-17] Proof of concept. Note we have average timings on every node of the tree, not just totals.</p>

<?php 

    echo '<table class="table table-sm w-auto table-bordered">';
    echo '<thead>';
    echo '<th class="header"></th>';
    foreach (range(0, 23) as $hour) {
        echo "<th style=\"text-align: center;\">$hour</th>";
    }
    echo '<th class="header" style="text-align: right;">&Sigma;</th>';
    echo '</thead>';
    echo '<tbody>';
    foreach ($summary as $day => $totalsByHour) {
        $dayLabel = join('-', [substr($day, 0, 4), substr($day, 4, 2), substr($day, 6, 2)]);
        echo '<tr>';
        echo "<th class=\"header\"><a href=\"?day=$day\">$dayLabel</a></th>";
        foreach (range(0, 23) as $hour) {
            $total = $totalsByHour[$hour] ?? 0; 
            if ($total > 0) {
                echo "<td class=\"cell\" style=\"text-align: center;\"><a href=\"?day=$day&hour=$hour\">&nbsp;$total&nbsp;</a></td>";
            } else {
                echo "<td class=\"cell\" style=\"text-align: center;\">&middot;</td>";
            }
        }
        $sum = array_sum($totalsByHour);
        echo "<td class=\"cell\" style=\"text-align: right;\">$sum</td>";
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

    echo $OUTPUT->footer();

} else {

    //  Now handled by json.php
    //  $tree = excimer_log::tree_data($paramDay, $paramHour);

    echo $OUTPUT->header();

?>

    <nav>
      <div class="pull-right">
        <form class="form-inline" id="form">
          <a class="btn" href="javascript: resetZoom();">Reset zoom</a>
          <div class="form-group">
            <input type="text" class="form-control" id="term">
          </div>
          <a class="btn btn-primary" href="javascript: search();">Search</a>
          <a class="btn" href="javascript: clear();">Clear</a>
        </form>
      </div>
    </nav>
    <h3 class="text-muted"><a href="?">Summary</a> &gt; Day: <?= ($paramDay) ?>, Hour: <?= json_encode($paramHour) ?></h3>
    <div id="details" style="min-height: 1.5rem;">
    </div>
    <div id="chart" style="margin-top: 1rem;">
    </div>
    <hr/>

    <div id="loading">
        Loading...
    </div>

    <script type="text/javascript" src="https://d3js.org/d3.v4.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/d3-flame-graph@4.0.6/dist/d3-flamegraph.min.js"></script>
    <script type="text/javascript">

    var chartElement = document.getElementById('chart');
    const chartWidth = chartElement.offsetWidth - 20;
    var chart = flamegraph().width(chartWidth).inverted(true);
    d3.json("json.php?<?= $_SERVER['QUERY_STRING'] ?>", function(error, data) {
        document.getElementById('loading').remove();
        if (error) return console.warn(error);
        d3.select("#chart").datum(data).call(chart);
    });
    document.getElementById("form").addEventListener("submit", function(event){
      event.preventDefault();
      search();
    });

    var detailsElement = document.getElementById("details");
    chart.setDetailsElement(details);

    function search() {
      var term = document.getElementById("term").value;
      chart.search(term);
    }

    function clear() {
      document.getElementById('term').value = '';
      chart.clear();
    }

    function resetZoom() {
      chart.resetZoom();
    }
    </script>

<?php 

    echo $OUTPUT->footer();

}

//  Not using D3 bundled module for now.
//
//  $PAGE->requires->js_call_amd('tool_excimer/bundle', 'init', []);
