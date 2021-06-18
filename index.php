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

    $count = tool_excimer_count_unique_paths();
    $s = $count === 1 ? '' : 's';

    $summary = tool_excimer_summary(); 

    echo $OUTPUT->header();

?>

<h3 class="text-muted"><?= $count ?> distinct graph path<?= $s ?></h3>

<p>[NC 2021-06-17] Proof of concept. Displaying the HTML equivalent of a flame graph. Note we have average timings on every node of the tree, not just totals.</p>

<?php 

    echo '<table>';
    echo '<thead>';
    echo '<th></th>';
    foreach (range(0, 23) as $hour) {
        echo "<th>$hour</th>";
    }
    echo '</thead>';
    echo '<tbody>';
    foreach ($summary as $day => $totalsByHour) {
        echo '<tr>';
        echo "<th><a href=\"?day=$day\">$day</a></th>";
        foreach (range(0, 23) as $hour) {
            $total = $totalsByHour[$hour] ?? 0; 
            if ($total > 0) {
                echo "<td><a href=\"?day=$day&hour=$hour\">&nbsp;$total&nbsp;</a></td>";
            } else {
                echo "<td>&middot;</td>";
            }
        }
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

    echo $OUTPUT->footer();

} else {

    //  Now handled by json.ph
    //  $tree = tool_excimer_tree_data($paramDay, $paramHour);

    echo $OUTPUT->header();

    echo "<p></p>";

?>

    <nav>
      <div class="pull-right">
        <form class="form-inline" id="form">
          <a class="btn" href="javascript: resetZoom();">Reset zoom</a>
          <a class="btn" href="javascript: clear();">Clear</a>
          <div class="form-group">
            <input type="text" class="form-control" id="term">
          </div>
          <a class="btn btn-primary" href="javascript: search();">Search</a>
        </form>
      </div>
    </nav>
    <h3 class="text-muted"><a href="?">Summary</a> &gt; Day: <?= ($paramDay) ?>, Hour: <?= json_encode($paramHour) ?></h3>
    <div id="details" style="min-height: 1.5rem;">
    </div>
    <div id="chart" style="margin-top: 1rem;">
    </div>
    <hr/>

    <script type="text/javascript" src="https://d3js.org/d3.v4.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/d3-flame-graph@4.0.6/dist/d3-flamegraph.min.js"></script>
    <script type="text/javascript">

    var chartElement = document.getElementById('chart');
    const chartWidth = chartElement.offsetWidth - 20;
    var chart = flamegraph().width(chartWidth).inverted(true);
    d3.json("json.php?<?= $_SERVER['QUERY_STRING'] ?>", function(error, data) {
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

    /**
     * HTML tree if needed for debugging.
     *
    function ol($ul) {
        echo "<ol>";
        foreach ($ul as $li) {
            echo "<li>" . $li['name'] . " (" . $li['value'] . ")</li>";
            if (isset($li['children'])) {
                echo ol($li['children']);
            }
        }
        echo "</ol>";
    }

    ol($tree);
     *
     */

    echo $OUTPUT->footer();

}

/**
 * Not showing the D3 infrastructure for now; comment in as needed.
 *
<div class="container">
  <div class="header clearfix">
    <nav>
      <div class="pull-right">
        <form class="form-inline" id="form">
          <a class="btn" href="javascript: resetZoom();">Reset zoom</a>
          <a class="btn" href="javascript: clear();">Clear</a>
          <div class="form-group">
            <input type="text" class="form-control" id="term">
          </div>
          <a class="btn btn-primary" href="javascript: search();">Search</a>
        </form>
      </div>
    </nav>
    <h3 class="text-muted"><?= $count ?> record<?= $s ?></h3>
  </div>
  <div id="chart">
  </div>
  <hr>
  <div id="details">
  </div>
</div>
 *
 */

//  Not showing the table for now; comment in as needed.
//
//  $logs = tool_excimer_get_log_data();
//  $table = new html_table();
//  $table->id = 'log-table';
//  //  $table->head = array(
    //  //  get_string('domain', 'tool_httpsreplace'),
    //  //  get_string('count', 'tool_httpsreplace'),
//  //  );
//  $data = array();
//  foreach ($logs as $line) {
    //  $cleanGraphPath = format_text($line->graphpath, FORMAT_PLAIN);
    //  $data[] = [(int)$line->total, '@', (float)$line->elapsed / (int)$line->total, $cleanGraphPath];
//  }
//  $table->data = $data;
//  echo html_writer::table($table);

//  For debugging $tree.
//  echo "<pre>" . json_encode($tree, JSON_PRETTY_PRINT) . "</pre>";

//  Not using D3 bundled module for now.
//
//  $PAGE->requires->js_call_amd('tool_excimer/bundle', 'init', []);
