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
 * UI renderer for Teams attendance manage unassigned page
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Handles UI rendering for the manage unassigned page
 */
class ui_renderer {
    
    /** @var object Course module */
    private $cm;
    
    /** @var object Page URL */
    private $page_url;
    
    /**
     * Constructor
     *
     * @param object $cm Course module
     * @param moodle_url $page_url Page URL
     */
    public function __construct($cm, $page_url) {
        $this->cm = $cm;
        $this->page_url = $page_url;
    }
    
    /**
     * Render suggestions summary notification
     *
     * @param array $suggestions All suggestions
     * @return string HTML output
     */
    public function render_suggestions_summary($suggestions) {
        global $OUTPUT;
        
        $stats = $this->calculate_suggestion_stats($suggestions);
        
        if ($stats['total'] > 0) {
            $summary_text = get_string('suggestions_summary', 'mod_teamsattendance', [
                'total' => $stats['total'],
                'name_matches' => $stats['name_based'],
                'email_matches' => $stats['email_based']
            ]);
            
            return $OUTPUT->notification($summary_text, 'notifysuccess');
        }
        
        return '';
    }
    
    /**
     * Start bulk suggestions form
     *
     * @return string HTML output
     */
    public function start_bulk_suggestions_form() {
        $output = html_writer::start_tag('form', array(
            'method' => 'post',
            'action' => $this->page_url->out(),
            'id' => 'bulk_suggestions_form'
        ));
        
        $output .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'apply_bulk_suggestions'
        ));
        
        $output .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        ));
        
        return $output;
    }
    
    /**
     * End bulk suggestions form with apply button
     *
     * @param int $suggestion_count Number of suggestions
     * @return string HTML output
     */
    public function end_bulk_suggestions_form($suggestion_count) {
        $output = '';
        
        if ($suggestion_count > 0) {
            $output .= html_writer::tag('div', 
                html_writer::empty_tag('input', array(
                    'type' => 'submit',
                    'value' => get_string('apply_selected_suggestions', 'mod_teamsattendance'),
                    'class' => 'btn btn-success btn-lg',
                    'onclick' => 'return confirmBulkAssignment();'
                )),
                array('class' => 'text-center mt-3')
            );
        }
        
        $output .= html_writer::end_tag('form');
        
        return $output;
    }
    
    /**
     * Create main unassigned records table
     *
     * @param array $sorted_records Sorted unassigned records
     * @param array $suggestions All suggestions
     * @param array $available_users Available users for assignment
     * @return string HTML output
     */
    public function render_unassigned_table($sorted_records, $suggestions, $available_users) {
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

        foreach ($sorted_records as $record) {
            $row = $this->create_table_row($record, $suggestions, $available_users);
            $table->data[] = $row;
        }

        return html_writer::table($table);
    }
    
    /**
     * Create a single table row for an unassigned record
     *
     * @param object $record Unassigned record
     * @param array $suggestions All suggestions
     * @param array $available_users Available users
     * @return html_table_row Table row object
     */
    private function create_table_row($record, $suggestions, $available_users) {
        // Check for suggestions
        $suggestion_info = isset($suggestions[$record->id]) ? $suggestions[$record->id] : null;
        $has_suggestion = !empty($suggestion_info);
        
        $suggestion_cell = '';
        $row_class = 'no-match-row'; // Default
        
        if ($suggestion_info) {
            $suggested_user = $suggestion_info['user'];
            $suggestion_type = $suggestion_info['type'];
            
            // Determine row class based on suggestion type
            $row_class = ($suggestion_type === 'name') ? 'suggested-match-row' : 'email-match-row';
            
            $suggestion_cell = $this->create_suggestion_cell($record->id, $suggested_user, $suggestion_type);
        } else {
            $suggestion_cell = html_writer::tag('em', get_string('no_suggestion', 'mod_teamsattendance'), 
                array('class' => 'text-muted'));
        }
        
        // Create manual assignment form
        $assign_form = $this->create_assignment_form($record, $available_users);

        // Create the row with appropriate styling class
        $row = new html_table_row();
        $row->attributes['class'] = $row_class;
        $row->attributes['data-record-id'] = $record->id;
        $row->attributes['data-has-suggestion'] = $has_suggestion ? '1' : '0';
        $row->attributes['data-suggestion-type'] = $suggestion_info ? $suggestion_info['type'] : 'none';
        
        $row->cells = array(
            $record->teams_user_id,
            format_time($record->attendance_duration),
            number_format($record->actual_attendance, 1) . '%',
            $suggestion_cell,
            $assign_form
        );

        return $row;
    }
    
    /**
     * Create suggestion cell content
     *
     * @param int $record_id Record ID
     * @param object $suggested_user Suggested user
     * @param string $suggestion_type Type of suggestion (name/email)
     * @return string HTML content
     */
    private function create_suggestion_cell($record_id, $suggested_user, $suggestion_type) {
        // Create suggestion type label
        $type_label = ($suggestion_type === 'name') ? 
            get_string('name_match_suggestion', 'mod_teamsattendance') : 
            get_string('email_match_suggestion', 'mod_teamsattendance');
        
        $content = html_writer::tag('div', 
            html_writer::tag('div', $type_label, array('class' => 'suggestion-type-label text-info small mb-1')) .
            html_writer::tag('strong', fullname($suggested_user), array('class' => 'text-success')) .
            html_writer::empty_tag('br') .
            html_writer::tag('small', $suggested_user->email, array('class' => 'text-muted')) .
            html_writer::empty_tag('br') .
            html_writer::checkbox('suggestions[' . $record_id . ']', $suggested_user->id, true, 
                get_string('apply_suggestion', 'mod_teamsattendance'))
        );
        
        return $content;
    }
    
    /**
     * Create manual assignment form
     *
     * @param object $record Record object
     * @param array $available_users Available users
     * @return string HTML form
     */
    private function create_assignment_form($record, $available_users) {
        $form = html_writer::start_tag('form', array(
            'method' => 'post',
            'action' => $this->page_url->out(),
            'id' => 'assign_form_' . $record->id,
            'onsubmit' => 'return confirmAssignment(this);'
        ));
        
        $form .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'assign'
        ));
        
        $form .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'recordid',
            'value' => $record->id
        ));
        
        $form .= html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        ));
        
        // Create user dropdown
        $user_options = $this->format_users_for_dropdown($available_users);
        
        $form .= html_writer::select(
            $user_options,
            'userid',
            null,
            array('' => get_string('select_user', 'mod_teamsattendance')),
            array(
                'id' => 'user_selector_' . $record->id,
                'onchange' => 'enableAssignButton(' . $record->id . ');'
            )
        );
        
        $form .= ' ';
        
        $form .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'value' => get_string('assign', 'mod_teamsattendance'),
            'id' => 'assign_btn_' . $record->id,
            'disabled' => 'disabled',
            'class' => 'btn btn-primary btn-sm'
        ));
        
        $form .= html_writer::end_tag('form');
        
        return $form;
    }
    
    /**
     * Format users array for dropdown
     *
     * @param array $available_users Available users
     * @return array Formatted user options
     */
    private function format_users_for_dropdown($available_users) {
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
    
    /**
     * Calculate suggestion statistics
     *
     * @param array $suggestions All suggestions
     * @return array Statistics
     */
    private function calculate_suggestion_stats($suggestions) {
        $stats = array(
            'total' => count($suggestions),
            'name_based' => 0,
            'email_based' => 0
        );
        
        foreach ($suggestions as $suggestion) {
            if ($suggestion['type'] === 'name') {
                $stats['name_based']++;
            } else {
                $stats['email_based']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Render empty state when no unassigned records
     *
     * @return string HTML output
     */
    public function render_no_unassigned_state() {
        global $OUTPUT;
        
        return $OUTPUT->notification(get_string('no_unassigned', 'mod_teamsattendance'), 'notifymessage');
    }
}
