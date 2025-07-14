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
 * Manage unassigned Teams attendance records
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/teamsattendance/lib.php');

// Load our modular components
require_once($CFG->dirroot . '/mod/teamsattendance/classes/suggestion_engine.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/user_assignment_handler.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/ui_renderer.php');

// Get parameters
$id = required_param('id', PARAM_INT); // Course module ID
$action = optional_param('action', '', PARAM_ALPHA);
$recordid = optional_param('recordid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

// Initialize Moodle objects
$cm = get_coursemodule_from_id('teamsattendance', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$teamsattendance = $DB->get_record('teamsattendance', array('id' => $cm->instance), '*', MUST_EXIST);

// Security checks
require_login($course, true, $cm);
require_capability('mod/teamsattendance:manageattendance', context_module::instance($cm->id));

// Setup page
$PAGE->set_url('/mod/teamsattendance/manage_unassigned.php', array('id' => $cm->id));
$PAGE->set_title(format_string($teamsattendance->name));
$PAGE->set_heading(format_string($course->fullname));

// Initialize our modular components
$assignment_handler = new user_assignment_handler($cm, $teamsattendance, $course);
$ui_renderer = new ui_renderer($cm, $PAGE->url);

// ========================= ACTION HANDLERS =========================

// Handle bulk application of suggested matches
if ($action === 'apply_bulk_suggestions' && confirm_sesskey()) {
    $suggestions = optional_param_array('suggestions', array(), PARAM_INT);
    $result = $assignment_handler->apply_bulk_suggestions($suggestions);
    
    if ($result['applied_count'] > 0) {
        $message = get_string('bulk_assignments_applied', 'mod_teamsattendance', $result['applied_count']);
        if (!empty($result['errors'])) {
            $message .= ' ' . get_string('some_errors_occurred', 'mod_teamsattendance');
        }
        redirect($PAGE->url, $message);
    } else {
        $error_message = get_string('no_assignments_applied', 'mod_teamsattendance');
        if (!empty($result['errors'])) {
            $error_message .= ' ' . implode('; ', $result['errors']);
        }
        redirect($PAGE->url, $error_message);
    }
}

// Handle single user assignment
if ($action === 'assign' && $recordid && $userid && confirm_sesskey()) {
    $result = $assignment_handler->assign_single_user($recordid, $userid);
    
    if ($result['success']) {
        redirect($PAGE->url, get_string('user_assigned', 'mod_teamsattendance'));
    } else {
        redirect($PAGE->url, get_string('user_assignment_failed', 'mod_teamsattendance') . ': ' . $result['error']);
    }
}

// ========================= DATA PREPARATION =========================

// Get unassigned records and available users
$unassigned_records = $assignment_handler->get_unassigned_records();
$available_users = $assignment_handler->get_available_users();

// Generate suggestions if we have unassigned records
$all_suggestions = array();
$sorted_unassigned = array();

if (!empty($unassigned_records)) {
    // Initialize suggestion engine
    $suggestion_engine = new suggestion_engine($available_users);
    
    // Generate suggestions
    $all_suggestions = $suggestion_engine->generate_suggestions($unassigned_records);
    
    // Sort records by suggestion priority
    $sorted_unassigned = $suggestion_engine->sort_records_by_suggestion_types($unassigned_records, $all_suggestions);
    
    // Get suggestion statistics
    $suggestion_stats = $suggestion_engine->get_suggestion_statistics($all_suggestions);
} else {
    $suggestion_stats = array('total' => 0, 'name_based' => 0, 'email_based' => 0);
}

// Get assignment statistics
$assignment_stats = $assignment_handler->get_assignment_statistics();

// ========================= PAGE OUTPUT =========================

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('unassigned_records', 'mod_teamsattendance'));

// Add custom CSS
echo $ui_renderer->render_custom_css();

// Add color legend
echo $ui_renderer->render_color_legend();

// Show assignment statistics
if ($assignment_stats['total_records'] > 0) {
    echo $ui_renderer->render_statistics_box($assignment_stats);
}

if (empty($unassigned_records)) {
    // No unassigned records
    echo $ui_renderer->render_no_unassigned_state();
} else {
    // Show suggestions summary
    echo $ui_renderer->render_suggestions_summary($all_suggestions);
    
    // Start bulk suggestions form if we have suggestions
    if ($suggestion_stats['total'] > 0) {
        echo $ui_renderer->start_bulk_suggestions_form();
    }
    
    // Render action buttons
    echo $ui_renderer->render_action_buttons($suggestion_stats['total'] > 0);
    
    // Render main table
    echo $ui_renderer->render_unassigned_table($sorted_unassigned, $all_suggestions, $available_users);
    
    // End bulk suggestions form if we have suggestions
    if ($suggestion_stats['total'] > 0) {
        echo $ui_renderer->end_bulk_suggestions_form($suggestion_stats['total']);
    }
    
    // Add JavaScript functionality
    echo $ui_renderer->render_javascript();
    echo $ui_renderer->render_additional_javascript();
}

echo $OUTPUT->footer();
