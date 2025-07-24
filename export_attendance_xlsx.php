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

// Include Moodle config
require('../../config.php');

// Include PhpSpreadsheet Autoloader
require(__DIR__ . '/phpoffice/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Moodle standard requirements
$id = required_param('id', PARAM_INT); // Course Module ID.
list($course, $cm) = get_course_and_cm_from_cmid($id, 'teamsattendance');
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/teamsattendance:view', $context); // Ensure user has view capability

// Fetch session data
$session = $DB->get_record('teamsattendance', ['id' => $cm->instance], '*', MUST_EXIST);

// Fetch attendance data
$sql = "SELECT tad.*, u.firstname, u.lastname, u.idnumber
        FROM {teamsattendance_data} tad
        JOIN {user} u ON u.id = tad.userid
        WHERE tad.sessionid = :sessionid AND tad.userid <> :siteguestid";
$records = $DB->get_records_sql($sql, ['sessionid' => $session->id, 'siteguestid' => $CFG->siteguest]);

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Define headers
$headers = [
    get_string('cognome', 'mod_teamsattendance'),
    get_string('nome', 'mod_teamsattendance'),
    get_string('codice_fiscale', 'mod_teamsattendance'),
    get_string('role', 'mod_teamsattendance'),
    get_string('tempo_totale', 'mod_teamsattendance'),
    get_string('attendance_percentage', 'mod_teamsattendance'),
    get_string('soglia_raggiunta', 'mod_teamsattendance')
];

// Add headers to the sheet
$sheet->fromArray($headers, NULL, 'A1');

// Add data to the sheet
$row = 2; // Start from the second row after headers
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
        format_time($record->attendance_duration), // Moodle function for formatting time
        round($attendance_percentage, 1) . '%',
        $record->completion_met ? get_string('yes', 'moodle') : get_string('no', 'moodle')
    ];

    $sheet->fromArray($data, NULL, 'A' . $row);
    $row++;
}

// Set filename
$filename = 'teamsattendance_export_' . clean_filename($session->name) . '.xlsx';

// Set headers for XLSX download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Create XLSX Writer and save to output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

// Exit the script
exit;
