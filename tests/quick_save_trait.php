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
 * Trait providing a convenience method to save profile records in tests.
 *
 * @package    tool_excimer
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait quick_save_trait {
    /**
     * A convenience function to save a profile record.
     *
     * @param string $request
     * @param flamed3_node $node
     * @param int $reason
     * @param float $duration
     * @param int $created
     * @param string $lockreason
     * @return int The ID of the record.
     */
    public function quick_save(
        string $request,
        flamed3_node $node,
        int $reason,
        float $duration,
        int $created = 12345,
        string $lockreason = ''
    ): int {
        $profile = new profile();
        $profile->add_env($request);
        $profile->set('reason', $reason);
        $profile->set('flamedatad3', $node);
        $profile->set('created', $created);
        $profile->set('duration', $duration);
        $profile->set('finished', $created + 2);
        $profile->set('lockreason', $lockreason);
        return $profile->save_record();
    }
}
