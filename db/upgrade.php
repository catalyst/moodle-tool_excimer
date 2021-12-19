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
 * Strings for availability conditions options.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_tool_excimer_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Automatically generated Moodle v3.11.0 release upgrade line.
    // Put any upgrade step following this.
    if ($oldversion < 2021121500) {

        // Define field pathinfo to be added to tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('pathinfo', XMLDB_TYPE_CHAR, '256', null, XMLDB_NOTNULL, null, null, 'request');

        // Conditionally launch add field pathinfo.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2021121500, 'tool', 'excimer');
    }

    if ($oldversion < 2021121700) {
        // Changing precision of field method on table tool_excimer_profiles to (7).
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('method', XMLDB_TYPE_CHAR, '7', null, XMLDB_NOTNULL, null, null, 'scripttype');

        // Launch change of precision for field method.
        $dbman->change_field_precision($table, $field);

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2021121700, 'tool', 'excimer');
    }
    if ($oldversion < 2021122000) {
        $table = new xmldb_table('tool_excimer_profiles');

        // Add 'datasize' field - The size of the profile data in KB.
        $field = new xmldb_field('datasize', XMLDB_TYPE_INTEGER, '11', true, XMLDB_NOTNULL, null, 0, 'referer');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add 'numsamples' field - The number of samples taken.
        $field = new xmldb_field('numsamples', XMLDB_TYPE_INTEGER, '11', true, XMLDB_NOTNULL, null, 0, 'datasize');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('flamedata');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2021121600, 'tool', 'excimer');
    }

    return true;
}
