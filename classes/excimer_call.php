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

use ExcimerLog;
use ExcimerLogEntry;
use tool_excimer\excimer_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Manage excimer_call table
 *
 * @package   tool_excimer
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class excimer_call {

    /**
     * Save Excimer log to DB.
     *

CREATE TABLE mdl_tool_excimer (

    id              BIGSERIAL,
    day             INTEGER         NOT NULL,
    hour            SMALLINT        NOT NULL,
    graphpath       TEXT            NOT NULL,
    graphpathmd5    VARCHAR(32)     NOT NULL DEFAULT '',
    elapsed         NUMERIC(12,6)   NOT NULL,
    total           BIGINT          NOT NULL,
    profileid       BIGINT,
);

     *
     * @param ExcimerLog $log The iterable list of entries to be inserted
     * @param int $created The time this request was initiated (same as profile, if any)
     * @param int $profileid The profile ID (if any)
     *
     */
    public static function save_log_entries(ExcimerLog $log, $created, $profileid=null) {
        global $DB;
        $records = [];
        foreach ($log as $entry) {
            $day = (int)date('Ymd', $created);
            $hour = (int)date('H', $created);
            $graphpath = excimer_helper::get_graph_path($entry);
            $graphpathmd5 = md5($graphpath);
            $elapsed = $entry->getTimestamp();
            $total = $entry->getEventCount();
            $records[] = (object) [
                'day' => $day,
                'hour' => $hour,
                'graphpath' => $graphpath,
                'graphpathmd5' => $graphpathmd5,
                'elapsed' => $elapsed,
                'total' => $total,
                'profileid' => $profileid,
            ];
        };
        $DB->insert_records('tool_excimer_call', $records);
    }

    /**
     * Summarise data by day and hour
     *
     * @return array e.g. [20210621 => [20 => 3451, ...], ...]
     */
    public static function summarize() {
        global $DB;
        $sql = "
            SELECT day, hour, SUM(total)
              FROM {tool_excimer_call}
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
        $sql = "SELECT COUNT(DISTINCT graphpathmd5) FROM {tool_excimer_call}";
        return $DB->get_field_sql($sql);
    }
    /**
     * Select log data by profileid
     *
     * @param int $profileid
     * @return iterable Result set for query
     */
    public static function get_profile_data($profileid) {
        global $DB;
        $safeprofileid = (int)$profileid;
        $sql = "
              SELECT  graphpath,
                      SUM(total) AS total,
                      SUM(elapsed) AS elapsed
                FROM  {tool_excimer_call}
               WHERE  profileid = $safeprofileid
            GROUP BY  graphpath
            ORDER BY  graphpath
        ";
        return $DB->get_recordset_sql($sql);
    }

    /**
     * Select log data by optional day and hour params.
     *
     * @param int $day e.g. 20160621
     * @param int $hour e.g. 20
     * @return iterable Result set for query
     */
    public static function get_time_based_data($day=null, $hour=null) {
        global $DB;

        $safeday = (int)$day;
        $safehour = (int)$hour;
        $clauses = ['1 = 1'];
        if ($safeday > 0) {
            $clauses[] = "day = $safeday";
        }
        if ($safehour > 0) {
            $clauses[] = "hour = $safehour";
        }
        $condition = join(' AND ', $clauses);

        $sql = "
              SELECT  graphpath,
                      SUM(total) AS total,
                      SUM(elapsed) AS elapsed
                FROM  {tool_excimer_call}
               WHERE  $condition
            GROUP BY  graphpath
            ORDER BY  graphpath
        ";

        return $DB->get_recordset_sql($sql);
    }

    /**
     * Turn graphpaths from excimer_call entries into D3 format recursive JSON
     * for flame graphs.
     *
     * @param int $day e.g. 20210621
     * @param int $hour e.g. 20
     * @return string JSON {'name': 'root', 'value': value', 'children': [...]}
     */
    public static function tree_data($calls) {

        $tree = self::build_tree($calls);
        $data = self::format_json($tree); // Ready for D3 json.
        $getvalues = function($item) {
            return (int)$item['value'];
        };
        $total = array_sum(array_map($getvalues, $data));
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

    /**
     * Delete log entries earlier than a given time; but preserve whole hours.
     *
     * @param int $expirytime Epoch seconds
     * @return void
     */
    public static function delete_before_epoch_time($expirytime) {
        global $DB;

        $expiryday = date('Ymd', $expirytime);
        $expiryhour = date('H', $expirytime);

        $DB->delete_records_select(
            $table = 'tool_excimer_call',
            $where = 'day < :expiry_day1 OR (day = :expiry_day2 AND hour < :expiry_hour)',
            $params = [
                'expiry_day1' => $expiryday,
                'expiry_day2' => $expiryday,
                'expiry_hour' => $expiryhour,
            ]
        );
    }

}
