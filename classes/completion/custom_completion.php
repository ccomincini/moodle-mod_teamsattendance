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

namespace mod_teamsattendance\completion;

defined('MOODLE_INTERNAL') || die();

/**
 * Custom completion rules implementation for the Teams Attendance module.
 *
 * @package   mod_teamsattendance
 * @copyright 2025, Invisiblefarm
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends \core_completion\activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     * This is where the core logic for checking your custom rule goes.
     *
     * @param string $rule The completion rule name (e.g., 'completionattendance').
     * @return int The completion state (COMPLETION_COMPLETE, COMPLETION_INCOMPLETE).
     */
    public function get_state(string $rule): int {
        global $DB;

        // Check if this is our supported rule
        if ($rule !== 'completionattendance') {
            return COMPLETION_INCOMPLETE;
        }

        // Get the specific teamsattendance instance
        $teamsattendance_instance = $DB->get_record('teamsattendance', ['id' => $this->cm->instance]);

        if (!$teamsattendance_instance) {
            return COMPLETION_INCOMPLETE;
        }

        // Check if the "Require attendance" rule is actually enabled
        // in the settings of this specific activity instance
        if (empty($teamsattendance_instance->completionattendance)) {
            return COMPLETION_INCOMPLETE;
        }

        // Get the user's attendance data
        $attendance_data = $DB->get_record('teamsattendance_data', [
            'sessionid' => $this->cm->instance,
            'userid'    => $this->userid
        ]);

        if (!$attendance_data) {
            return COMPLETION_INCOMPLETE;
        }

        // Check if completion criteria is met
        // $attendance_data->completion_met is 1 if completed, 0 otherwise
        if (!empty($attendance_data->completion_met) && $attendance_data->completion_met == 1) {
            return COMPLETION_COMPLETE;
        } else {
            return COMPLETION_INCOMPLETE;
        }
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array Array of rule names.
     */
    public static function get_defined_custom_rules(): array {
        return ['completionattendance'];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array Associative array where keys are rule names and values are descriptions.
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completionattendance' => get_string('completionattendance_desc', 'mod_teamsattendance')
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array Array of rule names.
     */
    public function get_sort_order(): array {
        return ['completionattendance'];
    }
}
