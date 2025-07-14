($applied_user_id);
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
