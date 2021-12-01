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

use tool_excimer\converter;
use tool_excimer\manager;
use tool_excimer\profile;

defined('MOODLE_INTERNAL') || die();

class tool_excimer_profile_testcase extends advanced_testcase {

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
     * @return ExcimerLog
     */
    protected function quick_log(int $iterations): \ExcimerLog {
        $prof = new \ExcimerProfiler();
        $prof->setPeriod(1);

        $x = 0;
        $prof->start();
        for ($i = 0; $i < $iterations; ++$i) {
            // Do some busy work.
            $this->busy_function1();
            $this->busy_function2();
        }
        $prof->stop();
        return $prof->flush();
    }

    /**
     * Tests the functionality to keep only the N slowest profiles.
     *
     * @throws dml_exception
     */
    public function test_n_slowest_kept(): void {
        $numtokeep = 5;
        set_config('excimernum_slowest', $numtokeep, 'tool_excimer');
        set_config('excimeranableauto', 1, 'tool_excimer');
        set_config('excimertrigger_ms', 0, 'tool_excimer');

        // Manual saves should have no impact, so chuck a few in o see if it gumms up the works.
        profile::save(self::quick_log(10), manager::REASON_MANUAL, 12345, 2.345);

        // Test number of profiles never exceed max allowed.
        for ($i = 1; $i < $numtokeep + 2; ++$i) {
            $expectednum = min($i, $numtokeep);
            $started = microtime(true);
            $log = $this->quick_log(5 * ($i + 100));
            manager::on_flush($log, $started);
            $this->assertEquals($expectednum, profile::get_num_auto_profiles());
        }

        profile::save(self::quick_log(10), manager::REASON_MANUAL, 2345, 2.456);

        // Test run that is faster than what's on there.
        $fastest = profile::get_fastest_auto_profile();
        $started = microtime(true);
        $log = $this->quick_log(5 * 10); // Should be quicker than any of the above.
        manager::on_flush($log, $started);
        $this->assertEquals($fastest->duration, profile::get_fastest_auto_profile()->duration);
        $this->assertEquals($numtokeep, profile::get_num_auto_profiles());

        profile::save(self::quick_log(10), manager::REASON_MANUAL, 65432, 0.0012);

        // Test run that is slower than what's on there.
        $fastest = profile::get_fastest_auto_profile();
        $started = microtime(true);
        $log = $this->quick_log(5 * 1000); // Should be slower than any of the above.
        manager::on_flush($log, $started);
        $this->assertGreaterThan($fastest->duration, profile::get_fastest_auto_profile()->duration);
        $this->assertEquals($numtokeep, profile::get_num_auto_profiles());
    }

    /**
     * Tests profile::save()
     *
     * @throws dml_exception
     */
    public function test_save(): void {
        global $DB;

        $log = self::quick_log(150);
        $flamedata = trim(str_replace("\n;", "\n", $log->formatCollapsed()));
        $flamedatad3 = json_encode(converter::process($flamedata));
        $reason = manager::REASON_AUTO;
        $created = 56;
        $duration = 0.123;

        $id = profile::save($log, $reason, $created, $duration);
        $record = $DB->get_record('tool_excimer_profiles', [ 'id' => $id ]);

        $this->assertEquals($id, $record->id);
        $this->assertEquals($reason, $record->reason);
        $this->assertEquals(profile::SCRIPTTYPE_CLI, $record->scripttype);
        $this->assertEquals($created, $record->created);
        $this->assertEquals($duration, $record->duration);
        $this->assertEquals($flamedata, $record->flamedata);
        $this->assertEquals($flamedatad3, $record->flamedatad3);

        $log = self::quick_log(1500);
        $flamedata = trim(str_replace("\n;", "\n", $log->formatCollapsed()));
        $flamedatad3 = json_encode(converter::process($flamedata));
        $reason = manager::REASON_AUTO;
        $created = 120;
        $duration = 0.456;

        $id = profile::save($log, $reason, $created, $duration);
        $record = $DB->get_record('tool_excimer_profiles', [ 'id' => $id ]);

        $this->assertEquals($id, $record->id);
        $this->assertEquals($reason, $record->reason);
        $this->assertEquals(profile::SCRIPTTYPE_CLI, $record->scripttype);
        $this->assertEquals($created, $record->created);
        $this->assertEquals($duration, $record->duration);
        $this->assertEquals($flamedata, $record->flamedata);
        $this->assertEquals($flamedatad3, $record->flamedatad3);
    }

    /**
     * Tests profile::get_num_auto_profiles()
     *
     * @throws dml_exception
     */
    public function test_get_num_auto_profiles(): void {
        $log = self::quick_log(10);
        $created = 56;
        $duration = 0.123;

        for ($i = 1; $i < 6; ++$i) {
            profile::save($log, manager::REASON_AUTO, $created, $duration);
            profile::save($log, manager::REASON_MANUAL, $created, $duration);
            $this->assertEquals($i, profile::get_num_auto_profiles());
        }
    }

    /**
     * Tests profile::get_fastest_auto_profile()
     *
     * @throws dml_exception
     */
    public function test_get_fastest_auto_profile(): void {
        $log = self::quick_log(10);
        $times = [ 0.345, 0.234, 0.123, 0.456 ];
        $fastest = min($times);
        $fastmanual = 0.003;

        foreach ($times as $time) {
            profile::save($log, manager::REASON_AUTO, 12345, $time);
        }
        profile::save($log, manager::REASON_MANUAL, 12345, $fastmanual);

        $tocheck = profile::get_fastest_auto_profile();
        $this->assertEquals($fastest, $tocheck->duration);
    }

    /**
     * Tests profile::purge_fastest_auto_profiles()
     *
     * @throws dml_exception
     */
    public function test_purge_fastest_auto_profiles(): void {
        $log = self::quick_log(10);
        $times = [ 0.345, 0.234, 0.123, 0.456 ];
        $sortedtimes = $times;
        sort($sortedtimes);

        $fastmanual = 0.003;

        foreach ($times as $time) {
            profile::save($log, manager::REASON_AUTO, 12345, $time);
        }
        $manualid = profile::save($log, manager::REASON_MANUAL, 12345, $fastmanual);

        $tocheck = profile::get_fastest_auto_profile();
        $this->assertEquals($sortedtimes[0], $tocheck->duration);

        profile::purge_fastest_auto_profiles(1);
        $tocheck = profile::get_fastest_auto_profile();
        $this->assertEquals($sortedtimes[1], $tocheck->duration);

        profile::purge_fastest_auto_profiles(2);
        $tocheck = profile::get_fastest_auto_profile();
        $this->assertEquals($sortedtimes[3], $tocheck->duration);
    }

    public function test_purge_old_profiles(): void {
        global $DB;
        $log = self::quick_log(10);
        $times = [ 12345, 23456, 34567, 45678 ];
        $cutoff1 = 30000;
        $cutoff2 = 20000;
        $cutoff3 = 40000;

        $this->assertEquals(0, profile::get_num_profiles());
        $expectedcount = 0;
        foreach ($times as $time) {
            profile::save($log, manager::REASON_MANUAL, $time, 0.2);
            $this->assertEquals(++$expectedcount, profile::get_num_profiles());
        }

        profile::purge_profiles_before_epoch_time($cutoff1);
        $this->assertEquals(2, profile::get_num_profiles());
        profile::purge_profiles_before_epoch_time($cutoff2);
        $this->assertEquals(2, profile::get_num_profiles());
        profile::purge_profiles_before_epoch_time($cutoff3);
        $this->assertEquals(1, profile::get_num_profiles());
        profile::purge_profiles_before_epoch_time($cutoff1);
        $this->assertEquals(1, profile::get_num_profiles());
        $record = $DB->get_record("tool_excimer_profiles", []);
        $this->assertEquals($times[3], $record->created);
    }
}
