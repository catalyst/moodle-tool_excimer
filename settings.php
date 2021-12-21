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

/**
 * Plugin administration pages are defined here.
 *
 * @package   tool_excimer
 * @author    Nigel Chapman <nigelchapman@catalyst-au.net>
 * @copyright 2021, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_excimer\manager;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $ADMIN->add('development', new admin_category('tool_excimer_reports', 'Excimer'));

    $ADMIN->add(
        'tool_excimer_reports',
        new admin_externalpage(
            'tool_excimer_report_slowest',
            get_string('report_slowest', 'tool_excimer'),
            new moodle_url('/admin/tool/excimer/slowest.php'),
            'moodle/site:config'
        )
    );

    $ADMIN->add(
        'tool_excimer_reports',
        new admin_externalpage(
            'tool_excimer_report_slowest_grouped',
            get_string('report_slowest_grouped', 'tool_excimer'),
            new moodle_url('/admin/tool/excimer/slowest_grouped.php'),
            'moodle/site:config'
        )
    );

    $ADMIN->add(
        'tool_excimer_reports',
        new admin_externalpage(
            'tool_excimer_report_recent',
            get_string('report_recent', 'tool_excimer'),
            new moodle_url('/admin/tool/excimer/recent.php'),
            'moodle/site:config'
        )
    );

    $settings = new admin_settingpage(
        'tool_excimer',
        get_string('pluginname', 'tool_excimer')
    );
    $ADMIN->add('tool_excimer_reports', $settings);

    if ($ADMIN->fulltree) {
        $warntext = '';
        if (!class_exists('ExcimerProfiler')) {
            $packageinstallurl = new \moodle_url('https://github.com/catalyst/moodle-tool_excimer#installation');
            $packageinstalllink = html_writer::link($packageinstallurl, get_string('here', 'tool_excimer'), [
                'target' => '_blank',
                'rel' => 'noreferrer noopener',
            ]);
            $warntext  .= $OUTPUT->notification(get_string('noexcimerprofiler', 'tool_excimer', $packageinstalllink));
        }
        $warntext .= get_string('general_settings_desc', 'tool_excimer');
        $settings->add(new admin_setting_heading('tool_excimer/general',
            new lang_string('general_settings', 'tool_excimer'), $warntext));

        $settings->add(
            new admin_setting_configtext(
                'tool_excimer/sample_ms',
                get_string('period_ms', 'tool_excimer'),
                get_string('period_ms_desc', 'tool_excimer'),
                '100',
                PARAM_INT
            )
        );

        $settings->add(
                new admin_setting_configtext(
                        'tool_excimer/long_interval_s',
                        get_string('long_interval_s', 'tool_excimer'),
                        get_string('long_interval_s_desc', 'tool_excimer'),
                        '10',
                        PARAM_FLOAT
                )
        );

        $settings->add(
            new admin_setting_configduration(
                'tool_excimer/expiry_s',
                get_string('expiry_s', 'tool_excimer'),
                get_string('expiry_s_desc', 'tool_excimer'),
                WEEKSECS
            )
        );

        $settings->add(new admin_setting_heading(
            'tool_excimer/auto',
            get_string('auto_settings', 'tool_excimer'),
            get_string('auto_settings_desc', 'tool_excimer'),
        ));

        $settings->add(
            new admin_setting_configcheckbox(
                'tool_excimer/enable_auto',
                get_string('enable_auto', 'tool_excimer'),
                get_string('enable_auto_desc', 'tool_excimer'),
                0,
            )
        );

        $settings->add(
            new admin_setting_configtext(
                'tool_excimer/trigger_ms',
                get_string('request_ms', 'tool_excimer'),
                get_string('request_ms_desc', 'tool_excimer'),
                '100',
                PARAM_INT
            )
        );

        $settings->add(
            new admin_setting_configtext(
                'tool_excimer/num_slowest',
                get_string('num_slowest', 'tool_excimer'),
                get_string('num_slowest_desc', 'tool_excimer'),
                '100',
                PARAM_INT
            )
        );

        $settings->add(
            new admin_setting_configtext(
                'tool_excimer/num_slowest_by_page',
                get_string('num_slowest_by_page', 'tool_excimer'),
                get_string('num_slowest_by_page_desc', 'tool_excimer'),
                '5',
                PARAM_INT
            )
        );
    }
}
