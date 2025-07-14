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

$PAGE->set_url('/mod/teamsattendance/manage_unassigned.php', array('id' => $cm->id));
$PAGE->set_title(format_string($teamsattendance->name));
$PAGE->set_heading(format_string($course->fullname));

// Handle user assignment
if ($action === 'assign' && $recordid && $userid && confirm_sesskey()) {
    $record = $DB->get_record('teamsattendance_data', array('id' => $recordid), '*', MUST_EXIST);
    $record->userid = $userid;
    $record->manually_assigned = 1; // Mark as manually assigned
    
    if ($DB->update_record('teamsattendance_data', $record)) {
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

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('unassigned_records', 'mod_teamsattendance'));

if (empty($unassigned)) {
    echo $OUTPUT->notification(get_string('no_unassigned', 'mod_teamsattendance'), 'notifymessage');
} else {
    $table = new html_table();
    $table->head = array(
        get_string('teams_user', 'mod_teamsattendance'),
        get_string('tempo_totale', 'mod_teamsattendance'),
        get_string('attendance_percentage', 'mod_teamsattendance'),
        get_string('assign_user', 'mod_teamsattendance')
    );

    foreach ($unassigned as $record) {
        // Create a form for user assignment with confirmation
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
            get_users_list(),
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
            'class' => 'btn btn-primary'
        ));
        
        $assign_form .= html_writer::end_tag('form');

        $table->data[] = array(
            $record->teams_user_id,
            format_time($record->attendance_duration),
            $record->actual_attendance . '%',
            $assign_form
        );
    }

    echo html_writer::table($table);
    
    // Add JavaScript for confirmation and button enabling
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