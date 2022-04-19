<?php
// This file is part of Moodle - http://moodle.org/  <--change
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

/**
 * Defines names of plugin types and some strings used at the plugin managment
 *
 * @package    tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  2022 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_excimer_profile_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Busy function to fill out profiling
     */
    protected function busy_function1() {
        usleep(100);
    }

    /**
     * Busy function to fill out profiling
     */
    protected function busy_function2() {
        usleep(100);
    }

    /**
     * Quick & dirty profile generator
     *
     * @param int $iterations More iterations means more time is consumed.
     * @return \ExcimerLog
     */
    protected function quick_log(int $iterations): \ExcimerLog {
        $prof = new \ExcimerProfiler();
        $prof->setPeriod(1);
        $prof->start();
        for ($i = 0; $i < $iterations; ++$i) {
            // Do some busy work.
            $this->busy_function1();
            $this->busy_function2();
        }
        $prof->stop();
        return $prof->flush();
    }

    public function quick_save(string $request, flamed3_node $node, int $reason, float $duration, int $created = 12345): int {
        $profile = new profile();
        $profile->add_env($request);
        $profile->set('reason', $reason);
        $profile->set('flamedatad3', $node);
        $profile->set('created', $created);
        $profile->set('duration', $duration);
        $profile->set('finished', $created + 2);
        return $profile->save_record();
    }

    public function test_set_flamedata(): void {
        $profile = new profile();
        $log = $this->quick_log(10);
        $sampleset = new sample_set('name', 0);
        $sampleset->add_many_samples($log);
        $node = flamed3_node::from_excimer_log_entries($sampleset->samples);
        $nodejson = json_encode($node);
        $compressed = gzcompress($nodejson);
        $size = strlen($compressed);

        $profile->set('flamedatad3', $node);
        $profile->set('numsamples', $sampleset->count());

        $this->assertEquals($nodejson, $profile->get_flamedatad3json());
        $this->assertEquals($size, $profile->get('datasize'));
        $this->assertEquals($node->value, $profile->get('numsamples'));
    }

    public function test_save(): void {
        global $DB;
        $this->preventResetByRollback();

        $log = $this->quick_log(150); // TODO change to use stubs.

        $sampleset = new sample_set('name', 0);
        $sampleset->add_many_samples($log);

        $node = flamed3_node::from_excimer_log_entries($sampleset->samples);
        $flamedatad3json = json_encode($node);
        $numsamples = $node->value;
        $datasize = strlen(gzcompress($flamedatad3json));
        $reason = profile::REASON_SLOW;
        $created = 56;
        $duration = 1.123;
        $finished = 57;
        $request = 'mock';

        $profile = new profile();
        $profile->add_env($request);
        $profile->set('reason', $reason);
        $profile->set('created', $created);
        $profile->set('duration', $duration);
        $profile->set('finished', $finished);
        $profile->set('flamedatad3', $node);
        $profile->set('numsamples', $sampleset->count());

        $id = $profile->save_record();
        $record = $DB->get_record(profile::TABLE, [ 'id' => $id ]);

        $this->assertEquals($id, $record->id);
        $this->assertEquals($reason, $record->reason);
        $this->assertEquals(profile::SCRIPTTYPE_CLI, $record->scripttype);
        $this->assertEquals($created, $record->created);
        $this->assertEquals($duration, $record->duration);
        $this->assertEquals($flamedatad3json, gzuncompress($record->flamedatad3));
        $this->assertEquals($numsamples, $record->numsamples);
        $this->assertEquals($datasize, $record->datasize);
        $this->assertEquals(getmypid(), $record->pid);
    }

    public function test_partial_save() {
        $this->preventResetByRollback();

        $log = $this->quick_log(1); // TODO change to use stubs.
        $node = flamed3_node::from_excimer_log_entries($log);
        $flamedatad3json = json_encode($node);
        $reason = profile::REASON_SLOW;
        $created = 56;
        $duration = 1.123;
        $request = 'mock';

        $profile = new profile();
        $profile->add_env($request);
        $profile->set('reason', $reason);
        $profile->set('created', $created);
        $profile->set('duration', $duration);
        $profile->set('finished', 0);
        $profile->set('flamedatad3', $node);
        $id = $profile->save_record();

        $record = new profile($id);
        $this->assertEquals($id, $record->get('id'));
        $this->assertEquals($reason, $record->get('reason'));
        $this->assertEquals(profile::SCRIPTTYPE_CLI, $record->get('scripttype'));
        $this->assertEquals($created, $record->get('created'));
        $this->assertEquals($duration, $record->get('duration'));
        $this->assertEquals(0, $record->get('finished'));
        $this->assertEquals($flamedatad3json, $record->get_flamedatad3json());
        $this->assertEquals(getmypid(), $record->get('pid'));

        $log = $this->quick_log(2);
        $node = flamed3_node::from_excimer_log_entries($log);
        $flamedatad3json = json_encode($node);
        $duration = 2.123;
        $finished = 58;

        $profile->set('duration', $duration);
        $profile->set('finished', $finished);
        $profile->set('flamedatad3', $node);
        $id2 = $profile->save_record();
        $this->assertEquals($id, $id2);

        $record = new profile($id);
        $this->assertEquals($id, $record->get('id'));
        $this->assertEquals($request, $record->get('request'));
        $this->assertEquals($reason, $record->get('reason'));
        $this->assertEquals(profile::SCRIPTTYPE_CLI, $record->get('scripttype'));
        $this->assertEquals($created, $record->get('created'));
        $this->assertEquals($duration, $record->get('duration'));
        $this->assertEquals($finished, $record->get('finished'));
        $this->assertEquals($flamedatad3json, $record->get_flamedatad3json());
        $this->assertEquals(getmypid(), $record->get('pid'));
    }

    public function test_reasons_are_being_stored(): void {
        global $DB;
        $this->preventResetByRollback();

        // Initialise the logs object.
        $log = $this->quick_log(0);

        // Non-auto saves should have no impact, so chuck a few in to see if it gums up the works.
        $allthereasons = 0;
        foreach (profile::REASONS as $reason) {
            $allthereasons |= $reason;
        }
        $id = $this->quick_save('mock', flamed3_node::from_excimer_log_entries($log), $allthereasons, 2.345);
        $record = $DB->get_record(profile::TABLE, ['id' => $id]);

        // Fetch profile from DB and confirm it matches for all the reasons.
        $recordedreason = (int) $record->reason;
        foreach (profile::REASONS as $reason) {
            $this->assertTrue((bool) ($recordedreason & $reason));
        }
    }
}
