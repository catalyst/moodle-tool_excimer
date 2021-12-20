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

use tool_excimer\output\tabs;

class profile_table_page {

    const SORT_COLUMN = [
        'slowest' => 'duration',
        'recent' => 'created',
    ];

    /**
     * Common display function for the profile table page.
     *
     * @param string $report Report type (slowest, recent etc)
     * @param \moodle_url $url URL of page
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function display(profile_table $table, string $report, \moodle_url $url): void {
        global $PAGE;

        $download = optional_param('download', '', PARAM_ALPHA);

        $context = \context_system::instance();

        $PAGE->set_context($context);
        $PAGE->set_url($url);

        $output = $PAGE->get_renderer('tool_excimer');
        $pluginname = get_string('pluginname', 'tool_excimer');

        $table->is_downloading($download, 'profile', 'profile_record');
        $table->define_baseurl($url);

        if (!$table->is_downloading()) {
            $PAGE->set_title($pluginname);
            $PAGE->set_pagelayout('admin');
            $PAGE->set_heading($pluginname);
            echo $output->header();

            $tabs = new tabs($url);
            echo $output->render_tabs($tabs);

            if (profile::get_num_profiles() > 0) {
                $deleteurl = new \moodle_url('/admin/tool/excimer/delete.php', ['deleteall' => true]);
                $deletebutton = new \single_button($deleteurl, get_string('deleteall'));
                $deletebutton->add_confirm_action(get_string('deleteallwarning', 'tool_excimer'));
                echo $output->render($deletebutton);
            }
        }

        $table->out(40, true); // TODO replace with a value from settings.

        if (!$table->is_downloading()) {
            echo $output->footer();
        }
    }
}
