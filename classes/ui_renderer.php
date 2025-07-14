('select_suggestions_first', 'mod_teamsattendance') . '");
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
     * Render assignment statistics box
     *
     * @param array $stats Assignment statistics
     * @return string HTML output
     */
    public function render_statistics_box($stats) {
        $output = html_writer::start_tag('div', array('class' => 'alert alert-info'));
        $output .= html_writer::tag('h5', get_string('assignment_statistics', 'mod_teamsattendance'));
        
        $output .= html_writer::tag('p', 
            get_string('total_records', 'mod_teamsattendance') . ': ' . $stats['total_records']
        );
        
        $output .= html_writer::tag('p', 
            get_string('unassigned_records', 'mod_teamsattendance') . ': ' . $stats['unassigned_records']
        );
        
        if ($stats['total_records'] > 0) {
            $output .= html_writer::tag('p', 
                get_string('assignment_rate', 'mod_teamsattendance') . ': ' . 
                number_format($stats['assignment_rate'], 1) . '%'
            );
        }
        
        $output .= html_writer::end_tag('div');
        
        return $output;
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
    
    /**
     * Render action buttons section
     *
     * @param bool $has_suggestions Whether there are suggestions available
     * @return string HTML output
     */
    public function render_action_buttons($has_suggestions) {
        $output = html_writer::start_tag('div', array('class' => 'action-buttons-section mt-3'));
        
        if ($has_suggestions) {
            $output .= html_writer::tag('div',
                html_writer::link('#', get_string('select_all_suggestions', 'mod_teamsattendance'), 
                    array('class' => 'btn btn-secondary', 'onclick' => 'selectAllSuggestions(); return false;')) .
                ' ' .
                html_writer::link('#', get_string('deselect_all_suggestions', 'mod_teamsattendance'), 
                    array('class' => 'btn btn-secondary', 'onclick' => 'deselectAllSuggestions(); return false;')),
                array('class' => 'mb-2')
            );
        }
        
        $output .= html_writer::end_tag('div');
        
        return $output;
    }
    
    /**
     * Add additional JavaScript for select/deselect all functionality
     *
     * @return string JavaScript output
     */
    public function render_additional_javascript() {
        $js = html_writer::start_tag('script', array('type' => 'text/javascript'));
        $js .= '
        function selectAllSuggestions() {
            var checkboxes = document.querySelectorAll("input[name^=\'suggestions[\']");
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = true;
                var row = checkbox.closest("tr");
                row.style.boxShadow = "0 0 10px rgba(40, 167, 69, 0.5)";
            });
        }
        
        function deselectAllSuggestions() {
            var checkboxes = document.querySelectorAll("input[name^=\'suggestions[\']");
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = false;
                var row = checkbox.closest("tr");
                row.style.boxShadow = "none";
            });
        }
        ';
        $js .= html_writer::end_tag('script');
        
        return $js;
    }
}
