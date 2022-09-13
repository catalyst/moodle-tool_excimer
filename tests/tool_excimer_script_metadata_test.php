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

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests strip_parameters
     *
     * @covers \tool_excimer\script_metadata::stripparameters
     */
    public function test_strip_parameters() {
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
     * Tests get_parameters()
     *
     * @dataProvider get_parameters_provider
     * @covers \tool_excimer\script_metadata::get_parameters
     * @param string $querystring
     * @param string $expected
     */
    public function test_get_parameters(string $querystring, string $expected) {
        global $ME;

        $globalme = $ME ?? null;
        $ME = 'abc.php?' . $querystring;
        $params = script_metadata::get_parameters(profile::SCRIPTTYPE_WEB);
        $this->assertEquals($expected, $params);
        $ME = $globalme;

        $serverquerystring = $_SERVER['QUERY_STRING'] ?? null;
        $_SERVER['QUERY_STRING'] = $querystring;
        $params = script_metadata::get_parameters(profile::SCRIPTTYPE_WEB);
        $this->assertEquals($expected, $params);
        $_SERVER['QUERY_STRING'] = $serverquerystring;
    }

    /**
     * Input values for test_get_parameters().
     *
     * @return \string[][]
     */
    public function get_parameters_provider(): array {
        $args = [
            ['a=1&b=2&c=3', 'a=1&b=2&c=3'],
        ];
        foreach (script_metadata::DENYLIST as $tobedenied) {
            $args[] = ['a=1&b=2&' . $tobedenied . '=1', 'a=1&b=2'];
        }
        foreach (script_metadata::REDACTLIST as $toberedacted) {
            $args[] = ['a=1&b=2&' . $toberedacted . '=1', 'a=1&b=2&' . $toberedacted . '='];
        }
        return $args;
    }

    /**
     * Test script_metadata::get_groupby_value().
     *
     * @dataProvider group_by_value_provider
     * @covers \tool_excimer\script_metadata::get_groupby_value
     * @param string $request
     * @param string $pathinfo
     * @param string $parameters
     * @param string $expected
     */
    public function test_get_groupby_value(string $request, string $pathinfo, string $parameters, string $expected) {
        $profile = new profile();
        $profile->set('request', $request);
        $profile->set('pathinfo', $pathinfo);
        $profile->set('parameters', $parameters);
        $group = script_metadata::get_groupby_value($profile);
        $this->assertEquals($expected, $group);
    }

    /**
     * Input values for test_get_groupby_value.
     *
     * @return \string[][]
     */
    public function group_by_value_provider(): array {
        return [
            ['admin/index.php', '', '', 'admin/index.php'],
            ['admin/index.php', '/a/54/c', '', 'admin/index.php/a/x/c'],
            ['admin/index.php', '', 'a=1&b&c=3', 'admin/index.php?a=&b&c='],
            ['admin/index.php', '/1/2/3/', 'a=1&b&c=3', 'admin/index.php/x/x/x/?a=&b&c='],
            ['pluginfile.php', '/12/mod/book/3242/3/tool.png', '', 'pluginfile.php/x/mod/book/xxx'],
        ];
    }

    /**
     * Tests script_metadata::get_samplelimit().
     *
     * @dataProvider sampling_limit_provider
     * @covers \tool_excimer\script_metadata::get_sample_limit
     * @param int $limit
     * @param int $expected
     */
    public function test_get_sample_limit(int $limit, int $expected) {
        $this->preventResetByRollback();
        set_config('samplelimit', $limit, 'tool_excimer');
        $this->assertEquals($expected, script_metadata::get_sample_limit());
    }

    /**
     * Input values for test_get_samplelimit().
     *
     * @return \int[][]
     */
    public function sampling_limit_provider(): array {
        return [
            [ 0, 1024 ],
            [ 1, 1 ],
            [ 1024, 1024 ],
            [ 10000, 10000 ],
            [ -1, 1024 ],
        ];
    }
}
