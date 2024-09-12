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

namespace tool_excimer\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

/**
 * Import excimer form class.
 *
 * @package   tool_excimer
 * @author    Benjamin Walker <benjaminwalker@catalyst-au.net>
 * @copyright 2024, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends \moodleform {

    /**
     * Build form for importing woekflows.
     *
     * {@inheritDoc}
     * @see \moodleform::definition()
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // Profile file.
        $mform->addElement(
            'filepicker',
            'userfile',
            get_string('profile_file', 'tool_excimer'),
            null,
            ['maxbytes' => $CFG->maxbytes, 'accepted_types' => ['.json']]
        );
        $mform->addRule('userfile', get_string('required'), 'required');

        $this->add_action_buttons();
    }

    /**
     * Validate uploaded json file.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        global $USER;

        $validationerrors = [];

        // Get the file from the filestystem. $files will always be empty.
        $fs = get_file_storage();

        $context = \context_user::instance($USER->id);
        $itemid = $data['userfile'];

        // This is how core gets files in this case.
        if (!$files = $fs->get_area_files($context->id, 'user', 'draft', $itemid, 'id DESC', false)) {
            $validationerrors['nofile'] = get_string('no_profile_file', 'tool_excimer');
            return $validationerrors;
        }
        $file = reset($files);

        // Check if file is valid json.
        $content = $file->get_content();
        if (!empty($content)) {
            json_decode($content);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $validationerrors['userfile'] = json_last_error_msg();
            }
        }

        return $validationerrors;
    }

    /**
     * Get the errors returned during form validation.
     *
     * @return array|mixed
     */
    public function get_errors() {
        $form = $this->_form;
        $errors = $form->_errors;

        return $errors;
    }
}
