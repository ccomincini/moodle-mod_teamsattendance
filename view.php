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
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * View Teams Meeting Attendance
 * 
 * This page displays attendance records for a Teams meeting.
 * 
 * Data Flow:
 * 1. Get session data from teamsattendance
 * 2. Get attendance records from teamsattendance_data
 * 3. Display attendance data in a table
 */

require('../../config.php');
require_once($CFG->libdir.'/tablelib.php');

$id = required_param('id', PARAM_INT);
list($course, $cm) = get_course_and_cm_from_cmid($id, 'teamsattendance');
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/teamsattendance:view', $context);

$PAGE->set_url('/mod/teamsattendance/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($cm->name));
$PAGE->set_heading(format_string($course->fullname));

// Handle reset automatic assignments action
$action = optional_param('action', '', PARAM_TEXT);
if ($action === 'reset_accepted_suggestions' && confirm_sesskey() && has_capability('mod/teamsattendance:manageattendance', $context)) {
    
    // Get all manually assigned records for this session
    $manual_records = $DB->get_records('teamsattendance_data', [
        'sessionid' => $cm->instance,
        'manually_assigned' => 1
    ]);
    
    $deleted_count = 0;
    
    foreach ($manual_records as $record) {
        // Check if this assignment was made by accepting a suggestion
        $preference_name = 'teamsattendance_suggestion_applied_' . $record->id;
        
        // Check if ANY user has this preference (not just current user)
        $applied_user = $DB->get_field_sql("
            SELECT value FROM {user_preferences} 
            WHERE name = ? AND value = ?
        ", [$preference_name, $record->userid]);
        
        if ($applied_user) {
            // This was a suggestion that was accepted - reset it
            $record->userid = $CFG->siteguest;
            $record->manually_assigned = 0;
            
            if ($DB->update_record('teamsattendance_data', $record)) {
                // Remove the preference to clean up
                $DB->delete_records('user_preferences', ['name' => $preference_name]);
                $deleted_count++;
            }
        }
    }
    
  redirect($PAGE->url, get_string('automatic_assignments_reset', 'mod_teamsattendance', $deleted_count), null, \core\output\notification::NOTIFY_SUCCESS);
}
// Fetch session data
$session = $DB->get_record('teamsattendance', ['id' => $cm->instance], '*', MUST_EXIST);

echo $OUTPUT->header();

echo '<style>
.duration-info, .last-fetch-time { margin-bottom: 1rem; }
.manage-unassigned-link { margin-bottom: 1rem; }
.card { margin-bottom: 2rem; }
</style>';

echo '<div class="card mb-4">';
echo '<div class="card-body">';

echo $OUTPUT->heading($session->name, 2, 'mb-3');

// Duration and required attendance
echo html_writer::div(
    html_writer::tag('strong', get_string('expected_duration', 'mod_teamsattendance') . ': ') .
    format_time($session->expected_duration) . ' ' .
    html_writer::tag('strong', get_string('required_attendance', 'mod_teamsattendance') . ': ') .
    $session->required_attendance . '%',
    'duration-info'
);

// Fetch attendance button
if (has_capability('mod/teamsattendance:manageattendance', $context)) {
    $fetchurl = new moodle_url('/mod/teamsattendance/fetch_attendance.php', [
        'id' => $cm->id,
        'sesskey' => sesskey()
    ]);
    echo $OUTPUT->single_button($fetchurl, get_string('fetch_attendance', 'mod_teamsattendance'), 'get', ['class' => 'mb-3']);
    echo html_writer::div(
	    get_string('fetch_warning', 'mod_teamsattendance')
    );
}

// Last fetch time
if ($session->timemodified) {
    echo html_writer::div(
        get_string('last_fetch_time', 'mod_teamsattendance', userdate($session->timemodified)),
        'last-fetch-time text-muted'
    );
}

// Check for unassigned users
$unassigned_count = $DB->count_records('teamsattendance_data', [
    'sessionid' => $session->id,
    'userid' => $CFG->siteguest,
]);

if ($unassigned_count > 0) {
    echo $OUTPUT->notification(
        get_string('unassigned_users_alert', 'mod_teamsattendance', $unassigned_count),
        'alert alert-warning'
    );
    if (has_capability('mod/teamsattendance:manageattendance', $context)) {
        $manageurl = new moodle_url('/mod/teamsattendance/manage_unassigned.php', ['id' => $cm->id]);
        echo html_writer::div(
            html_writer::link($manageurl, get_string('manage_unassigned', 'mod_teamsattendance'), ['class' => 'btn btn-warning mb-3']),
            'manage-unassigned-link'
        );
    }
}

// Count suggestions that were accepted
$accepted_suggestions = $DB->get_records_sql("
    SELECT tad.id, tad.userid 
    FROM {teamsattendance_data} tad
    WHERE tad.sessionid = ? AND tad.manually_assigned = 1
    AND EXISTS (
        SELECT 1 FROM {user_preferences} up 
        WHERE up.name = CONCAT('teamsattendance_suggestion_applied_', tad.id) COLLATE utf8mb4_unicode_ci
        AND up.value COLLATE utf8mb4_unicode_ci = CAST(tad.userid AS CHAR) COLLATE utf8mb4_unicode_ci
    )
", [$session->id]);
    
    $accepted_count = count($accepted_suggestions);
    
    if ($accepted_count > 0) {
        echo $OUTPUT->notification(
            get_string('automatic_assignments_info', 'mod_teamsattendance', $accepted_count),
            'alert alert-info'
        );
        
        $reseturl = new moodle_url('/mod/teamsattendance/view.php', [
            'id' => $cm->id,
            'action' => 'reset_accepted_suggestions',
            'sesskey' => sesskey()
        ]);
        
        echo html_writer::div(
            html_writer::link($reseturl, get_string('reset_automatic_assignments', 'mod_teamsattendance'), [
                'class' => 'btn btn-warning mb-3',
                'onclick' => 'return confirm("' . get_string('confirm_reset_automatic', 'mod_teamsattendance') . '")'
            ]),
            'reset-suggestions-link'
        );
    }
}
echo '</div></div>'; // Close card


// 2025-06-09 CARLO Add Export button
$exportcsvurl = new moodle_url('/mod/teamsattendance/export_attendance.php', [
    'id' => $cm->id,
    'sesskey' => sesskey() // Include sesskey for security if needed
]);
echo $OUTPUT->single_button($exportcsvurl, get_string('exporttocsv', 'mod_teamsattendance'), 'get', ['class' => 'btn btn-primary mb-3 mr-1']); // Added mr-1 for spacing

$exportxlsxurl = new moodle_url('/mod/teamsattendance/export_attendance_xlsx.php', [ // New script for XLSX
    'id' => $cm->id,
    'sesskey' => sesskey()
]);
echo $OUTPUT->single_button($exportxlsxurl, get_string('exporttoxlsx', 'mod_teamsattendance'), 'get', ['class' => 'btn btn-success mb-3']); // Different class/color for distinction

$table = new flexible_table('teamsattendance-attendance');
// CARLO FINE


$table = new flexible_table('teamsattendance-attendance');
$table->define_columns(['lastname', 'firstname', 'idnumber', 'role', 'total_duration', 'attendance_percentage', 'completion', 'assignment_type']);
$table->define_headers([
    get_string('cognome', 'mod_teamsattendance'),
    get_string('nome', 'mod_teamsattendance'),
    get_string('codice_fiscale', 'mod_teamsattendance'),
    get_string('role', 'mod_teamsattendance'),
    get_string('tempo_totale', 'mod_teamsattendance'),
    get_string('attendance_percentage', 'mod_teamsattendance'),
    get_string('soglia_raggiunta', 'mod_teamsattendance'),
    get_string('assignment_type', 'mod_teamsattendance')
]);
$table->define_baseurl($PAGE->url);
$table->set_attribute('class', 'table table-striped table-hover mb-4');
$table->setup();

// Fetch attendance data from the database
$records = $DB->get_records('teamsattendance_data', ['sessionid' => $session->id]);

foreach ($records as $record) {
    // Skip unassigned users
    if ($record->userid == $CFG->siteguest) {
        continue;
    }
    // Fetch user data
    $user = $DB->get_record('user', ['id' => $record->userid]);
    $expected_duration_seconds = $session->expected_duration; // Convert minutes to seconds
    $attendance_percentage = ($record->attendance_duration / $expected_duration_seconds) * 100;
    
    // Determine assignment type
    $assignment_type = '';
    if ($record->manually_assigned == 1) {
        $assignment_type = '<span class="badge badge-warning" title="' . get_string('manually_assigned_tooltip', 'mod_teamsattendance') . '">' . 
                          get_string('manual', 'mod_teamsattendance') . '</span>';
    } else {
        $assignment_type = '<span class="badge badge-secondary" title="' . get_string('automatically_assigned_tooltip', 'mod_teamsattendance') . '">' . 
                          get_string('automatic', 'mod_teamsattendance') . '</span>';
    }
    
    $table->add_data([
        $user->lastname,
        $user->firstname,
        $user->idnumber,
        $record->role,
        format_time($record->attendance_duration),
        round($attendance_percentage, 1) . '%',
        $record->completion_met ? get_string('yes', 'moodle') : get_string('no', 'moodle'),
        $assignment_type
    ]);
}

$table->finish_output();

echo $OUTPUT->footer();
