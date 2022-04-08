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

        upgrade_plugin_savepoint(true, 2021122000, 'tool', 'excimer');
    }

    if ($oldversion < 2021122001) {

        // Define field contenttypecategory to be added to tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('contenttypecategory', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'flamedatad3');

        // Conditionally launch add field contenttypecategory.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field contenttypekey to be added to tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('contenttypekey', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'contenttypecategory');

        // Conditionally launch add field contenttypekey.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field contenttypevalue to be added to tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('contenttypevalue', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'contenttypekey');

        // Conditionally launch add field contenttypevalue.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2021122001, 'tool', 'excimer');
    }

    if ($oldversion < 2021122200) {
        $table = new xmldb_table('tool_excimer_profiles');

        // Add 'finished' field - The timestamp for finishind. If zero, then the run did not finish.
        $field = new xmldb_field('finished', XMLDB_TYPE_INTEGER, '11', true, XMLDB_NOTNULL, null, 0, 'created');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2021122200, 'tool', 'excimer');
    }

    if ($oldversion < 2021122400) {
        $table = new xmldb_table('tool_excimer_profiles');

        // Add 'pid' field - Process ID.
        $field = new xmldb_field('pid', XMLDB_TYPE_INTEGER, '11', true, XMLDB_NOTNULL, null, 0, 'referer');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add 'hostname' field - The hostname of the server.
        $field = new xmldb_field('hostname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 0, 'pid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add 'useragent' field - The hostname of the server.
        $field = new xmldb_field('useragent', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 0, 'pid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add 'versionhash' field - The hash of versions of plugins.
        $field = new xmldb_field('versionhash', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 0, 'pid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2021122400, 'tool', 'excimer');
    }

    if ($oldversion < 2021122900) {

        // Changing type of field flamedatad3 on table tool_excimer_profiles to binary.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('flamedatad3', XMLDB_TYPE_BINARY, null, null, null, null, null, 'numsamples');

        // A text field cannot be directly converted into a byte field.
        $profiles = $DB->get_records('tool_excimer_profiles', null, '', 'id, flamedatad3');

        // Launch change of type for field flamedatad3.
        $dbman->drop_field($table, $field);
        $dbman->add_field($table, $field);

        // We need to convert any existing records to store the compressed data.
        foreach ($profiles as $profile) {
            $profile->flamedatad3 = gzcompress($profile->flamedatad3);
            $profile->datasize = strlen($profile->flamedatad3);
            $DB->update_record('tool_excimer_profiles', $profile);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2021122900, 'tool', 'excimer');
    }

    if ($oldversion < 2021123100) {

        // Define field dbreads to be added to tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('dbreads', XMLDB_TYPE_INTEGER, '11', null, null, null, null, 'contenttypevalue');

        // Conditionally launch add field dbreads.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field dbwrites to be added to tool_excimer_profiles.
        $field = new xmldb_field('dbwrites', XMLDB_TYPE_INTEGER, '11', null, null, null, null, 'dbreads');

        // Conditionally launch add field dbwrites.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2021123100, 'tool', 'excimer');
    }

    if ($oldversion < 2022010400) {

        // Define field dbreads to be added to tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('dbreplicareads', XMLDB_TYPE_INTEGER, '11', null, null, null, null, 'dbwrites');

        // Conditionally launch add field dbreads.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2022010400, 'tool', 'excimer');
    }

    if ($oldversion < 2022011300) {

        // Define field usermodified to be added to tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, 0, 'dbreplicareads');

        // Conditionally launch add field usermodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, 0, 'usermodified');

        // Conditionally launch add field usermodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, 0, 'timecreated');

        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2022011300, 'tool', 'excimer');
    }

    if ($oldversion < 2022011400) {

        // Define field groupby to be added to tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('groupby', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'request');

        // A text field cannot be directly converted into a byte field.
        $profiles = $DB->get_records('tool_excimer_profiles', null, '', 'id, request');

        // Conditionally launch add field groupby.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // We set groupby to be the same as request as the initial default.
        foreach ($profiles as $profile) {
            $profile->groupby = $profile->request;
            $DB->update_record('tool_excimer_profiles', $profile);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2022011400, 'tool', 'excimer');
    }

    if ($oldversion < 2022040801) {

        // Change field 'parameters' into a text field.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('parameters', XMLDB_TYPE_TEXT);

        $dbman->change_field_type($table, $field);

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2022040801, 'tool', 'excimer');
    }

    return true;
}
