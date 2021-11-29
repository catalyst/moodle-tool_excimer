<?php

defined('MOODLE_INTERNAL') || die;

function xmldb_tool_excimer_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // Automatically generated Moodle v3.11.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2021112900) {

        // Define table tool_excimer_flamegraph to be created.
        $table = new xmldb_table('tool_excimer_flamegraph');

        // Adding fields to table tool_excimer_flamegraph.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('request', XMLDB_TYPE_CHAR, '1000', null, XMLDB_NOTNULL, null, null);
        $table->add_field('created', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('flamedata', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('flamedatad3', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table tool_excimer_flamegraph.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table tool_excimer_flamegraph.
        $table->add_index('created', XMLDB_INDEX_NOTUNIQUE, ['created']);

        // Conditionally launch create table for tool_excimer_flamegraph.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Excimer savepoint reached.
        upgrade_plugin_savepoint(true, 2021112900, 'tool', 'excimer');
    }

    return true;
}
