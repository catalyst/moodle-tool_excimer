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

use tool_excimer\profile;

/**
 * Delete profiles.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_once($CFG->libdir.'/adminlib.php');

require_admin();

$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
if ($returnurl == '') {
    $returnurl = get_local_referer(false);
}

if (confirm_sesskey()) {
    $deleteall = optional_param('deleteall', 0, PARAM_BOOL);

    if ($deleteall) {
        $DB->delete_records('tool_excimer_profiles');
        redirect($returnurl, get_string('allprofiesdeleted', 'tool_excimer'));
    }

    $deleteid = optional_param('deleteid', 0, PARAM_INT);

    if ($deleteid) {
        $DB->delete_records('tool_excimer_profiles', [ 'id' => $deleteid ]);
        redirect($returnurl, get_string('profiedeleted', 'tool_excimer'));
    }
}

redirect($returnurl);
