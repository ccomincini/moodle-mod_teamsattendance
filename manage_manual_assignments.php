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

$cm = get_coursemodule_from_id('teamsattendance', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$teamsattendance = $DB->get_record('teamsattendance', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/teamsattendance:manageattendance', context_module::instance($cm->id));

$PAGE->set_url('/mod/teamsattendance/manage_manual_assignments.php', array('id' => $cm->id));
$PAGE->set_title(format_string($teamsattendance->name));
$PAGE->set_heading(format_string($course->fullname));

// Handle user reassignment
if ($action === 'reassign' && $recordid && $userid && confirm_sesskey()) {
    $record = $DB->get_record('teamsattendance_data', array('id' => $recordid), '*', MUST_EXIST);
    
    // Only allow reassignment of manually assigned records
    if ($record->manually_assigned == 1) {
        $record->userid = $userid;
        
        if ($DB->update_record('teamsattendance_data', $record)) {
            redirect($PAGE->url, get_string('user_reassigned', 'mod_teamsattendance'));
        } else {
            redirect($PAGE->url, get_string('user_reassignment_failed', 'mod_teamsattendance'));
        }
    } else {
        redirect($PAGE->url, get_string('cannot_reassign_automatic', 'mod_teamsattendance'));
    }
}

// Handle marking as automatic (remove manual assignment flag)
if ($action === 'mark_automatic' && $recordid && confirm_sesskey()) {
    $record = $DB->get_record('teamsattendance_data', array('id' => $recordid), '*', MUST_EXIST);
    $record->manually_assigned = 0;
    
    if ($DB->update_record('teamsattendance_data', $record)) {
        redirect($PAGE->url, get_string('marked_as_automatic', 'mod_teamsattendance'));
    } else {
        redirect($PAGE->url, get_string('mark_automatic_failed', 'mod_teamsattendance'));
    }
}

// Get manually assigned records
$manual_records = $DB->get_records_sql("
    SELECT tad.*, u.firstname, u.lastname, u.email
    FROM {teamsattendance_data} tad
    JOIN {user} u ON u.id = tad.userid
    WHERE tad.sessionid = ? AND tad.manually_assigned = 1
    ORDER BY u.lastname, u.firstname
", array($teamsattendance->id));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manual_assignments', 'mod_teamsattendance'));

if (empty($manual_records)) {
    echo $OUTPUT->notification(get_string('no_manual_assignments', 'mod_teamsattendance'), 'notifymessage');
} else {
    $table = new html_table();
    $table->head = array(
        get_string('teams_user', 'mod_teamsattendance'),
        get_string('assigned_user', 'mod_teamsattendance'),
        get_string('tempo_totale', 'mod_teamsattendance'),
        get_string('attendance_percentage', 'mod_teamsattendance'),
        get_string('actions', 'mod_teamsattendance')
    );

    foreach ($manual_records as $record) {
        // Reassign user form
        $reassign_form = html_writer::start_tag('form', array(
            'method' => 'post',
            'action' => $PAGE->url->out(),
            'id' => 'reassign_form_' . $record->id,
            'onsubmit' => 'return confirmReassignment(this);',
            'style' => 'display: inline-block; margin-right: 10px;'
        ));
        
        $reassign_form .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'reassign'
        ));
        
        $reassign_form .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'recordid',
            'value' => $record->id
        ));
        
        $reassign_form .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        ));
        
        $reassign_form .= html_writer::select(
            get_users_list(),
            'userid',
            $record->userid,
            array('' => get_string('select_different_user', 'mod_teamsattendance')),
            array(
                'id' => 'user_selector_' . $record->id,
                'onchange' => 'enableReassignButton(' . $record->id . ');'
            )
        );
        
        $reassign_form .= ' ';
        
        $reassign_form .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'value' => get_string('reassign', 'mod_teamsattendance'),
            'id' => 'reassign_btn_' . $record->id,
            'disabled' => 'disabled',
            'class' => 'btn btn-primary btn-sm'
        ));
        
        $reassign_form .= html_writer::end_tag('form');
        
        // Mark as automatic form
        $automatic_form = html_writer::start_tag('form', array(
            'method' => 'post',
            'action' => $PAGE->url->out(),
            'onsubmit' => 'return confirmMarkAutomatic();',
            'style' => 'display: inline-block;'
        ));
        
        $automatic_form .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'mark_automatic'
        ));
        
        $automatic_form .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'recordid',
            'value' => $record->id
        ));
        
        $automatic_form .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        ));
        
        $automatic_form .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'value' => get_string('mark_automatic', 'mod_teamsattendance'),
            'class' => 'btn btn-secondary btn-sm'
        ));
        
        $automatic_form .= html_writer::end_tag('form');

        $table->data[] = array(
            $record->teams_user_id,
            fullname($record) . ' (' . $record->email . ')',
            format_time($record->attendance_duration),
            $record->actual_attendance . '%',
            $reassign_form . $automatic_form
        );
    }

    echo html_writer::table($table);
    
    // Add JavaScript for confirmation and button enabling
    echo html_writer::start_tag('script', array('type' => 'text/javascript'));
    echo '
        function enableReassignButton(recordId) {
            var select = document.getElementById("user_selector_" + recordId);
            var button = document.getElementById("reassign_btn_" + recordId);
            var originalValue = select.getAttribute("data-original-value") || select.options[select.selectedIndex].value;
            
            if (select.value !== "" && select.value !== originalValue) {
                button.disabled = false;
            } else {
                button.disabled = true;
            }
        }
        
        function confirmReassignment(form) {
            var select = form.querySelector("select[name=\'userid\']");
            var selectedOption = select.options[select.selectedIndex];
            
            if (select.value === "") {
                alert("' . get_string('select_user_first', 'mod_teamsattendance') . '");
                return false;
            }
            
            var userName = selectedOption.text;
            var confirmMessage = "' . get_string('confirm_reassignment', 'mod_teamsattendance') . '".replace("{user}", userName);
            
            return confirm(confirmMessage);
        }
        
        function confirmMarkAutomatic() {
            return confirm("' . get_string('confirm_mark_automatic', 'mod_teamsattendance') . '");
        }
        
        // Store original values for comparison
        document.addEventListener("DOMContentLoaded", function() {
            var selects = document.querySelectorAll("select[id^=\'user_selector_\']");
            selects.forEach(function(select) {
                select.setAttribute("data-original-value", select.value);
            });
        });
    ';
    echo html_writer::end_tag('script');
}

echo $OUTPUT->footer();

/**
 * Get a list of users for the selector
 *
 * @return array Array of userid => fullname
 */
function get_users_list() {
    global $DB, $COURSE;
    
    $context = context_course::instance($COURSE->id);
    $users = get_enrolled_users($context);
    
    $userlist = array();
    foreach ($users as $user) {
        $userlist[$user->id] = fullname($user) . ' (' . $user->email . ')';
    }
    
    return $userlist;
} 