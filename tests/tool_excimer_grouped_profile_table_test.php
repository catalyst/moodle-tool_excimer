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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . "/quick_save_trait.php");

/**
 * Tests for grouped_profile_table SQL generation.
 *
 * @package    tool_excimer
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_excimer\grouped_profile_table
 */
final class tool_excimer_grouped_profile_table_test extends excimer_testcase {
    use quick_save_trait;

    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        profile_helper::init();
        script_metadata::init();
    }

    /**
     * Test that the grouped script profile table query executes without SQL errors.
     *
     * Regression test: COUNT (CASE ...) with a space between COUNT and the
     * opening parenthesis caused "FUNCTION dbname.COUNT does not exist" on
     * MySQL servers without the IGNORE_SPACE sql_mode.
     */
    public function test_grouped_script_profile_table_query_executes(): void {
        $node = new flamed3_node('root', 0);

        $this->quick_save('/course/view.php?id=1', $node, profile::REASON_SLOW, 2.5);
        $this->quick_save('/course/view.php?id=2', $node, profile::REASON_SLOW, 1.0);
        $this->quick_save('/mod/assign/view.php?id=3', $node, profile::REASON_SLOW, 3.0, 12345, 'test lock');

        $table = new grouped_script_profile_table('test_grouped_script');
        $table->set_scripttypes([profile::SCRIPTTYPE_WEB, profile::SCRIPTTYPE_AJAX]);

        $url = new \moodle_url('/admin/tool/excimer/slowest_web.php');
        $table->set_url_path($url);
        $table->sortable(true, 'maxduration', SORT_DESC);
        $table->define_baseurl($url);
        $table->make_columns();

        $this->expectOutputRegex('/.+/s');
        $table->out(40, true);
    }

    /**
     * Test that the lockedcount aggregation in grouped tables works correctly.
     *
     * Verifies that the COUNT(CASE WHEN lockreason != '' ...) expression
     * correctly counts only profiles with a non-empty lock reason.
     */
    public function test_grouped_table_lockedcount(): void {
        global $DB;

        $node = new flamed3_node('root', 0);

        $this->quick_save('/test/page.php', $node, profile::REASON_SLOW, 1.0, 12345, '');
        $this->quick_save('/test/page.php', $node, profile::REASON_SLOW, 2.0, 12345, 'locked for review');
        $this->quick_save('/test/page.php', $node, profile::REASON_SLOW, 3.0, 12345, 'another reason');

        $records = $DB->get_records_sql(
            "SELECT scriptgroup,
                    COUNT(request) as requestcount,
                    COUNT(CASE WHEN lockreason != '' THEN 1 END) as lockedcount
               FROM {tool_excimer_profiles}
              WHERE scriptgroup IS NOT NULL
           GROUP BY scriptgroup",
            []
        );

        $this->assertCount(1, $records);
        $record = reset($records);
        $this->assertEquals(3, $record->requestcount);
        $this->assertEquals(2, $record->lockedcount);
    }
}
