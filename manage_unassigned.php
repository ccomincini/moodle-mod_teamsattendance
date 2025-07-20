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
 * Performance-optimized manage unassigned Teams attendance records (Modular Version)
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    2.1.0
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/teamsattendance/lib.php');

// Load performance-optimized components
require_once($CFG->dirroot . '/mod/teamsattendance/classes/performance_data_handler.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/suggestion_engine.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/ui_renderer.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/ui_assets_manager.php');

// Load modular components
require_once($CFG->dirroot . '/mod/teamsattendance/templates/unassigned_interface.php');

// Get parameters
$id = required_param('id', PARAM_INT); // Course module ID
$page = optional_param('page', 0, PARAM_INT);
$per_page = optional_param('per_page', 0, PARAM_INT);
$filter = optional_param('filter', 'all', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_TEXT);

// AJAX parameters
$ajax = optional_param('ajax', 0, PARAM_INT);
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
$PAGE->set_title(format_string($teamsattendance->name . ' - ' . get_string('manage_unassigned', 'teamsattendance')));
$PAGE->set_heading(format_string($course->fullname));

// Initialize performance handler
$performance_handler = new performance_data_handler($cm, $teamsattendance, $course);

// Get performance statistics first
$perf_stats = $performance_handler->get_performance_statistics();

// Set optimal page size if not specified
if ($per_page <= 0) {
    $per_page = $perf_stats['recommended_page_size'];
}

// Get available users for manual assignment
$context = context_course::instance($course->id);
$enrolled_users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname', 'u.lastname ASC, u.firstname ASC');

// 1. Ottieni tutti gli utenti già assegnati per questa sessione
$assigned_userids = $DB->get_fieldset_select('teamsattendance_data', 
    'DISTINCT userid', 
    'sessionid = ? AND userid IS NOT NULL AND userid > 0', 
    array($teamsattendance->id)
);

// 2. Ottieni tutti gli utenti disponibili (non ancora assegnati)
$available_users = array();
foreach ($enrolled_users as $user) {
    if (!in_array($user->id, $assigned_userids)) {
        $available_users[] = array(
            'id' => $user->id,
            'name' => $user->lastname . ', ' . $user->firstname,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname
        );
    }
}

// ========================= AJAX HANDLERS =========================

if ($ajax) {
    header('Content-Type: application/json');
    
    try {
        switch ($action) {
            case 'load_page':
                $paginated_data = $performance_handler->get_unassigned_records_paginated($page, $per_page, $filter);
                
                if ($filter === 'with_suggestions' || $filter === 'without_suggestions') {
                    $paginated_data = $performance_handler->filter_records_by_suggestions($paginated_data, $filter);
                }
                
                // Get suggestions for current page
                $suggestions = $performance_handler->get_suggestions_for_batch($paginated_data['records']);
                
                // Prepare data for frontend
                $response_data = array(
                    'records' => array(),
                    'pagination' => array(
                        'page' => $paginated_data['page'],
                        'per_page' => $paginated_data['per_page'],
                        'total_pages' => $paginated_data['total_pages'],
                        'total_count' => $paginated_data['total_count'],
                        'has_next' => $paginated_data['has_next'],
                        'has_previous' => $paginated_data['has_previous']
                    )
                );
                
                foreach ($paginated_data['records'] as $record) {
                    $record_data = array(
                        'id' => $record->id,
                        'teams_user_id' => $record->teams_user_id,
                        'attendance_duration' => $record->attendance_duration,
                        'has_suggestion' => isset($suggestions[$record->id]),
                        'suggestion' => isset($suggestions[$record->id]) ? $suggestions[$record->id] : null
                    );
                    $response_data['records'][] = $record_data;
                }
                
                echo json_encode(array('success' => true, 'data' => $response_data));
                break;
                
            case 'assign_user':
                if ($recordid && $userid && confirm_sesskey()) {
                    // Use original assignment handler for single assignments
                    require_once($CFG->dirroot . '/mod/teamsattendance/classes/user_assignment_handler.php');
                    $assignment_handler = new user_assignment_handler($cm, $teamsattendance, $course);
                    $result = $assignment_handler->assign_single_user($recordid, $userid);
                    
                    if ($result['success']) {
                        // Clear cache after assignment
                        $performance_handler->clear_cache();
                        echo json_encode(array('success' => true, 'message' => 'User assigned successfully'));
                    } else {
                        echo json_encode(array('success' => false, 'error' => $result['error']));
                    }
                } else {
                    echo json_encode(array('success' => false, 'error' => 'Invalid parameters'));
                }
                break;
                
            case 'bulk_assign':
                if (confirm_sesskey()) {
                    $assignments = optional_param_array('assignments', array(), PARAM_INT);
                    $result = $performance_handler->apply_bulk_assignments_with_progress($assignments);
                    
                    echo json_encode(array(
                        'success' => true,
                        'data' => $result
                    ));
                } else {
                    echo json_encode(array('success' => false, 'error' => 'Invalid session'));
                }
                break;
                
            default:
                echo json_encode(array('success' => false, 'error' => 'Unknown action'));
        }
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'error' => $e->getMessage()));
    }
    
    exit;
}

// ========================= PAGE OUTPUT =========================

// Load CSS and JavaScript
$PAGE->requires->css('/mod/teamsattendance/styles/unassigned_manager.css');
$PAGE->requires->jquery();

echo $OUTPUT->header();

// Show performance warning for large datasets
if ($perf_stats['performance_level'] === 'challenging') {
    echo $OUTPUT->notification(
        get_string('performance_challenging', 'teamsattendance') . ' ' .
        get_string('estimated_time', 'teamsattendance') . ': ' . $perf_stats['estimated_suggestion_time'],
        'warning'
    );
}

echo $OUTPUT->heading(get_string('manage_unassigned', 'teamsattendance'));

// Prepare template context
$template_context = (object) array(
    'perf_stats' => $perf_stats,
    'per_page' => $per_page,
    'cm_id' => $cm->id
);

// Render the interface using the template
echo render_unassigned_interface($template_context);

// Initialize JavaScript with configuration
$js_config = array(
    'defaultPageSize' => $per_page,
    'cmId' => $cm->id,
    'sesskey' => sesskey(),
    'availableUsers' => $available_users,
    'strings' => array(
        'teams_user_id' => get_string('teams_user_id', 'teamsattendance'),
        'attendance_duration' => get_string('attendance_duration', 'teamsattendance'),
        'suggested_match' => get_string('suggested_match', 'teamsattendance'),
        'actions' => get_string('actions', 'teamsattendance'),
        'no_records_found' => get_string('no_records_found', 'teamsattendance'),
        'no_suggestion' => get_string('no_suggestion', 'teamsattendance'),
        'apply_suggestion' => get_string('apply_suggestion', 'teamsattendance'),
        'apply_selected' => get_string('apply_selected', 'teamsattendance'),
        'applying' => get_string('applying', 'teamsattendance'),
        'previous' => get_string('previous', 'teamsattendance'),
        'next' => get_string('next', 'teamsattendance'),
        'page' => get_string('page', 'teamsattendance'),
        'of' => get_string('of', 'teamsattendance'),
        'total_records' => get_string('total_records', 'teamsattendance'),
        'select_user' => get_string('select_user', 'teamsattendance'),
        'assign' => get_string('assign', 'teamsattendance')
    )
);
// Load modular JavaScript 
$PAGE->requires->js_call_amd('mod_teamsattendance/unassigned_manager', 'init', [$js_config]);

echo $OUTPUT->footer();
