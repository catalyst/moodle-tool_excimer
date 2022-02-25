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

/**
 * A CLI script that can be used to manually test the plugin. This complements, but does not replace
 * the unit tests.
 *
 * @package    tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2021 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
define('USE_TRANSACTIONS', false); // Set this to true to test with a transaction.

require(__DIR__.'/../../../../config.php');

$cache = \cache::make('tool_excimer', 'request_metadata');

/**
 * Consumes time to simulate a long task.
 *
 * @param int $cycles
 */
function busy_function(int $cycles) {
    for ($i = 0; $i < $cycles; ++$i) {
        usleep(1);
    }
}

// A closure to add to the function complexity.
$bf = function($c1, $c2) {
    echo "Busy!\n";
    busy_function($c1);
    busy_function($c2);
};

if (USE_TRANSACTIONS) {
    $transaction = $DB->start_delegated_transaction();
}

for ($i = 0; $i < 28; ++$i) {
    $bf(1000, 10000);
}

if (USE_TRANSACTIONS) {
    $transaction->rollback(new Exception('x'));
}
