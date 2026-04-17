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
 * Tests for profile_table column rendering.
 *
 * @package    tool_excimer
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_excimer\profile_table
 */
final class tool_excimer_profile_table_test extends excimer_testcase {
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
     * Regression test: col_request used undefined $title in pix_icon alt text.
     */
    public function test_col_request_locked_profile_lock_icon_alt(): void {
        global $DB, $PAGE;

        $this->setAdminUser();
        $node = new flamed3_node('root', 0);
        $id = $this->quick_save('/test/page.php', $node, profile::REASON_SLOW, 2.0);

        $profile = profile::get_record(['id' => $id]);
        $profile->update_lock('locked for review');
        $record = $DB->get_record('tool_excimer_profiles', ['id' => $id]);

        $table = new recent_profile_table('test_profile_table');
        $url = new \moodle_url('/admin/tool/excimer/slowest_web.php');
        $table->define_baseurl($url);
        $table->make_columns();
        $PAGE->set_url($url);

        $html = $table->col_request($record);

        $this->assertStringContainsString(get_string('locked', 'tool_excimer'), $html);
        $this->assertStringContainsString(get_string('locked_by', 'tool_excimer', 'Admin User'), $html);
    }
}
