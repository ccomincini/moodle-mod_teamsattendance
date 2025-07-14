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

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/teamsattendance/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID
$action = optional_param('action', '', PARAM_ALPHA);
$recordid = optional_param('recordid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$apply_suggestions = optional_param('apply_suggestions', 0, PARAM_INT);

$cm = get_coursemodule_from_id('teamsattendance', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$teamsattendance = $DB->get_record('teamsattendance', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/teamsattendance:manageattendance', context_module::instance($cm->id));

$PAGE->set_url('/mod/teamsattendance/manage_unassigned.php', array('id' => $cm->id));
$PAGE->set_title(format_string($teamsattendance->name));
$PAGE->set_heading(format_string($course->fullname));

// Handle bulk application of suggested matches
if ($action === 'apply_bulk_suggestions' && confirm_sesskey()) {
    $suggestions = optional_param_array('suggestions', array(), PARAM_INT);
    $applied_count = 0;
    
    foreach ($suggestions as $recordid => $suggested_userid) {
        if ($recordid && $suggested_userid) {
            $record = $DB->get_record('teamsattendance_data', array('id' => $recordid), '*', MUST_EXIST);
            $record->userid = $suggested_userid;
            $record->manually_assigned = 1;
            
            if ($DB->update_record('teamsattendance_data', $record)) {
                // Mark this record as having had its suggestion applied
                mark_suggestion_as_applied($recordid, $suggested_userid);
                $applied_count++;
            }
        }
    }
    
    if ($applied_count > 0) {
        redirect($PAGE->url, get_string('bulk_assignments_applied', 'mod_teamsattendance', $applied_count));
    } else {
        redirect($PAGE->url, get_string('no_assignments_applied', 'mod_teamsattendance'));
    }
}

// Handle single user assignment
if ($action === 'assign' && $recordid && $userid && confirm_sesskey()) {
    $record = $DB->get_record('teamsattendance_data', array('id' => $recordid), '*', MUST_EXIST);
    $record->userid = $userid;
    $record->manually_assigned = 1; // Mark as manually assigned
    
    if ($DB->update_record('teamsattendance_data', $record)) {
        // Mark suggestion as applied if this was a suggested assignment
        mark_suggestion_as_applied($recordid, $userid);
        redirect($PAGE->url, get_string('user_assigned', 'mod_teamsattendance'));
    } else {
        redirect($PAGE->url, get_string('user_assignment_failed', 'mod_teamsattendance'));
    }
}

// Get unassigned records (where userid is guest)
$unassigned = $DB->get_records_sql("
    SELECT tad.*, u.firstname, u.lastname, u.email
    FROM {teamsattendance_data} tad
    LEFT JOIN {user} u ON u.id = tad.userid
    WHERE tad.sessionid = ? AND tad.userid = ?
    ORDER BY tad.teams_user_id
", array($teamsattendance->id, $CFG->siteguest));

// Get course users and already assigned users
$available_users = get_available_users_for_assignment();

// Get both types of suggestions
$name_suggestions = get_name_based_suggestions($unassigned, $available_users);
$email_suggestions = get_email_based_suggestions($unassigned, $available_users, $name_suggestions);

// Merge suggestions with type information
$all_suggestions = merge_suggestions_with_types($name_suggestions, $email_suggestions);

// Sort unassigned records: name suggestions first, then email suggestions, then non-suggested
$sorted_unassigned = sort_records_by_suggestion_types($unassigned, $all_suggestions);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('unassigned_records', 'mod_teamsattendance'));

// Add CSS for row styling
add_custom_css();

if (empty($unassigned)) {
    echo $OUTPUT->notification(get_string('no_unassigned', 'mod_teamsattendance'), 'notifymessage');
} else {
    // Show suggestions summary
    $name_suggestion_count = count(array_filter($name_suggestions));
    $email_suggestion_count = count(array_filter($email_suggestions));
    $total_suggestion_count = $name_suggestion_count + $email_suggestion_count;
    
    if ($total_suggestion_count > 0) {
        $summary_text = get_string('suggestions_summary', 'mod_teamsattendance', [
            'total' => $total_suggestion_count,
            'name_matches' => $name_suggestion_count,
            'email_matches' => $email_suggestion_count
        ]);
        
        echo $OUTPUT->notification($summary_text, 'notifysuccess');
        
        // Bulk apply suggestions form
        echo html_writer::start_tag('form', array(
            'method' => 'post',
            'action' => $PAGE->url->out(),
            'id' => 'bulk_suggestions_form'
        ));
        
        echo html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'apply_bulk_suggestions'
        ));
        
        echo html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        ));
    }
    
    $table = new html_table();
    $table->head = array(
        get_string('teams_user', 'mod_teamsattendance'),
        get_string('tempo_totale', 'mod_teamsattendance'),
        get_string('attendance_percentage', 'mod_teamsattendance'),
        get_string('suggested_match', 'mod_teamsattendance'),
        get_string('assign_user', 'mod_teamsattendance')
    );

    // Set table attributes for styling
    $table->attributes['class'] = 'generaltable manage-unassigned-table';

    foreach ($sorted_unassigned as $record) {
        // Check for suggestions
        $suggestion_info = isset($all_suggestions[$record->id]) ? $all_suggestions[$record->id] : null;
        $has_suggestion = !empty($suggestion_info);
        
        $suggestion_cell = '';
        $row_class = 'no-match-row'; // Default
        
        if ($suggestion_info) {
            $suggested_user = $suggestion_info['user'];
            $suggestion_type = $suggestion_info['type'];
            
            // Determine row class based on suggestion type
            $row_class = ($suggestion_type === 'name') ? 'suggested-match-row' : 'email-match-row';
            
            // Create suggestion type label
            $type_label = ($suggestion_type === 'name') ? 
                get_string('name_match_suggestion', 'mod_teamsattendance') : 
                get_string('email_match_suggestion', 'mod_teamsattendance');
            
            $suggestion_cell = html_writer::tag('div', 
                html_writer::tag('div', $type_label, array('class' => 'suggestion-type-label text-info small mb-1')) .
                html_writer::tag('strong', fullname($suggested_user), array('class' => 'text-success')) .
                html_writer::empty_tag('br') .
                html_writer::tag('small', $suggested_user->email, array('class' => 'text-muted')) .
                html_writer::empty_tag('br') .
                html_writer::checkbox('suggestions[' . $record->id . ']', $suggested_user->id, true, 
                    get_string('apply_suggestion', 'mod_teamsattendance'))
            );
        } else {
            $suggestion_cell = html_writer::tag('em', get_string('no_suggestion', 'mod_teamsattendance'), 
                array('class' => 'text-muted'));
        }
        
        // Create manual assignment form
        $assign_form = html_writer::start_tag('form', array(
            'method' => 'post',
            'action' => $PAGE->url->out(),
            'id' => 'assign_form_' . $record->id,
            'onsubmit' => 'return confirmAssignment(this);'
        ));
        
        $assign_form .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'assign'
        ));
        
        $assign_form .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'recordid',
            'value' => $record->id
        ));
        
        $assign_form .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        ));
        
        $assign_form .= html_writer::select(
            get_filtered_users_list($available_users),
            'userid',
            null,
            array('' => get_string('select_user', 'mod_teamsattendance')),
            array(
                'id' => 'user_selector_' . $record->id,
                'onchange' => 'enableAssignButton(' . $record->id . ');'
            )
        );
        
        $assign_form .= ' ';
        
        $assign_form .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'value' => get_string('assign', 'mod_teamsattendance'),
            'id' => 'assign_btn_' . $record->id,
            'disabled' => 'disabled',
            'class' => 'btn btn-primary btn-sm'
        ));
        
        $assign_form .= html_writer::end_tag('form');

        // Create the row with appropriate styling class
        $row = new html_table_row();
        $row->attributes['class'] = $row_class;
        $row->attributes['data-record-id'] = $record->id;
        $row->attributes['data-has-suggestion'] = $has_suggestion ? '1' : '0';
        $row->attributes['data-suggestion-type'] = $suggestion_info ? $suggestion_info['type'] : 'none';
        
        $row->cells = array(
            $record->teams_user_id,
            format_time($record->attendance_duration),
            $record->actual_attendance . '%',
            $suggestion_cell,
            $assign_form
        );

        $table->data[] = $row;
    }

    echo html_writer::table($table);
    
    // Close bulk suggestions form and add apply button
    if ($total_suggestion_count > 0) {
        echo html_writer::tag('div', 
            html_writer::empty_tag('input', array(
                'type' => 'submit',
                'value' => get_string('apply_selected_suggestions', 'mod_teamsattendance'),
                'class' => 'btn btn-success btn-lg',
                'onclick' => 'return confirmBulkAssignment();'
            )),
            array('class' => 'text-center mt-3')
        );
        
        echo html_writer::end_tag('form');
    }
    
    // Add JavaScript for functionality
    add_javascript_functions();
}

echo $OUTPUT->footer();

// ========================= HELPER FUNCTIONS =========================

function get_available_users_for_assignment() {
    global $DB, $COURSE, $teamsattendance;
    
    $context = context_course::instance($COURSE->id);
    $enrolled_users = get_enrolled_users($context);
    
    // Get already assigned user IDs for this session
    $assigned_userids = $DB->get_fieldset_select(
        'teamsattendance_data',
        'userid',
        'sessionid = ? AND userid != ?',
        array($teamsattendance->id, $GLOBALS['CFG']->siteguest)
    );
    
    // Filter out already assigned users
    $available_users = array();
    foreach ($enrolled_users as $user) {
        if (!in_array($user->id, $assigned_userids)) {
            $available_users[$user->id] = $user;
        }
    }
    
    return $available_users;
}

function get_name_based_suggestions($unassigned_records, $available_users) {
    $suggestions = array();
    
    foreach ($unassigned_records as $record) {
        if (was_suggestion_applied($record->id)) {
            continue;
        }
        
        $teams_name = trim($record->teams_user_id);
        $parsed_names = parse_teams_name($teams_name);
        
        if (!empty($parsed_names)) {
            $best_match = find_best_name_match($parsed_names, $available_users);
            if ($best_match) {
                $suggestions[$record->id] = $best_match;
            }
        }
    }
    
    return $suggestions;
}

function get_email_based_suggestions($unassigned_records, $available_users, $name_suggestions) {
    $suggestions = array();
    
    foreach ($unassigned_records as $record) {
        // Skip if already has a name-based suggestion
        if (isset($name_suggestions[$record->id])) {
            continue;
        }
        
        if (was_suggestion_applied($record->id)) {
            continue;
        }
        
        $teams_user_id = trim($record->teams_user_id);
        
        // Check if teams_user_id looks like an email
        if (filter_var($teams_user_id, FILTER_VALIDATE_EMAIL)) {
            $best_match = find_best_email_match($teams_user_id, $available_users);
            if ($best_match) {
                $suggestions[$record->id] = $best_match;
            }
        }
    }
    
    return $suggestions;
}

function find_best_email_match($teams_email, $available_users) {
    $email_parts = explode('@', strtolower($teams_email));
    if (count($email_parts) !== 2) {
        return null;
    }
    
    $local_part = $email_parts[0]; // Part before @
    
    $best_match = null;
    $best_score = 0;
    
    foreach ($available_users as $user) {
        $score = calculate_email_similarity($local_part, $user);
        
        if ($score > $best_score && $score >= 0.7) { // 70% similarity threshold for email matching
            $best_score = $score;
            $best_match = $user;
        }
    }
    
    return $best_match;
}

function calculate_email_similarity($local_part, $user) {
    $firstname = strtolower($user->firstname);
    $lastname = strtolower($user->lastname);
    
    // Remove non-alphanumeric characters and normalize
    $local_part = preg_replace('/[^a-z0-9]/', '', $local_part);
    $firstname = preg_replace('/[^a-z0-9]/', '', $firstname);
    $lastname = preg_replace('/[^a-z0-9]/', '', $lastname);
    
    $scores = array();
    
    // Test different email patterns
    $patterns = array(
        $firstname . $lastname,           // nome.cognome
        $lastname . $firstname,           // cognome.nome
        $firstname[0] . $lastname,        // n.cognome (initial + lastname)
        $lastname . $firstname[0],        // cognome.n (lastname + initial)
        $firstname . $lastname[0],        // nome.c (firstname + initial)
        $firstname,                       // solo nome
        $lastname,                        // solo cognome
        $firstname[0] . $lastname[0],     // n.c (initials)
    );
    
    foreach ($patterns as $pattern) {
        if (!empty($pattern)) {
            $similarity = similarity_score($local_part, $pattern);
            $scores[] = $similarity;
        }
    }
    
    // Return the best score
    return empty($scores) ? 0 : max($scores);
}

function merge_suggestions_with_types($name_suggestions, $email_suggestions) {
    $merged = array();
    
    // Add name-based suggestions
    foreach ($name_suggestions as $record_id => $user) {
        $merged[$record_id] = array(
            'user' => $user,
            'type' => 'name',
            'priority' => 1
        );
    }
    
    // Add email-based suggestions
    foreach ($email_suggestions as $record_id => $user) {
        $merged[$record_id] = array(
            'user' => $user,
            'type' => 'email',
            'priority' => 2
        );
    }
    
    return $merged;
}

function sort_records_by_suggestion_types($unassigned_records, $all_suggestions) {
    $name_suggested = array();
    $email_suggested = array();
    $not_suggested = array();
    
    foreach ($unassigned_records as $record) {
        if (isset($all_suggestions[$record->id])) {
            $suggestion_type = $all_suggestions[$record->id]['type'];
            if ($suggestion_type === 'name') {
                $name_suggested[] = $record;
            } else {
                $email_suggested[] = $record;
            }
        } else {
            $not_suggested[] = $record;
        }
    }
    
    // Merge arrays: name suggestions first, then email suggestions, then not suggested
    return array_merge($name_suggested, $email_suggested, $not_suggested);
}

function mark_suggestion_as_applied($record_id, $user_id) {
    global $DB;
    
    $preference_name = 'teamsattendance_suggestion_applied_' . $record_id;
    set_user_preference($preference_name, $user_id);
}

function was_suggestion_applied($record_id) {
    $preference_name = 'teamsattendance_suggestion_applied_' . $record_id;
    $applied_user_id = get_user_preferences($preference_name, null);
    
    return !is_null($applied_user_id);
}

function parse_teams_name($teams_name) {
    $names = array();
    
    // Remove common separators and clean up
    $clean_name = preg_replace('/[,;|]/', ' ', $teams_name);
    $clean_name = preg_replace('/\s+/', ' ', trim($clean_name));
    
    if (empty($clean_name)) {
        return $names;
    }
    
    $parts = explode(' ', $clean_name);
    $parts = array_filter($parts); // Remove empty parts
    
    if (count($parts) >= 2) {
        // Try "LastName, FirstName" format
        if (strpos($teams_name, ',') !== false) {
            $comma_parts = array_map('trim', explode(',', $teams_name));
            if (count($comma_parts) >= 2) {
                $names[] = array(
                    'firstname' => $comma_parts[1],
                    'lastname' => $comma_parts[0]
                );
            }
        }
        
        // Try "FirstName LastName" format
        $names[] = array(
            'firstname' => $parts[0],
            'lastname' => $parts[count($parts) - 1]
        );
        
        // Try "LastName FirstName" format
        $names[] = array(
            'firstname' => $parts[count($parts) - 1],
            'lastname' => $parts[0]
        );
        
        // If more than 2 parts, try middle combinations
        if (count($parts) > 2) {
            $names[] = array(
                'firstname' => $parts[0] . ' ' . $parts[1],
                'lastname' => $parts[count($parts) - 1]
            );
        }
    }
    
    return $names;
}

function find_best_name_match($parsed_names, $available_users) {
    $best_match = null;
    $best_score = 0;
    
    foreach ($parsed_names as $name_combo) {
        foreach ($available_users as $user) {
            $score = calculate_name_similarity($name_combo, $user);
            
            if ($score > $best_score && $score >= 0.8) { // Minimum 80% similarity
                $best_score = $score;
                $best_match = $user;
            }
        }
    }
    
    return $best_match;
}

function calculate_name_similarity($parsed_name, $user) {
    $firstname_similarity = similarity_score(
        strtolower($parsed_name['firstname']), 
        strtolower($user->firstname)
    );
    
    $lastname_similarity = similarity_score(
        strtolower($parsed_name['lastname']), 
        strtolower($user->lastname)
    );
    
    // Weight both names equally
    return ($firstname_similarity + $lastname_similarity) / 2;
}

function similarity_score($str1, $str2) {
    // Use Levenshtein distance for similarity
    $max_len = max(strlen($str1), strlen($str2));
    if ($max_len == 0) return 1.0;
    
    $distance = levenshtein($str1, $str2);
    return 1 - ($distance / $max_len);
}

function get_filtered_users_list($available_users) {
    $userlist = array();
    $sortable_users = array();
    
    // Create array with sortable full names
    foreach ($available_users as $user) {
        $fullname = fullname($user);
        $display_name = $fullname . ' (' . $user->email . ')';
        $sortable_users[] = array(
            'id' => $user->id,
            'fullname' => $fullname,
            'display_name' => $display_name,
            'lastname' => $user->lastname,
            'firstname' => $user->firstname
        );
    }
    
    // Sort by lastname first, then firstname (Italian standard)
    usort($sortable_users, function($a, $b) {
        $lastname_comparison = strcasecmp($a['lastname'], $b['lastname']);
        if ($lastname_comparison === 0) {
            return strcasecmp($a['firstname'], $b['firstname']);
        }
        return $lastname_comparison;
    });
    
    // Build the final array for the dropdown
    foreach ($sortable_users as $user_data) {
        $userlist[$user_data['id']] = $user_data['display_name'];
    }
    
    return $userlist;
}

function add_custom_css() {
    echo html_writer::start_tag('style', array('type' => 'text/css'));
    echo '
        /* Styling for name-based suggested match rows */
        .manage-unassigned-table tr.suggested-match-row {
            background-color: #d4edda !important; /* Light green background */
            border-left: 4px solid #28a745; /* Green left border */
        }
        
        /* Styling for email-based suggested match rows */
        .manage-unassigned-table tr.email-match-row {
            background-color: #e8d5ff !important; /* Light purple background */
            border-left: 4px solid #8b5cf6; /* Purple left border */
        }
        
        /* Styling for no match rows */
        .manage-unassigned-table tr.no-match-row {
            background-color: #fff3cd !important; /* Light orange background */
            border-left: 4px solid #ffc107; /* Orange left border */
        }
        
        /* Hover effects */
        .manage-unassigned-table tr.suggested-match-row:hover {
            background-color: #c3e6cb !important; /* Slightly darker green on hover */
        }
        
        .manage-unassigned-table tr.email-match-row:hover {
            background-color: #ddd6fe !important; /* Slightly darker purple on hover */
        }
        
        .manage-unassigned-table tr.no-match-row:hover {
            background-color: #ffeaa7 !important; /* Slightly darker orange on hover */
        }
        
        /* Legend for color coding */
        .color-legend {
            margin: 15px 0;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        
        .legend-item {
            display: inline-block;
            margin-right: 20px;
            padding: 5px 10px;
            border-radius: 3px;
        }
        
        .legend-suggested {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
        }
        
        .legend-email {
            background-color: #e8d5ff;
            border-left: 4px solid #8b5cf6;
        }
        
        .legend-no-match {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        /* Make suggested checkboxes more prominent */
        .suggested-match-row input[type="checkbox"],
        .email-match-row input[type="checkbox"] {
            transform: scale(1.2);
            margin-right: 5px;
        }
        
        /* Styling for suggestion type labels */
        .suggestion-type-label {
            font-weight: bold;
            font-style: italic;
        }
    ';
    echo html_writer::end_tag('style');
    
    // Add color legend
    echo html_writer::start_tag('div', array('class' => 'color-legend'));
    echo html_writer::tag('strong', get_string('color_legend', 'mod_teamsattendance') . ': ');
    echo html_writer::tag('span', 
        get_string('name_based_matches', 'mod_teamsattendance'), 
        array('class' => 'legend-item legend-suggested')
    );
    echo html_writer::tag('span', 
        get_string('email_based_matches', 'mod_teamsattendance'), 
        array('class' => 'legend-item legend-email')
    );
    echo html_writer::tag('span', 
        get_string('no_matches', 'mod_teamsattendance'), 
        array('class' => 'legend-item legend-no-match')
    );
    echo html_writer::end_tag('div');
}

function add_javascript_functions() {
    echo html_writer::start_tag('script', array('type' => 'text/javascript'));
    echo '
        function enableAssignButton(recordId) {
            var select = document.getElementById("user_selector_" + recordId);
            var button = document.getElementById("assign_btn_" + recordId);
            
            if (select.value !== "") {
                button.disabled = false;
            } else {
                button.disabled = true;
            }
        }
        
        function confirmAssignment(form) {
            var select = form.querySelector("select[name=\'userid\']");
            var selectedOption = select.options[select.selectedIndex];
            
            if (select.value === "") {
                alert("' . get_string('select_user_first', 'mod_teamsattendance') . '");
                return false;
            }
            
            var userName = selectedOption.text;
            var confirmMessage = "' . get_string('confirm_assignment', 'mod_teamsattendance') . '".replace("{user}", userName);
            
            return confirm(confirmMessage);
        }
        
        function confirmBulkAssignment() {
            var checkedBoxes = document.querySelectorAll("input[name^=\'suggestions[\']:checked");
            
            if (checkedBoxes.length === 0) {
                alert("' . get_string('select_suggestions_first', 'mod_teamsattendance') . '");
                return false;
            }
            
            var confirmMessage = "' . get_string('confirm_bulk_assignment', 'mod_teamsattendance') . '".replace("{count}", checkedBoxes.length);
            
            return confirm(confirmMessage);
        }
        
        // Add visual feedback when suggestions are selected/deselected
        document.addEventListener("DOMContentLoaded", function() {
            var checkboxes = document.querySelectorAll("input[name^=\'suggestions[\']");
            checkboxes.forEach(function(checkbox) {
                checkbox.addEventListener("change", function() {
                    var row = this.closest("tr");
                    if (this.checked) {
                        row.style.boxShadow = "0 0 10px rgba(40, 167, 69, 0.5)";
                    } else {
                        row.style.boxShadow = "none";
                    }
                });
            });
        });
    ';
    echo html_writer::end_tag('script');
}
