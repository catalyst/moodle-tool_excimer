<?php

namespace tool_excimer;

defined('MOODLE_INTERNAL') || die();

class manager {

    const EXCIMER_LOG_LIMIT = 10000;
    const EXCIMER_PERIOD = 0.01;  // Default in seconds; used if config is out of sensible range.
    const EXCIMER_TRIGGER = 0.01; // Default in seconds; used if config is out of sensible range.

    /**
     * Checks if the given flag is set
     *
     * @param $flag Name of the flag
     * @return bool if the flag is set or not.
     */
    static function isflagset(string $flag): bool {
        return !empty(getenv($flag)) ||
                isset($_COOKIE[$flag]) ||
                isset($_POST[$flag]) ||
                isset($_GET[$flag]);
    }

    /**
     * Returns true if the profiler is currently set to be used.
     *
     * @return bool
     */
    static function isprofileon(): bool {
        return self::isflagset('FLAMEME') || (bool)get_config('tool_excimer', 'excimerenable');
    }

    /**
     * Get the list of stored profiles. Does not return the data.
     *
     * @return array
     */
    public static function getprofiles(): object {
        global $DB;
        $sql = "
            SELECT id, request, created
              FROM {tool_excimer_flamegraph}
          ORDER BY created DESC
        ";
        return $DB->get_recordset_sql($sql);
    }

    /**
     * Gets a single profile, including data.
     * @param $id
     * @return object
     */
    public static function getprofile($id): object {
        global $DB;
        return $DB->get_record('tool_excimer_flamegraph', ['id' => $id],'*', MUST_EXIST);
    }

    /**
     * Initialises the profiler and also sets up the shutdown callback.
     */
    public static function init(): void {
        $samplems = (int)get_config('tool_excimer', 'excimersample_ms');
        $hassensiblerange = $samplems > 10 && $samplems < 10000;
        $sampleperiod = $hassensiblerange ? round($samplems / 1000, 3) : self::EXCIMER_PERIOD;

        $prof = new \ExcimerProfiler();
        $prof->setPeriod($sampleperiod);

        $started = microtime(true);

        // TODO: a setting to determine if logs are saved locally or sent to an external process.

        // Call self::saveprofile whenever the logs get flushed.
        $spool = function(\ExcimerLog $log) use ($started) {
            manager::saveprofile($log, $started);
        };
        $prof->setFlushCallback($spool, self::EXCIMER_LOG_LIMIT);
        $prof->start();

        // Stop the profiler as a part of the shutdown sequence.
        \core_shutdown_manager::register_function(function() use ($prof) { $prof->stop(); $prof->flush(); });
    }

    /**
     * Saves a snaphot of the logs into the database.
     *
     * @param \ExcimerLog $log
     * @param float $started
     */
    public static function saveprofile(\ExcimerLog $log, float $started): void {
        global $DB;
        $stopped  = microtime(true);
        $flamedata = trim(str_replace("\n;", "\n", $log->formatCollapsed()));
        $flamedatad3 = json_encode(converter::process($flamedata));
        $id = $DB->insert_record('tool_excimer_flamegraph', [
            'request' => $_SERVER['PHP_SELF'] ?? 'UNKNOWN',
            'created' => (int)$started,
            'flamedata' => $flamedata,
            'flamedatad3' => $flamedatad3
        ]);
    }
}