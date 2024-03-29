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
 * Delete profiles.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\profile;
use tool_excimer\profile_helper;

require_once(__DIR__ . '/../../../config.php');

require_login(null, false);
require_capability('moodle/site:config', context_system::instance());

$returnurl = optional_param('returnurl', get_local_referer(false), PARAM_LOCALURL);
$deleteall = optional_param('deleteall', 0, PARAM_BOOL);
$deleteid = optional_param('deleteid', 0, PARAM_INT);

$filter = optional_param('filter', 0, PARAM_TEXT);

require_sesskey();

// Prepare the cache instance.
$cache = \cache::make('tool_excimer', 'request_metadata');

// Delete all profiles.
if ($deleteall) {
    // Clears all profile metadata caches.
    $cache->purge();

    // Delete all profile records.
    $isemptysql = $DB->sql_isempty(profile::TABLE, 'lockreason', true, true);
    $DB->delete_records_select(profile::TABLE, $isemptysql);
    redirect($returnurl, get_string('allprofilesdeleted', 'tool_excimer'));
}

// Delete profile specified by an ID.
if ($deleteid) {
    // Clears the profile metadata cache affected by this record deletion.
    $conditions = ['id' => $deleteid];
    $profile = $DB->get_record(profile::TABLE, $conditions, 'request, reason, lockreason');
    if (empty($profile->lockreason)) {
        $cache->delete($profile->request);
        $cache->delete(profile_helper::ALL_GROUP_CACHE_KEY);
        // Deletes the profile record.
        $DB->delete_records(profile::TABLE, $conditions);
    }
    redirect($returnurl, get_string('profiledeleted', 'tool_excimer'));
}

// Delete profiles according to a filter value.
if ($filter) {
    $filtervalue = json_decode($filter, true);
    if (!is_null($filtervalue)) {
        // Clears the profile metadata caches affected by this filter.
        $requests = $DB->get_records(profile::TABLE, $filtervalue, '', 'DISTINCT request');
        $reasons = $DB->get_records(profile::TABLE, $filtervalue, '', 'DISTINCT reason');

        // Clears the request_metadata cache for the specific request and
        // affected reasons.
        if (!empty($requests)) {
            $requests = array_keys($requests);
            $requests[] = profile_helper::ALL_GROUP_CACHE_KEY;
            $cache->delete_many($requests);
        }

        $select = [];
        foreach ($filtervalue as $key => $value) {
            $select[] = $DB->sql_equal($key, ":$key");
        }
        $select[] = $DB->sql_isempty(profile::TABLE, 'lockreason', true, true);
        // Deletes affected profile records.
        $DB->delete_records_select(profile::TABLE, implode(' AND ', $select), $filtervalue);
        redirect($returnurl, get_string('profilesdeleted', 'tool_excimer'));
    }
}

// Universal graceful fallback.
redirect($returnurl);
