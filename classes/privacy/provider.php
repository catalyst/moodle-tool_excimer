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

namespace tool_excimer\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use tool_excimer\profile;

/**
 * Privacy classes for tool_excimer.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider,
                          \core_privacy\local\request\plugin\provider,
                          \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this system.
     *
     * @param   collection     $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'tool_excimer_profiles',
            [
                'userid' => 'field_userid',
                'useragent' => 'field_useragent',
                'request' => 'field_request',
                'pathinfo' => 'field_pathinfo',
                'parameters' => 'field_parameters',
                'referer' => 'field_referer',
                'usermodified' => 'field_usermodified',
                'lockreason' => 'field_lockreason',
                'timemodified' => 'field_timemodified',
            ],
            'privacy:metadata:tool_excimer_profiles'
        );
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int         $userid     The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                // Profile data.
                $list = [];
                $rows = $DB->get_records(profile::TABLE, ['userid' => $userid]);
                foreach ($rows as $row) {
                    $list[] = [
                        'userid' => $userid,
                        'useragent' => $row->useragent,
                        'request' => $row->request,
                        'pathinfo' => $row->pathinfo,
                        'parameters' => $row->parameters,
                        'referer' => $row->referer,
                    ];
                }
                // Lock data.
                $rows = $DB->get_records(profile::TABLE, ['usermodified' => $userid]);
                foreach ($rows as $row) {
                    // Ignore if we have no lock and usermodified is covered by the profile.
                    if (empty($row->lockreason) && $row->userid === $userid) {
                        continue;
                    }
                    $list[] = [
                        'usermodified' => $userid,
                        'lockreason' => $row->lockreason,
                        'timemodified' => $row->timemodified,
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:metadata:tool_excimer_profiles', 'tool_excimer')],
                    (object) $list
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context                 $context   The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->delete_records(profile::TABLE, []);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $DB->delete_records(profile::TABLE, ['userid' => $userid]);
                $DB->set_field(profile::TABLE, 'usermodified', 0, ['usermodified' => $userid]);
            }
        }
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $sql = "SELECT * FROM {tool_excimer_profiles}";
            $userlist->add_from_sql('userid', $sql, []);
            $userlist->add_from_sql('usermodified', $sql, []);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $users = $userlist->get_users();
            foreach ($users as $user) {
                $DB->delete_records(profile::TABLE, ['userid' => $user->id]);
                $DB->set_field(profile::TABLE, 'usermodified', 0, ['usermodified' => $user->id]);
            }
        }
    }
}
