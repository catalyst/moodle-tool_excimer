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
    public static function display(string $report, \moodle_url $url): void {
        global $PAGE, $OUTPUT;

        $download = optional_param('download', '', PARAM_ALPHA);

        $context = \context_system::instance();

        $PAGE->set_context($context);
        $PAGE->set_url($url);

        $pluginname = get_string('pluginname', 'tool_excimer');

        $table = new profile_table('profile_table_' . $report);
        $table->sortable(true, self::SORT_COLUMN[$report], SORT_DESC);
        $table->is_downloading($download, 'profile', 'profile_record');
        $table->define_baseurl($url);

        if (!$table->is_downloading()) {
            $PAGE->set_title($pluginname);
            $PAGE->set_pagelayout('admin');
            $PAGE->set_heading($pluginname);
            echo $OUTPUT->header();

            $tabs = self::report_tabs($url);
            echo $OUTPUT->render_from_template('core/tabtree', $tabs);

            if (profile::get_num_profiles() > 0) {
                $deleteurl = new \moodle_url('/admin/tool/excimer/delete.php', ['deleteall' => true]);
                $deletebutton = new \single_button($deleteurl, get_string('deleteall', 'tool_excimer'));
                $deletebutton->add_confirm_action(get_string('deleteallwarning', 'tool_excimer'));
                echo $OUTPUT->render($deletebutton);
            }
        }

        $table->out(40, true); // TODO replace with a value from settings.

        if (!$table->is_downloading()) {
            echo $OUTPUT->footer();
        }
    }

    /**
     * Constructs the tab structure for the page.
     *
     * @param \moodle_url $url The active URL.
     * @return \array[][] Tab structure to draw with the core tab template.
     * @throws \coding_exception
     */
    public static function report_tabs(\moodle_url $url): array {
        $tabs = [
            'tabs' => [
                [
                    'link' => [[ 'link' => new \moodle_url('/admin/tool/excimer/slowest.php') ]],
                    'title' => get_string('report_slowest', 'tool_excimer'),
                    'text' => get_string('slowest', 'tool_excimer')
                ],
                [
                    'link' => [[ 'link' => new \moodle_url('/admin/tool/excimer/recent.php') ]],
                    'title' => get_string('report_recent', 'tool_excimer'),
                    'text' => get_string('recent', 'tool_excimer')
                ]
            ]
        ];

        foreach ($tabs['tabs'] as &$tab) {
            if ($tab['link'][0]['link'] == $url) {
                $tab['active'] = true;
            }
        }
        return $tabs;
    }
}
