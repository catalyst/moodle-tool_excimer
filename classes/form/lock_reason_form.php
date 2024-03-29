<?php
// This file is part of Moodle - https://moodle.org/
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

namespace tool_excimer\form;

/**
 * form for editing the lock reason on profiles.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lock_reason_form extends \moodleform {

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        // Get returnurl if not coming from the lock page.
        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_LOCALURL);
        $mform->setDefault('returnurl', get_local_referer(false));

        $mform->addElement('textarea', 'lockreason', get_string('lockreason', 'tool_excimer'),
            ['rows' => '4', 'style' => 'width:100%']);

        $mform->addElement('static', 'lockreason_help', '', get_string('lockreason_help', 'tool_excimer'));

        $this->add_action_buttons(false);
    }
}
