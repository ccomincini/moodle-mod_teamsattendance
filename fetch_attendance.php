<?php

// This file is part of the Teams Meeting Attendance plugin for Moodle - http://moodle.org/
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
 * Fetch Teams Meeting Attendance
 * 
 * This script fetches attendance data from Microsoft Teams API and updates the database.
 * 
 * Process:
 * 1. Get session data from teamsattendance using cmid
 * 2. Fetch attendance from Teams API
 * 3. For each user:
 *    - Calculate attendance percentage
 *    - Create/update record in teamsattendance_data
 */

require('../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/graph_api.php');

// Check if running from CLI
if (!CLI_SCRIPT) {
    require_login();
    require_sesskey();
}

$cmid = required_param('id', PARAM_INT);
list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'teamsattendance');
$context = context_module::instance($cm->id);

// Check if we have permission to fetch attendance
if (!has_capability('mod/teamsattendance:manageattendance', $context)) {
    throw new moodle_exception('nopermission', 'mod_teamsattendance');
}

try {
    $result = teamsattendance_fetch_attendance($cmid);

    if ($result) {
        redirect(new moodle_url('/mod/teamsattendance/view.php', ['id' => $cmid]), 
                get_string('fetch_attendance_success', 'mod_teamsattendance'));
    } else {
        throw new moodle_exception('attendancefetchfailed', 'mod_teamsattendance');
    }
} catch (Exception $e) {
    debugging('Error fetching attendance: ' . $e->getMessage(), DEBUG_DEVELOPER);
    throw new moodle_exception('attendancefetchfailed', 'mod_teamsattendance', '', $e->getMessage());
}
