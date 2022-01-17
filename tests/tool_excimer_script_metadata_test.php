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

/**
 * Units tests for the scriptmetadata class.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_excimer_script_metadata_test extends \advanced_testcase {

    public function test_stripparamters(): void {
        $param = ['a' => '1', 'b' => 2, 'c' => 3];
        $paramexpect = $param;
        $this->assertEquals($paramexpect, script_metadata::stripparameters($param));

        $param = ['a' => '1', 'sesskey' => 2, 'c' => 3];
        $paramexpect = ['a' => '1', 'sesskey' => '', 'c' => 3];
        $this->assertEquals($paramexpect, script_metadata::stripparameters($param));

        $param = ['a' => '1', 'sesskey' => 2, 'FLAMEME' => 3];
        $paramexpect = ['a' => '1', 'sesskey' => ''];
        $this->assertEquals($paramexpect, script_metadata::stripparameters($param));
    }

    /**
     * @dataProvider group_by_value_provider
     * @throws \coding_exception
     */
    public function test_get_groupby_value($request, $pathinfo, $parameters, $expected): void {
        $profile = new profile();
        $profile->set('request', $request);
        $profile->set('pathinfo', $pathinfo);
        $profile->set('parameters', $parameters);
        $group = script_metadata::get_groupby_value($profile);
        $this->assertEquals($expected, $group);
    }

    public function group_by_value_provider(): array {
        return [
            ['admin/index.php', '', '', 'admin/index.php'],
            ['admin/index.php', '/a/54/c', '', 'admin/index.php/a/x/c'],
            ['admin/index.php', '', 'a=1&b&c=3', 'admin/index.php?a=&b&c='],
            ['admin/index.php', '/1/2/3/', 'a=1&b&c=3', 'admin/index.php/x/x/x/?a=&b&c='],
            ['pluginfile.php', '/12/mod/book/3242/3/tool.png', '', 'pluginfile.php/x/mod/book/xxx'],
        ];
    }

}
