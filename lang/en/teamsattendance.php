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

/**
 * English strings for teamsattendance
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin information
$string['pluginname'] = 'Teams meeting attendance';
$string['pluginadministration'] = 'Teams meeting attendance administration';
$string['modulename'] = 'Teams meeting attendance';
$string['modulenameplural'] = 'Teams meeting attendance';

// Settings strings
$string['settingsheader'] = 'Teams attendance settings';
$string['settingsheader_desc'] = 'Configure Microsoft Teams API settings for attendance tracking';
$string['tenantid'] = 'Tenant ID';
$string['tenantid_desc'] = 'Microsoft Azure tenant ID for API authentication';
$string['apiendpoint'] = 'API endpoint';
$string['apiendpoint_desc'] = 'Microsoft Graph API endpoint URL';
$string['apiversion'] = 'API version';
$string['apiversion_desc'] = 'Microsoft Graph API version to use';

// Basic strings
$string['description'] = 'Description';
$string['activityname'] = 'Activity name';
$string['meetingdetails'] = 'Meeting details';
$string['completionsettings'] = 'Completion settings';
$string['minutes'] = 'minutes';

// Meeting configuration
$string['meetingurl'] = 'Teams meeting URL';
$string['meetingurl_help'] = 'Select the Teams meeting to track attendance for.';
$string['organizer_email'] = 'Meeting organizer email';
$string['organizer_email_help'] = 'Email address of the person who organized the Teams meeting. Required to retrieve attendance reports.';
$string['meeting_start_time'] = 'Meeting start time';
$string['meeting_start_time_help'] = 'The start time of the meeting session to filter attendance reports.';
$string['meeting_end_time'] = 'Meeting end time';
$string['meeting_end_time_help'] = 'The end time of the meeting session to filter attendance reports.';
$string['expected_duration'] = 'Expected duration';
$string['expected_duration_help'] = 'The expected duration of the meeting in minutes. Calculated automatically from start and end times.';
$string['required_attendance'] = 'Required attendance (%)';
$string['required_attendance_help'] = 'The minimum attendance percentage required for completion. Students must participate for at least this percentage of the expected meeting duration.';

// Completion
$string['completionattendance'] = 'Student must meet attendance requirement';
$string['completionattendance_help'] = 'If enabled, students must achieve the minimum attendance percentage to complete this activity.';
$string['completionattendance_desc'] = 'Student must achieve the required attendance percentage';

// View page
$string['attendance_register'] = 'Attendance register';
$string['close_register'] = 'Close register';
$string['reopen_register'] = 'Reopen register';
$string['fetch_attendance'] = 'Fetch attendance data';
$string['fetch_warning'] = 'This will retrieve the latest attendance data from Microsoft Teams. The process may take a few moments.';
$string['last_fetch_time'] = 'Last updated: {$a}';
$string['exporttocsv'] = 'Export to CSV';
$string['exporttoxlsx'] = 'Export to Excel';

// Table headers
$string['cognome'] = 'Last name';
$string['nome'] = 'First name';
$string['idnumber'] = 'ID number';
$string['role'] = 'Role';
$string['tempo_totale'] = 'Total time';
$string['attendance_percentage'] = 'Attendance %';
$string['soglia_raggiunta'] = 'Threshold met';
$string['assignment_type'] = 'Assignment type';
$string['teams_user'] = 'Teams user';
$string['teams_user_id'] = 'Teams user ID';
$string['attendance_duration'] = 'Attendance duration';
$string['suggested_match'] = 'Suggested match';
$string['assign_user'] = 'Assign user';
$string['actions'] = 'Actions';

// Assignment types
$string['manual'] = 'Manual';
$string['automatic'] = 'Automatic';
$string['manually_assigned_tooltip'] = 'This user has been manually assigned by an administrator';
$string['automatically_assigned_tooltip'] = 'This user has been automatically associated based on email address';

// Unassigned management
$string['unassigned_records'] = 'Unassigned records';
$string['manage_unassigned'] = 'Manage unassigned records';
$string['manage_manual_assignments'] = 'Manage manual assignments';
$string['no_unassigned'] = 'All attendance records have been assigned to users.';
$string['unassigned_users_alert'] = 'There are {$a} unassigned attendance records that need manual review.';

// Performance strings
$string['total_records'] = 'Total records';
$string['performance_level'] = 'Performance level';
$string['recommended_page_size'] = 'Recommended page size';
$string['available_users'] = 'Available users';
$string['for_assignment'] = 'for assignment';
$string['estimated_time'] = 'Estimated time';
$string['for_suggestions'] = 'for suggestions';
$string['filter_by'] = 'Filter by';
$string['filter_all'] = 'All records';
$string['all_records'] = 'All records';
$string['filter_name_suggestions'] = 'Name-based suggestions';
$string['filter_email_suggestions'] = 'Email-based suggestions';
$string['with_suggestions'] = 'With suggestions';
$string['without_suggestions'] = 'Without suggestions';
$string['filter_long_duration'] = 'Long duration sessions';
$string['records_per_page'] = 'Records per page';
$string['advanced_users'] = 'Advanced users only';
$string['refresh'] = 'Refresh';
$string['apply_selected'] = 'Apply selected';
$string['bulk_assignment_progress'] = 'Bulk assignment progress';
$string['loading_initial_data'] = 'Loading initial data';
$string['loading'] = 'Loading';
$string['applying'] = 'Applying';
$string['page'] = 'Page';
$string['of'] = 'of';
$string['previous'] = 'Previous';
$string['next'] = 'Next';
$string['no_records_found'] = 'No records found';

// Performance levels
$string['performance_excellent'] = 'Excellent performance expected';
$string['performance_good'] = 'Good performance expected';
$string['performance_moderate'] = 'Moderate performance - consider using filters';
$string['performance_challenging'] = 'Large dataset - use pagination and filters for better performance';

// Suggestions system
$string['suggestions_found'] = '{$a} automatic matching suggestions found based on names';
$string['suggestions_summary'] = 'Found {$a->total} total suggestions: {$a->name_matches} based on name similarity, {$a->email_matches} based on email patterns';
$string['name_match_suggestion'] = 'Suggested match based on name similarity';
$string['email_match_suggestion'] = 'Suggested match based on email pattern';
$string['no_suggestion'] = 'No automatic suggestion';
$string['apply_suggestion'] = 'Apply this suggestion';
$string['apply_selected_suggestions'] = 'Apply selected suggestions';
$string['bulk_assignments_applied'] = '{$a} assignments have been applied successfully.';
$string['no_assignments_applied'] = 'No assignments were applied.';

// Color legend
$string['color_legend'] = 'Color legend';
$string['name_based_matches'] = 'Name-based suggestions';
$string['email_based_matches'] = 'Email-based suggestions';
$string['suggested_matches'] = 'Suggested matches';
$string['no_matches'] = 'No automatic matches';
$string['name_suggestions_count'] = 'Name-based suggestions';
$string['email_suggestions_count'] = 'Email-based suggestions';

// User assignment
$string['select_user'] = 'Select user...';
$string['assign'] = 'Assign';
$string['user_assigned'] = 'User has been assigned successfully.';
$string['user_assignment_failed'] = 'User assignment failed. Please try again.';

// JavaScript messages
$string['select_user_first'] = 'Please select a user first.';
$string['confirm_assignment'] = 'Are you sure you want to assign this record to {user}?';
$string['select_suggestions_first'] = 'Please select at least one suggestion to apply.';
$string['confirm_bulk_assignment'] = 'Are you sure you want to apply {count} selected suggestions?';

// Error messages
$string['meetingurl_required'] = 'Teams meeting URL is required.';
$string['invalid_meetingurl'] = 'Please enter a valid Teams meeting URL.';
$string['organizer_email_required'] = 'Meeting organizer email is required.';
$string['invalid_email'] = 'Please enter a valid email address.';
$string['meeting_start_time_required'] = 'Meeting start time is required.';
$string['meeting_end_time_required'] = 'Meeting end time is required.';
$string['end_time_after_start'] = 'End time must be after start time.';
$string['invalid_meeting_duration'] = 'Invalid meeting duration.';
$string['required_attendance_error'] = 'Required attendance must be between 0 and 100 percent.';

// Help strings
$string['required_attendance_help'] = 'Enter the minimum attendance percentage required for students to complete this activity. Value must be between 0 and 100.';
$string['expected_duration_help'] = 'This field shows the expected meeting duration in minutes, automatically calculated from the start and end times set above.';
$string['meetingurl_help'] = 'Select the Teams meeting from available meetings in this course. If no meetings are available, you must first create a Teams meeting activity.';
$string['organizer_email_help'] = 'Enter the email address of the person who organized the Teams meeting. This email is used to authenticate with the Microsoft Teams API and retrieve attendance reports.';
$string['meeting_start_time_help'] = 'Set the start time for this meeting session. This will be used to filter attendance reports to include only participants in this time range.';
$string['meeting_end_time_help'] = 'Set the end time for this meeting session. This will be used to filter attendance reports to include only participants in this time range.';
$string['completionattendance_help'] = 'If enabled, students will need to achieve the minimum attendance percentage specified above to mark this activity as completed.';

// API and system messages
$string['missingapicredentials'] = 'Microsoft Graph API credentials are missing. Please configure the auth_oidc plugin.';
$string['missingtenantid'] = 'Tenant ID is missing. Please configure it in plugin settings.';
$string['invalidaccesstoken'] = 'Failed to obtain a valid access token from Microsoft Graph API.';
$string['sessionnotfound'] = 'Teams attendance session not found.';
$string['invalidattendanceformat'] = 'Invalid attendance data format received from Microsoft Teams API.';
$string['attendancefetchfailed'] = 'Failed to fetch attendance data from Microsoft Teams.';
$string['fetch_attendance_success'] = 'Attendance data has been successfully retrieved from Microsoft Teams.';

// Completion descriptions
$string['completionattendance_desc'] = 'Student must achieve the required attendance percentage';

// Capabilities
$string['teamsattendance:view'] = 'View Teams attendance reports';
$string['teamsattendance:manageattendance'] = 'Manage Teams attendance data';
$string['teamsattendance:addinstance'] = 'Add Teams attendance activity';

// Reset automatic assignments
$string['automatic_assignments_info'] = '{$a} records associated based on suggestions.';
$string['reset_automatic_assignments'] = 'Reset all suggestion-based assignments';
$string['confirm_reset_automatic'] = 'Are you sure you want to reset all suggestion-based associations? All reset associations will need to be made manually again.';
$string['automatic_assignments_reset'] = '{$a} automatic assignments reset.';

$string['manual_assignments_info'] = '{$a} manual assignments found.';
$string['reset_manual_assignments'] = 'Reset manual assignments';
$string['confirm_reset_manual_assignments'] = 'Are you sure you want to reset all manual assignments?';

$string['potential_suggestions_info'] = 'There are {$a} manual associations that match current automatic suggestions';
$string['reset_suggestion_assignments'] = 'Reset suggestion-based associations';
$string['confirm_reset_suggestions'] = 'Reset associations that match automatic suggestions?';
$string['suggestion_assignments_reset'] = 'Reset {$a} suggestion-based associations';

//Privacy
$string['privacy:metadata'] = 'The Teams meeting attendance plugin stores attendance data retrieved from Microsoft Teams.';
$string['privacy:metadata:teamsattendance_data'] = 'Attendance records for Teams meetings';
$string['privacy:metadata:teamsattendance_data:userid'] = 'The user ID';
$string['privacy:metadata:teamsattendance_data:attendance_duration'] = 'Duration of attendance in the meeting';
$string['privacy:metadata:teamsattendance_data:actual_attendance'] = 'Actual attendance percentage';
$string['privacy:metadata:teamsattendance_data:completion_met'] = 'Whether completion criteria were met';