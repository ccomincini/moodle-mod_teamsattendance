    /**
     * Get records with suggestion filtering applied server-side
     */
    private function get_records_with_suggestion_filter($page, $per_page, $filters) {
        global $DB, $CFG;
        
        // Ensure page is not negative
        $page = max(0, $page);
        
        // Get all unassigned records with alphabetical ordering
        $sql = "SELECT tad.*, u.firstname, u.lastname, u.email
                FROM {teamsattendance_data} tad
                LEFT JOIN {user} u ON u.id = tad.userid
                WHERE tad.sessionid = ? AND tad.userid = ?
                ORDER BY LOWER(tad.teams_user_id)"; // Alphabetical ascending order
        
        $params = array($this->teamsattendance->id, $CFG->siteguest);
        $all_records = $DB->get_records_sql($sql, $params);
        
        // Get suggestions for all records (use cache if available)
        $suggestions = $this->get_suggestions_for_all_records($all_records);
        
        // Filter records based on suggestion type
        $filtered_records = array();
        $suggestion_type = $filters['suggestion_type'];
        
        foreach ($all_records as $record) {
            $include_record = false;
            
            switch ($suggestion_type) {
                case 'name_based':
                    if (isset($suggestions[$record->id]) && $suggestions[$record->id]['type'] === 'name_based') {
                        $include_record = true;
                    }
                    break;
                    
                case 'email_based':
                    if (isset($suggestions[$record->id]) && $suggestions[$record->id]['type'] === 'email_based') {
                        $include_record = true;
                    }
                    break;
                    
                case 'none':
                    if (!isset($suggestions[$record->id])) {
                        $include_record = true;
                    }
                    break;
                    
                default: // 'all'
                    $include_record = true;
                    break;
            }
            
            if ($include_record) {
                $filtered_records[] = $record;
            }
        }
        
        $total_filtered = count($filtered_records);
        
        // SMART PAGINATION: If filtered records <= per_page, show all without pagination
        if ($total_filtered <= $per_page) {
            $result = array(
                'records' => array_values($filtered_records),
                'total_count' => $total_filtered,
                'page' => 0,
                'per_page' => $per_page,
                'total_pages' => 1,
                'has_next' => false,
                'has_previous' => false,
                'show_all' => true
            );
        } else {
            // Normal pagination
            $total_pages = ceil($total_filtered / $per_page);
            
            // Ensure page doesn't exceed available pages
            if ($page >= $total_pages) {
                $page = max(0, $total_pages - 1);
            }
            
            $offset = $page * $per_page;
            $paginated_records = array_slice($filtered_records, $offset, $per_page);
            
            $result = array(
                'records' => array_values($paginated_records),
                'total_count' => $total_filtered,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => $total_pages,
                'has_next' => (($page + 1) * $per_page) < $total_filtered,
                'has_previous' => $page > 0,
                'show_all' => false
            );
        }
        
        return $result;
    }