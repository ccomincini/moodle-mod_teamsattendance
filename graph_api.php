<?php
// This file centralizes Microsoft Graph API calls for the Teams Meeting Attendance plugin.

require_once($CFG->dirroot . '/config.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * Fetch the access token from Microsoft Graph API.
 *
 * @param string $client_id The client ID.
 * @param string $client_secret The client secret.
 * @param string $tenant_id The tenant ID.
 * @return string The access token.
 * @throws moodle_exception If the token cannot be fetched.
 */
function get_graph_access_token($client_id, $client_secret, $tenant_id) {
    global $CFG;
    
    // Log the attempt to get token
    if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
        error_log("Attempting to get access token for client_id: " . substr($client_id, 0, 5) . "...");
    }

    $url = "https://login.microsoftonline.com/$tenant_id/oauth2/v2.0/token";
    
    // Set up the POST data - ensure client_id and client_secret are properly encoded
    $postdata = http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => trim($client_id),
        'client_secret' => trim($client_secret),
        'scope' => 'https://graph.microsoft.com/.default'
    ], '', '&', PHP_QUERY_RFC3986);

    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postdata,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]
    ]);

    // Enable verbose debug output if debugging is enabled
    if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
    }

    // Execute the request
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    // Get debug information if verbose mode is enabled
    if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        error_log("Token request response code: " . $httpcode);
        error_log("Token request response: " . $response);
    }

    curl_close($ch);

    // Check for cURL errors
    if ($error) {
        throw new moodle_exception('CURL error: ' . $error);
    }

    // Check HTTP response code
    if ($httpcode !== 200) {
        $error_message = 'HTTP error: ' . $httpcode;
        if ($response) {
            $error_data = json_decode($response, true);
            if (isset($error_data['error_description'])) {
                $error_message .= ' - ' . $error_data['error_description'];
            } else {
                $error_message .= ' Response: ' . $response;
            }
        }
        throw new moodle_exception($error_message);
    }
    
    // Ensure we have a string response
    if (!is_string($response)) {
        throw new moodle_exception('Invalid response format from token endpoint');
    }
    
    $data = json_decode($response);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new moodle_exception('Invalid JSON response from token endpoint: ' . json_last_error_msg());
    }

    if (isset($data->access_token)) {
        // Log successful token acquisition
        if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
            error_log("Successfully obtained access token");
        }
        return $data->access_token;
    } else {
        throw new moodle_exception('Unable to fetch access token: ' . 
            (isset($data->error_description) ? $data->error_description : 'Unknown error'));
    }
}

/**
 * Verify that the application has the required permissions.
 * 
 * @param string $access_token The access token to use for verification.
 * @return bool True if all required permissions are present.
 */
function verify_required_permissions($access_token) {
    $url = "https://graph.microsoft.com/v1.0/me";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
        error_log("Permission verification response code: " . $httpcode);
        error_log("Permission verification response: " . $response);
    }

    return $httpcode === 200;
}

/**
 * Get user ID from email using Microsoft Graph API.
 *
 * @param string $email The user's email address.
 * @param string $accessToken The access token.
 * @return string The user ID.
 * @throws moodle_exception If the user ID cannot be retrieved.
 */
function get_user_id_from_email($email, $accessToken) {
    // Query the users endpoint to get the user ID
    $url = "https://graph.microsoft.com/v1.0/users/$email";
    
    if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
        error_log("User lookup URL: " . $url);
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);

    if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
    }

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        error_log("User lookup response code: " . $httpcode);
        error_log("User lookup response: " . $response);
    }

    curl_close($ch);

    if ($error) {
        throw new moodle_exception('CURL error: ' . $error);
    }

    if ($httpcode !== 200) {
        $error_message = 'HTTP error: ' . $httpcode;
        if ($response) {
            $error_data = json_decode($response, true);
            if (isset($error_data['error']['message'])) {
                $error_message .= ' - ' . $error_data['error']['message'];
            }
        }
        throw new moodle_exception($error_message);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new moodle_exception('Invalid JSON response from users endpoint: ' . json_last_error_msg());
    }

    // Check if we have a single user object
    if (isset($data['id'])) {
        return $data['id'];
    }
    
    // Check if we have an array of users
    if (isset($data['value']) && !empty($data['value'])) {
        return $data['value'][0]['id'];
    }

    throw new moodle_exception('No user found with the provided email');
}

/**
 * Get meeting ID from the meeting URL.
 *
 * @param string $meetingUrl The Teams meeting URL.
 * @param string $organizerEmail The email of the meeting organizer.
 * @param string $accessToken The access token.
 * @return string The meeting ID.
 * @throws moodle_exception If the meeting ID cannot be retrieved.
 */
function get_meeting_id_from_url($meetingUrl, $organizerEmail, $accessToken) {
    // Get the user ID from email
    $userId = get_user_id_from_email($organizerEmail, $accessToken);

    // Properly encode the URL for the filter parameter
    $encodedUrl = urlencode($meetingUrl);
    
    // Query the onlineMeetings endpoint to get the meeting ID
    $url = "https://graph.microsoft.com/v1.0/users/{$userId}/onlineMeetings?\$filter=joinWebUrl%20eq%20'{$encodedUrl}'";
    
    if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
        error_log("Meeting lookup URL before encoding: " . $meetingUrl);
        error_log("Meeting lookup URL after encoding: " . $encodedUrl);
        error_log("Full API URL: " . $url);
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);

    if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
    }

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        error_log("Meeting lookup response code: " . $httpcode);
        error_log("Meeting lookup response: " . $response);
    }

    curl_close($ch);

    if ($error) {
        throw new moodle_exception('CURL error: ' . $error);
    }

    if ($httpcode !== 200) {
        $error_message = 'HTTP error: ' . $httpcode;
        if ($response) {
            $error_data = json_decode($response, true);
            if (isset($error_data['error']['message'])) {
                $error_message .= ' - ' . $error_data['error']['message'];
            }
        }
        throw new moodle_exception($error_message);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new moodle_exception('Invalid JSON response from meetings endpoint: ' . json_last_error_msg());
    }

    if (!isset($data['value']) || empty($data['value'])) {
        throw new moodle_exception('No meeting found with the provided URL');
    }

    // Return the ID of the first matching meeting
    return $data['value'][0]['id'];
}

/**
 * Get detailed attendance data for a specific report ID.
 *
 * @param string $userId The user ID of the meeting organizer.
 * @param string $meetingId The meeting ID.
 * @param string $reportId The attendance report ID.
 * @param string $accessToken The access token.
 * @return array The detailed attendance data.
 * @throws moodle_exception If the attendance data cannot be retrieved.
 */
function get_meeting_attendance_report($userId, $meetingId, $reportId, $accessToken) {
    $url = "https://graph.microsoft.com/v1.0/users/{$userId}/onlineMeetings/{$meetingId}/attendanceReports/{$reportId}?\$expand=attendanceRecords";
    
    
    if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
        error_log("Fetching attendance report for ID: " . $reportId);
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error) {
        throw new moodle_exception('CURL error: ' . $error);
    }

    if ($httpcode !== 200) {
        $error_message = 'HTTP error: ' . $httpcode;
        if ($response) {
            $error_data = json_decode($response, true);
            if (isset($error_data['error']['message'])) {
                $error_message .= ' - ' . $error_data['error']['message'];
            }
        }
        throw new moodle_exception($error_message);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new moodle_exception('Invalid JSON response from attendance report endpoint: ' . json_last_error_msg());
    }

    return $data;
}

/**
 * Fetch attendance data for a specific meeting.
 *
 * @param string $meetingUrl The Teams meeting URL.
 * @param string $organizerEmail The email of the meeting organizer.
 * @param string $accessToken The access token.
 * @param int $startDatetime Optional start datetime timestamp for filtering reports.
 * @param int $endDatetime Optional end datetime timestamp for filtering reports.
 * @return array The attendance data.
 * @throws moodle_exception If the attendance data cannot be fetched.
 */
function fetch_attendance_data($meetingUrl, $organizerEmail, $accessToken, $startDatetime = 0, $endDatetime = 0) {
    // Get the user ID from email
    $userId = get_user_id_from_email($organizerEmail, $accessToken);
    
    // Get the meeting ID
    $meetingId = get_meeting_id_from_url($meetingUrl, $organizerEmail, $accessToken);
    
    // Get the list of attendance report IDs
    $url = "https://graph.microsoft.com/v1.0/users/{$userId}/onlineMeetings/{$meetingId}/attendanceReports";
    
    if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
        error_log("Fetching attendance report IDs for meeting ID: " . $meetingId);
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
        throw new moodle_exception('CURL error: ' . $error);
    }

    if ($httpcode !== 200) {
        $error_message = 'HTTP error: ' . $httpcode;
        if ($response) {
            $error_data = json_decode($response, true);
            if (isset($error_data['error']['message'])) {
                $error_message .= ' - ' . $error_data['error']['message'];
            }
        }
        throw new moodle_exception($error_message);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new moodle_exception('Invalid JSON response from attendance reports endpoint: ' . json_last_error_msg());
    }

    $attendance_data = [];
    
    // Process each report ID to get detailed attendance data
    if (isset($data['value']) && is_array($data['value'])) {
        foreach ($data['value'] as $report) {
            if (isset($report['id'])) {
                // Apply datetime filtering if start/end times are specified
                if ($startDatetime > 0 || $endDatetime > 0) {
                    // Get meeting session times from the report
                    $meetingStartTime = null;
                    $meetingEndTime = null;
                    
                    if (isset($report['meetingStartDateTime'])) {
                        $meetingStartTime = strtotime($report['meetingStartDateTime']);
                    }
                    if (isset($report['meetingEndDateTime'])) {
                        $meetingEndTime = strtotime($report['meetingEndDateTime']);
                    }
                    
                    // If we have filtering criteria and can determine the meeting times
                    if ($meetingStartTime !== null && $meetingEndTime !== null) {
                        $shouldSkip = false;
                        
                        // Check if the meeting session overlaps with our specified timeframe
                        // A session overlaps if: session_start < our_end_time AND session_end > our_start_time
                        if ($startDatetime > 0 && $endDatetime > 0) {
                            // Both start and end specified - check for overlap
                            if ($meetingEndTime <= $startDatetime || $meetingStartTime >= $endDatetime) {
                                $shouldSkip = true; // No overlap
                            }
                        } else if ($startDatetime > 0) {
                            // Only start time specified - meeting must end after our start time
                            if ($meetingEndTime <= $startDatetime) {
                                $shouldSkip = true;
                            }
                        } else if ($endDatetime > 0) {
                            // Only end time specified - meeting must start before our end time
                            if ($meetingStartTime >= $endDatetime) {
                                $shouldSkip = true;
                            }
                        }
                        
                        if ($shouldSkip) {
                            if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
                                error_log("Skipping report {$report['id']} (participants: {$report['totalParticipantCount']}) - outside timeframe. " .
                                         "Meeting: " . date('Y-m-d H:i:s', $meetingStartTime) . " to " . date('Y-m-d H:i:s', $meetingEndTime) . 
                                         ", Filter: " . ($startDatetime > 0 ? date('Y-m-d H:i:s', $startDatetime) : 'none') . 
                                         " to " . ($endDatetime > 0 ? date('Y-m-d H:i:s', $endDatetime) : 'none'));
                            }
                            continue;
                        } else {
                            if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
                                error_log("Processing report {$report['id']} (participants: {$report['totalParticipantCount']}) - within timeframe. " .
                                         "Meeting: " . date('Y-m-d H:i:s', $meetingStartTime) . " to " . date('Y-m-d H:i:s', $meetingEndTime));
                            }
                        }
                    }
                }
                
                $report_data = get_meeting_attendance_report($userId, $meetingId, $report['id'], $accessToken);
		if (isset($report_data['attendanceRecords']) && is_array($report_data['attendanceRecords'])) {
                    foreach ($report_data['attendanceRecords'] as $record) {
                        $total_duration = 0;
                        $join_time = null;
                        $leave_time = null;
                        
                        // Calculate total duration from attendance intervals
                        if (isset($record['attendanceIntervals']) && is_array($record['attendanceIntervals'])) {
                            foreach ($record['attendanceIntervals'] as $interval) {
                                $total_duration += $interval['durationInSeconds'];
                                
                                // Track first join and last leave time
                                if ($join_time === null || strtotime($interval['joinDateTime']) < strtotime($join_time)) {
                                    $join_time = $interval['joinDateTime'];
                                }
                                if ($leave_time === null || strtotime($interval['leaveDateTime']) > strtotime($leave_time)) {
                                    $leave_time = $interval['leaveDateTime'];
                                }
                            }
                        }
                        
                        if (!empty($record['emailAddress'])) {
                            $identifier = $record['emailAddress'];
                        } elseif (!empty($record['identity']['displayName'])) {
                            $identifier = $record['identity']['displayName'];
                        } else {
                            $identifier = 'Unknown';
                        }
                        
                        $attendance_data[] = [
                            'reportId' => $report['id'],
                            'userId' => $identifier,
                            'totalAttendanceInSeconds' => $total_duration,
                            'joinTime' => $join_time,
                            'leaveTime' => $leave_time,
                            'role' => $record['role'] ?? 'Attendee'
                        ];
                    }
                }
            }
        }
    }

    return ['value' => $attendance_data];
}
