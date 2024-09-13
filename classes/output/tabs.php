<?php
// This file is part of Moodle - http://moodle.org/  <--change
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

namespace tool_excimer\output;

/**
 * Tabs component used for plugin.
 *
 * @package    tool_excimer
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2021 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tabs implements \templatable {

    /** @var \moodle_url The current URL. */
    protected $activeurl;

    /**
     * Construct a tabs object.
     *
     * @param \moodle_url $activeurl
     */
    public function __construct(\moodle_url $activeurl) {
        $this->activeurl = $activeurl;
    }

    /**
     * Exports tabs for use with a template.
     *
     * @param \renderer_base $output
     * @return \array[][]
     * @throws \coding_exception
     */
    public function export_for_template(\renderer_base $output): array {
        $tabs = [
            'tabs' => [
                [
                    'id' => 'slowest_web',
                    'link' => [['link' => new \moodle_url('/admin/tool/excimer/slowest_web.php')]],
                    'title' => get_string('report_slowest_web', 'tool_excimer'),
                    'text' => get_string('tab_slowest_web', 'tool_excimer'),
                ],
                [
                    'id' => 'slowest_other',
                    'link' => [['link' => new \moodle_url('/admin/tool/excimer/slowest_other.php')]],
                    'title' => get_string('report_slowest_other', 'tool_excimer'),
                    'text' => get_string('tab_slowest_other', 'tool_excimer'),
                ],
                [
                    'id' => 'course',
                    'link' => [['link' => new \moodle_url('/admin/tool/excimer/slow_course.php')]],
                    'title' => get_string('tab_page_course', 'tool_excimer'),
                    'text' => get_string('tab_page_course', 'tool_excimer'),
                ],
                [
                    'id' => 'session_locks',
                    'link' => [['link' => new \moodle_url('/admin/tool/excimer/session_locks.php')]],
                    'title' => get_string('report_session_locks', 'tool_excimer'),
                    'text' => get_string('tab_session_locks', 'tool_excimer'),
                ],
                [
                    'id' => 'recent',
                    'link' => [['link' => new \moodle_url('/admin/tool/excimer/recent.php')]],
                    'title' => get_string('report_recent', 'tool_excimer'),
                    'text' => get_string('recent', 'tool_excimer'),
                ],
                [
                    'id' => 'unfinished',
                    'link' => [['link' => new \moodle_url('/admin/tool/excimer/unfinished.php')]],
                    'title' => get_string('report_unfinished', 'tool_excimer'),
                    'text' => get_string('unfinished', 'tool_excimer'),
                ],
                [
                    'id' => 'page_groups',
                    'link' => [['link' => new \moodle_url('/admin/tool/excimer/page_groups.php')]],
                    'title' => get_string('report_page_groups', 'tool_excimer'),
                    'text' => get_string('tab_page_groups', 'tool_excimer'),
                ],
                [
                    'id' => 'import',
                    'link' => [['link' => new \moodle_url('/admin/tool/excimer/import.php')]],
                    'title' => get_string('import_profile', 'tool_excimer'),
                    'text' => get_string('tab_import', 'tool_excimer'),
                ],
            ],
        ];

        foreach ($tabs['tabs'] as &$tab) {
            if ($tab['link'][0]['link']->get_path(false) == $this->activeurl->get_path(false)) {
                $tab['active'] = true;
            }
        }
        return $tabs;
    }
}
