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

defined('MOODLE_INTERNAL') || die();

const EXCIMER_PERIOD = 0.01; // <-- default in seconds
const EXCIMER_LOG_LIMIT = 10000;

/**
 * Global to store log pool.
 */
$EXCIMER_LOG_ENTRIES = [];


//  ------------------------------------------------------------
//  Hooks
//  ------------------------------------------------------------

/**
 * Hook to be run after initial site config.
 *
 * This allows the plugin to selectively activate the ExcimerProfiler while
 * having access to the database. It means that the initialisation of the
 * request up to this point will not be captured by the profiler. This
 * eliminates the need for an auto_prepend_file/auto_append_file. 
 *
 */
function tool_excimer_after_config() {
            
    static $prof;  // <-- Stay in scope

    $prof = new ExcimerProfiler();
    $prof->setPeriod(EXCIMER_PERIOD);
    $prof->setFlushCallback(fn($log) => tool_excimer_spool($log), EXCIMER_LOG_LIMIT);
    $prof->start();

    core_shutdown_manager::register_function('tool_excimer_shutdown', [$prof]);
}

function tool_excimer_spool(ExcimerLog $log) {
    global $EXCIMER_LOG_ENTRIES;
    foreach ($log as $entry) {
        $EXCIMER_LOG_ENTRIES[] = $entry;
    }
}

function tool_excimer_shutdown(ExcimerProfiler $prof) {
    $prof->stop();
    $prof->flush();
    tool_excimer_save_log();
}

//  ------------------------------------------------------------
//  Queries: Totals
//  ------------------------------------------------------------

function tool_excimer_summary() {
    global $DB;
    $sql = "
        SELECT day, hour, SUM(total)
          FROM {tool_excimer}
      GROUP BY day, hour
      ORDER BY day, hour
    ";
    $result = $DB->get_recordset_sql($sql);
    $summary = [];
    foreach ($result as $row) {
        $day = (int)$row->day;
        $hour = (int)$row->hour;
        $total = (int)$row->sum;
        if (!isset($summary[$day])) {
            $summary[$day] = [];
        }
        $summary[$day][$hour] = $total;
    }
    return $summary;
}

//  ------------------------------------------------------------
//  Queries: Tabular Data
//  ------------------------------------------------------------

/**
 * 
 */
function tool_excimer_count_unique_paths() {
    global $DB;
    $sql = "SELECT COUNT(DISTINCT graphpathmd5) FROM {tool_excimer}";
    return $DB->get_field_sql($sql);
}

function tool_excimer_get_log_data($day=null, $hour=null)
{
    global $DB;
    $safeDay = (int)$day;
    $safeHour = (int)$hour;
    $clauses = ['1 = 1'];
    if ($safeDay > 0) {
        $clauses[] = "day = $safeDay";
    }
    if ($safeHour > 0) {
        $clauses[] = "hour = $safeHour";
    }
    $condition = join(' AND ', $clauses);
    $sql = "
          SELECT graphpath, SUM(total) AS total, SUM(elapsed) AS elapsed
            FROM {tool_excimer}
           WHERE $condition
        GROUP BY graphpath
        ORDER BY graphpath
    ";
    $result = $DB->get_recordset_sql($sql);
    return $result;
}

//  TODO: get day/hour graph, with total busyness


//  ------------------------------------------------------------
//  Queries: Hierarchical Data
//  ------------------------------------------------------------
//  This can be shown as an HTML list or a D3 Flame Graph
//  ------------------------------------------------------------

function tool_excimer_tree_data($day=null, $hour=null) {
    $logs = tool_excimer_get_log_data($day, $hour);
    $tree = tool_excimer_build_tree($logs);
    $data = tool_excimer_format_json($tree); // <-- ready for D3 json
    return $data;
}

/**
 * Process excimer log data into recursive tree structure.
 *
 * [
 *    $name => [
 *       'count' => 0,
 *       'children' => [... RECURSE ...]
 *    ],
 * ]
 *
 */
function tool_excimer_build_tree($data)
{
    $tree = [];
    foreach ($data as $line) {
        $path = explode('|', $line->graphpath);
        tool_excimer_add_to_tree($tree, $path, $line->total, $line->elapsed);
    }
    return $tree;
}


function tool_excimer_add_to_tree(&$tree, $path, $total, $elapsed) {
    if (count($path) > 0) {
        [$head, $tail] = [$path[0], array_slice($path, 1)]; 
        if (isset($tree[$head])) {
            $tree[$head]['total'] += $total; 
            if (count($tail) > 0) {
                if (!isset($tree[$head]['total'])) {
                    $tree[$head]['children'] = [];
                }
                tool_excimer_add_to_tree($tree[$head]['children'], $tail, $total, $elapsed);
            }
        } else {
            if (count($tail) > 0) {
                $tree[$head] = [
                    'total' => $total,
                    'elapsed' => $elapsed,
                    'children' => [],
                ];
                tool_excimer_add_to_tree($tree[$head]['children'], $tail, $total, $elapsed);
            } else {
                $tree[$head] = [
                    'total' => $total,
                    'elapsed' => $elapsed,
                ];
            }
        }
    }
}

/**
 * Process tree structure into json, so that 'name' is an array key.
 *
 */
function tool_excimer_format_json($tree) {
    $nodes = [];
    foreach ($tree as $key => $val) {
        $node = [
            'name' => $key,
            'value' => (int)$val['total'],
        ];
        if (isset($val['children'])) {
            $node['children'] = tool_excimer_format_json($val['children']);
        }
        $nodes[] = $node;
    }
    return $nodes;
}


//  ------------------------------------------------------------
//  Database: Write log data during shutdown
//  ------------------------------------------------------------

/**
 * Save Excimer log to DB.
 *
 * moodle=# \d mdl_tool_excimer;
                                   Table "public.mdl_tool_excimer"
    Column    |         Type          |                           Modifiers
--------------+-----------------------+---------------------------------------------------------------
 id           | bigint                | not null default nextval('mdl_tool_excimer_id_seq'::regclass)
 day          | integer               | not null
 hour         | smallint              | not null
 graphpath    | text                  | not null
 graphpathmd5 | character varying(32) | not null default ''::character varying
 elapsed      | numeric(12,6)         | not null
 total        | bigint                | not null
Indexes:
    "mdl_toolexci_id_pk" PRIMARY KEY, btree (id)
 *
 */
function tool_excimer_save_log() {

    global $DB;
    global $EXCIMER_LOG_ENTRIES;

    if (!is_iterable($EXCIMER_LOG_ENTRIES)) {
        return;
    }
    
    foreach ($EXCIMER_LOG_ENTRIES as $entry) {

        $table = 'tool_excimer';

        $day = (int)date('Ymd');
        $hour = (int)date('H');
        $graphPath = tool_excimer_get_graph_path($entry);
        $graphPathMD5 = md5($graphPath);
        $elapsed = $entry->getTimestamp();
        $total = $entry->getEventCount();

        $matching = [
            'day' => $day,
            'hour' => $hour,
            'graphpathmd5' => $graphPathMD5,
        ];

        //  SLOW.
        $record = $DB->get_record($table, $matching);
        if (is_object($record)) {
            $record->total = (int)$record->total + (int)$total;
            $record->elapsed = (float)$record->elapsed + (float)$elapsed;
            $DB->update_record($table, $record);
        } else {
            $record = (object) [
                'day' => $day,
                'hour' => $hour,
                'graphpath' => $graphPath,
                'graphpathmd5' => $graphPathMD5,
                'elapsed' => $elapsed,
                'total' => $total,
            ];
            $DB->insert_record($table, $record);
        }
    }
}

function tool_excimer_get_graph_path(ExcimerLogEntry $entry) {
    $trace = $entry->getTrace();
    $stack = array_map(fn($call) => tool_excimer_format_call($call), $trace);
    return join('|', array_reverse($stack));
}

function tool_excimer_format_call($call) {
    if (isset($call['function'])) {
        if (isset($call['class'])) {
            return $call['class'] . '::' . $call['function'];
        } else {
            return $call['function'];
        }
    } else {
        if (isset($call['file'])) {
            return $call['file'];
        } else {
            return 'UNKNOWN';
        }
    }
}


