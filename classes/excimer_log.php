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

namespace tool_excimer;

use ExcimerLogEntry;
use tool_excimer\excimer_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Manage excimer_log table
 *
 * @package   tool_excimer
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class excimer_log {

    //  --------------------------------------------------------
    //  Create
    //  --------------------------------------------------------

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
    public static function save_entry(ExcimerLogEntry $entry) {

        global $DB;

        $table = 'tool_excimer';

        $day = (int)date('Ymd');
        $hour = (int)date('H');
        $graphPath = excimer_helper::get_graph_path($entry);
        $graphPathMD5 = md5($graphPath);
        $elapsed = $entry->getTimestamp();
        $total = $entry->getEventCount();

        $matching = [
            'day' => $day,
            'hour' => $hour,
            'graphpathmd5' => $graphPathMD5,
        ];

        //  SLOW; FIXME: Will be more efficient on some data to process wholly
        //  in memory and save as a single bulk insert at the end. 
        //
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

    //  --------------------------------------------------------
    //  Read
    //  --------------------------------------------------------

    /**
     * Summarise data by day and hour
     *
     * @return array e.g. [20210621 => [20 => 3451, ...], ...]
     */
    public static function summarize() {
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

    /**
     * Count unique graphpaths in table (use MD5 shortcut for comparison).
     *
     * @return int
     */
    public static function count_unique_paths() {
        global $DB;
        $sql = "SELECT COUNT(DISTINCT graphpathmd5) FROM {tool_excimer}";
        return $DB->get_field_sql($sql);
    }

    /**
     * Select log data by optional day and hour params.
     *
     * @param int $day e.g. 20160621
     * @param int $hour e.g. 20
     * @return iterable Result set for query
     */
    public static function get_log_data($day=null, $hour=null) {
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

        return $DB->get_recordset_sql($sql);
    }

    /**
     * Turn graphpaths from excimer_log entries into D3 format recursive JSON
     * for flame graphs.
     *
     * @param int $day e.g. 20210621
     * @param int $hour e.g. 20
     * @return string JSON {'name': 'root', 'value': value', 'children': [...]}
     */
    public static function tree_data($day=null, $hour=null) {

        $logs = self::get_log_data($day, $hour);
        $tree = self::build_tree($logs);
        $data = self::format_json($tree); // <-- ready for D3 json

        $total = array_sum(array_map(fn($node) => (int)$node['value'], $data));
        return (object)[
            'name' => 'root',
            'value' => $total,
            'children' => $data,
        ];
    }

    /**
     * Process excimer log data into recursive tree structure; use name as the
     * key for speed and uniqueness.
     *
     * [
     *    $name => [
     *       'count' => 0,
     *       'children' => [... RECURSE ...]
     *    ],
     * ]
     *
     * @param iterable $data of {graphpath, total, elapsed}
     * @return array 
     */
    public static function build_tree($data) {
        $tree = [];
        foreach ($data as $line) {
            $path = explode('|', $line->graphpath);
            self::add_to_tree($tree, $path, $line->total, $line->elapsed);
        }
        return $tree;
    }

    /**
     * Build tree of call paths, storing totals.
     * 
     * @param array $tree Reference to array, recursively updated in-place
     * @param array $path array of graph path calls
     * @param int Total calls to this graph path
     * @param int Total time elapsed in reaching this function call
     * @return void
     */
    public static function add_to_tree(&$tree, $path, $total, $elapsed) {
        if (count($path) > 0) {
            [$head, $tail] = [$path[0], array_slice($path, 1)]; 
            if (isset($tree[$head])) {
                $tree[$head]['total'] += $total; 
                if (count($tail) > 0) {
                    if (!isset($tree[$head]['total'])) {
                        $tree[$head]['children'] = [];
                    }
                    self::add_to_tree($tree[$head]['children'], $tail, $total, $elapsed);
                }
            } else {
                if (count($tail) > 0) {
                    $tree[$head] = [
                        'total' => $total,
                        'elapsed' => $elapsed,
                        'children' => [],
                    ];
                    self::add_to_tree($tree[$head]['children'], $tail, $total, $elapsed);
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
     * Turn PHP assoc array into JSON where the $name array key becomes a property.
     * 
     * @param array $tree associative array 
     * @return array JSON D3 format 
     */
    public static function format_json($tree) {
        $nodes = [];
        foreach ($tree as $key => $val) {
            $node = [
                'name' => $key,
                'value' => (int)$val['total'],
            ];
            if (isset($val['children'])) {
                $node['children'] = self::format_json($val['children']);
            }
            $nodes[] = $node;
        }
        return $nodes;
    }


    //  --------------------------------------------------------
    //  Delete
    //  --------------------------------------------------------


    /**
     * Delete log entries earlier than a given time; but preserve whole hours.
     *
     * @param int $expiryTime Epoch seconds 
     * @return void
     */
    public static function delete_before_epoch_time($expiryTime) {
        global $DB;

        $expiryDay = date('Ymd', $expiryTime);
        $expiryHour = date('H', $expiryTime);

        $DB->delete_records_select(
            $table = 'tool_excimer',
            $where = 'day < :expiry_day OR (day = :expiry_day AND hour < :expiry_hour)',
            $params = [
                'expiry_day' => $expiryDay,
                'expiry_hour' => $expiryHour,
            ]
        );
    }

}
