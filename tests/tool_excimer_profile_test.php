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
use tool_excimer\context;

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
        profile::$partialsaveid = 0;
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
        $this->preventResetByRollback();

        $log = $this->quick_log(10);

        $times = [ 0.345, 0.234, 0.123, 0.456, 0.4, 0.5, 0.88, 0.1, 0.14, 0.22 ];
        $sortedtimes = $times;
        sort($sortedtimes);
        $this->assertGreaterThan($sortedtimes[0], $sortedtimes[1]); // Sanity check.

        // Non-auto saves should have no impact, so chuck a few in to see if it gums up the works.
        profile::save($log, manager::REASON_MANUAL, 12345, 2.345);
        profile::save($log, manager::REASON_FLAMEALL, 12345, 0.104);

        foreach ($times as $time) {
            profile::save($log, manager::REASON_SLOW, 12345, $time);
        }

        profile::save($log, manager::REASON_MANUAL, 12345, 0.001);

        $this->assertEquals(count($times) + 3, $DB->count_records('tool_excimer_profiles'));

        // Should remove a few profiles.
        $numtokeep = 8;
        profile::purge_fastest($numtokeep);
        $this->assertEquals($numtokeep + 3, $DB->count_records('tool_excimer_profiles'));
        $sortedtimes = array_slice($sortedtimes, -$numtokeep);
        $this->assertEquals($sortedtimes[0],
                $DB->get_field_sql("select min(duration) from {tool_excimer_profiles} where reason = ?", [manager::REASON_SLOW]));

        // Should remove a few more profiles.
        $numtokeep = 5;
        profile::purge_fastest($numtokeep);
        $this->assertEquals($numtokeep + 3, $DB->count_records('tool_excimer_profiles'));
        $sortedtimes = array_slice($sortedtimes, -$numtokeep);
        $this->assertEquals($sortedtimes[0],
                $DB->get_field_sql("select min(duration) from {tool_excimer_profiles} where reason = ?", [manager::REASON_SLOW]));

        // Should remove no profiles.
        $numtokeepnew = 9;
        profile::purge_fastest($numtokeepnew);
        $this->assertEquals($numtokeep + 3, $DB->count_records('tool_excimer_profiles'));
        $this->assertEquals($sortedtimes[0],
                $DB->get_field_sql("select min(duration) from {tool_excimer_profiles} where reason = ?", [manager::REASON_SLOW]));
    }

    /**
     * Tests the functionality to keep only the N slowest profiles for each page.
     *
     * @throws dml_exception
     */
    public function test_n_slowest_kept_per_page(): void {
        global $DB, $SCRIPT;
        $this->preventResetByRollback();

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
            profile::save($log, manager::REASON_SLOW, 12345, $times[$i]);
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
            [manager::REASON_SLOW]
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
        $this->preventResetByRollback();

        $log = $this->quick_log(150);
        $flamedata = trim(str_replace("\n;", "\n", $log->formatCollapsed()));
        // Remove full pathing to dirroot and only keep pathing from site root (non-issue in most sane cases).
        $flamedata = str_replace($CFG->dirroot . DIRECTORY_SEPARATOR, '', $flamedata);
        $flamedatad3 = converter::process($flamedata);
        $flamedatad3json = json_encode($flamedatad3);
        $numsamples = $flamedatad3['value'];
        $datasize = strlen(gzcompress($flamedatad3json));
        $reason = manager::REASON_SLOW;
        $created = 56;
        $duration = 0.123;

        $id = profile::save($log, $reason, $created, $duration);
        $record = $DB->get_record('tool_excimer_profiles', [ 'id' => $id ]);

        $this->assertEquals($id, $record->id);
        $this->assertEquals($reason, $record->reason);
        $this->assertEquals(profile::SCRIPTTYPE_CLI, $record->scripttype);
        $this->assertEquals($created, $record->created);
        $this->assertEquals($duration, $record->duration);
        $this->assertEquals($flamedatad3json, gzuncompress($record->flamedatad3));
        $this->assertEquals($numsamples, $record->numsamples);
        $this->assertEquals($datasize, $record->datasize);

        $log = $this->quick_log(1500);
        $flamedata = trim(str_replace("\n;", "\n", $log->formatCollapsed()));
        // Remove full pathing to dirroot and only keep pathing from site root (non-issue in most sane cases).
        $flamedata = str_replace($CFG->dirroot . DIRECTORY_SEPARATOR, '', $flamedata);
        $flamedatad3 = converter::process($flamedata);
        $flamedatad3json = json_encode($flamedatad3);
        $numsamples = $flamedatad3['value'];
        $datasize = strlen(gzcompress($flamedatad3json));
        $reason = manager::REASON_SLOW;
        $created = 120;
        $duration = 0.456;

        $id = profile::save($log, $reason, $created, $duration);
        $record = $DB->get_record('tool_excimer_profiles', [ 'id' => $id ]);

        $this->assertEquals($id, $record->id);
        $this->assertEquals($reason, $record->reason);
        $this->assertEquals(profile::SCRIPTTYPE_CLI, $record->scripttype);
        $this->assertEquals($created, $record->created);
        $this->assertEquals($duration, $record->duration);
        $this->assertEquals($flamedatad3json, gzuncompress($record->flamedatad3));
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
        $this->preventResetByRollback();

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

        $_REQUEST[manager::MANUAL_PARAM_NAME] = 1;
        $this->assertTrue(manager::is_profiling());

        unset($_REQUEST[manager::MANUAL_PARAM_NAME]);
        $this->assertFalse(manager::is_profiling());

        $_REQUEST[manager::FLAME_ON_PARAM_NAME] = 1;
        $this->assertTrue(manager::is_profiling());

        unset($_REQUEST[manager::FLAME_ON_PARAM_NAME]);
        $this->assertTrue(manager::is_profiling());

        $_REQUEST[manager::FLAME_OFF_PARAM_NAME] = 1;
        $this->assertFalse(manager::is_profiling());

        unset($_REQUEST[manager::FLAME_OFF_PARAM_NAME]);
        $this->assertFalse(manager::is_profiling());

        set_config('enable_auto', 1, 'tool_excimer');
        $this->assertTrue(manager::is_profiling());

        set_config('enable_auto', 0, 'tool_excimer');
        $this->assertFalse(manager::is_profiling());
    }

    public function test_stripparamters(): void {
        $param = [ 'a' => '1', 'b' => 2, 'c' => 3 ];
        $paramexpect = $param;
        $this->assertEquals($paramexpect, context::stripparameters($param));

        $param = [ 'a' => '1', 'sesskey' => 2, 'c' => 3 ];
        $paramexpect = [ 'a' => '1', 'sesskey' => '', 'c' => 3 ];
        $this->assertEquals($paramexpect, context::stripparameters($param));

        $param = [ 'a' => '1', 'sesskey' => 2, 'FLAMEME' => 3 ];
        $paramexpect = [ 'a' => '1', 'sesskey' => '' ];
        $this->assertEquals($paramexpect, context::stripparameters($param));
    }

    public function test_reasons_are_being_stored(): void {
        global $DB;
        $this->preventResetByRollback();

        // Initialise the logs object.
        $log = $this->quick_log(0);

        // Non-auto saves should have no impact, so chuck a few in to see if it gums up the works.
        $allthereasons = 0;
        foreach (manager::REASONS as $reason) {
            $allthereasons |= $reason;
        }
        $id = profile::save($log, $allthereasons, 12345, 2.345);
        $record = $DB->get_record('tool_excimer_profiles', ['id' => $id]);

        // Fetch profile from DB and confirm it matches for all the reasons.
        $recordedreason = (int) $record->reason;
        foreach (manager::REASONS as $reason) {
            $this->assertTrue((bool) ($recordedreason & $reason));
        }
    }

    public function test_reasons_being_removed(): void {
        global $DB;
        $this->preventResetByRollback();

        // Initialise the logs object.
        $log = $this->quick_log(0);

        // Non-auto saves should have no impact, so chuck a few in to see if it gums up the works.
        $allthereasons = 0;
        foreach (manager::REASONS as $reason) {
            $allthereasons |= $reason;
        }
        $id = profile::save($log, $allthereasons, 12345, 2.345);
        $profile = $DB->get_record('tool_excimer_profiles', ['id' => $id]);

        // Fetch profile from DB and confirm it matches for all the reasons, and
        // that the reason no longer exists on the profile after the change.
        foreach (manager::REASONS as $reason) {
            profile::remove_reason([$profile], $reason);
            $profile = $DB->get_record('tool_excimer_profiles', ['id' => $id]);
            if ($profile) {
                $remainingreason = (int) $profile->reason;
                $this->assertFalse((bool) ($remainingreason & $reason));
            }
        }

        // The profile should no longer exist once the reasons are all removed.
        $this->assertFalse($profile);
    }

    public function test_save_partial_profile(): void {
        global $DB, $CFG;
        $this->preventResetByRollback();

        $log = $this->quick_log(1);
        $flamedata = trim(str_replace("\n;", "\n", $log->formatCollapsed()));
        // Remove full pathing to dirroot and only keep pathing from site root (non-issue in most sane cases).
        $flamedata = str_replace($CFG->dirroot . DIRECTORY_SEPARATOR, '', $flamedata);
        $flamedatad3 = converter::process($flamedata);
        $flamedatad3json = json_encode($flamedatad3);
        $numsamples = $flamedatad3['value'];
        $datasize = strlen(gzcompress($flamedatad3json));
        $reason = manager::REASON_SLOW;
        $created = 56;
        $duration = 0.123;

        $id = profile::save($log, $reason, $created, $duration);
        $record = $DB->get_record('tool_excimer_profiles', [ 'id' => $id ]);
        profile::$partialsaveid = $id;

        $this->assertEquals($id, $record->id);
        $this->assertEquals($reason, $record->reason);
        $this->assertEquals(profile::SCRIPTTYPE_CLI, $record->scripttype);
        $this->assertEquals($created, $record->created);
        $this->assertEquals($duration, $record->duration);
        $this->assertEquals($flamedatad3json, gzuncompress($record->flamedatad3));
        $this->assertEquals($numsamples, $record->numsamples);
        $this->assertEquals($datasize, $record->datasize);

        $log = $this->quick_log(2);
        $flamedata = trim(str_replace("\n;", "\n", $log->formatCollapsed()));
        // Remove full pathing to dirroot and only keep pathing from site root (non-issue in most sane cases).
        $flamedata = str_replace($CFG->dirroot . DIRECTORY_SEPARATOR, '', $flamedata);
        $flamedatad3 = converter::process($flamedata);
        $flamedatad3json = json_encode($flamedatad3);
        $numsamples = $flamedatad3['value'];
        $datasize = strlen(gzcompress($flamedatad3json));
        $reason = manager::REASON_SLOW | manager::REASON_MANUAL;
        $duration = 0.456;

        $secondid = profile::save($log, $reason, $created, $duration);
        $this->assertEquals($id, $secondid);
        $record2 = $DB->get_record('tool_excimer_profiles', [ 'id' => $id ]);

        $this->assertEquals($id, $record2->id);
        $this->assertEquals($reason, $record2->reason);
        $this->assertEquals(profile::SCRIPTTYPE_CLI, $record2->scripttype);
        $this->assertEquals($duration, $record2->duration);
        $this->assertEquals($flamedatad3json, gzuncompress($record2->flamedatad3));
        $this->assertEquals($numsamples, $record2->numsamples);
        $this->assertEquals($datasize, $record2->datasize);

        $this->assertEquals($record->id, $record2->id);
        $this->assertEquals($record->created, $record2->created);
        $this->assertEquals($record->pathinfo, $record2->pathinfo);
        $this->assertEquals($record->sessionid, $record2->sessionid);
        $this->assertEquals($record->cookies, $record2->cookies);
        $this->assertEquals($record->parameters, $record2->parameters);
        $this->assertEquals($record->buffering, $record2->buffering);
        $this->assertEquals($record->request, $record2->request);
        $this->assertEquals($record->contenttypevalue, $record2->contenttypevalue);
        $this->assertEquals($record->contenttypecategory, $record2->contenttypecategory);
        $this->assertEquals($record->contenttypekey, $record2->contenttypekey);
        $this->assertEquals($record->request, $record2->request);
        $this->assertEquals($record->userid, $record2->userid);
    }

    private function mock_profile_insertion_with_duration(float $duration) {
        // Same handling with on_interval and on_flush of the manager
        // class, with a custom duration set.
        $profile = new \ExcimerProfiler();
        $started = microtime(true);
        $finalduration = $duration / 1000;

        // Divide by 1000 required, as microtime(true) returns the value in seconds.
        $reason = manager::get_reasons($finalduration);
        if ($reason !== manager::REASON_NONE) {
            $log = $profile->getLog();
            // Won't show DB writes count since saves are stored via another DB connection.
            profile::save($log, $reason, (int) $started, $finalduration);
        }
    }

    public function test_minimal_db_reads_writes_for_warm_cache() {
        global $DB;

        // Prepare test environment.
        set_config('num_slowest_by_page', 2, 'tool_excimer'); // 3 per page
        set_config('num_slowest', 4, 'tool_excimer'); // 5 max slowest
        set_config('trigger_ms', 2, 'tool_excimer'); // Should capture anything at least 1ms slow.
        get_config('tool_excimer', 'trigger_ms');

        // Emulate a scenario where all breakpoints are met (request quota, system quota, etc).
        $startreads = $DB->perf_get_reads();
        $startwrites = $DB->perf_get_writes();

        // Under triggerms - no R/Ws.
        $this->mock_profile_insertion_with_duration(1);
        $this->assertEquals(0, $DB->perf_get_reads() - $startreads);
        $this->assertEquals(0, $DB->perf_get_writes() - $startwrites);

        // Equal to triggerms - no R/Ws - value skipped if equal - must be greater.
        $this->mock_profile_insertion_with_duration(2);
        $this->assertEquals(0, $DB->perf_get_reads() - $startreads);
        $this->assertEquals(0, $DB->perf_get_writes() - $startwrites);

        foreach ([
            3, // Should add 3.
            3, // Should add 3 - request quota reached.
            4, // Should add 4.
            5, // Should add 5 - reason quota reached (note request min should be 4).
            1, // 1 read (since previous iteration would have cleared cache after insertion), but otherwise skipped.
            2, // Same as above.
            3, // Since it's above the triggerms threshold, and quota is
               // reached. It will warm the reason cache and set the min value.
            4, // Same as above, but for the request. Since min is 4 it won't be
               // added, but this will warm the cache.
        ] as $duration) {
            $this->mock_profile_insertion_with_duration($duration);
        }

        $startwarmcachereads = $DB->perf_get_reads();
        $startwarmcachewrites = $DB->perf_get_writes();
        foreach ([
            // Cache should be warm, anything under here should be 0/0.
            1, 2, 3, 4,
            1, 2, 3, 4,
            1, 2, 3, 4,
        ] as $duration) {
            $this->mock_profile_insertion_with_duration($duration);
        }

        $endreads = $DB->perf_get_reads();
        $endwrites = $DB->perf_get_writes();

        // Tests that some activity has happened before the caches were warm,
        // which means caching and the like is happening as expected.
        $totalreads = $endreads - $startreads;
        $totalwrites = $endwrites - $startwrites;
        $this->assertNotEmpty($totalreads);
        $this->assertNotEmpty($totalwrites);

        $totalwarmcachereads = $endreads - $startwarmcachereads;
        $totalwarmcachewrites = $endwrites - $startwarmcachewrites;
        $this->assertEquals(0, $totalwarmcachereads);
        $this->assertEquals(0, $totalwarmcachewrites);
    }
}
