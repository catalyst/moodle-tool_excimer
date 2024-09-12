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

use tool_excimer\script_metadata;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('modules', new admin_category('tool_excimer_settings', get_string('adminname', 'tool_excimer')));
    $ADMIN->add('development', new admin_category('tool_excimer_reports', get_string('adminname', 'tool_excimer')));

    $settings = new admin_settingpage(
        'tool_excimer',
        get_string('pluginname', 'tool_excimer')
    );
    $ADMIN->add('tools', $settings);

    $reports = new admin_settingpage(
        'tool_excimer_reports',
        get_string('reportname', 'tool_excimer')
    );

    if ($ADMIN->fulltree) {
        // Ensure if particular setting(s) are updated, the cache for profile
        // request metadata is cleared, at most once.
        $clearprofiletimingscachecallback = function () {
            static $called = false;
            if (!$called) {
                $called = true;
                // Clear the profile request_metadata caches.
                $cache = \cache::make('tool_excimer', 'request_metadata');
                $cache->purge();
            }
        };

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
                get_string('period_ms_desc', 'tool_excimer',
                    ['min' => script_metadata::SAMPLING_PERIOD_MIN * 1000, 'max' => script_metadata::SAMPLING_PERIOD_MAX * 1000]),
                '10',
                PARAM_INT
            )
        );

        $settings->add(
            new admin_setting_configtext(
                'tool_excimer/long_interval_s',
                get_string('long_interval_s', 'tool_excimer'),
                get_string('long_interval_s_desc', 'tool_excimer'),
                '10',
                PARAM_INT
            )
        );

        $settings->add(
            new admin_setting_configtext(
                'tool_excimer/samplelimit',
                get_string('samplelimit', 'tool_excimer'),
                get_string('samplelimit_desc', 'tool_excimer'),
                '1024',
                PARAM_INT
            )
        );

        $settings->add(
            new admin_setting_configtext(
                'tool_excimer/stacklimit',
                get_string('stacklimit', 'tool_excimer'),
                get_string('stacklimit_desc', 'tool_excimer'),
                '1000',
                PARAM_INT
            )
        );

        $settings->add(
            new admin_setting_configduration(
                'tool_excimer/expiry_s',
                get_string('expiry_s', 'tool_excimer'),
                get_string('expiry_s_desc', 'tool_excimer'),
                30 * DAYSECS
            )
        );

        $settings->add(
            new admin_setting_configtext(
                'tool_excimer/expiry_fuzzy_counts',
                get_string('expiry_fuzzy_counts', 'tool_excimer'),
                get_string('expiry_fuzzy_counts_desc', 'tool_excimer'),
                12,
                PARAM_INT
            )
        );

        // Get a list of builtin redactable params, quoted and separated by commas.
        $builtin = implode(
            ', ',
            array_map(
                function ($v) {
                    return "'$v'";
                },
                \tool_excimer\script_metadata::REDACT_LIST
            )
        );
        $settings->add(
            new admin_setting_configtextarea(
                'tool_excimer/redact_params',
                get_string('redact_params', 'tool_excimer'),
                get_string('redact_params_desc', 'tool_excimer', $builtin),
                '',
                PARAM_TEXT
            )
        );

        $settings->add(new admin_setting_heading(
            'tool_excimer/auto',
            get_string('auto_settings', 'tool_excimer'),
            get_string('auto_settings_desc', 'tool_excimer')
        ));

        $settings->add(
            new admin_setting_configcheckbox(
                'tool_excimer/enable_auto',
                get_string('enable_auto', 'tool_excimer'),
                get_string('enable_auto_desc', 'tool_excimer'),
                1
            )
        );

        $wikilink = html_writer::link(
            \tool_excimer\manager::APPROX_ALGO_WIKI_URL,
            get_string('approx_count_algorithm', 'tool_excimer')
        );
        $settings->add(
            new admin_setting_configcheckbox(
                'tool_excimer/enable_fuzzy_count',
                get_string('enable_fuzzy_count', 'tool_excimer'),
                get_string('enable_fuzzy_count_desc', 'tool_excimer', $wikilink),
                1
            )
        );

        $settings->add(
            new admin_setting_configcheckbox(
                'tool_excimer/enable_partial_save',
                get_string('enable_partial_save', 'tool_excimer'),
                get_string('enable_partial_save_desc', 'tool_excimer'),
                0
            )
        );

        $item = new admin_setting_configtext(
            'tool_excimer/trigger_ms',
            get_string('request_ms', 'tool_excimer'),
            get_string('request_ms_desc', 'tool_excimer'),
            '1000',
            PARAM_INT
        );
        $item->set_updatedcallback($clearprofiletimingscachecallback);
        $settings->add($item);

        $item = new admin_setting_configtext(
            'tool_excimer/task_min_duration',
            get_string('task_min_duration', 'tool_excimer'),
            get_string('task_min_duration_desc', 'tool_excimer'),
            '60',
            PARAM_INT
        );
        $item->set_updatedcallback($clearprofiletimingscachecallback);
        $settings->add($item);

        $item = new admin_setting_configtext(
            'tool_excimer/num_slowest',
            get_string('num_slowest', 'tool_excimer'),
            get_string('num_slowest_desc', 'tool_excimer'),
            '1000',
            PARAM_INT
        );
        $item->set_updatedcallback($clearprofiletimingscachecallback);
        $settings->add($item);

        $item = new admin_setting_configtext(
            'tool_excimer/num_slowest_by_page',
            get_string('num_slowest_by_page', 'tool_excimer'),
            get_string('num_slowest_by_page_desc', 'tool_excimer'),
            '5',
            PARAM_INT
        );
        $item->set_updatedcallback($clearprofiletimingscachecallback);
        $settings->add($item);
    }

    $ADMIN->add(
        'tool_excimer_reports',
        new admin_externalpage(
            'tool_excimer_report_slowest_web',
            get_string('report_slowest_web', 'tool_excimer'),
            new moodle_url('/admin/tool/excimer/slowest_web.php'),
            'moodle/site:config'
        )
    );

    $ADMIN->add(
        'tool_excimer_reports',
        new admin_externalpage(
            'tool_excimer_report_slowest_other',
            get_string('report_slowest_other', 'tool_excimer'),
            new moodle_url('/admin/tool/excimer/slowest_other.php'),
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

    $ADMIN->add(
        'tool_excimer_reports',
        new admin_externalpage(
            'tool_excimer_report_unfinished',
            get_string('report_unfinished', 'tool_excimer'),
            new moodle_url('/admin/tool/excimer/unfinished.php'),
            'moodle/site:config'
        )
    );

    $ADMIN->add(
        'tool_excimer_reports',
        new admin_externalpage(
            'tool_excimer_report_page_groups',
            get_string('report_page_groups', 'tool_excimer'),
            new moodle_url('/admin/tool/excimer/page_groups.php'),
            'moodle/site:config'
        )
    );

    $ADMIN->add(
        'tool_excimer_reports',
        new admin_externalpage(
            'tool_excimer_report_page_slow_course',
            get_string('report_page_slow_course', 'tool_excimer'),
            new moodle_url('/admin/tool/excimer/slow_course.php'),
            'moodle/site:config'
        )
    );

    $ADMIN->add(
        'tool_excimer_reports',
        new admin_externalpage(
            'tool_excimer_report_session_locks',
            get_string('report_session_locks', 'tool_excimer'),
            new moodle_url('/admin/tool/excimer/session_locks.php'),
            'moodle/site:config'
        )
    );

    $ADMIN->add(
        'tool_excimer_reports',
        new admin_externalpage(
            'tool_excimer_import_profile',
            get_string('import_profile', 'tool_excimer'),
            new moodle_url('/admin/tool/excimer/import.php'),
            'moodle/site:config'
        )
    );
}
