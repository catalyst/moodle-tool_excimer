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
use tool_excimer\excimer_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Manage tool_excimer_profile table
 *
 * @package   tool_excimer
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class excimer_profile {

    /**
     * Save Excimer log to tool_excimer_call, assuming profile has been created
     * if needed. This should only be called if excimerenable_profiling is set.
     *
     * CREATE TABLE mdl_tool_excimer_profile (
     *
     *     id              BIGSERIAL,
     *     type            VARCHAR(3)      NOT NULL DEFAULT '',
     *     created         BIGINT          NOT NULL,
     *     duration        NUMERIC(12,6)   NOT NULL,
     *     request         VARCHAR(256)    NOT NULL DEFAULT '',
     *     parameters      VARCHAR(256)    NOT NULL DEFAULT '',
     *     responsecode    SMALLINT        NOT NULL DEFAULT 0,
     *     explanation     VARCHAR(256)    NOT NULL DEFAULT '',
     * );
     *
     */

    public static function conditional_save($started, $stopped) {
        global $DB;

        $request = $_SERVER['PHP_SELF'] ?? 'UNKNOWN';

        if (self::is_cli()) {
            // Web request: split CRON tasks later.
            return;
            //  $type = 'cli';
            //  $parameters = join(' ', array_slice($argv, 1));
        } else {
            // Web request: split API calls later.
            $type = 'web';
            $parameters = $_SERVER['QUERY_STRING'];
        }

        // Decide if there is any reason for saving this profile.
        $explanations = [];

        // Was the script too slow?
        $duration = round($stopped - $started, 6);
        $minduration = round((int)get_config('tool_excimer', 'excimertrigger_ms') / 1000, 6);
        if ($duration > $minduration) {
            $explanations[] = "Slower than $minduration\s ($duration\s)";
        }

        // Add extra checks here...
        // Did we match a query string? (And not a NOT-match string?)
        // Are we following a specific user?

        // Save if necessary.
        if (count($explanations) > 0) {
            $record = (object) [
                'created' => (int)$started,
                'duration' => $duration,
                'type' => $type,
                'explanation' => join(', ', $explanations),
                'request' => $request,
                'parameters' => $parameters,
            ];
            try {
                $id = $DB->insert_record('tool_excimer_profile', $record);
            } catch (\Exception $e) {
                // An exception would be thrown here if we were running
                // uninstall, since the plugin tables would have been deleted.
                debugging('Insert failed for tool_excimer_profile; table missing?');
            }
            return $id;
        } else {
            return null;
        }
    }

    public static function is_cli() {
        return php_sapi_name() === 'cli';
    }

    /**
     * Listing of latest profile data
     *
     * @return array e.g. [20210621 => [20 => 3451, ...], ...]
     */
    public static function listing($type='all', $limit=500) {
        global $DB;
        $sql = "
            SELECT *
              FROM {tool_excimer_profile}
          ORDER BY created DESC
        ";
        return $DB->get_recordset_sql($sql);
    }

    /**
     * Count unique graphpaths in table (use MD5 shortcut for comparison).
     *
     * @return int
     */
    public static function count_profiles() {
        global $DB;
        $sql = "SELECT COUNT(*) FROM {tool_excimer_profile}";
        return $DB->get_field_sql($sql);
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
            $table = 'tool_excimer_profile',
            $where = 'day < :expiry_day1 OR (day = :expiry_day2 AND hour < :expiry_hour)',
            $params = [
                'expiry_day1' => $expiryday,
                'expiry_day2' => $expiryday,
                'expiry_hour' => $expiryhour,
            ]
        );
    }

}
