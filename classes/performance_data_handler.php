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
 * Performance-optimized data handler for Teams attendance management
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_teamsattendance;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teamsattendance/classes/suggestion_engine.php');

/**
 * Performance data handler class for managing unassigned Teams attendance records
 *
 * Handles efficient loading, filtering, and pagination of unassigned attendance data
 * with suggestion generation and caching capabilities.
 */
class performance_data_handler {
    
    /** @var object Course module instance */
    private $cm;
    
    /** @var object Teams attendance instance */
    private $teamsattendance;
    
    /** @var object Course instance */
    private $course;
    
    /** @var array Cache for suggestions */
    private $suggestions_cache = array();
    
    /** @var bool Whether cache is loaded */
    private $cache_loaded = false;

    /**
     * Constructor
     *
     * @param object $cm Course module
     * @param object $teamsattendance Teams attendance instance
     * @param object $course Course object
     */
    public function __construct($cm, $teamsattendance, $course) {
        $this->cm = $cm;
        $this->teamsattendance = $teamsattendance;
        $this->course = $course;
    }

    /**
     * Get performance statistics for the current session
     *
     * @return array Performance statistics
     */
    public function get_performance_statistics() {
        global $DB;

        $stats = array();
        
        // Count total unassigned records
        $unassigned_count = $DB->count_records_select('teamsattendance_data', 
            'sessionid = ? AND (userid IS NULL OR userid = ?)', 
            array($this->teamsattendance->id, 0)
        );
        
        // Count total assigned records
        $assigned_count = $DB->count_records_select('teamsattendance_data', 
            'sessionid = ? AND userid IS NOT NULL AND userid > 0', 
            array($this->teamsattendance->id)
        );

        $stats['total_unassigned'] = $unassigned_count;
        $stats['total_assigned'] = $assigned_count;
        $stats['total_records'] = $unassigned_count + $assigned_count;
        
        return $stats;
    }

    /**
     * Get all unassigned records for suggestion generation
     *
     * @return array All unassigned records
     */
    public function get_all_unassigned_records() {
        global $DB, $CFG;

        $sql = "SELECT tad.*, u.firstname, u.lastname, u.email
                FROM {teamsattendance_data} tad
                LEFT JOIN {user} u ON u.id = tad.userid
                WHERE tad.sessionid = ? AND (tad.userid IS NULL OR tad.userid = ?)
                ORDER BY LOWER(tad.teams_user_id)";
        
        $params = array($this->teamsattendance->id, $CFG->siteguest);
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get unassigned records with pagination and filtering
     *
     * @param int $page Page number (0-based)
     * @param mixed $per_page Records per page or 'all'
     * @param array $filters Filter criteria
     * @return array Paginated results with metadata
     */
    public function get_unassigned_records_paginated($page, $per_page, $filters = array()) {
        if ($per_page === 'all') {
            return $this->get_all_records_without_pagination($filters);
        }
        
        return $this->get_records_with_suggestion_filter($page, $per_page, $filters);
    }

    /**
     * Get all records without pagination (for "show all" functionality)
     *
     * @param array $filters Filter criteria
     * @return array All filtered records
     */
    private function get_all_records_without_pagination($filters) {
        global $DB, $CFG;
        
        // Get all unassigned records
        $sql = "SELECT tad.*, u.firstname, u.lastname, u.email
                FROM {teamsattendance_data} tad
                LEFT JOIN {user} u ON u.id = tad.userid
                WHERE tad.sessionid = ? AND (tad.userid IS NULL OR tad.userid = ?)
                ORDER BY LOWER(tad.teams_user_id)";
        
        $params = array($this->teamsattendance->id, $CFG->siteguest);
        $all_records = $DB->get_records_sql($sql, $params);
        
        // Apply filtering if specified
        if (!empty($filters) && isset($filters['suggestion_type'])) {
            $filtered_records = $this->apply_suggestion_filter($all_records, $filters['suggestion_type']);
        } else {
            $filtered_records = array_values($all_records);
        }
        
        return array(
            'records' => $filtered_records,
            'total_count' => count($filtered_records),
            'page' => 0,
            'per_page' => 'all',
            'total_pages' => 1,
            'has_next' => false,
            'has_previous' => false,
            'show_all' => true
        );
    }

    /**
     * Get records with suggestion filtering applied server-side
     *
     * @param int $page Page number
     * @param int $per_page Records per page
     * @param array $filters Filter criteria
     * @return array Paginated and filtered results
     */
    private function get_records_with_suggestion_filter($page, $per_page, $filters) {
        global $DB, $CFG;
        
        // Ensure page is not negative
        $page = max(0, $page);
        
        // Get all unassigned records with alphabetical ordering
        $sql = "SELECT tad.*, u.firstname, u.lastname, u.email
                FROM {teamsattendance_data} tad
                LEFT JOIN {user} u ON u.id = tad.userid
                WHERE tad.sessionid = ? AND (tad.userid IS NULL OR tad.userid = ?)
                ORDER BY LOWER(tad.teams_user_id)"; // Alphabetical ascending order
        
        $params = array($this->teamsattendance->id, $CFG->siteguest);
        $all_records = $DB->get_records_sql($sql, $params);
        
        // Apply filtering if specified
        if (!empty($filters) && isset($filters['suggestion_type'])) {
            $filtered_records = $this->apply_suggestion_filter($all_records, $filters['suggestion_type']);
        } else {
            $filtered_records = array_values($all_records);
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

    /**
     * Apply suggestion filter to records
     *
     * @param array $all_records All unassigned records
     * @param string $suggestion_type Filter type
     * @return array Filtered records
     */
    private function apply_suggestion_filter($all_records, $suggestion_type) {
        // Get suggestions for all records (use cache if available)
        $suggestions = $this->get_suggestions_for_all_records($all_records);
        
        // Filter records based on suggestion type
        $filtered_records = array();
        
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
        
        return $filtered_records;
    }

    /**
     * Get suggestions for all records (with caching)
     *
     * @param array $records Records to generate suggestions for
     * @return array Suggestions keyed by record ID
     */
    public function get_suggestions_for_all_records($records) {
        if (!$this->cache_loaded) {
            // Get enrolled users for suggestion generation
            $context = \context_course::instance($this->course->id);
            $enrolled_users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email');
            
            // Generate suggestions using the suggestion engine
            $suggestion_engine = new suggestion_engine($enrolled_users);
            $this->suggestions_cache = $suggestion_engine->generate_suggestions($records);
            $this->cache_loaded = true;
        }
        
        return $this->suggestions_cache;
    }

    /**
     * Get suggestions for a specific batch of records
     *
     * @param array $records Batch of records
     * @return array Suggestions for the batch
     */
    public function get_suggestions_for_batch($records) {
        if (empty($records)) {
            return array();
        }
        
        // If cache is not loaded, load it with all records first
        if (!$this->cache_loaded) {
            $all_records = $this->get_all_unassigned_records();
            $this->get_suggestions_for_all_records($all_records);
        }
        
        // Extract suggestions for this batch
        $batch_suggestions = array();
        foreach ($records as $record) {
            if (isset($this->suggestions_cache[$record->id])) {
                $batch_suggestions[$record->id] = $this->suggestions_cache[$record->id];
            }
        }
        
        return $batch_suggestions;
    }

    /**
     * Apply bulk assignments with progress tracking
     *
     * @param array $assignments Array of recordid => userid assignments
     * @return array Results with success/error counts
     */
    public function apply_bulk_assignments_with_progress($assignments) {
        global $DB;
        
        $results = array(
            'success_count' => 0,
            'error_count' => 0,
            'errors' => array()
        );
        
        if (empty($assignments)) {
            return $results;
        }
        
        foreach ($assignments as $recordid => $userid) {
            try {
                // Validate the assignment
                if (!$this->validate_assignment($recordid, $userid)) {
                    $results['error_count']++;
                    $results['errors'][] = "Invalid assignment: record $recordid to user $userid";
                    continue;
                }
                
                // Update the record
                $update_data = new \stdClass();
                $update_data->id = $recordid;
                $update_data->userid = $userid;
                $update_data->manually_assigned = 1;
                
                $DB->update_record('teamsattendance_data', $update_data);
                $results['success_count']++;
                
            } catch (\Exception $e) {
                $results['error_count']++;
                $results['errors'][] = "Error assigning record $recordid: " . $e->getMessage();
            }
        }
        
        // Clear cache after bulk assignments
        $this->clear_cache();
        
        return $results;
    }

    /**
     * Validate an assignment before applying it
     *
     * @param int $recordid Record ID
     * @param int $userid User ID
     * @return bool True if valid
     */
    private function validate_assignment($recordid, $userid) {
        global $DB;
        
        // Check if record exists and is unassigned
        $record = $DB->get_record('teamsattendance_data', 
            array('id' => $recordid, 'sessionid' => $this->teamsattendance->id));
        
        if (!$record) {
            return false;
        }
        
        // Check if user exists and is enrolled
        $context = \context_course::instance($this->course->id);
        $enrolled_users = get_enrolled_users($context, '', 0, 'u.id');
        
        return isset($enrolled_users[$userid]);
    }

    /**
     * Clear the suggestions cache
     */
    public function clear_cache() {
        $this->suggestions_cache = array();
        $this->cache_loaded = false;
    }

    /**
     * Get cache status information
     *
     * @return array Cache status
     */
    public function get_cache_status() {
        return array(
            'is_loaded' => $this->cache_loaded,
            'cache_size' => count($this->suggestions_cache)
        );
    }
}