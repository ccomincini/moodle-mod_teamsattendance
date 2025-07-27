<?php
// This file is part of Moodle - http://moodle.org/
//
// Extended test script for teams_id_matcher with real course data
// Tests all Teams IDs from course 57 against all enrolled users

define('CLI_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/teams_id_matcher.php');

$course_id = 57;

echo "=== EXTENDED TEAMS ID PATTERN MATCHING TEST ===\n";
echo "Course ID: $course_id\n\n";

// Get all enrolled users for course 57
$course_context = context_course::instance($course_id);
$enrolled_users = get_enrolled_users($course_context, '', 0, 'u.id, u.firstname, u.lastname, u.email');

echo "Enrolled users: " . count($enrolled_users) . "\n";

// Get all Teams IDs from teamsattendance_data for this course
$sql = "SELECT DISTINCT tad.teams_user_id
        FROM {teamsattendance_data} tad
        JOIN {teamsattendance} ta ON ta.id = tad.teamsattendance
        WHERE ta.course = ?
        AND tad.teams_user_id IS NOT NULL
        AND tad.teams_user_id != ''
        ORDER BY tad.teams_user_id";

$teams_ids = $DB->get_records_sql($sql, [$course_id]);

echo "Unique Teams IDs: " . count($teams_ids) . "\n\n";

if (empty($enrolled_users) || empty($teams_ids)) {
    echo "ERROR: No data found for course $course_id\n";
    exit(1);
}

// Initialize matcher
$matcher = new teams_id_matcher(array_values($enrolled_users));

// Test statistics
$total_tests = 0;
$matches_found = 0;
$email_skipped = 0;

echo "=== MATCHING RESULTS ===\n\n";

foreach ($teams_ids as $record) {
    $teams_id = trim($record->teams_user_id);
    
    if (empty($teams_id)) {
        continue;
    }
    
    $total_tests++;
    
    echo "Teams ID: '$teams_id'\n";
    
    // Check if it's an email (should be handled by email matcher)
    if (filter_var($teams_id, FILTER_VALIDATE_EMAIL)) {
        echo "  → SKIPPED (email - handled by email_pattern_matcher)\n\n";
        $email_skipped++;
        continue;
    }
    
    $match = $matcher->find_best_teams_match($teams_id);
    
    if ($match) {
        echo "  ✓ MATCH: {$match->firstname} {$match->lastname} (ID: {$match->id})\n";
        echo "    Email: {$match->email}\n";
        $matches_found++;
    } else {
        echo "  ✗ No match found\n";
    }
    echo "\n";
}

echo "=== STATISTICS ===\n";
echo "Total Teams IDs tested: $total_tests\n";
echo "Email addresses skipped: $email_skipped\n";
echo "Non-email IDs tested: " . ($total_tests - $email_skipped) . "\n";
echo "Matches found: $matches_found\n";
echo "Match rate: " . round(($matches_found / max(1, $total_tests - $email_skipped)) * 100, 1) . "%\n";
echo "\n=== Test completed ===\n";
