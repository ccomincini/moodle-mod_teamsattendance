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
 * UI assets manager for Teams attendance
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Handles CSS and JavaScript assets for the manage unassigned page
 */
class ui_assets_manager {
    
    /**
     * Render custom CSS for three-color styling
     *
     * @return string CSS output
     */
    public static function render_custom_css() {
        $css = html_writer::start_tag('style', array('type' => 'text/css'));
        $css .= '
        /* Table column width control */
        .manage-unassigned-table {
            table-layout: fixed;
            width: 100%;
        }
        
        .manage-unassigned-table th:nth-child(1),
        .manage-unassigned-table td:nth-child(1) {
            width: 25%; /* Utente Teams */
        }
        
        .manage-unassigned-table th:nth-child(2),
        .manage-unassigned-table td:nth-child(2) {
            width: 12%; /* Tempo Totale */
            text-align: center;
        }
        
        .manage-unassigned-table th:nth-child(3),
        .manage-unassigned-table td:nth-child(3) {
            width: 12%; /* Presenza % */
            text-align: center;
        }
        
        .manage-unassigned-table th:nth-child(4),
        .manage-unassigned-table td:nth-child(4) {
            width: 26%; /* Corrispondenza Suggerita (resto: 100% - 25% - 12% - 12% - 25% = 26%) */
        }
        
        .manage-unassigned-table th:nth-child(5),
        .manage-unassigned-table td:nth-child(5) {
            width: 25%; /* Assegna Utente */
        }
        
        /* Styling for name-based suggested match rows */
        .manage-unassigned-table tr.suggested-match-row {
            background-color: #d4edda !important;
            border-left: 4px solid #28a745;
        }
        
        /* Styling for email-based suggested match rows */
        .manage-unassigned-table tr.email-match-row {
            background-color: #e8d5ff !important;
            border-left: 4px solid #8b5cf6;
        }
        
        /* Styling for no match rows */
        .manage-unassigned-table tr.no-match-row {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffc107;
        }
        
        /* Hover effects */
        .manage-unassigned-table tr.suggested-match-row:hover {
            background-color: #c3e6cb !important;
        }
        
        .manage-unassigned-table tr.email-match-row:hover {
            background-color: #ddd6fe !important;
        }
        
        .manage-unassigned-table tr.no-match-row:hover {
            background-color: #ffeaa7 !important;
        }
        
        /* Text overflow handling for long content */
        .manage-unassigned-table td:nth-child(1) {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .manage-unassigned-table td:nth-child(4) {
            overflow: visible; /* Allow suggestion content to wrap */
            word-wrap: break-word;
        }
        
        /* Form elements sizing in last column */
        .manage-unassigned-table td:nth-child(5) select {
            width: 100%;
            max-width: 200px;
            margin-bottom: 5px;
        }
        
        .manage-unassigned-table td:nth-child(5) input[type="submit"] {
            width: auto;
            min-width: 70px;
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
            font-size: 0.85em;
            margin-bottom: 3px;
        }
        
        /* Responsive adjustments for smaller screens */
        @media (max-width: 768px) {
            .manage-unassigned-table th:nth-child(1),
            .manage-unassigned-table td:nth-child(1) {
                width: 30%;
            }
            
            .manage-unassigned-table th:nth-child(2),
            .manage-unassigned-table td:nth-child(2),
            .manage-unassigned-table th:nth-child(3),
            .manage-unassigned-table td:nth-child(3) {
                width: 10%;
                font-size: 0.9em;
            }
            
            .manage-unassigned-table th:nth-child(4),
            .manage-unassigned-table td:nth-child(4) {
                width: 25%;
            }
            
            .manage-unassigned-table th:nth-child(5),
            .manage-unassigned-table td:nth-child(5) {
                width: 25%;
            }
            
            .manage-unassigned-table td:nth-child(5) select {
                max-width: 150px;
                font-size: 0.9em;
            }
        }
        ';
        $css .= html_writer::end_tag('style');
        
        return $css;
    }
    
    /**
     * Render color legend
     *
     * @return string HTML output
     */
    public static function render_color_legend() {
        $output = html_writer::start_tag('div', array('class' => 'color-legend'));
        $output .= html_writer::tag('strong', get_string('color_legend', 'mod_teamsattendance') . ': ');
        $output .= html_writer::tag('span', 
            get_string('name_based_matches', 'mod_teamsattendance'), 
            array('class' => 'legend-item legend-suggested')
        );
        $output .= html_writer::tag('span', 
            get_string('email_based_matches', 'mod_teamsattendance'), 
            array('class' => 'legend-item legend-email')
        );
        $output .= html_writer::tag('span', 
            get_string('no_matches', 'mod_teamsattendance'), 
            array('class' => 'legend-item legend-no-match')
        );
        $output .= html_writer::end_tag('div');
        
        return $output;
    }
    
    /**
     * Add JavaScript functions for interactivity
     *
     * @return string JavaScript output
     */
    public static function render_javascript() {
        $js = html_writer::start_tag('script', array('type' => 'text/javascript'));
        $js .= '
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
        $js .= html_writer::end_tag('script');
        
        return $js;
    }
}
