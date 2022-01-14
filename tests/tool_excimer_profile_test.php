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

    /**
     * Tests the functionality to keep only the N slowest profiles.
     *
     * @throws \dml_exception
     */
    public function test_n_slowest_kept(): void {
        global $DB;
        $this->preventResetByRollback();

        $log = $this->quick_log(10);

        $times = [ 0.345, 0.234, 0.123, 0.456, 0.4, 0.5, 0.88, 0.1, 0.14, 0.22 ];
        $sortedtimes = $times;
        sort($sortedtimes);
        $this->assertGreaterThan($sortedtimes[0], $sortedtimes[1]); // Sanity check.
        $node = flamed3_node::from_excimer_log_entries($log);

        // Non-auto saves should have no impact, so chuck a few in to see if it gums up the works.
        $this->quick_save('mock', $node, profile::REASON_FLAMEME, 2.345);
        $this->quick_save('mock', $node, profile::REASON_FLAMEALL, 0.104);

        foreach ($times as $time) {
            $this->quick_save('mock', $node, profile::REASON_SLOW, $time);
        }

        $this->quick_save('mock', $node, profile::REASON_FLAMEME, 0.001);

        $this->assertEquals(count($times) + 3, $DB->count_records(profile::TABLE));

        // Should remove a few profiles.
        $numtokeep = 8;
        profile::purge_fastest($numtokeep);
        $this->assertEquals($numtokeep + 3, $DB->count_records(profile::TABLE));
        $sortedtimes = array_slice($sortedtimes, -$numtokeep);
        $this->assertEquals($sortedtimes[0],
                $DB->get_field_sql("select min(duration) from {tool_excimer_profiles} where reason = ?", [profile::REASON_SLOW]));

        // Should remove a few more profiles.
        $numtokeep = 5;
        profile::purge_fastest($numtokeep);
        $this->assertEquals($numtokeep + 3, $DB->count_records(profile::TABLE));
        $sortedtimes = array_slice($sortedtimes, -$numtokeep);
        $this->assertEquals($sortedtimes[0],
                $DB->get_field_sql("select min(duration) from {tool_excimer_profiles} where reason = ?", [profile::REASON_SLOW]));

        // Should remove no profiles.
        $numtokeepnew = 9;
        profile::purge_fastest($numtokeepnew);
        $this->assertEquals($numtokeep + 3, $DB->count_records(profile::TABLE));
        $this->assertEquals($sortedtimes[0],
                $DB->get_field_sql("select min(duration) from {tool_excimer_profiles} where reason = ?", [profile::REASON_SLOW]));
    }

    /**
     * Tests the functionality to keep only the N slowest profiles for each group.
     *
     * @throws \dml_exception
     */
    public function test_n_slowest_kept_per_group(): void {
        global $DB;
        $this->preventResetByRollback();

        $log = $this->quick_log(10);

        $times = [ 0.345, 0.234, 0.123, 0.456, 0.4, 0.5, 0.88, 0.1, 0.14, 0.22, 0.111, 0.9 ];
        $reqnames = [ 'a', 'b', 'c', 'a', 'd', 'a', 'a', 'c', 'd', 'c', 'c', 'c' ];
        $sortedtimes = $times;
        sort($sortedtimes);
        $this->assertGreaterThan($sortedtimes[0], $sortedtimes[1]); // Sanity check.
        $node = flamed3_node::from_excimer_log_entries($log);

        // Non-auto saves should have no impact, so chuck a few in to see if it gums up the works.
        $this->quick_save('a', $node, profile::REASON_FLAMEME, 2.345);
        $this->quick_save('b', $node, profile::REASON_FLAMEALL, 0.104);

        for ($i = 0; $i < count($times); ++$i) {
            $this->quick_save($reqnames[$i], $node, profile::REASON_SLOW, $times[$i]);
        }

        $this->quick_save('c', $node, profile::REASON_FLAMEME, 0.001);

        $this->assertEquals(count($times) + 3, $DB->count_records(profile::TABLE));

        // Should remove a few profiles.
        $numtokeep = 3;
        $expectedreqcount = [ 'a' => 3, 'b' => 1, 'c' => 3, 'd' => 2 ];
        $expectedfastest = [ 'a' => 0.456, 'b' => 0.234, 'c' => 0.123, 'd' => 0.14 ];
        profile::purge_fastest_by_group($numtokeep);
        $this->assertEquals(array_sum($expectedreqcount) + 3, $DB->count_records(profile::TABLE));
        $records = $DB->get_records_sql(
            "SELECT request, COUNT(*) AS c, MIN(duration) AS m
               FROM {tool_excimer_profiles}
              WHERE reason = ?
           GROUP BY request",
            [profile::REASON_SLOW]
        );

        foreach ($records as $i => $record) {
            $this->assertEquals($expectedreqcount[$i], $record->c);
            $this->assertEquals($expectedfastest[$i], $record->m);
        }
    }

    public function test_set_flamedata(): void {
        $profile = new profile();
        $log = $this->quick_log(10);
        $node = flamed3_node::from_excimer_log_entries($log);
        $nodejson = json_encode($node);
        $compressed = gzcompress($nodejson);
        $size = strlen($compressed);

        $profile->set('flamedatad3', $node);
        $this->assertEquals($nodejson, $profile->get_flamedatad3json());
        $this->assertEquals($size, $profile->get('datasize'));
        $this->assertEquals($node->value, $profile->get('numsamples'));
    }

    public function test_save(): void {
        global $DB;
        $this->preventResetByRollback();

        $log = $this->quick_log(150);
        $node = flamed3_node::from_excimer_log_entries($log);
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

        $log = $this->quick_log(1);
        $node = flamed3_node::from_excimer_log_entries($log);
        $flamedatad3json = json_encode($node);
        $reason = profile::REASON_SLOW;
        $created = 56;
        $duration = 1.123;
        $request = 'mock';

        $profile = profile::get_running_profile();
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

        $profile = profile::get_running_profile();
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

    /**
     * Tests the expiry of profiles.
     *
     * @throws \dml_exception
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
            $this->quick_save('mock', flamed3_node::from_excimer_log_entries($log), profile::REASON_FLAMEME, 0.2, $time);
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

    public function test_reasons_being_removed(): void {
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
        $profile = $DB->get_record(profile::TABLE, ['id' => $id]);

        // Fetch profile from DB and confirm it matches for all the reasons, and
        // that the reason no longer exists on the profile after the change.
        foreach (profile::REASONS as $reason) {
            profile::remove_reason([$profile], $reason);
            $profile = $DB->get_record(profile::TABLE, ['id' => $id]);
            if ($profile) {
                $remainingreason = (int) $profile->reason;
                $this->assertFalse((bool) ($remainingreason & $reason));
            }
        }

        // The profile should no longer exist once the reasons are all removed.
        $this->assertFalse($profile);
    }

    /**
     * @param float $duration time in seconds.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function mock_profile_insertion_with_duration(float $duration) {
        // Same handling with on_interval and on_flush of the manager
        // class, with a custom duration set.
        $profiler = new \ExcimerProfiler();
        $profile = new profile();
        $profile->add_env('mock');
        $profile->set('created', (int) microtime(false));
        $profile->set('duration', $duration);

        // Divide by 1000 required, as microtime(true) returns the value in seconds.
        $reason = manager::get_reasons($profile);
        if ($reason !== profile::REASON_NONE) {
            $profile->set('flamedatad3', flamed3_node::from_excimer_log_entries($profiler->getLog()));
            $profile->set('reason', $reason);

            // Won't show DB writes count since saves are stored via another DB connection.
            $profile->save_record();
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
        $this->mock_profile_insertion_with_duration(0.001);
        $this->assertEquals(0, $DB->perf_get_reads() - $startreads);
        $this->assertEquals(0, $DB->perf_get_writes() - $startwrites);

        // Equal to triggerms - no R/Ws - value skipped if equal - must be greater.
        $this->mock_profile_insertion_with_duration(0.002);
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
            $this->mock_profile_insertion_with_duration($duration / 1000);
        }

        $startwarmcachereads = $DB->perf_get_reads();
        $startwarmcachewrites = $DB->perf_get_writes();
        foreach ([
            // Cache should be warm, anything under here should be 0/0.
            1, 2, 3, 4,
            1, 2, 3, 4,
            1, 2, 3, 4,
        ] as $duration) {
            $this->mock_profile_insertion_with_duration($duration / 1000);
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
