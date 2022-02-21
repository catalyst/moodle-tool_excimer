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

require_once(__DIR__ . "/excimer_testcase.php"); // This is needed. File will not be automatically included.

/**
 * Units tests for the manager class.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_excimer_cron_manager_test extends excimer_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests cron_manager::findtastname().
     */
    public function test_findtaskname(): void {
        $entry = $this->get_log_entry_stub(['c::a', 'b', 'c']);
        $taskname = cron_manager::findtaskname($entry);
        $this->assertNull($taskname);

        $entry = $this->get_log_entry_stub(['bud', 'cron_run_inner_scheduled_task', 'ced::execute']);
        $taskname = cron_manager::findtaskname($entry);
        $this->assertEquals('ced', $taskname);

        $entry = $this->get_log_entry_stub(['bud', 'cron_run_inner_scheduled_task', 'max']);
        $taskname = cron_manager::findtaskname($entry);
        $this->assertNull($taskname);
    }

    /**
     * Tests cron_manager::on_interval().
     */
    public function test_on_interval(): void {
        global $DB;
        $this->preventResetByRollback();
        set_config('trigger_ms', 2, 'tool_excimer'); // Should capture anything at least 1ms slow.

        $started = 100.0;
        $period = 50.0;

        // Adding one sample.
        $profiler = $this->get_profiler_stub([
            ['c::a', 'b', 'c'],
        ], $period);
        cron_manager::on_interval($profiler, $started);

        $this->assertEquals($started + ($period * 1), cron_manager::$sampletime);
        $this->assertNull(cron_manager::$currenttask);

        // Adding 3 more samples.
        $profiler = $this->get_profiler_stub([
            ['a', 'b'],
            ['cron_run_inner_scheduled_task', 'max::execute', 'read::john'],
            ['cron_run_inner_scheduled_task', 'max::execute'],
        ], $period);
        cron_manager::on_interval($profiler, cron_manager::$sampletime);
        $this->assertEquals($started + ($period * 4), cron_manager::$sampletime);

        // There should be a current sample set being recorded.
        $this->assertNotNull(cron_manager::$currenttask);
        $this->assertEquals($started + ($period * 2), cron_manager::$currenttask->starttime);
        $this->assertEquals(2, count(cron_manager::$currenttask->samples));

        // Adding four more samples. Should record two sample sets into the database.
        $profiler = $this->get_profiler_stub([
            ['cron_run_inner_scheduled_task', 'max::execute'],
            ['a', 'b'],
            ['cron_run_inner_adhoc_task', 'simle::execute'],
            ['a', 'b'],
        ], $period);
        cron_manager::on_interval($profiler,  cron_manager::$sampletime);
        $this->assertEquals($started + ($period * 8), cron_manager::$sampletime);

        // There should not be a current sample set.
        $this->assertNull(cron_manager::$currenttask);

        // Check to see if the tasks have been recorded.
        $records = array_values($DB->get_records(profile::TABLE, null, 'created'));

        $this->assertEquals(2, count($records));

        $this->assertEquals('max', $records[0]->request);
        $this->assertEquals($started + ($period * 2), $records[0]->created);
        $this->assertEquals($started + ($period * 5), $records[0]->finished);
        $this->assertEquals($period * 3, $records[0]->duration);

        $this->assertEquals('simle', $records[1]->request);
        $this->assertEquals($started + ($period * 6), $records[1]->created);
        $this->assertEquals($started + ($period * 7), $records[1]->finished);
        $this->assertEquals($period * 1, $records[1]->duration);
    }
}

