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
    
    $manual_records = $DB->get_records('teamsattendance_data', [
        'sessionid' => $cm->instance,
        'manually_assigned' => 1
    ]);
    
    $deleted_count = 0;
    
    if (!empty($manual_records)) {
        // Generate current suggestions
        $context_course = context_course::instance($course->id);
        $enrolled_users = get_enrolled_users($context_course, '', 0, 'u.id, u.firstname, u.lastname, u.email');
        
        require_once($CFG->dirroot . '/mod/teamsattendance/classes/suggestion_engine.php');
        $suggestion_engine = new suggestion_engine($enrolled_users);
        $suggestions = $suggestion_engine->generate_suggestions($manual_records);
        
        foreach ($manual_records as $record) {
            // If current assignment matches suggestion, it's likely from a suggestion
            if (isset($suggestions[$record->id]) && 
                $suggestions[$record->id]['user']->id == $record->userid) {
                
                $record->userid = $CFG->siteguest;
                $record->manually_assigned = 0;
                
                if ($DB->update_record('teamsattendance_data', $record)) {
                    $deleted_count++;
                }
            }
        }
    }
    
    redirect($PAGE->url, get_string('suggestion_assignments_reset', 'mod_teamsattendance', $deleted_count), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Fetch session data
$session = $DB->get_record('teamsattendance', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->requires->css('/mod/teamsattendance/styles/view_attendance.css');

echo $OUTPUT->header();

echo '<div class="mod_teamsattendance">';

// Session info card
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

// Last fetch time
if ($session->timemodified) {
    echo html_writer::div(
        get_string('last_fetch_time', 'mod_teamsattendance', userdate($session->timemodified)),
        'last-fetch-time text-muted mb-3'
    );
}
echo '</div></div>';

// Get statistics for counters
$total_records = $DB->count_records('teamsattendance_data', ['sessionid' => $session->id]);
$automatic_records = $DB->count_records('teamsattendance_data', [
    'sessionid' => $session->id,
    'manually_assigned' => 0
]);
$manual_records = $DB->count_records('teamsattendance_data', [
    'sessionid' => $session->id,
    'manually_assigned' => 1
]);
$unassigned_count = $DB->count_records('teamsattendance_data', [
    'sessionid' => $session->id,
    'userid' => $CFG->siteguest,
]);

// Statistics counters
echo '<div class="stats-container">';
echo '<div class="stats-card card-total">';
echo '<h4>' . get_string('total_records', 'mod_teamsattendance') . '</h4>';
echo '<div class="metric">' . $total_records . '</div>';
echo '</div>';

echo '<div class="stats-card card-automatic">';
echo '<h4>' . get_string('automatic', 'mod_teamsattendance') . '</h4>';
echo '<div class="metric">' . $automatic_records . '</div>';
echo '</div>';

echo '<div class="stats-card card-manual">';
echo '<h4>' . get_string('manual', 'mod_teamsattendance') . '</h4>';
echo '<div class="metric">' . $manual_records . '</div>';
echo '</div>';

echo '<div class="stats-card card-unassigned">';
echo '<h4>' . get_string('unassigned_records', 'mod_teamsattendance') . '</h4>';
echo '<div class="metric">' . $unassigned_count . '</div>';
echo '</div>';
echo '</div>';

// Action buttons in cards
echo '<div class="action-buttons-container">';

// Fetch attendance card
if (has_capability('mod/teamsattendance:manageattendance', $context)) {
    echo '<div class="action-card card-fetch">';
    echo '<h4>' . get_string('fetch_attendance', 'mod_teamsattendance') . '</h4>';
    echo '<p>' . get_string('fetch_warning', 'mod_teamsattendance') . '</p>';
    $fetchurl = new moodle_url('/mod/teamsattendance/fetch_attendance.php', [
        'id' => $cm->id,
        'sesskey' => sesskey()
    ]);
    echo $OUTPUT->single_button($fetchurl, get_string('fetch_attendance', 'mod_teamsattendance'), 'get', ['class' => 'btn btn-light']);
    echo '</div>';
}

// Manage unassigned card
if ($unassigned_count > 0 && has_capability('mod/teamsattendance:manageattendance', $context)) {
    echo '<div class="action-card card-manage">';
    echo '<h4>' . get_string('manage_unassigned', 'mod_teamsattendance') . '</h4>';
    echo '<p>' . get_string('unassigned_users_alert', 'mod_teamsattendance', $unassigned_count) . '</p>';
    $manageurl = new moodle_url('/mod/teamsattendance/manage_unassigned.php', ['id' => $cm->id]);
    echo html_writer::link($manageurl, get_string('manage_unassigned', 'mod_teamsattendance'), ['class' => 'btn btn-light']);
    echo '</div>';
}

// Reset manual assignments card
if (has_capability('mod/teamsattendance:manageattendance', $context)) {
    $manual_records_count = $DB->count_records('teamsattendance_data', [
        'sessionid' => $session->id,
        'manually_assigned' => 1
    ]);
    
    if ($manual_records_count > 0) {
        echo '<div class="action-card card-reset">';
        echo '<h4>' . get_string('reset_manual_assignments', 'mod_teamsattendance') . '</h4>';
        echo '<p>' . get_string('manual_assignments_info', 'mod_teamsattendance', $manual_records_count) . '</p>';
        
        $reseturl = new moodle_url('/mod/teamsattendance/view.php', [
            'id' => $cm->id,
            'action' => 'reset_accepted_suggestions',
            'sesskey' => sesskey()
        ]);
        
        echo html_writer::link($reseturl, get_string('reset_manual_assignments', 'mod_teamsattendance'), [
            'class' => 'btn btn-light',
            'onclick' => 'return confirm("' . get_string('confirm_reset_manual_assignments', 'mod_teamsattendance') . '")'
        ]);
        echo '</div>';
    }
}

echo '</div>'; // Close action-buttons-container


// Export buttons in card
echo '<div class="export-buttons-container">';
echo '<div class="action-card card-export">';
echo '<h4>Export Data</h4>';

$exportcsvurl = new moodle_url('/mod/teamsattendance/export_attendance.php', [
    'id' => $cm->id,
    'sesskey' => sesskey()
]);
echo $OUTPUT->single_button($exportcsvurl, get_string('exporttocsv', 'mod_teamsattendance'), 'get', ['class' => 'btn btn-light mr-2']);

$exportxlsxurl = new moodle_url('/mod/teamsattendance/export_attendance_xlsx.php', [
    'id' => $cm->id,
    'sesskey' => sesskey()
]);
echo $OUTPUT->single_button($exportxlsxurl, get_string('exporttoxlsx', 'mod_teamsattendance'), 'get', ['class' => 'btn btn-light']);
echo '</div>';
echo '</div>';


$table = new flexible_table('teamsattendance-attendance');
$table->define_columns(['lastname', 'firstname', 'idnumber', 'role', 'total_duration', 'attendance_percentage', 'completion', 'assignment_type']);
$table->define_headers([
    get_string('cognome', 'mod_teamsattendance'),
    get_string('nome', 'mod_teamsattendance'),
    get_string('codice_identificativo', 'mod_teamsattendance'),
    get_string('role', 'mod_teamsattendance'),
    get_string('tempo_totale', 'mod_teamsattendance'),
    get_string('attendance_percentage', 'mod_teamsattendance'),
    get_string('soglia_raggiunta', 'mod_teamsattendance'),
    get_string('assignment_type', 'mod_teamsattendance')
]);
$table->define_baseurl($PAGE->url);
$table->set_attribute('class', 'table table-striped table-hover mb-4');
$table->set_attribute('id', 'attendance-table');
$table->sortable(true, 'lastname', SORT_ASC);
$table->no_sorting('assignment_type');
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
    $expected_duration_seconds = $session->expected_duration;
    $attendance_percentage = ($record->attendance_duration / $expected_duration_seconds) * 100;
    
    // Determine assignment type and row class
    $assignment_type = '';
    $row_class = '';
    if ($record->manually_assigned == 1) {
        $assignment_type = '<span class="badge badge-warning" title="' . get_string('manually_assigned_tooltip', 'mod_teamsattendance') . '">' . 
                          get_string('manual', 'mod_teamsattendance') . '</span>';
        $row_class = 'manual-assignment';
    } else {
        $assignment_type = '<span class="badge badge-success" title="' . get_string('automatically_assigned_tooltip', 'mod_teamsattendance') . '">' . 
                          get_string('automatic', 'mod_teamsattendance') . '</span>';
        $row_class = 'automatic-assignment';
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
    ], $row_class);
}

$table->finish_output();

echo '</div>'; // Close mod_teamsattendance wrapper

// Add JavaScript for enhanced table sorting
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    const table = document.getElementById("attendance-table");
    if (!table) return;
    
    const headers = table.querySelectorAll("thead th");
    
    headers.forEach((header, index) => {
        if (!header.classList.contains("no-sort")) {
            header.classList.add("sortable");
            header.style.cursor = "pointer";
            
            header.addEventListener("click", function() {
                sortTable(table, index, header);
            });
        }
    });
    
    function sortTable(table, columnIndex, header) {
        const tbody = table.querySelector("tbody");
        const rows = Array.from(tbody.querySelectorAll("tr"));
        
        // Determine sort direction
        const isAsc = !header.classList.contains("sort-asc");
        
        // Clear all sort classes
        headers.forEach(h => h.classList.remove("sort-asc", "sort-desc"));
        
        // Add appropriate sort class
        header.classList.add(isAsc ? "sort-asc" : "sort-desc");
        
        // Sort rows
        rows.sort((a, b) => {
            const aVal = a.cells[columnIndex].textContent.trim();
            const bVal = b.cells[columnIndex].textContent.trim();
            
            // Handle percentage values
            if (aVal.includes("%") && bVal.includes("%")) {
                const aNum = parseFloat(aVal.replace("%", ""));
                const bNum = parseFloat(bVal.replace("%", ""));
                return isAsc ? aNum - bNum : bNum - aNum;
            }
            
            // Handle time values (format: "X ore Y min.")
            if (aVal.includes("min") && bVal.includes("min")) {
                const aSeconds = parseTimeToSeconds(aVal);
                const bSeconds = parseTimeToSeconds(bVal);
                return isAsc ? aSeconds - bSeconds : bSeconds - aSeconds;
            }
            
            // Default string comparison
            return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        });
        
        // Re-append sorted rows
        rows.forEach(row => tbody.appendChild(row));
    }
    
    function parseTimeToSeconds(timeStr) {
        let totalSeconds = 0;
        const oreMatch = timeStr.match(/(\d+)\s*ore?/);
        const minMatch = timeStr.match(/(\d+)\s*min/);
        
        if (oreMatch) totalSeconds += parseInt(oreMatch[1]) * 3600;
        if (minMatch) totalSeconds += parseInt(minMatch[1]) * 60;
        
        return totalSeconds;
    }
});
</script>';

echo $OUTPUT->footer();
