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

    $report = new admin_externalpage(
        'tool_excimer_report',
        get_string('pluginname', 'tool_excimer'),
        new moodle_url('/admin/tool/excimer/index.php'),
        'moodle/site:config'
    );
    $ADMIN->add('reports', $report);

    $settings = new admin_settingpage(
        'tool_excimer',
        get_string('pluginname', 'tool_excimer')
    );
    $ADMIN->add('tools', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'tool_excimer/general',
            get_string('excimergeneral_settings', 'tool_excimer'),
            get_string('excimergeneral_settings_desc', 'tool_excimer'),
        ));

        $settings->add(new admin_setting_configtext(
            'tool_excimer/excimermaxstored',
            get_string('excimermaxstored', 'tool_excimer'),
            get_string('excimermaxstored_desc', 'tool_excimer'),
            '100',
            PARAM_INT
        ));

        $settings->add(
            new admin_setting_configtext(
                'tool_excimer/excimersample_ms',
                get_string('excimerperiod_ms', 'tool_excimer'),
                get_string('excimerperiod_ms_desc', 'tool_excimer'),
                '100',
                PARAM_INT
            )
        );

        $settings->add(
            new admin_setting_configduration(
                'tool_excimer/excimerexpiry_s',
                get_string('excimerexpiry_s', 'tool_excimer'),
                get_string('excimerexpiry_s_desc', 'tool_excimer'),
                WEEKSECS
            )
        );

        $settings->add(new admin_setting_heading(
            'tool_excimer/auto',
            get_string('excimerauto_settings', 'tool_excimer'),
            get_string('excimerauto_settings_desc', 'tool_excimer'),
        ));

        $settings->add(
            new admin_setting_configcheckbox(
                'tool_excimer/excimeranableauto',
                get_string('excimeranableauto', 'tool_excimer'),
                get_string('excimeranableauto_desc', 'tool_excimer'),
                0,
            )
        );

        $settings->add(
            new admin_setting_configtext(
                'tool_excimer/excimertrigger_ms',
                get_string('excimerrequest_ms', 'tool_excimer'),
                get_string('excimerrequest_ms_desc', 'tool_excimer'),
                '100',
                PARAM_INT
            )
        );

        $setting =
        new admin_setting_configtext(
            'tool_excimer/excimernum_slowest',
            get_string('excimernum_slowest', 'tool_excimer'),
            get_string('excimernum_slowest_desc', 'tool_excimer'),
            '5',
            PARAM_INT
        );
        $setting->set_updatedcallback('tool_excimer\manager::on_num_slow_setting_change');
        $settings->add($setting);
    }
}
