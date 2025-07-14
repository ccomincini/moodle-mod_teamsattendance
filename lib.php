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

require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/graph_api.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Teams Meeting Attendance Module
 *
 * This module tracks attendance for Microsoft Teams meetings.
 *
 * Database Structure:
 * - teamsattendance: Stores Teams meeting configuration (one record per meeting)
 * - id, course, name, intro, meetingurl, organizer_email, expected_duration, required_attendance, status
 *
 * - teamsattendance_data: Stores individual user attendance (multiple records per session)
 * - id, sessionid (FK to teamsattendance), userid, attendance_duration, actual_attendance, completion_met
 *
 * Workflow:
 * 1. Create Teams meeting activity -> Creates record in teamsattendance
 * 2. Fetch attendance from Teams -> Creates/updates records in teamsattendance_data
 * 3. View attendance -> Reads from teamsattendance_data
 * 4. Check completion -> Reads from teamsattendance_data
 */

function teamsattendance_supports($feature)
{
    switch ($feature) {
        case MOD_PURPOSE_ADMINISTRATION:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        default:
            return null;
    }
}

// Questa dice a Moodle quali regole personalizzate il tuo plugin supporta.
function teamsattendance_supports_specific_completion_rule($rulename) {
    return $rulename === 'completionattendance';
}

// Questa fornisce le descrizioni per le regole.
function teamsattendance_get_completion_rule_descriptions() {
    // Assicurati che 'completionattendance_desc' sia una stringa di lingua valida nel tuo plugin
    return [
        'completionattendance' => get_string('completionattendance_desc', 'teamsattendance')
    ];
}

function teamsattendance_completion_rule_enabled($data)
{
    return !empty($data->completionattendance);
}

/**
 * Function to check if a user has met the attendance completion criteria
 *
 * @param object $cm Course module object
 * @param int $userid User ID
 * @return bool True if completion criteria met, false otherwise
 */
function teamsattendance_check_completion($cm, $userid)
{
    global $DB;

    // Fetch the session and user attendance data
    $session = $DB->get_record('teamsattendance', ['id' => $cm->instance], '*', MUST_EXIST);
    if (!$session) {
        debugging("Session record not found for cm->instance: {$cm->instance}", DEBUG_DEVELOPER);
        return false;
    }
    $attendance = $DB->get_record('teamsattendance_data', [
        'sessionid' => $cm->instance,
        'userid' => $userid
    ]);

    if (!$attendance) {
        debugging("Attendance record not found for userid: {$userid}, sessionid: {$cm->instance}", DEBUG_DEVELOPER);
        return false; // No attendance record found
    }

    // Ensure the attendance duration is valid
    if ($attendance->attendance_duration <= 0) {
        debugging("Invalid attendance duration (<= 0) for userid: {$userid}, duration: {$attendance->attendance_duration}", DEBUG_DEVELOPER);
        return false; // Invalid attendance duration
    }

    $expected_duration = $session->expected_duration;
    $required_attendance = $session->required_attendance;

    $attendance_percentage = ($attendance->attendance_duration / $expected_duration) * 100;

    // Check if the user meets the required percentage (from session)
    $completion_met = $attendance_percentage >= $required_attendance;

    // Update the attendance record with the completion status and actual percentage
    $attendance->actual_attendance = $attendance_percentage;
    $attendance->completion_met = $completion_met ? 1 : 0;

    $DB->update_record('teamsattendance_data', $attendance);

    $context = context_module::instance($cm->id);

    // Log completion status change with additional data
    if ($completion_met) {
        \core\event\course_module_completion_updated::create([
            'objectid' => $cm->id,
            'context' => $context,
            'relateduserid' => $userid,
            'other' => [
                'completionstate' => $completion_met,
                'attendance_duration' => $attendance->attendance_duration,
                'actual_attendance' => $attendance->actual_attendance,
                'join_time' => $attendance->join_time,
                'leave_time' => $attendance->leave_time,
                'role' => $attendance->role
            ]
        ])->trigger();
    }
    debugging("teamsattendance_check_completion: User: {$userid}, cmid: {$cm->id}, Completion Met: " . ($completion_met ? 'Yes' : 'No') . ", Percentage: {$attendance_percentage}", DEBUG_DEVELOPER);

    return $completion_met;
}

/**
 * Add a new field to track the status of the attendance register (open/closed)
 *
 * @param settings_navigation $settingsnav The settings navigation object
 * @param context $context The context object
 */
function mod_teamsattendance_extend_settings_navigation($settingsnav, $context)
{
    global $DB, $PAGE;

    if (has_capability('mod/teamsattendance:manageattendance', $context)) {
        $node = $settingsnav->add(get_string('attendance_register', 'mod_teamsattendance'));

        $cmid = $PAGE->cm->id;
        $session = $DB->get_record('teamsattendance', ['id' => $PAGE->cm->instance], '*', MUST_EXIST);

        if ($session->status === 'open') {
            $node->add(get_string('close_register', 'mod_teamsattendance'),
                new moodle_url('/mod/teamsattendance/close_register.php', ['id' => $cmid]));
        } else {
            $node->add(get_string('reopen_register', 'mod_teamsattendance'),
                new moodle_url('/mod/teamsattendance/reopen_register.php', ['id' => $cmid]));
        }

        // Add link to manage unassigned records
        $node->add(get_string('manage_unassigned', 'mod_teamsattendance'),
                new moodle_url('/mod/teamsattendance/manage_unassigned.php', ['id' => $cmid]));
        
        // Add link to manage manual assignments
        $node->add(get_string('manage_manual_assignments', 'mod_teamsattendance'),
                new moodle_url('/mod/teamsattendance/manage_manual_assignments.php', ['id' => $cmid]));
    }
}

/**
 * Adds a new instance of the Teams Meeting Attendance module.
 *
 * @param stdClass $data The data submitted from the form.
 * @param mod_teamsattendance_mod_form $mform The form instance.
 * @return int The ID of the newly created instance.
 */
function teamsattendance_add_instance($data, $mform)
{
    global $DB;

    // Validate required fields
    if (empty($data->name) || empty($data->meetingurl) || empty($data->organizer_email)) {
        throw new moodle_exception('missingrequiredfield', 'mod_teamsattendance');
    }

    // Prepare the record for insertion (session-level data).
    $record = new stdClass();
    $record->course = $data->course;
    $record->name = $data->name;
    $record->intro = $data->intro;
    $record->introformat = $data->introformat;
    $record->meetingurl = $data->meetingurl;
    $record->organizer_email = $data->organizer_email;
    $record->start_datetime = isset($data->start_datetime) ? $data->start_datetime : 0;
    $record->end_datetime = isset($data->end_datetime) ? $data->end_datetime : 0;

    // Convert duration to seconds if hours were selected
    $duration = $data->expected_duration;
    if ($data->duration_unit === 'hours') {
        $duration *= 3600; // Convert hours to seconds
    } else {
        $duration *= 60; // Convert minutes to seconds
    }
    $record->expected_duration = $duration;

    $record->required_attendance = $data->required_attendance;
    $record->status = 'open'; // Open by default
    $record->timecreated = time();
    $record->timemodified = time();

    return $DB->insert_record('teamsattendance', $record);
}

/**
 * Updates an existing instance of the Teams Meeting Attendance module.
 *
 * @param stdClass $data The data submitted from the form.
 * @param mod_teamsattendance_mod_form $mform The form instance.
 * @return bool True if the update was successful, false otherwise.
 */
function teamsattendance_update_instance($data, $mform)
{
    global $DB;

    // Validate required fields
    if (empty($data->name) || empty($data->meetingurl) || empty($data->organizer_email)) {
        throw new moodle_exception('missingrequiredfield', 'mod_teamsattendance');
    }

    // Prepare the record for update (session-level data).
    $record = new stdClass();
    $record->id = $data->instance;
    $record->course = $data->course;
    $record->name = $data->name;
    $record->intro = $data->intro;
    $record->introformat = $data->introformat;
    $record->meetingurl = $data->meetingurl;
    $record->organizer_email = $data->organizer_email;
    $record->start_datetime = isset($data->start_datetime) ? $data->start_datetime : 0;
    $record->end_datetime = isset($data->end_datetime) ? $data->end_datetime : 0;

    // Convert duration to seconds if hours were selected
    $duration = $data->expected_duration;
    if ($data->duration_unit === 'hours') {
        $duration *= 3600; // Convert hours to seconds
    } else {
        $duration *= 60; // Convert minutes to seconds
    }
    $record->expected_duration = $duration;

    $record->required_attendance = $data->required_attendance;
    $record->timemodified = time();

    // Update the record in the database.
    $result = $DB->update_record('teamsattendance', $record);

    return $result;
}

/**
 * Delete an instance of the teamsattendance module.
 *
 * @param int $id ID of the module instance
 * @return bool Success status
 */
function teamsattendance_delete_instance($id)
{
    global $DB;

    // Get the instance
    if (!$instance = $DB->get_record('teamsattendance', array('id' => $id))) {
        return false;
    }

    // Delete all attendance records for this instance
    $DB->delete_records('teamsattendance_data', array('sessionid' => $id));

    // Delete the instance
    $DB->delete_records('teamsattendance', array('id' => $id));

    return true;
}

/**
 * Fetch attendance data for a Teams meeting session
 *
 * @param int $cmid Course module ID
 * @return bool True if successful, false otherwise
 */
function teamsattendance_fetch_attendance($cmid)
{
    global $DB, $CFG;

    // Get course module and context
    list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'teamsattendance');
    $context = context_module::instance($cm->id);

    // Get OIDC settings from auth_oidc configuration
    $client_id = get_config('auth_oidc', 'clientid');
    $client_secret = get_config('auth_oidc', 'clientsecret');

    // Get module-specific settings
    $tenant_id = get_config('mod_teamsattendance', 'tenantid');

    if (!$client_id || !$client_secret) {
        throw new moodle_exception('missingapicredentials', 'mod_teamsattendance');
    }

    if (!$tenant_id) {
        throw new moodle_exception('missingtenantid', 'mod_teamsattendance');
    }

    // Get the session record
    $session = $DB->get_record('teamsattendance', ['id' => $cm->instance], '*', MUST_EXIST);
    if (!$session) {
        throw new moodle_exception('sessionnotfound', 'mod_teamsattendance', $cm->instance);
    }

    try {
        $access_token = get_graph_access_token($client_id, $client_secret, $tenant_id);
        if (!$access_token) {
            throw new moodle_exception('invalidaccesstoken', 'mod_teamsattendance');
        }

        // Convert local datetimes to UTC for API filtering
        $start_datetime_utc = 0;
        $end_datetime_utc = 0;
        
        if (!empty($session->start_datetime)) {
            // Convert from user timezone to UTC using Moodle's proper timezone handling
            $user_timezone = core_date::get_user_timezone();
            
            // Create DateTime object in user timezone
            $start_dt = new DateTime();
            $start_dt->setTimestamp($session->start_datetime);
            $start_dt->setTimezone(new DateTimeZone($user_timezone));
            
            // Convert to UTC
            $start_dt->setTimezone(new DateTimeZone('UTC'));
            $start_datetime_utc = $start_dt->getTimestamp();
            
            debugging("Converting start datetime: Local=" . date('Y-m-d H:i:s', $session->start_datetime) . 
                     " ({$user_timezone}) -> UTC=" . gmdate('Y-m-d H:i:s', $start_datetime_utc), DEBUG_DEVELOPER);
        }
        
        if (!empty($session->end_datetime)) {
            // Convert from user timezone to UTC using Moodle's proper timezone handling
            $user_timezone = core_date::get_user_timezone();
            
            // Create DateTime object in user timezone
            $end_dt = new DateTime();
            $end_dt->setTimestamp($session->end_datetime);
            $end_dt->setTimezone(new DateTimeZone($user_timezone));
            
            // Convert to UTC
            $end_dt->setTimezone(new DateTimeZone('UTC'));
            $end_datetime_utc = $end_dt->getTimestamp();
            
            debugging("Converting end datetime: Local=" . date('Y-m-d H:i:s', $session->end_datetime) . 
                     " ({$user_timezone}) -> UTC=" . gmdate('Y-m-d H:i:s', $end_datetime_utc), DEBUG_DEVELOPER);
        }

        $attendance_data = fetch_attendance_data($session->meetingurl, $session->organizer_email, $access_token, $start_datetime_utc, $end_datetime_utc);
        if (!isset($attendance_data['value']) || !is_array($attendance_data['value'])) {
            throw new moodle_exception('invalidattendanceformat', 'mod_teamsattendance');
        }

        $updated = 0;
        $errors = []; // Initialize errors array
        $skipped = 0;
        $processed = 0;
        $user_completion_data = []; // Array to store user completion data for batch update

        // Debug: Log total records received from API
        debugging("Total attendance records received from API: " . count($attendance_data['value']), DEBUG_DEVELOPER);

        foreach ($attendance_data['value'] as $record) {
            $processed++;
            if (!isset($record['userId']) || !isset($record['totalAttendanceInSeconds'])) {
                $errors[] = "Invalid record format: missing required fields for record #$processed";
                $skipped++;
                debugging("Skipped record #$processed: missing userId or totalAttendanceInSeconds", DEBUG_DEVELOPER);
                continue;
            }

            $teams_user_id = $record['userId'];
            if (filter_var($teams_user_id, FILTER_VALIDATE_EMAIL)) {
                $userid = $DB->get_field('user', 'id', ['email' => $teams_user_id]);
                if (!$userid) {
                    $userid = $CFG->siteguest; // Not found, treat as unassigned
                } else {
                    //check if the user is enrolled in the course
                    $enrolled = is_enrolled($context, $userid);
                    if (!$enrolled) {
                        $userid = $CFG->siteguest; // Not enrolled, treat as unassigned
                    }
                }
            } else {
                $userid = $CFG->siteguest; // Not an email, treat as unassigned
            }


            try {
                $attendance_duration = $record['totalAttendanceInSeconds'];

                // Insert or update attendance record
                $attendance_record = $DB->get_record('teamsattendance_data', [
                    'sessionid' => $session->id,
                    'teams_user_id' => $teams_user_id,
                ]);

                if ($attendance_record) {
                    debugging("Found existing record for teams_user_id: $teams_user_id (record #$processed)", DEBUG_DEVELOPER);
                    // Preserve manual user assignments - only update userid if it's currently unassigned
                    if ($attendance_record->userid == $CFG->siteguest) {
                        $attendance_record->userid = $userid;
                    }
                    $attendance_record->sessionid = $session->id;
                    $attendance_record->teams_user_id = $teams_user_id;
                    $attendance_record->attendance_duration = 0;
                    $attendance_record->actual_attendance = 0;
                    $attendance_record->completion_met = 0;
                    $DB->update_record('teamsattendance_data', $attendance_record);

                    // get report data
                    $report_data = $DB->get_record('teamsattendance_reports', [
                        'data_id' => $attendance_record->id,
                        'report_id' => $record['reportId'],
                    ]);

                } else {
                    debugging("Creating new record for teams_user_id: $teams_user_id (record #$processed)", DEBUG_DEVELOPER);
                    $attendance_record = new stdClass();
                    $attendance_record->sessionid = $session->id;
                    $attendance_record->userid = $userid;
                    $attendance_record->teams_user_id = $teams_user_id;
                    $attendance_record->attendance_duration = 0;
                    $attendance_record->actual_attendance = 0;
                    $attendance_record->completion_met = 0;
                    $attendance_record->id = $DB->insert_record('teamsattendance_data', $attendance_record);
                }

                if ($report_data) {
                    $report_data->report_id = $record['reportId'];
                    $report_data->attendance_duration = $attendance_duration;
                    $report_data->join_time = strtotime($record['joinTime']) ?? null;
                    $report_data->leave_time = strtotime($record['leaveTime']) ?? null;
                    $DB->update_record('teamsattendance_reports', $report_data);
                } else {
                    $report_data = new stdClass();
                    $report_data->data_id = $attendance_record->id;
                    $report_data->report_id = $record['reportId'];
                    $report_data->attendance_duration = $attendance_duration;
                    $report_data->join_time = strtotime($record['joinTime']) ?? null;
                    $report_data->leave_time = strtotime($record['leaveTime']) ?? null;
                    $DB->insert_record('teamsattendance_reports', $report_data);
                }

                $updated++;
            } catch (Exception $e) {
                $errors[] = "Error processing attendance for user {$record['userId']}: " . $e->getMessage() . 'data ' . json_encode($record);
                $skipped++;
                debugging("Database error for record #$processed (user: {$record['userId']}): " . $e->getMessage(), DEBUG_DEVELOPER);
                continue;
            }
        }

        // Debug: Log processing summary
        debugging("Processing Summary - Total API records: " . count($attendance_data['value']) . 
                 ", Processed: $processed, Updated: $updated, Skipped: $skipped", DEBUG_DEVELOPER);
        
        if (!empty($errors)) {
            debugging("Errors encountered: " . implode('; ', $errors), DEBUG_DEVELOPER);
        }

        //for each user in the attendance data, calculate the attendance percentage from the report data (there can be multiple reports for each user)
        $users = $DB->get_records('teamsattendance_data', ['sessionid' => $session->id]);
        $module_context = context_module::instance($cm->id);

        foreach ($users as $user) {
            $reports = $DB->get_records('teamsattendance_reports', ['data_id' => $user->id]);

            $total_duration = 0;
            foreach ($reports as $report) {
                $total_duration += $report->attendance_duration;
            }
            $user->attendance_duration = $total_duration;
            $user->actual_attendance = ($user->attendance_duration / $session->expected_duration) * 100;
            $user->completion_met = ($user->actual_attendance >= $session->required_attendance) ? 1 : 0;

            $DB->update_record('teamsattendance_data', $user);

            $course = get_course($cm->course);



            $course = get_course($cm->course);
            $completion = new \completion_info($course); // Oggetto da lib/completionlib.php
    
            $stato_da_impostare_come_risultato_possibile = $user->completion_met ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
            $completion->update_state(
                $cm,
                $stato_da_impostare_come_risultato_possibile,
                $user->userid
            );
        }

        // Update session's last fetch time
        $session->timemodified = time();
        $DB->update_record('teamsattendance', $session);

        // Check for unassigned users
        $unassigned_count = $DB->count_records('teamsattendance_data', [
            'sessionid' => $session->id,
            'userid' => $CFG->siteguest
        ]);

        if ($unassigned_count > 0) {

            if (has_capability('mod/teamsattendance:manageattendance', $context)) {
                $manageurl = new moodle_url('/mod/teamsattendance/manage_unassigned.php', ['id' => $cm->id]);
            }
        }

        return true;
    } catch (Exception $e) {
        debugging('Error fetching attendance: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Add a get_coursemodule_info function in case any activity type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 * will know about (most noticeably, an icon).
 */
function teamsattendance_get_coursemodule_info($coursemodule)
{
    global $DB;

    $dbparams = array('id' => $coursemodule->instance);
    $fields = 'id, name, intro, introformat, completionattendance';
    if (!$teamsattendance = $DB->get_record('teamsattendance', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $teamsattendance->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('teamsattendance', $teamsattendance, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionattendance'] = $teamsattendance->completionattendance;
    }

    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_teamsattendance_get_completion_active_rule_descriptions($cm)
{
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules']) || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        if ($key == 'completionattendance' && !empty($val)) {
            $descriptions[] = get_string('completionattendance_desc', 'mod_teamsattendance');
        }
    }
    return $descriptions;
}


