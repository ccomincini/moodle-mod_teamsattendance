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
 * Performance-optimized UI renderer for Teams attendance
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Handles performance-optimized UI rendering with pagination and tabs
 */
class performance_ui_renderer {
    
    /** @var object Course module */
    private $cm;
    
    /** @var object Page URL */
    private $page_url;
    
    /** @var int Records per page */
    const RECORDS_PER_PAGE = 50;
    
    /** @var int Suggestion processing batch size */
    const SUGGESTION_BATCH_SIZE = 25;
    
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
     * Render tabbed interface for different match types
     *
     * @param array $suggestion_stats Statistics about suggestions
     * @param string $active_tab Currently active tab
     * @return string HTML output
     */
    public function render_tabs_interface($suggestion_stats, $active_tab = 'name_matches') {
        $tabs = array();
        
        // Name-based matches tab
        $name_count = $suggestion_stats['name_based'] ?? 0;
        $tabs[] = html_writer::link(
            new moodle_url($this->page_url, array('tab' => 'name_matches')),
            '🟢 ' . get_string('name_based_matches', 'mod_teamsattendance') . ' (' . $name_count . ')',
            array('class' => $active_tab === 'name_matches' ? 'nav-link active' : 'nav-link')
        );
        
        // Email-based matches tab
        $email_count = $suggestion_stats['email_based'] ?? 0;
        $tabs[] = html_writer::link(
            new moodle_url($this->page_url, array('tab' => 'email_matches')),
            '🟣 ' . get_string('email_based_matches', 'mod_teamsattendance') . ' (' . $email_count . ')',
            array('class' => $active_tab === 'email_matches' ? 'nav-link active' : 'nav-link')
        );
        
        // No matches tab
        $no_match_count = $suggestion_stats['no_matches'] ?? 0;
        $tabs[] = html_writer::link(
            new moodle_url($this->page_url, array('tab' => 'no_matches')),
            '🟠 ' . get_string('no_matches', 'mod_teamsattendance') . ' (' . $no_match_count . ')',
            array('class' => $active_tab === 'no_matches' ? 'nav-link active' : 'nav-link')
        );
        
        // All records tab
        $total_count = $suggestion_stats['total_unassigned'] ?? 0;
        $tabs[] = html_writer::link(
            new moodle_url($this->page_url, array('tab' => 'all')),
            '📋 ' . get_string('all_records', 'mod_teamsattendance') . ' (' . $total_count . ')',
            array('class' => $active_tab === 'all' ? 'nav-link active' : 'nav-link')
        );
        
        $output = html_writer::start_tag('ul', array('class' => 'nav nav-tabs mb-3'));
        foreach ($tabs as $tab) {
            $output .= html_writer::tag('li', $tab, array('class' => 'nav-item'));
        }
        $output .= html_writer::end_tag('ul');
        
        return $output;
    }
    
    /**
     * Render pagination controls
     *
     * @param int $total_records Total number of records
     * @param int $current_page Current page number
     * @param string $tab_type Current tab type
     * @return string HTML output
     */
    public function render_pagination($total_records, $current_page, $tab_type) {
        $total_pages = ceil($total_records / self::RECORDS_PER_PAGE);
        
        if ($total_pages <= 1) {
            return '';
        }
        
        $output = html_writer::start_tag('nav', array('aria-label' => 'Page navigation'));
        $output .= html_writer::start_tag('ul', array('class' => 'pagination justify-content-center'));
        
        // Previous button
        if ($current_page > 1) {
            $prev_url = new moodle_url($this->page_url, array('tab' => $tab_type, 'page' => $current_page - 1));
            $output .= html_writer::tag('li', 
                html_writer::link($prev_url, '«', array('class' => 'page-link')),
                array('class' => 'page-item')
            );
        } else {
            $output .= html_writer::tag('li', 
                html_writer::tag('span', '«', array('class' => 'page-link')),
                array('class' => 'page-item disabled')
            );
        }
        
        // Page numbers
        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            $page_url = new moodle_url($this->page_url, array('tab' => $tab_type, 'page' => $i));
            $active = ($i == $current_page) ? 'page-item active' : 'page-item';
            $output .= html_writer::tag('li',
                html_writer::link($page_url, $i, array('class' => 'page-link')),
                array('class' => $active)
            );
        }
        
        // Next button
        if ($current_page < $total_pages) {
            $next_url = new moodle_url($this->page_url, array('tab' => $tab_type, 'page' => $current_page + 1));
            $output .= html_writer::tag('li',
                html_writer::link($next_url, '»', array('class' => 'page-link')),
                array('class' => 'page-item')
            );
        } else {
            $output .= html_writer::tag('li',
                html_writer::tag('span', '»', array('class' => 'page-link')),
                array('class' => 'page-item disabled')
            );
        }
        
        $output .= html_writer::end_tag('ul');
        $output .= html_writer::end_tag('nav');
        
        return $output;
    }
    
    /**
     * Render performance info panel
     *
     * @param array $perf_stats Performance statistics
     * @return string HTML output
     */
    public function render_performance_info($perf_stats) {
        $output = html_writer::start_tag('div', array('class' => 'alert alert-info d-flex justify-content-between'));
        
        $info_text = get_string('showing_records', 'mod_teamsattendance', [
            'showing' => $perf_stats['showing'],
            'total' => $perf_stats['total'],
            'processing_time' => number_format($perf_stats['processing_time'], 2)
        ]);
        
        $output .= html_writer::tag('span', $info_text);
        
        if ($perf_stats['suggestions_processed'] < $perf_stats['total']) {
            $progress = ($perf_stats['suggestions_processed'] / $perf_stats['total']) * 100;
            $output .= html_writer::tag('span', 
                get_string('suggestions_progress', 'mod_teamsattendance', [
                    'processed' => $perf_stats['suggestions_processed'],
                    'total' => $perf_stats['total'],
                    'percent' => number_format($progress, 1)
                ]),
                array('class' => 'text-muted small')
            );
        }
        
        $output .= html_writer::end_tag('div');
        
        return $output;
    }
    
    /**
     * Render lazy loading placeholder for suggestions
     *
     * @param int $record_id Record ID
     * @return string HTML output
     */
    public function render_suggestion_placeholder($record_id) {
        return html_writer::tag('div',
            html_writer::tag('div', '', array('class' => 'spinner-border spinner-border-sm', 'role' => 'status')) .
            ' ' . get_string('loading_suggestions', 'mod_teamsattendance'),
            array(
                'class' => 'suggestion-placeholder',
                'id' => 'suggestion_' . $record_id,
                'data-record-id' => $record_id
            )
        );
    }
    
    /**
     * Render compact table for large datasets
     *
     * @param array $records Records to display
     * @param array $suggestions Suggestions for records
     * @param array $available_users Available users
     * @param string $tab_type Current tab type
     * @return string HTML output
     */
    public function render_compact_table($records, $suggestions, $available_users, $tab_type) {
        $table = new html_table();
        $table->head = array(
            get_string('teams_user', 'mod_teamsattendance'),
            get_string('time_percent', 'mod_teamsattendance'), // Combined column
            get_string('suggested_match', 'mod_teamsattendance'),
            get_string('actions', 'mod_teamsattendance')
        );

        // Set table attributes for compact styling
        $table->attributes['class'] = 'generaltable manage-unassigned-table compact-table';

        foreach ($records as $record) {
            $row = $this->create_compact_table_row($record, $suggestions, $available_users, $tab_type);
            $table->data[] = $row;
        }

        return html_writer::table($table);
    }
    
    /**
     * Create compact table row
     *
     * @param object $record Record object
     * @param array $suggestions Suggestions array
     * @param array $available_users Available users
     * @param string $tab_type Current tab type
     * @return html_table_row
     */
    private function create_compact_table_row($record, $suggestions, $available_users, $tab_type) {
        $suggestion_info = isset($suggestions[$record->id]) ? $suggestions[$record->id] : null;
        $has_suggestion = !empty($suggestion_info);
        
        // Determine row class
        $row_class = 'no-match-row'; // Default
        if ($suggestion_info) {
            $row_class = ($suggestion_info['type'] === 'name') ? 'suggested-match-row' : 'email-match-row';
        }
        
        // Combined time and percentage cell
        $time_percent_cell = format_time($record->attendance_duration) . '<br>' .
                            '<small>' . number_format($record->actual_attendance, 1) . '%</small>';
        
        // Suggestion cell (simplified for performance)
        if ($has_suggestion) {
            $suggested_user = $suggestion_info['user'];
            $type_icon = ($suggestion_info['type'] === 'name') ? '🟢' : '🟣';
            $suggestion_cell = $type_icon . ' ' . fullname($suggested_user) . '<br>' .
                              '<small>' . $suggested_user->email . '</small><br>' .
                              html_writer::checkbox('suggestions[' . $record->id . ']', $suggested_user->id, true, '');
        } else {
            $suggestion_cell = '🟠 ' . get_string('no_suggestion', 'mod_teamsattendance');
        }
        
        // Compact action cell
        $action_cell = $this->create_compact_action_cell($record, $available_users);
        
        // Create row
        $row = new html_table_row();
        $row->attributes['class'] = $row_class;
        $row->attributes['data-record-id'] = $record->id;
        
        $row->cells = array(
            $record->teams_user_id,
            $time_percent_cell,
            $suggestion_cell,
            $action_cell
        );

        return $row;
    }
    
    /**
     * Create compact action cell
     *
     * @param object $record Record object
     * @param array $available_users Available users
     * @return string HTML content
     */
    private function create_compact_action_cell($record, $available_users) {
        // Simplified dropdown with fewer options for performance
        $top_users = array_slice($available_users, 0, 20, true); // Limit to first 20 users
        
        $form = html_writer::start_tag('form', array(
            'method' => 'post',
            'action' => $this->page_url->out(),
            'class' => 'compact-assign-form'
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
        
        // Compact user selector
        $user_options = array('' => '...');
        foreach ($top_users as $user) {
            $user_options[$user->id] = fullname($user);
        }
        
        $form .= html_writer::select(
            $user_options,
            'userid',
            null,
            array(),
            array('class' => 'form-control form-control-sm', 'onchange' => 'this.form.submit();')
        );
        
        $form .= html_writer::end_tag('form');
        
        return $form;
    }
}
