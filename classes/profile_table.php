<?php

namespace tool_excimer;

defined('MOODLE_INTERNAL') || die();

class profile_table extends \table_sql {
    function col_id($record) {
        return "<a href='?profileid=$record->id'>$record->id</a>";
    }

    function col_request($record) {
        return $record->request;
    }

    function col_created($record) {
        return date('Y-m-d H:i:s', $record->created);
    }
}
