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

use core\persistent;

/**
 * Profile saving/loading and manipulation.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile extends persistent {

    /** Table name. */
    const TABLE = 'tool_excimer_profiles';

    /** Reason - NONE - Default fallback reason value, this will not be stored. */
    const REASON_NONE = 0b0000;

    /** Reason - MANUAL - Profiles are manually stored for the request using FLAMEME as a page param. */
    const REASON_FLAMEME   = 0b0001;

    /** Reason - SLOW - Set when conditions are met and these profiles are automatically stored. */
    const REASON_SLOW     = 0b0010;

    /** Reason - FLAMEALL - Toggles profiling for all subsequent pages, until FLAMEALLSTOP param is passed as a page param. */
    const REASON_FLAMEALL = 0b0100;

    /** Reason - STACK - Set when maxstackdepth exceeds a predefined limit. */
    const REASON_STACK = 0b1000;

    /** Reason - IMPORTED - Set when a profile is imported. */
    const REASON_IMPORT = 0b010000;

    /** Reasons for profiling (bitmask flags). NOTE: Excluding the NONE option intentionally. */
    const REASONS = [
        self::REASON_FLAMEME,
        self::REASON_SLOW,
        self::REASON_FLAMEALL,
        self::REASON_STACK,
        self::REASON_IMPORT,
    ];

    /** String map for profiling reasons.  */
    const REASON_STR_MAP = [
        self::REASON_FLAMEME => 'manual',
        self::REASON_SLOW => 'slowest',
        self::REASON_FLAMEALL => 'flameall',
        self::REASON_STACK => 'stackdepth',
        self::REASON_IMPORT => 'import',
    ];

    /** Ajax scripts */
    const SCRIPTTYPE_AJAX = 0;
    /** CLI scripts */
    const SCRIPTTYPE_CLI = 1;
    /** Web page scripts */
    const SCRIPTTYPE_WEB = 2;
    /** Web service scripts */
    const SCRIPTTYPE_WS = 3;
    /** Cron tasks */
    const SCRIPTTYPE_TASK = 4;

    /** Profile fields to ignore during imports */
    const IMPORT_IGNORE = [
        'id',
        'siteidentifier',
    ];

    /** Profile fields that are site specific and should be locked behind a site check during imports */
    const IMPORT_CHECK_SITE = [
        'referer',
        'userid',
        'usermodified',
        'courseid',
        'lockwaiturl',
    ];

    /**
     * Custom setter to set the flame data.
     *
     * @param flamed3_node $node
     */
    protected function set_flamedatad3(flamed3_node $node): void {
        $flamedata = gzcompress(json_encode($node));
        $this->raw_set('flamedatad3', $flamedata);
        $this->raw_set('datasize', strlen($flamedata));
    }

    /**
     * Custom getter for flame data.
     *
     * @return array|\stdClass|null json decoded value
     */
    protected function get_flamedatad3() {
        return json_decode($this->get_flamedatad3json());
    }

    /**
     * Special getter to obtain the flame data JSON.
     *
     * @return string
     */
    public function get_flamedatad3json(): string {
        return $this->get_uncompressed_json('flamedatad3');
    }

    /**
     * Custom setter to set the memory usage d3 data.
     *
     * @param array $node
     */
    protected function set_memoryusagedatad3(array $node): void {
        $memoryusagejson = json_encode($node);
        $this->raw_set('memoryusagedatad3', gzcompress($memoryusagejson));
    }

    /**
     * Custom getter for memory usage data.
     *
     * @return array|\stdClass|null json decoded value
     */
    protected function get_memoryusagedatad3() {
        return json_decode($this->get_uncompressed_json('memoryusagedatad3'));
    }

    /**
     * Special getter to obtain the uncompressed stored JSON.
     *
     * @param string $fieldname
     * @return string
     */
    public function get_uncompressed_json(string $fieldname): string {
        $rawdata = $this->raw_get($fieldname);
        if (isset($rawdata)) {
            return gzuncompress($rawdata);
        }
        return json_encode(null);
    }

    /**
     * Gets the JSON for an export.
     *
     * @return string JSON
     */
    protected function get_export_json(): string {
        $data = new \stdClass();

        // Add siteidentifier so imports can match sites.
        $data->siteidentifier = md5(get_site_identifier());

        // Load data the same way as to_record(), but use get() to transform d3 data.
        $properties = array_keys(static::properties_definition());
        foreach ($properties as $property) {
            $data->$property = $this->get($property);
        }

        return json_encode($data);
    }

    /**
     * Download the profile in JSON format.
     */
    public function download(): void {
        $id = $this->raw_get('id');
        $filename = "excimer-profile-$id.json";

        header('Content-Type: application/json; charset: utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");

        echo $this->get_export_json();
        exit();
    }

    /**
     * Imports a prfile from JSON format.
     *
     * @param string $json
     * @return bool|int the id of the imported profile, or false if unsuccessful
     */
    public static function import(string $json) {
        global $DB;

        $profile = new profile();
        $data = json_decode($json);

        // Remove properties that shouldn't be imported.
        $removeproperties = self::IMPORT_IGNORE;

        // If uploading to a different site, we should also remove data that is site specific.
        if (!isset($data->siteidentifier) || $data->siteidentifier !== md5(get_site_identifier())) {
            // Remove ids and full urls.
            $removeproperties = array_merge($removeproperties, self::IMPORT_CHECK_SITE);

            // Don't copy the reason to external sites.
            $data->reason = 0;
        }

        // Add import to reasons.
        if (isset($data->reason) && $data->reason !== self::REASON_IMPORT) {
            $data->reason += self::REASON_IMPORT;
        }

        // Convert flamedatad3 to flame_node.
        if (isset($data->flamedatad3)) {
            $data->flamedatad3 = flamed3_node::from_import($data->flamedatad3);
        }

        foreach ($data as $property => $value) {
            if (isset($value) && !in_array($property, $removeproperties)) {
                $profile->set($property, $value);
            }
        }

        // The normal profile->save() doesn't work with flamedatad3 and memoryuseagedatad3.
        return $DB->insert_record(self::TABLE, $profile->to_record());
    }

    /**
     * Convenience method to add environment data to the profile.
     *
     * @param string $request The name/URL of the script.
     */
    public function add_env(string $request): void {
        global $USER, $CFG;

        $this->raw_set('request', $request);
        $this->raw_set('sessionid', substr(session_id(), 0, 10));
        $this->raw_set('scripttype', script_metadata::get_script_type());
        $this->raw_set('userid', $USER ? $USER->id : 0);
        $this->raw_set('pid', getmypid());
        $this->raw_set('hostname', gethostname());
        $this->raw_set('versionhash', $CFG->allversionshash);

        // Store the sample rate at the time this profile is created.
        $this->raw_set('samplerate', script_metadata::$samplems);

        $this->raw_set('method', $_SERVER['REQUEST_METHOD'] ?? '');
        $this->raw_set('pathinfo', $_SERVER['PATH_INFO'] ?? '');
        $this->raw_set('useragent', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $this->raw_set('referer', $_SERVER['HTTP_REFERER'] ?? '');
        $this->raw_set('cookies', !defined('NO_MOODLE_COOKIES') || !NO_MOODLE_COOKIES);
        $this->raw_set('buffering', !defined('NO_OUTPUT_BUFFERING') || !NO_OUTPUT_BUFFERING);
        $this->raw_set('parameters', script_metadata::get_parameters($this->get('scripttype')));
        $this->raw_set('scriptgroup', script_metadata::get_groupby_value($this));

        list($contenttypevalue, $contenttypekey, $contenttypecategory) = script_metadata::resolve_content_type($this);
        $this->raw_set('contenttypevalue', $contenttypevalue);
        $this->raw_set('contenttypekey', $contenttypekey);
        $this->raw_set('contenttypecategory', $contenttypecategory);
    }

    /**
     * Saves the record to the database. Any transaction is bypassed.
     * Additional information is obtained and inserted into the profile before recording.
     *
     * Note: save_record() should only get called in the process of making a profile. For other manipulation,
     * use save().
     *
     * @return int The database ID of the record.
     * @throws \dml_exception
     */
    public function save_record(): int {
        global $DB, $USER, $COURSE;

        $db = manager::get_altconnection();
        // If a connection cannot be established, we simply do not record.
        if ($db === false) {
            debugging('tool_excimer: Not recording due to the lack of a DB connection.');
            return 0;
        }

        // Get max memory usage.
        $this->raw_set('memoryusagemax', memory_get_peak_usage());

        // Get DB ops (reads/writes).
        $this->raw_set('dbreads', $DB->perf_get_reads());
        $this->raw_set('dbwrites', $DB->perf_get_writes());
        $this->raw_set(
            'dbreplicareads',
            (method_exists($DB, 'want_read_slave') && $DB->want_read_slave()) ? $DB->perf_get_reads_slave() : 0
        );

        $this->raw_set('responsecode', http_response_code());

        $now = time();
        $this->raw_set('timemodified', $now);
        $this->raw_set('usermodified', $USER->id);

        if ($this->check_update_userid($USER->id)) {
            $this->raw_set('userid', $USER->id);
        }

        if ($this->check_update_courseid($COURSE->id)) {
            $this->raw_set('courseid', $COURSE->id);
        }

        if ($this->raw_get('id') <= 0) {
            $this->raw_set('timecreated', $now);
            $id = $db->insert_record(self::TABLE, $this->to_record());
            $this->raw_set('id', $id);
        } else {
            $db->update_record(self::TABLE, $this->to_record());
        }

        // NOTE: Does clearing the cache on partial saves make sense? The cache
        // currently sets the min duration for how long a profile should go for
        // before it gets stored, for other reasons later on, it might be
        // pushing on additional constraints. In either case, clearing the cache
        // here assumes a few things: 1 - quota has been reached, 2 - minimum duration
        // will have changed, typically higher. 3 - every partial save will
        // cause some sort of reordering and potentially the cached items won't
        // hold correct values.

        // Updates the request_metadata and per reason cache with more recent values.
        if ($this->get('reason') & self::REASON_SLOW) {
            profile_helper::get_min_duration_for_group_and_reason($this->get('scriptgroup'), self::REASON_SLOW, false);
            profile_helper::get_min_duration_for_reason(self::REASON_SLOW, false);
        }

        return (int) $this->raw_get('id');
    }

    /**
     * Decide if the user id stored with this profile should be updated with the current $USER->id.
     *
     * @param int $currentid
     * @return bool
     */
    protected function check_update_userid(int $currentid): bool {
        global $CFG;

        $stored = (int) $this->raw_get('userid');

        // We may not have obtained a valid userid when the profile record was created.
        // If the stored userid is 0, and there's now a valid $USER->id, update the stored userid.
        if ($currentid && !$stored) {
            return true;
        }

        // If the would-be user matches the currently stored, do not update.
        if ($currentid === $stored) {
            return false;
        }

        // If stored user is guest, update it.
        $guestid = (int) $CFG->siteguest;
        if ($guestid && $stored === $guestid) {
            return true;
        }

        // If stored user is cli admin, update it.
        if (!empty($CFG->siteadmins) && in_array($stored, explode(',', $CFG->siteadmins))) {
            return true;
        }

        return false;
    }

    /**
     * Decide if the course id stored with this profile should be updated with the current $COURSE->id.
     *
     * @param int $currentid
     * @return bool
     */
    protected function check_update_courseid(int $currentid): bool {
        $stored = (int) $this->raw_get('courseid');

        // We may not have obtained a valid courseid when the profile record was created.
        // If the stored courseid is 0, and there's now a valid $COURSE->id, update the stored courseid.
        if ($currentid && !$stored) {
            return true;
        }

        return false;
    }

    /**
     * Updates the lock status of a profile.
     *
     * @param string $lockreason reason for the lock, or an empty string to remove the lock.
     * @return void
     */
    public function update_lock(string $lockreason) {
        global $DB, $USER;

        $previousreason = $this->get('lockreason');
        $updatefields = [
            'id' => $this->get('id'),
            'lockreason' => $lockreason,
        ];

        $now = time();

        // Update modified info when creating a new lock.
        if ($lockreason && !$previousreason) {
            $updatefields['usermodified'] = $USER->id;
            $updatefields['timemodified'] = $now;
        }

        // Reset modified info when removing a lock.
        if (!$lockreason) {
            // Return usermodified to what it was originally (userid should be an exact match).
            $updatefields['usermodified'] = $this->get('userid');
            $updatefields['timemodified'] = $now;
        }

        // Update directly as save() won't work with memoryusagedatad3 and flamedatad3.
        $DB->update_record('tool_excimer_profiles', (object) $updatefields);
    }

    /**
     * Returns the slowest profile on record.
     *
     * @return false|mixed The slowest profile, or false if no profiles are stored.
     * @throws \dml_exception
     */
    public static function get_slowest_profile() {
        global $DB;
        return $DB->get_record_sql(
            "SELECT id, request, duration, pathinfo, parameters, scripttype
                FROM {tool_excimer_profiles}
            ORDER BY duration DESC
               LIMIT 1"
        );
    }

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'reason' => ['type' => PARAM_INT],
            'scripttype' => ['type' => PARAM_INT],
            'method' => ['type' => PARAM_ALPHA, 'default' => ''],
            'created' => ['type' => PARAM_INT, 'default' => 0],
            'finished' => ['type' => PARAM_INT, 'default' => 0],
            'duration' => ['type' => PARAM_FLOAT, 'default' => 0],
            'request' => ['type' => PARAM_TEXT, 'default' => ''],
            'scriptgroup' => ['type' => PARAM_TEXT, 'default' => ''],
            'pathinfo' => ['type' => PARAM_SAFEPATH, 'default' => ''],
            'parameters' => ['type' => PARAM_TEXT, 'default' => ''],
            'sessionid' => ['type' => PARAM_ALPHANUM, 'default' => ''],
            'userid' => ['type' => PARAM_INT, 'default' => 0],
            'maxstackdepth' => ['type' => PARAM_INT, 'default' => 0],
            'cookies' => ['type' => PARAM_BOOL],
            'buffering' => ['type' => PARAM_BOOL],
            'responsecode' => ['type' => PARAM_INT, 'default' => 0],
            'referer' => ['type' => PARAM_URL, 'default' => ''],
            'pid' => ['type' => PARAM_INT, 'default' => 0],
            'hostname' => ['type' => PARAM_TEXT, 'default' => ''],
            'useragent' => ['type' => PARAM_TEXT, 'default' => ''],
            'versionhash' => ['type' => PARAM_TEXT, 'default' => ''],
            'datasize' => ['type' => PARAM_INT, 'default' => 0],
            'numsamples' => ['type' => PARAM_INT, 'default' => 0],
            'samplerate' => ['type' => PARAM_INT, 'default' => 0],
            'memoryusagedatad3' => ['type' => PARAM_RAW],
            'memoryusagemax' => ['type' => PARAM_INT],
            'flamedatad3' => ['type' => PARAM_RAW],
            'contenttypecategory' => ['type' => PARAM_TEXT, 'default' => ''],
            'contenttypekey' => ['type' => PARAM_TEXT],
            'contenttypevalue' => ['type' => PARAM_TEXT],
            'dbreads' => ['type' => PARAM_INT, 'default' => 0],
            'dbwrites' => ['type' => PARAM_INT, 'default' => 0],
            'dbreplicareads' => ['type' => PARAM_INT, 'default' => 0],
            'lockreason' => ['type' => PARAM_TEXT, 'default' => ''],
            'courseid' => ['type' => PARAM_INT, 'default' => null],
            'lockheld' => ['type' => PARAM_FLOAT, 'default' => 0],
            'lockwait' => ['type' => PARAM_FLOAT, 'default' => 0],
            'lockwaiturl' => ['type' => PARAM_TEXT, 'default' => ''],
        ];
    }
}
