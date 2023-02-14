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
class tool_excimer_cron_processor_test extends excimer_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests cron_processor::findtaskname().
     *
     * @covers \tool_excimer\cron_processor::findtaskname
     */
    public function test_findtaskname() {
        $processor = new cron_processor();
        $entry = $this->get_log_entry_stub(['c::a', 'b', 'c']);
        $taskname = $processor->findtaskname($entry);
        $this->assertNull($taskname);

        $entry = $this->get_log_entry_stub(['bud', 'cron_run_inner_scheduled_task', 'ced::execute']);
        $taskname = $processor->findtaskname($entry);
        $this->assertEquals('ced', $taskname);

        $entry = $this->get_log_entry_stub(['bud', 'cron_run_inner_scheduled_task', 'max']);
        $taskname = $processor->findtaskname($entry);
        $this->assertNull($taskname);
    }

    /**
     * Tests cron_processor::on_interval().
     *
     * @covers \tool_excimer\cron_processor::on_interval
     */
    public function test_on_interval() {
        global $DB;
        $this->preventResetByRollback();

        script_metadata::init();
        $processor = new cron_processor();
        $timer = new \ExcimerTimer();

        $started = 100.0;
        $period = 50.0;

        $processor->sampletime = $started;

        // Adding one sample.
        $profiler = $this->get_profiler_stub([
            ['c::a', 'b', 'c'],
        ], $period);
        $manager = $this->get_manager_stub($processor, $profiler, $timer, $started);

        $processor->on_interval($manager);

        $this->assertEquals($started + ($period * 1), $processor->sampletime);
        $this->assertNull($processor->tasksampleset);

        // Adding 3 more samples.
        $profiler = $this->get_profiler_stub([
            ['a', 'b'],
            ['cron_run_inner_scheduled_task', 'max::execute', 'read::john'],
            ['cron_run_inner_scheduled_task', 'max::execute'],
        ], $period, ($period * 1));

        $manager = $this->get_manager_stub($processor, $profiler, $timer, $started);
        $processor->on_interval($manager);
        $this->assertEquals($started + ($period * 4), $processor->sampletime);

        // There should be a current sample set being recorded.
        $this->assertNotNull($processor->tasksampleset);
        $this->assertEquals($started + ($period * 2), $processor->tasksampleset->starttime);
        $this->assertEquals(2, count($processor->tasksampleset->samples));

        // Adding four more samples. Should record two sample sets into the database.
        $profiler = $this->get_profiler_stub([
            ['cron_run_inner_scheduled_task', 'max::execute'],
            ['a', 'b'],
            ['cron_run_inner_adhoc_task', 'simle::execute'],
            ['a', 'b'],
        ], $period, ($period * 4));

        $manager = $this->get_manager_stub($processor, $profiler, $timer, $started);
        $processor->on_interval($manager);
        $this->assertEquals($started + ($period * 8), $processor->sampletime);

        // There should not be a current sample set.
        $this->assertNull($processor->tasksampleset);

        // Check to see if the tasks have been recorded.
        $records = array_values($DB->get_records(profile::TABLE, null, 'created'));

        $this->assertEquals(2, count($records));

        $this->assertEquals('max', $records[0]->request);
        $this->assertEquals($started + ($period * 2), $records[0]->created);
        $this->assertEquals($started + ($period * 5), $records[0]->finished);
        $this->assertEquals($period * 3, $records[0]->duration);
        $this->assertEquals(3, $records[0]->numsamples);

        $this->assertEquals('simle', $records[1]->request);
        $this->assertEquals($started + ($period * 6), $records[1]->created);
        $this->assertEquals($started + ($period * 7), $records[1]->finished);
        $this->assertEquals($period * 1, $records[1]->duration);
        $this->assertEquals(1, $records[1]->numsamples);
    }
}
