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

use tool_excimer\excimer_call;
use tool_excimer\excimer_profile;
use tool_excimer\manager;
use tool_excimer\profile_table;

require_once('../../../config.php');
require_once($CFG->dirroot.'/admin/tool/excimer/lib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');

admin_externalpage_setup('tool_excimer_report');

// TODO: Just proof-of-concept now; add to third-party libraries or JS bundle later.

$PAGE->requires->css('/admin/tool/excimer/css/d3-flamegraph.css');

$pluginname = get_string('pluginname', 'tool_excimer');

$context = context_system::instance();
$url = new moodle_url("/admin/tool/excimer/index.php");

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title($pluginname);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pluginname);

$profileid = optional_param('profileid', null, PARAM_INT);

if (isset($profileid)) {

    echo $OUTPUT->header();

?>

    <nav class="vertical-padding pull-right">
      <form class="form-inline" id="form">
        <a class="btn" href="javascript: resetZoom();">Reset zoom</a>
        <div class="form-group">
          <input type="text" class="form-control" id="term">
        </div>
        <a class="btn btn-primary" href="javascript: search();">Search</a>
        <a class="btn" href="javascript: clear();">Clear</a>
      </form>
    </nav>

    <div id="details" style="min-height: 1.5rem; clear: both;">
    </div>

    <div id="loading">
        Loading...
    </div>

    <div id="chart" style="margin-top: 1rem;">
    </div>

    <script type="text/javascript" src="https://d3js.org/d3.v4.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/d3-flame-graph@4.0.6/dist/d3-flamegraph.min.js"></script>
    <script type="text/javascript">

    init();
    window.addEventListener('resize', _ => init());

    document.getElementById("form").addEventListener("submit", function(event){
      event.preventDefault();
      search();
    });

    function init() {

        var chart = document.getElementById('chart');
        var details = document.getElementById("details");

        const chartWidth = Math.max(chart.offsetWidth - 15, 500);

        window.excimerChart = flamegraph();
        window.excimerChart.width(chartWidth).inverted(true);
        window.excimerChart.setDetailsElement(details);

        if (window.excimerData === undefined) {
            setLoading(true);
            d3.json('flamegraph.json.php?profile=<?php echo $profileid ?>', function(error, data) {
                setLoading(false);
                if (error) return console.warn(error);
                window.excimerData = data;
                draw();
            });
        } else {
            draw();
        }
    }

    function draw() {
        var svg = document.querySelector('#chart svg');
        if (svg !== null) {
            svg.remove();
        }
        //  Append SVG:
        d3.select("#chart").datum(window.excimerData).call(window.excimerChart);
    }

    function setLoading(yn) {
        document.getElementById('loading').style.display = yn ? 'block' : 'none';
    }

    function search() {
        if (window.excimerChart !== undefined) {
            var term = document.getElementById("term").value;
            window.excimerChart.search(term);
        }
    }

    function clear() {
        if (window.excimerChart !== undefined) {
            document.getElementById('term').value = '';
            window.excimerChart.clear();
        }
    }

    function resetZoom() {
        if (window.excimerChart !== undefined) {
            window.excimerChart.resetZoom();
        }
    }
    </script>

    <?php

    echo $OUTPUT->footer();

} else {


    // TODO support downloading.

    $table = new profile_table('uniqueid');
    $table->is_downloading(false, 'profile', 'profile record');

    if (!$table->is_downloading()) {
        // Only print headers if not asked to download data
        // Print the page header
        // TODO get strings from string table.
        $PAGE->set_title('Excimer Profiles');
        $PAGE->set_heading('Excimer Profiles');
        $PAGE->navbar->add('Excimer Profiles', $url);
        echo $OUTPUT->header();
    }

    $columns = [
        'id',
        'created',
        'request'
    ];

    $headers = [
        "ID",
        "Created",
        "Request"
    ];

    // Work out the sql for the table.
    $table->set_sql('id, request, created', '{tool_excimer_flamegraph}', '1=1');
    $table->define_columns($columns);
    $table->define_headers($headers);


    $table->define_baseurl($url);

    $table->out(40, true);

    if (!$table->is_downloading()) {
        echo $OUTPUT->footer();
    }
}
