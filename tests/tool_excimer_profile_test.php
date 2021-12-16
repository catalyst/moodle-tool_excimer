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

/**
 * Tests the profile storage.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
        global $DB;

        $log = $this->quick_log(10);

        $times = [ 0.345, 0.234, 0.123, 0.456, 0.4, 0.5, 0.88, 0.1, 0.14, 0.22 ];
        $sortedtimes = $times;
        sort($sortedtimes);
        $this->assertGreaterThan($sortedtimes[0], $sortedtimes[1]); // Sanity check.

        // Non-auto saves should have no impact, so chuck a few in to see if it gums up the works.
        profile::save($log, manager::REASON_MANUAL, 12345, 2.345);
        profile::save($log, manager::REASON_FLAMEALL, 12345, 0.104);

        foreach ($times as $time) {
            profile::save($log, manager::REASON_AUTO, 12345, $time);
        }

        profile::save($log, manager::REASON_MANUAL, 12345, 0.001);

        $this->assertEquals(count($times) + 3, $DB->count_records('tool_excimer_profiles'));

        // Should remove a few profiles.
        $numtokeep = 8;
        profile::purge_fastest($numtokeep);
        $this->assertEquals($numtokeep + 3, $DB->count_records('tool_excimer_profiles'));
        $sortedtimes = array_slice($sortedtimes, -$numtokeep);
        $this->assertEquals($sortedtimes[0],
                $DB->get_field_sql("select min(duration) from {tool_excimer_profiles} where reason = ?", [manager::REASON_AUTO]));

        // Should remove a few more profiles.
        $numtokeep = 5;
        profile::purge_fastest($numtokeep);
        $this->assertEquals($numtokeep + 3, $DB->count_records('tool_excimer_profiles'));
        $sortedtimes = array_slice($sortedtimes, -$numtokeep);
        $this->assertEquals($sortedtimes[0],
                $DB->get_field_sql("select min(duration) from {tool_excimer_profiles} where reason = ?", [manager::REASON_AUTO]));

        // Should remove no profiles.
        $numtokeepnew = 9;
        profile::purge_fastest($numtokeepnew);
        $this->assertEquals($numtokeep + 3, $DB->count_records('tool_excimer_profiles'));
        $this->assertEquals($sortedtimes[0],
                $DB->get_field_sql("select min(duration) from {tool_excimer_profiles} where reason = ?", [manager::REASON_AUTO]));
    }

    /**
     * Tests the functionality to keep only the N slowest profiles for each page.
     *
     * @throws dml_exception
     */
    public function test_n_slowest_kept_per_page(): void {
        global $DB, $SCRIPT;

        $log = $this->quick_log(10);

        $times = [ 0.345, 0.234, 0.123, 0.456, 0.4, 0.5, 0.88, 0.1, 0.14, 0.22, 0.111, 0.9 ];
        $reqnames = [ 'a', 'b', 'c', 'a', 'd', 'a', 'a', 'c', 'd', 'c', 'c', 'c' ];
        $sortedtimes = $times;
        sort($sortedtimes);
        $this->assertGreaterThan($sortedtimes[0], $sortedtimes[1]); // Sanity check.

        // Non-auto saves should have no impact, so chuck a few in to see if it gums up the works.
        $SCRIPT = 'a';
        profile::save($log, manager::REASON_MANUAL, 12345, 2.345);
        $SCRIPT = 'b';
        profile::save($log, manager::REASON_FLAMEALL, 12345, 0.104);

        for ($i = 0; $i < count($times); ++$i) {
            $SCRIPT = $reqnames[$i];
            profile::save($log, manager::REASON_AUTO, 12345, $times[$i]);
        }

        $SCRIPT = 'c';
        profile::save($log, manager::REASON_MANUAL, 12345, 0.001);

        $this->assertEquals(count($times) + 3, $DB->count_records('tool_excimer_profiles'));

        // Should remove a few profiles.
        $numtokeep = 3;
        $expectedreqcount = [ 'a' => 3, 'b' => 1, 'c' => 3, 'd' => 2 ];
        $expectedfastest = [ 'a' => 0.456, 'b' => 0.234, 'c' => 0.123, 'd' => 0.14 ];
        profile::purge_fastest_by_page($numtokeep);
        $this->assertEquals(array_sum($expectedreqcount) + 3, $DB->count_records('tool_excimer_profiles'));
        $records = $DB->get_records_sql(
            "SELECT request, COUNT(*) AS c, MIN(duration) AS m
               FROM {tool_excimer_profiles}
              WHERE reason = ?
           GROUP BY request",
            [manager::REASON_AUTO]
        );

        foreach ($records as $i => $record) {
            $this->assertEquals($expectedreqcount[$i], $record->c);
            $this->assertEquals($expectedfastest[$i], $record->m);
        }
    }

    /**
     * Tests profile::save()
     *
     * @throws dml_exception
     */
    public function test_save(): void {
        global $DB, $CFG;

        $log = $this->quick_log(150);
        $flamedata = trim(str_replace("\n;", "\n", $log->formatCollapsed()));
        // Remove full pathing to dirroot and only keep pathing from site root (non-issue in most sane cases).
        $flamedata = str_replace($CFG->dirroot . DIRECTORY_SEPARATOR, '', $flamedata);
        $flamedatad3 = converter::process($flamedata);
        $flamedatad3json = json_encode($flamedatad3);
        $numsamples = $flamedatad3['value'];
        $datasize = strlen($flamedatad3json);
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
        $this->assertEquals($flamedatad3json, $record->flamedatad3);
        $this->assertEquals($numsamples, $record->numsamples);
        $this->assertEquals($datasize, $record->datasize);

        $log = $this->quick_log(1500);
        $flamedata = trim(str_replace("\n;", "\n", $log->formatCollapsed()));
        // Remove full pathing to dirroot and only keep pathing from site root (non-issue in most sane cases).
        $flamedata = str_replace($CFG->dirroot . DIRECTORY_SEPARATOR, '', $flamedata);
        $flamedatad3 = converter::process($flamedata);
        $flamedatad3json = json_encode($flamedatad3);
        $numsamples = $flamedatad3['value'];
        $datasize = strlen($flamedatad3json);
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
        $this->assertEquals($flamedatad3json, $record->flamedatad3);
        $this->assertEquals($numsamples, $record->numsamples);
        $this->assertEquals($datasize, $record->datasize);
    }

    /**
     * Tests the expiry of profiles.
     *
     * @throws dml_exception
     */
    public function test_purge_old_profiles(): void {
        global $DB;
        $log = $this->quick_log(10);
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

    /**
     * Test the is_profiling test.
     *
     * @throws dml_exception
     */
    public function test_is_profiling(): void {
        $this->assertFalse(manager::is_profiling());

        $_GET[manager::MANUAL_PARAM_NAME] = 1;
        $this->assertTrue(manager::is_profiling());

        unset($_GET[manager::MANUAL_PARAM_NAME]);
        $this->assertFalse(manager::is_profiling());

        $_GET[manager::FLAME_ON_PARAM_NAME] = 1;
        $this->assertTrue(manager::is_profiling());

        unset($_GET[manager::FLAME_ON_PARAM_NAME]);
        $this->assertTrue(manager::is_profiling());

        $_GET[manager::FLAME_OFF_PARAM_NAME] = 1;
        $this->assertFalse(manager::is_profiling());

        unset($_GET[manager::FLAME_OFF_PARAM_NAME]);
        $this->assertFalse(manager::is_profiling());

        set_config('enable_auto', 1, 'tool_excimer');
        $this->assertTrue(manager::is_profiling());

        set_config('enable_auto', 0, 'tool_excimer');
        $this->assertFalse(manager::is_profiling());
    }

    public function test_stripparamters(): void {
        $param = [ 'a' => '1', 'b' => 2, 'c' => 3 ];
        $paramexpect = $param;
        $this->assertEquals($paramexpect, profile::stripparameters($param));

        $param = [ 'a' => '1', 'sesskey' => 2, 'c' => 3 ];
        $paramexpect = [ 'a' => '1', 'sesskey' => '', 'c' => 3 ];
        $this->assertEquals($paramexpect, profile::stripparameters($param));

        $param = [ 'a' => '1', 'sesskey' => 2, 'FLAMEME' => 3 ];
        $paramexpect = [ 'a' => '1', 'sesskey' => '' ];
        $this->assertEquals($paramexpect, profile::stripparameters($param));
    }
}
