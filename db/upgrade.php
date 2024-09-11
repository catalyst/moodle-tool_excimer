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
 * Upgrade script for databases.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function to upgrade tool_excimer database
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
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

    if ($oldversion < 2022040802) {

        // Change field 'parameters' into a text field.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('parameters', XMLDB_TYPE_TEXT);

        if ($dbman->table_exists($table) && $dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2022040802, 'tool', 'excimer');
    }

    if ($oldversion < 2022041300) {

        // Define field memoryusagedatad3 to be added to tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('memoryusagedatad3', XMLDB_TYPE_BINARY, null, null, null, null, null, 'flamedatad3');

        // Conditionally launch add field memoryusagedatad3.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field memoryusagemax to be added to tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('memoryusagemax', XMLDB_TYPE_INTEGER, '11', null, null, null, null, 'timemodified');

        // Conditionally launch add field memoryusagemax.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2022041300, 'tool', 'excimer');
    }

    if ($oldversion < 2022041301) {

        // Define field samplerate to be added to tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('samplerate', XMLDB_TYPE_INTEGER, '11', null, null, null, null, 'memoryusagemax');

        // Conditionally launch add field samplerate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2022041301, 'tool', 'excimer');
    }

    if ($oldversion < 2022042000) {
        // Add field maxstackdepth.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('maxstackdepth', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, 0, 'userid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2022042000, 'tool', 'excimer');
    }

    if ($oldversion < 2022042700) {
        // Modify field maxstackdepth.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('maxstackdepth', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, 0, 'userid');

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        } else {
            $dbman->add_field($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2022042700, 'tool', 'excimer');
    }

    if ($oldversion < 2022050600) {
        // Modify field maxstackdepth.
        $table = new xmldb_table('tool_excimer_profiles');

        $field = new xmldb_field('memoryusagedatad3', XMLDB_TYPE_BINARY, 'medium', null, null, null, null, 'flamedatad3');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        } else {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('flamedatad3', XMLDB_TYPE_BINARY, 'medium', null, null, null, null, 'numsamples');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        } else {
            $dbman->add_field($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2022050600, 'tool', 'excimer');
    }

    if ($oldversion < 2022090100) {

        // Define field lockreason to be added to tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('lockreason', XMLDB_TYPE_TEXT, null, null, null, null, null, 'samplerate');

        // Conditionally launch add field lockreason.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2022090100, 'tool', 'excimer');
    }

    if ($oldversion < 2022091500) {

        // Define table tool_excimer_profile_groups to be created.
        $table = new xmldb_table('tool_excimer_page_groups');

        // Adding fields to table tool_excimer_profile_groups.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '256', null, XMLDB_NOTNULL, null);
        $table->add_field('month', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null);
        $table->add_field('fuzzycount', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('fuzzydurationcounts', XMLDB_TYPE_TEXT);
        $table->add_field('fuzzydurationsum', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, 0);

        // Adding keys to table tool_excimer_profile_groups.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for tool_excimer_profile_groups.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2022091500, 'tool', 'excimer');
    }

    if ($oldversion < 2023050800) {

        // Define field id to be added to tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Rename groupby to scriptgroup.
        $field = new xmldb_field('groupby', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'request');

        // Launch rename field scriptgroup.
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'scriptgroup');
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2023050800, 'tool', 'excimer');
    }

    if ($oldversion < 2023082900) {

        // Define field id to be changed in tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('referer', XMLDB_TYPE_TEXT, null, null, false);

        // Conditionally change the referer field.
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2023082900, 'tool', 'excimer');
    }

    if ($oldversion < 2024050700) {

        // Define field id to be added to tool_excimer_profiles.
        $table = new xmldb_table('tool_excimer_profiles');
        $field = new xmldb_field('lockheld', XMLDB_TYPE_NUMBER, '12, 6', null, null, null, null);

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field id to be added to tool_excimer_profiles.
        $field = new xmldb_field('lockwait', XMLDB_TYPE_NUMBER, '12, 6', null, null, null, null);

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field id to be added to tool_excimer_profiles.
        $field = new xmldb_field('lockwaiturl', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2024050700, 'tool', 'excimer');
    }

    if ($oldversion < 2024082301) {
        $exists = $DB->record_exists_select('tool_excimer_page_groups', $DB->sql_length('name') . ' > 255');
        if ($exists) {
            // Change precision of name to 255 so it can be used as an index.
            // First we need to drop a few edge cases that have a length of 256.
            $DB->delete_records_select('tool_excimer_page_groups', $DB->sql_length('name') . ' > 255');
        }

        // Check if there are any duplicates to be deleted by counting the
        // expected total against the number of records in the table.
        $expectedcount = $DB->count_records_sql('
            SELECT count(*) FROM (
                SELECT MIN(id) AS id
                  FROM mdl_tool_excimer_page_groups
                GROUP BY LOWER(name), month
            ) a
        ');
        $recordcount = $DB->count_records('tool_excimer_page_groups');
        $needsdedupe = $expectedcount !== $recordcount;

        if ($needsdedupe) {
            // Get ids to delete. This is still faster to query than handling
            // everything in a DELETE WHERE NOT IN on certain DBs.
            $removeids = $DB->get_fieldset_sql('
                SELECT id
                  FROM {tool_excimer_page_groups}
                 WHERE id NOT IN (
                     SELECT MIN(id)
                       FROM {tool_excimer_page_groups}
                      GROUP BY LOWER(name), month
                 )');

            // Remove the records in batch sizes of the following.
            // Batchsize is arbitrary.
            $batchsize = 1000;
            $chunks = array_chunk($removeids, $batchsize);

            foreach ($chunks as $chunk) {
                [$insql, $inparams] = $DB->get_in_or_equal($chunk);
                $DB->delete_records_select('tool_excimer_page_groups', "id $insql", $inparams);
            }
        }

        // Change precision of name to 255 so it can be used as an index.
        $table = new xmldb_table('tool_excimer_page_groups');
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'id');

        // Launch change of precision for field name.
        $dbman->change_field_precision($table, $field);

        // Define index pagegroup (unique) to be added to tool_excimer_page_groups.
        $table = new xmldb_table('tool_excimer_page_groups');
        $index = new xmldb_index('pagegroup', XMLDB_INDEX_UNIQUE, ['name', 'month']);

        // Conditionally launch add index pagegroup.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2024082301, 'tool', 'excimer');
    }

    return true;
}
