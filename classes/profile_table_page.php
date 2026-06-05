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

/**
 * Boilerplate for displaying a profile table.
 *
 * @package tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
        $urlfilter = optional_param('urlfilter', '', PARAM_TEXT);

        if ($urlfilter) {
            $url->param('urlfilter', $urlfilter);
            $table->add_filter_like('request', $urlfilter);
        }

        $context = \context_system::instance();

        $PAGE->set_context($context);
        $PAGE->set_url($url);

        $output = $PAGE->get_renderer('tool_excimer');
        $pluginname = get_string('reportname', 'tool_excimer');

        $table->is_downloading($download, 'profile', 'profile_record');
        $table->define_baseurl($url);
        $table->make_columns();
        $table->show_download_buttons_at([TABLE_P_BOTTOM]);

        if (!$table->is_downloading()) {
            $PAGE->set_title($pluginname);
            $PAGE->set_pagelayout('admin');
            $PAGE->set_heading($pluginname);
            echo $output->header();

            $tabs = new tabs($url);
            echo $output->render_tabs($tabs);

            $deleteallbutton = '';
            $isfiltered = $url->get_param('group') || $url->get_param('script');
            if (!$isfiltered && profile_helper::get_num_profiles() > 0) {
                $deleteurl = new \moodle_url('/admin/tool/excimer/delete.php', ['deleteall' => true]);
                $deleteallbutton = self::render_delete_button(
                    $deleteurl,
                    get_string('deleteall'),
                    get_string('deleteallwarning', 'tool_excimer')
                );
            }
            echo self::render_filter_form($url, $urlfilter, $table->get_filters(), $deleteallbutton);
        }

        $table->out(40, true); // TODO replace with a value from settings.

        if (!$table->is_downloading()) {
            echo $output->footer();
        }
    }

    /**
     * Renders a URL filter search form.
     *
     * @param \moodle_url $url The base URL for the form action (should include any non-filter params).
     * @param string $current The current filter value.
     * @param array $filters Exact-match filters from the table, used to render the delete button.
     * @return string HTML
     */
    private static function render_filter_form(
        \moodle_url $url,
        string $current,
        array $filters = [],
        string $deleteallbutton = ''
    ): string {
        // Build a clean URL with all params except urlfilter, for use as hidden fields.
        $actionurl = clone $url;
        $actionurl->remove_params('urlfilter');
        $params = $actionurl->params();

        $hidden = '';
        foreach ($params as $name => $value) {
            $hidden .= \html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => $name,
                'value' => $value,
            ]);
        }

        $input = \html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'urlfilter',
            'value' => $current,
            'placeholder' => get_string('filter_url_placeholder', 'tool_excimer'),
            'class' => 'form-control mr-2',
            'style' => 'max-width: 400px',
        ]);

        $submit = \html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('filter', 'moodle'),
            'class' => 'btn btn-secondary mr-2',
        ]);

        $clear = '';
        if ($current !== '') {
            $clear = \html_writer::link(
                $actionurl,
                get_string('clear', 'tool_excimer'),
                ['class' => 'btn btn-link mr-2']
            );
        }

        $deletebutton = '';
        if (!empty($filters)) {
            $deleteurl = new \moodle_url('/admin/tool/excimer/delete.php', ['filter' => json_encode($filters)]);
            $deletebutton = self::render_delete_button(
                $deleteurl,
                get_string('deleteprofiles_filter', 'tool_excimer'),
                get_string('deleteprofiles_filter_warning', 'tool_excimer')
            );
        }

        $innerdiv = \html_writer::tag(
            'div',
            $input . $submit . $clear,
            ['class' => 'd-flex align-items-center flex-wrap']
        );
        $form = \html_writer::tag(
            'form',
            $hidden . $innerdiv,
            ['method' => 'get', 'action' => $actionurl->out_omit_querystring()]
        );

        return \html_writer::tag(
            'div',
            $form . $deletebutton . $deleteallbutton,
            ['class' => 'd-flex align-items-center flex-wrap gap-2 mb-3']
        );
    }

    /**
     * Renders a delete button as a self-contained POST form with a bin icon and JS confirm.
     *
     * @param \moodle_url $url The delete action URL (params become hidden fields).
     * @param string $label Button label text.
     * @param string $confirm Confirmation message shown before proceeding.
     * @return string HTML
     */
    private static function render_delete_button(\moodle_url $url, string $label, string $confirm): string {
        $icon = \html_writer::tag('i', '', ['class' => 'fa fa-trash mr-1', 'aria-hidden' => 'true']);
        $button = \html_writer::tag('button', $icon . $label, [
            'type' => 'submit',
            'class' => 'btn btn-secondary',
            'onclick' => 'return confirm(' . json_encode($confirm) . ')',
        ]);
        $hidden = \html_writer::empty_tag('input', [
            'type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey(),
        ]);
        foreach ($url->params() as $name => $value) {
            $hidden .= \html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => $name, 'value' => $value,
            ]);
        }
        return \html_writer::tag('form', $hidden . $button, [
            'method' => 'post',
            'action' => $url->out_omit_querystring(),
        ]);
    }
}
