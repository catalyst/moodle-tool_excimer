<?php
// This file is part of Moodle - https://moodle.org/
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
 * Functions for working with a year and month value stored as an intenger.
 *
 * Representing a month and year as an integer in YYYYMM format. That is,
 * <year> * 100 + <month>.
 *
 * Having a date or a month as an integer allows for convenient usage. Comparisons
 * are simply a matter of comparing integers. Adding and subtracting are also arithmetic,
 * although you will need to take into account the gap between December and January.
 *
 * @package   tool_excimer
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class monthint {

    /** Format to create the value. */
    protected const FORMAT = '%Y%m';
    /** Extra amount to move to go from Dec->Jan or Jan->Dec. */
    protected const DEC_TO_JAN_GAP = 88;

    /**
     * Converts a timestamp into a monthyear.
     *
     * @param int $timestamp
     * @return int
     */
    public static function from_timestamp(int $timestamp): int {
        return (int) userdate($timestamp, self::FORMAT);
    }

    /**
     * Converts a monthyear into a timestamp.
     *
     * @param int $monthyear
     * @return int
     */
    public static function as_timestamp(int $monthyear): int {
        return strtotime($monthyear . '01');
    }

    /**
     * Increments the month, advancing to the next year if we get past December.
     *
     * @param int $monthyear
     * @return int
     */
    public static function increment_month(int $monthyear): int {
        ++$monthyear;
        // If we go beyond December, we need to go forward to the next year, so we add another 88 (100 - 12).
        if (self::month($monthyear) === 13) {
            $monthyear += self::DEC_TO_JAN_GAP;
        }
        return $monthyear;
    }

    /**
     * Decrements the month, receeding to the previous year if we go before January.
     *
     * @param int $monthyear
     * @return int
     */
    public static function decrement_month(int $monthyear): int {
        --$monthyear;
        // If we go to before January, we need to go back to the previous year, so we subtract another 88 (100 - 12).
        if (self::month($monthyear) === 0) {
            $monthyear -= self::DEC_TO_JAN_GAP;
        }
        return $monthyear;
    }

    /**
     * Returns the month portion of the value.
     *
     * @param int $monthyear
     * @return int
     */
    public static function month(int $monthyear): int {
        return $monthyear % 100;
    }
}
