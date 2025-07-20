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
$string['pluginname'] = 'Teams Meeting Attendance';
$string['pluginadministration'] = 'Teams Meeting Attendance Administration';
$string['modulename'] = 'Teams Meeting Attendance';
$string['modulenameplural'] = 'Teams Meeting Attendances';

// Settings strings
$string['settingsheader'] = 'Teams Attendance Settings';
$string['settingsheader_desc'] = 'Configure Microsoft Teams API settings for attendance tracking';
$string['tenantid'] = 'Tenant ID';
$string['tenantid_desc'] = 'Microsoft Azure tenant ID for API authentication';
$string['apiendpoint'] = 'API Endpoint';
$string['apiendpoint_desc'] = 'Microsoft Graph API endpoint URL';
$string['apiversion'] = 'API Version';
$string['apiversion_desc'] = 'Microsoft Graph API version to use';

// Basic strings
$string['description'] = 'Description';
$string['activityname'] = 'Activity name';
$string['meetingdetails'] = 'Meeting details';
$string['completionsettings'] = 'Completion settings';
$string['minutes'] = 'minutes';

// Meeting configuration
$string['meetingurl'] = 'Teams Meeting URL';
$string['meetingurl_help'] = 'Select the Teams meeting to track attendance for.';
$string['organizer_email'] = 'Meeting Organizer Email';
$string['organizer_email_help'] = 'Email address of the person who organized the Teams meeting. This is needed to fetch attendance reports.';
$string['meeting_start_time'] = 'Meeting Start Time';
$string['meeting_start_time_help'] = 'The start time of the meeting session for filtering attendance reports.';
$string['meeting_end_time'] = 'Meeting End Time';
$string['meeting_end_time_help'] = 'The end time of the meeting session for filtering attendance reports.';
$string['expected_duration'] = 'Expected Duration';
$string['expected_duration_help'] = 'The expected duration of the meeting in minutes. This is automatically calculated from the start and end times.';
$string['required_attendance'] = 'Required Attendance (%)';
$string['required_attendance_help'] = 'The minimum percentage of attendance required for completion. Students must attend at least this percentage of the expected meeting duration.';

// Completion
$string['completionattendance'] = 'Student must meet attendance requirement';
$string['completionattendance_help'] = 'When enabled, students must meet the minimum attendance percentage to complete this activity.';
$string['completionattendance_desc'] = 'Student must meet the required attendance percentage';

// View page
$string['attendance_register'] = 'Attendance Register';
$string['close_register'] = 'Close Register';
$string['reopen_register'] = 'Reopen Register';
$string['fetch_attendance'] = 'Fetch Attendance Data';
$string['fetch_warning'] = 'This will fetch the latest attendance data from Microsoft Teams. The process may take a few moments.';
$string['last_fetch_time'] = 'Last updated: {$a}';
$string['exporttocsv'] = 'Export to CSV';
$string['exporttoxlsx'] = 'Export to Excel';

// Table headers
$string['cognome'] = 'Last Name';
$string['nome'] = 'First Name';
$string['codice_fiscale'] = 'ID Number';
$string['role'] = 'Role';
$string['tempo_totale'] = 'Total Time';
$string['attendance_percentage'] = 'Attendance %';
$string['soglia_raggiunta'] = 'Threshold Met';
$string['assignment_type'] = 'Assignment Type';
$string['teams_user'] = 'Teams User';
$string['teams_user_id'] = 'Teams User ID';
$string['attendance_duration'] = 'Attendance Duration';
$string['suggested_match'] = 'Suggested Match';
$string['assign_user'] = 'Assign User';
$string['actions'] = 'Actions';

// Assignment types
$string['manual'] = 'Manual';
$string['automatic'] = 'Automatic';
$string['manually_assigned_tooltip'] = 'This user was manually assigned by an administrator';
$string['automatically_assigned_tooltip'] = 'This user was automatically matched based on email address';

// Unassigned management
$string['unassigned_records'] = 'Manage Unassigned Records';
$string['manage_unassigned'] = 'Manage Unassigned Records';
$string['manage_manual_assignments'] = 'Manage Manual Assignments';
$string['no_unassigned'] = 'All attendance records have been assigned to users.';
$string['unassigned_users_alert'] = 'There are {$a} unassigned attendance records that need manual review.';

// Performance strings - NEW
$string['total_records'] = 'Total Records';
$string['performance_level'] = 'Performance Level';
$string['recommended_page_size'] = 'Recommended Page Size';
$string['available_users'] = 'Available Users';
$string['for_assignment'] = 'for assignment';
$string['estimated_time'] = 'Estimated Time';
$string['for_suggestions'] = 'for suggestions';
$string['filter_by'] = 'Filter by';
$string['filter_all'] = 'All records';
$string['with_suggestions'] = 'With Suggestions';
$string['without_suggestions'] = 'Without Suggestions';
$string['filter_long_duration'] = 'Long duration sessions';
$string['records_per_page'] = 'Records per page';
$string['advanced_users'] = 'Advanced users only';
$string['refresh'] = 'Refresh';
$string['apply_selected'] = 'Apply Selected';
$string['bulk_assignment_progress'] = 'Bulk Assignment Progress';
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
$string['performance_challenging'] = 'Large dataset - use pagination and filters for best performance';

// Suggestions system - UPDATED FOR DUAL MATCHING
$string['suggestions_found'] = '{$a} automatic matching suggestions found based on names';
$string['suggestions_summary'] = 'Found {$a->total} total suggestions: {$a->name_matches} based on name similarity, {$a->email_matches} based on email patterns';
$string['name_match_suggestion'] = 'Name-based suggestion (homonymy match)';
$string['email_match_suggestion'] = 'Email-based suggestion (deduced from email address)';
$string['no_suggestion'] = 'No automatic suggestion';
$string['apply_suggestion'] = 'Apply this suggestion';
$string['apply_selected_suggestions'] = 'Apply Selected Suggestions';
$string['bulk_assignments_applied'] = '{$a} assignments have been applied successfully.';
$string['no_assignments_applied'] = 'No assignments were applied.';

// Color legend - UPDATED FOR THREE COLORS
$string['color_legend'] = 'Color Legend';
$string['name_based_matches'] = 'Name-based suggestions (homonymy)';
$string['email_based_matches'] = 'Email-based suggestions (from email)';
$string['suggested_matches'] = 'Suggested matches';
$string['no_matches'] = 'No automatic matches';

// User assignment
$string['select_user'] = 'Select user...';
$string['assign'] = 'Assign';
$string['user_assigned'] = 'User has been assigned successfully.';
$string['user_assignment_failed'] = 'Failed to assign user. Please try again.';

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
$string['required_attendance_help'] = 'Enter the minimum percentage of attendance required for students to complete this activity. Value must be between 0 and 100.';
$string['expected_duration_help'] = 'This field shows the expected duration of the meeting in minutes, automatically calculated from the start and end times you set above.';
$string['meetingurl_help'] = 'Select the Teams meeting from the available meetings in this course. If no meetings are available, you need to create a Teams meeting activity first.';
$string['organizer_email_help'] = 'Enter the email address of the person who organized the Teams meeting. This email is used to authenticate with Microsoft Teams API and fetch attendance reports.';
$string['meeting_start_time_help'] = 'Set the start time for this meeting session. This will be used to filter attendance reports to only include participants within this timeframe.';
$string['meeting_end_time_help'] = 'Set the end time for this meeting session. This will be used to filter attendance reports to only include participants within this timeframe.';
$string['completionattendance_help'] = 'If enabled, students will need to meet the minimum attendance percentage specified above to mark this activity as complete.';

// API and system messages
$string['missingapicredentials'] = 'Microsoft Graph API credentials are missing. Please configure the auth_oidc plugin.';
$string['missingtenantid'] = 'Tenant ID is missing. Please configure it in the plugin settings.';
$string['invalidaccesstoken'] = 'Failed to obtain valid access token from Microsoft Graph API.';
$string['sessionnotfound'] = 'Teams attendance session not found.';
$string['invalidattendanceformat'] = 'Invalid attendance data format received from Microsoft Teams API.';
$string['attendancefetchfailed'] = 'Failed to fetch attendance data from Microsoft Teams.';
$string['fetch_attendance_success'] = 'Attendance data has been successfully fetched from Microsoft Teams.';

// Completion descriptions
$string['completionattendance_desc'] = 'Student must achieve the required attendance percentage';

// Capabilities
$string['teamsattendance:view'] = 'View Teams attendance reports';
$string['teamsattendance:manageattendance'] = 'Manage Teams attendance data';
$string['teamsattendance:addinstance'] = 'Add Teams attendance activity';

// Reset automatic assignments
$string['automatic_assignments_info'] = 'There are {$a} automatically assigned records from suggestions.';\n$string['reset_automatic_assignments'] = 'Reset All Automatic Assignments';\n$string['confirm_reset_automatic'] = 'Are you sure you want to remove ALL automatic assignments? This will unassign all users that were matched automatically and they will need to be reassigned manually.';\n$string['automatic_assignments_reset'] = '{$a} automatic assignments have been reset successfully.';\n

// Privacy
$string['privacy:metadata'] = 'The Teams Meeting Attendance plugin stores attendance data fetched from Microsoft Teams.';
$string['privacy:metadata:teamsattendance_data'] = 'Attendance records for Teams meetings';
$string['privacy:metadata:teamsattendance_data:userid'] = 'The ID of the user';
$string['privacy:metadata:teamsattendance_data:attendance_duration'] = 'Duration of attendance in the meeting';
$string['privacy:metadata:teamsattendance_data:actual_attendance'] = 'Actual attendance percentage';
$string['privacy:metadata:teamsattendance_data:completion_met'] = 'Whether completion criteria was met';
