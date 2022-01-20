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

namespace tool_excimer\check;

use tool_excimer\profile;
use tool_excimer\helper;

use core\check\check;
use core\check\result;

/**
 * A performance check to find the slowest profile.
 *
 * @package    tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slowest extends check {
    /**
     * Links to the profile list ordered by duration.
     *
     * @return \action_link|null
     * @throws \coding_exception
     */
    public function get_action_link(): ?\action_link {
        $url = new \moodle_url('/admin/tool/excimer/slowest.php');
        return new \action_link($url, get_string('checkslowest:action', 'tool_excimer'));
    }

    /**
     * Gets info about the slowest profile.
     *
     * @return result
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_result() : result {
        $profile = profile::get_slowest_profile();
        if ($profile === false) {
            $status = result::OK;
            $summary = get_string('checkslowest:none', 'tool_excimer');
            $details = '';
        } else {
            $profile->duration = format_time($profile->duration);
            $status = result::INFO;
            $summary = get_string('checkslowest:summary', 'tool_excimer', $profile);
            $profile->request = helper::full_request($profile);
            $details = get_string('checkslowest:details', 'tool_excimer', $profile);
        }
        return new result($status, $summary, $details);
    }
}
