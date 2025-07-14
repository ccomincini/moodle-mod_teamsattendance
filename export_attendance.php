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
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT); // Course Module ID.
list($course, $cm) = get_course_and_cm_from_cmid($id, 'teamsattendance');
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/teamsattendance:view', $context); // Ensure user has view capability

// Fetch session data
$session = $DB->get_record('teamsattendance', ['id' => $cm->instance], '*', MUST_EXIST);

// Fetch attendance data
// Join with user table to get user details directly
$sql = "SELECT tad.*, u.firstname, u.lastname, u.idnumber
        FROM {teamsattendance_data} tad
        JOIN {user} u ON u.id = tad.userid
        WHERE tad.sessionid = :sessionid AND tad.userid <> :siteguestid";
$records = $DB->get_records_sql($sql, ['sessionid' => $session->id, 'siteguestid' => $CFG->siteguest]);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="teamsattendance_export_' . clean_filename($session->name) . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM (Byte Order Mark) for compatibility with Excel
fwrite($output, "\xEF\xBB\xBF");


// Output the CSV header row
$header = [
    get_string('cognome', 'mod_teamsattendance'),
    get_string('nome', 'mod_teamsattendance'),
    get_string('codice_fiscale', 'mod_teamsattendance'),
    get_string('role', 'mod_teamsattendance'),
    get_string('tempo_totale', 'mod_teamsattendance'),
    get_string('attendance_percentage', 'mod_teamsattendance'),
    get_string('soglia_raggiunta', 'mod_teamsattendance')
];
fputcsv($output, $header);

// Output data rows
$expected_duration_seconds = $session->expected_duration;

foreach ($records as $record) {
    $attendance_percentage = 0;
    if ($expected_duration_seconds > 0) {
        $attendance_percentage = ($record->attendance_duration / $expected_duration_seconds) * 100;
    }

    $data = [
        $record->lastname,
        $record->firstname,
        $record->idnumber,
        $record->role,
        format_time($record->attendance_duration),
        round($attendance_percentage, 1) . '%',
        $record->completion_met ? get_string('yes', 'moodle') : get_string('no', 'moodle')
    ];
    fputcsv($output, $data);
}

// Close the file pointer
fclose($output);

// Exit the script
exit;
