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
 * Renderer class for the Excimer plugin
 *
 * @package    tool_excimer
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2021 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render a tabs object.
     *
     * @param tabs $tabs
     * @return string
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function render_tabs(tabs $tabs): string {
        $data = $tabs->export_for_template($this);
        return $this->render_from_template('core/tabtree', $data);
    }
}
