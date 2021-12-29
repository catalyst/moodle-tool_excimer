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

    /**
     * Common display function for reports.
     *
     * @param profile_table $table Report table
     * @param \moodle_url $url Current URL
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function display(profile_table $table, \moodle_url $url): void {
        global $PAGE;

        $download = optional_param('download', '', PARAM_ALPHA);

        $context = \context_system::instance();

        $PAGE->set_context($context);
        $PAGE->set_url($url);

        $output = $PAGE->get_renderer('tool_excimer');
        $pluginname = get_string('pluginname', 'tool_excimer');

        $table->is_downloading($download, 'profile', 'profile_record');
        $table->define_baseurl($url);
        $table->make_columns();

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
            $filters = $table->get_filters();
            if (!empty($filters)) {
                $deleteurl = new \moodle_url('/admin/tool/excimer/delete.php', ['filter' => json_encode($filters)]);
                $deletebutton = new \single_button($deleteurl, get_string('deleteprofiles_filter', 'tool_excimer'));
                $deletebutton->add_confirm_action(get_string('deleteprofiles_filter_warning', 'tool_excimer'));
                echo $output->render($deletebutton);
            }
        }

        $table->out(40, true); // TODO replace with a value from settings.

        if (!$table->is_downloading()) {
            echo $output->footer();
        }
    }
}
