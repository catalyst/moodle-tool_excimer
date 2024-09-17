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

use core_filetypes;

/**
 * Helpers for displaying stuff.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Maps HTTP status codes to css badges.
     */
    const STATUS_BADGES = [
        2 => 'badge-success',
        3 => 'badge-secondary',
        4 => 'badge-warning',
        5 => 'badge-danger',
    ];

    /**
     * Returns a printable string for a script type value.
     *
     * @param int $type
     * @return string
     * @throws \coding_exception
     */
    public static function script_type_display(int $type): string {
        switch ($type) {
            case profile::SCRIPTTYPE_WEB:
                return get_string('scripttype_web', 'tool_excimer');
            case profile::SCRIPTTYPE_CLI:
                return get_string('scripttype_cli', 'tool_excimer');
            case profile::SCRIPTTYPE_AJAX:
                return get_string('scripttype_ajax', 'tool_excimer');
            case profile::SCRIPTTYPE_WS:
                return get_string('scripttype_ws', 'tool_excimer');
            case profile::SCRIPTTYPE_TASK:
                return get_string('scripttype_task', 'tool_excimer');
            default:
                return (string) $type;
        }
    }

    /**
     * Returns a printable string for the profiling reasons.
     *
     * @param int $reason
     * @return string
     * @throws \coding_exception
     */
    public static function reason_display(int $reason): string {
        $reasonsmatched = [];
        if ($reason & profile::REASON_SLOW) {
            $reasonsmatched[] = get_string('reason_slow', 'tool_excimer');
        }
        if ($reason & profile::REASON_FLAMEALL) {
            $reasonsmatched[] = get_string('reason_flameall', 'tool_excimer');
        }
        if ($reason & profile::REASON_FLAMEME) {
            $reasonsmatched[] = get_string('reason_flameme', 'tool_excimer');
        }
        if ($reason & profile::REASON_STACK) {
            $reasonsmatched[] = get_string('reason_stack', 'tool_excimer');
        }
        if ($reason & profile::REASON_IMPORT) {
            $reasonsmatched[] = get_string('reason_import', 'tool_excimer');
        }
        return implode(',', $reasonsmatched);
    }

    /**
     * Returns a formatted time duration in h:m:s format.
     *
     * @param float $duration
     * @param bool $markup If true, then use markup on the result.
     * @return string
     * @throws \Exception
     */
    public static function duration_display(float $duration, bool $markup = true): string {
        if (!$markup) {
            return $duration;
        }

        $s = (int) $duration;
        $h = $s / 3600;
        $m = ($s % 3600) / 60;
        $s = $s % 60;
        $formatted = ($h >= 1) ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);

        // Make text monospace.
        return \html_writer::tag('pre', $formatted, ['class' => 'm-0', 'style' => 'font-size: inherit;']);
    }

    /**
     * Returns a formatted time duration in a human readable format.
     *
     * @param float $duration
     * @param bool $markup If true, then use markup on the result.
     * @return string
     * @throws \Exception
     */
    public static function duration_display_text(float $duration, bool $markup = true): string {
        // Variable $markup allows a different format when viewed (true) vs downloaded (false).
        if ($markup) {
            if (intval($duration) > 10) {
                // Use whole seconds.
                $usetime = intval($duration);
            } else {
                // Add one decimal place.
                $usetime = round($duration, 1);
                // Fallback to prevent format_time returning the translated string 'now' when the rounded version is 0.
                if ($usetime == 0) {
                    // Try rounding to 3 decimal places, otherwise return 0 secs.
                    if (round($duration, 3) > 0) {
                        $usetime = round($duration, 3);
                    } else {
                        return '-';
                    }
                }
            }
            // This currently works with floats, but relies on undocumented behaviour of format_time(), which normally takes an int.
            return format_time($usetime);
        }
        // When downloading just provide the float.
        return $duration;
    }

    /**
     * Returns CLI script return status as a badge.
     *
     * @param int $status
     * @return string
     */
    public static function cli_return_status_display(int $status): string {
        $spanclass = 'badge ' . ($status ? 'badge-danger' : 'badge-success');
        return \html_writer::tag('span', $status, ['class' => $spanclass]);
    }

    /**
     * Returns HTTP status as a badge.
     *
     * @param int $status
     * @return string
     */
    public static function http_status_display(int $status): string {
        $spanclass = 'badge ' . self::STATUS_BADGES[floor($status / 100)];
        return \html_writer::tag('span', $status, ['class' => $spanclass]);
    }

    /**
     * Returns status as a badge.
     *
     * @param string $scripttype
     * @param int $responsecode
     * @return string
     */
    public static function status_display(string $scripttype, int $responsecode): string {
        if ($scripttype == profile::SCRIPTTYPE_TASK) {
            // TODO: A better way needs to be found to determine which kind of response code is being returned.
            if ($responsecode < 100) {
                return self::cli_return_status_display($responsecode);
            } else {
                return self::http_status_display($responsecode);
            }
        } else if ($scripttype == profile::SCRIPTTYPE_CLI) {
            return self::cli_return_status_display($responsecode);
        } else {
            return self::http_status_display($responsecode);
        }
    }

    /**
     * Get the full request of thh profile.
     *
     * @param \stdClass $profile
     * @return string URL
     */
    public static function full_request(\stdClass $profile): string {
        $displayedrequest = $profile->request . $profile->pathinfo;
        if (!empty($profile->parameters)) {
            if ($profile->scripttype == profile::SCRIPTTYPE_CLI) {
                // For CLI scripts, request should look like `command.php --flag=value` as an example.
                $separator = ' ';
                $profile->parameters = escapeshellcmd($profile->parameters);
            } else {
                // For GET requests, request should look like `myrequest.php?myparam=1` as an example.
                $separator = '?';
                $profile->parameters = urldecode($profile->parameters);
            }
            $displayedrequest .= $separator . $profile->parameters;
        }
        return $displayedrequest;
    }

    /**
     * Make a single record for a histogram table.
     * Row is of the form: 2^(k-1) - 2^k : 2^(v-1).
     *
     * @param int $durationexponent The exponent (k) of the high end of the duration range.
     * @param int|null $count The fuzzy count (v), or null if no value.
     * @return array
     */
    private static function make_histogram_record(int $durationexponent, ?int $count = null): array {
        $high = pow(2, $durationexponent);
        $low = ($high === 1) ? 0 : pow(2, $durationexponent - 1);
        $val = isset($count) ? pow(2, $count) : 0;
        return [
            'low'   => $low,
            'high'  => $high,
            'value' => $val,
        ];
    }

    /**
     * Create a histogram table for a page group.
     *
     * @param \stdClass $record Page group's record.
     * @return array
     */
    public static function make_histogram(\stdClass $record): array {
        $counts = json_decode($record->fuzzydurationcounts, true);
        ksort($counts);

        $histogramrecords = [];
        $durationexponent = 0;
        // Generate a line for each duration range up to the highest stored in the DB.
        foreach ($counts as $storeddurationexponent => $fuzzycount) {
            // Fill in lines that do not have stored values.
            while ($durationexponent < $storeddurationexponent) {
                $histogramrecords[] = self::make_histogram_record($durationexponent);
                ++$durationexponent;
            }
            $histogramrecords[] = self::make_histogram_record($storeddurationexponent, $fuzzycount);
            ++$durationexponent; // Ensures this line is not printed twice.
        }
        return $histogramrecords;
    }

    /**
     * Formats a monthint value with the mmm YYYY format. (e.g. Dec 2020).
     *
     * @param int $monthint
     * @return string
     */
    public static function monthint_formatted(int $monthint): string {
        return userdate(monthint::as_timestamp($monthint), get_string('strftime_monyear', 'tool_excimer'));
    }

    /**
     * Returns link HTML which links to the course with corresponding display text.
     * @param mixed $courseid Id of course, or null. If null, returns an empty string.
     * @return string html string.
     */
    public static function course_display_link($courseid = null): string {
        if (empty($courseid)) {
            return '';
        }

        $url = new \moodle_url('/course/view.php', ['id' => $courseid]);
        return \html_writer::link($url, self::course_display_name($courseid));
    }

    /**
     * Returns the display name for a course, even if the course is deleted.
     * @param int $courseid ID of moodle course
     * @return string display name
     */
    public static function course_display_name(int $courseid): string {
        global $DB;

        // Try find course name - it's possible it may not exist anymore.
        $course = $DB->get_record('course', ['id' => $courseid], 'fullname', IGNORE_MISSING);

        if (empty($course)) {
            return get_string('deletedcourse', 'tool_excimer', $courseid);
        }

        return $course->fullname;
    }

    /**
     * Displays the user who locked the profile and the date it was locked.
     * @param profile $profile
     * @return string
     */
    public static function lock_display_modified($profile): string {
        global $DB;

        // Only proceed if there's a lock.
        if (empty($profile->get('lockreason'))) {
            return '';
        }

        // Locks are the only time a profile should be modified after being fully saved.
        $userid = $profile->get('usermodified');
        $modified = $profile->get('timemodified');

        // If we have neither userid or time modified a lock doesn't exist.
        if (empty($userid) && empty($modified)) {
            return '';
        }

        // We don't want to show user modified if it was set before the lock (i.e. during creation).
        // No exact comparison, but can check it's different from created or greater than finished plus a small buffer.
        if ($modified === $profile->get('timecreated') || ($profile->get('finished') + 10) > $modified) {
            return '';
        }

        $user = $DB->get_record('user', ['id' => $userid]);
        if ($user) {
            $link = new \moodle_url('/user/profile.php', ['id' => $userid]);
            $userdisplay = \html_writer::link($link, fullname($user));
        } else {
            $userdisplay = get_string('unknown', 'tool_excimer');
        }
        $date = userdate($modified, get_string('strftimedate', 'core_langconfig'));

        return get_string('lockedinfo', 'tool_excimer', ['user' => $userdisplay, 'date' => $date]);
    }

    /**
     * Returns a HTML link based on the lockwait url.
     * @param string|null $lockwaiturl Relative lockwait url
     * @param float $lockwait Time spent waiting for the lock
     * @return string html string
     */
    public static function lockwait_display_link(?string $lockwaiturl, float $lockwait) {
        if (empty($lockwaiturl)) {
            return ($lockwait > 1) ? get_string('unknown', 'tool_excimer') : '-';
        }

        // Ideally we should link to an excimer profile of the url, but it's more reliable to link to group.
        $profile = new profile();
        $profile->add_env(script_metadata::get_normalised_relative_script_path($lockwaiturl, null));
        $groupurl = new \moodle_url('slowest_web.php?group=' . $profile->get('scriptgroup'));

        // Keep the link text short, but show the full url when hovering over it.
        return \html_writer::link($groupurl, get_string('checkslowest', 'tool_excimer'), ['title' => $lockwaiturl]);
    }

    /**
     * Returns lockwait help formatted after exporting for template.
     * @param \renderer_base $output
     * @param string $lockwaiturl lockwait display link url
     * @return array export for template
     */
    public static function lockwait_display_help(\renderer_base $output, string $lockwaiturl) {
        global $CFG;

        // Only show help information if we have an 'Unknown' url and debug session lock is off.
        if ($lockwaiturl === get_string('unknown', 'tool_excimer') && empty($CFG->debugsessionlock)) {
            $help = new \help_icon('field_lockwaiturl', 'tool_excimer');
            return $help->export_for_template($output);
        }
        return [];
    }
}
