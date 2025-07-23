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
 * Performance-optimized data handler for large datasets
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teamsattendance/classes/suggestion_engine.php');

/**
 * Handles large dataset operations with pagination and caching
 */
class performance_data_handler {
    
    /** @var object Course module */
    private $cm;
    
    /** @var object Teams attendance instance */
    private $teamsattendance;
    
    /** @var object Course object */
    private $course;
    
    /** @var int Records per page for pagination */
    const RECORDS_PER_PAGE = 20; // Changed default to 20
    
    /** @var int Max records to process suggestions at once */
    const MAX_SUGGESTION_BATCH = 100;
    
    /** @var int Cache duration in seconds */
    const CACHE_DURATION = 300; // 5 minutes
    
    /**
     * Constructor
     */
    public function __construct($cm, $teamsattendance, $course) {
        $this->cm = $cm;
        $this->teamsattendance = $teamsattendance;
        $this->course = $course;
    }
    
    /**
     * Get performance statistics for the dataset
     */
    public function get_performance_statistics() {
        global $DB, $CFG;
        
        $cache_key = 'teamsattendance_perf_stats_' . $this->teamsattendance->id;
        $cached = $this->get_cached_data($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $stats = array();
        
        // Total records
        $stats['total_records'] = $DB->count_records('teamsattendance_data', 
            array('sessionid' => $this->teamsattendance->id));
        
        // Unassigned records
        $stats['total_unassigned'] = $DB->count_records('teamsattendance_data', array(
            'sessionid' => $this->teamsattendance->id,
            'userid' => $CFG->siteguest
        ));
        
        // Performance level classification
        if ($stats['total_unassigned'] <= 100) {
            $stats['performance_level'] = 'excellent';
            $stats['recommended_page_size'] = 50;
        } elseif ($stats['total_unassigned'] <= 500) {
            $stats['performance_level'] = 'good';
            $stats['recommended_page_size'] = 25;
        } elseif ($stats['total_unassigned'] <= 1500) {
            $stats['performance_level'] = 'moderate';
            $stats['recommended_page_size'] = 20;
        } else {
            $stats['performance_level'] = 'challenging';
            $stats['recommended_page_size'] = 20; // Changed to 20
        }
        
        // Estimate processing time
        $stats['estimated_suggestion_time'] = $this->estimate_processing_time($stats['total_unassigned']);
        
        // Available users count
        $stats['available_users_count'] = count($this->get_available_users_lightweight());
        
        $this->set_cached_data($cache_key, $stats);
        return $stats;
    }
    
    /**
     * Get paginated unassigned records with suggestion filtering
     */
    public function get_unassigned_records_paginated($page = 0, $per_page = null, $filters = array()) {
        global $DB, $CFG;
        
        if ($per_page === null) {
            $per_page = self::RECORDS_PER_PAGE;
        }
        
        // Handle "all" pagesize
        if ($per_page === 'all' || $per_page > 900000) {
            $per_page = 999999; // Large number for "all"
        } else {
            $per_page = min(max($per_page, 10), 999999);
        }
        
        // Ensure page is not negative
        $page = max(0, $page);
        
        // Include filters in cache key
        $filter_hash = md5(serialize($filters));
        $cache_key = "teamsattendance_records_{$this->teamsattendance->id}_{$page}_{$per_page}_{$filter_hash}";
        $cached = $this->get_cached_data($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // If we have suggestion filters, use suggestion filtering
        if (isset($filters['suggestion_type']) && $filters['suggestion_type'] !== 'all') {
            return $this->get_records_with_suggestion_filter($page, $per_page, $filters);
        }
        
        // For "all" filter, get all records with alphabetical ordering
        $total_count = $this->get_total_unassigned_count();
        
        // Smart pagination: if total_count <= per_page, show all records
        if ($total_count <= $per_page) {
            $sql = "SELECT tad.*, u.firstname, u.lastname, u.email
                    FROM {teamsattendance_data} tad
                    LEFT JOIN {user} u ON u.id = tad.userid
                    WHERE tad.sessionid = ? AND tad.userid = ?
                    ORDER BY LOWER(tad.teams_user_id)"; // Alphabetical ascending order
            
            $params = array($this->teamsattendance->id, $CFG->siteguest);
            $records = $DB->get_records_sql($sql, $params);
            
            $result = array(
                'records' => array_values($records),
                'total_count' => $total_count,
                'page' => 0,
                'per_page' => $per_page,
                'total_pages' => 1,
                'has_next' => false,
                'has_previous' => false,
                'show_all' => true
            );
        } else {
            // Normal pagination with alphabetical ordering
            $offset = $page * $per_page;
            
            $sql = "SELECT tad.*, u.firstname, u.lastname, u.email
                    FROM {teamsattendance_data} tad
                    LEFT JOIN {user} u ON u.id = tad.userid
                    WHERE tad.sessionid = ? AND tad.userid = ?
                    ORDER BY LOWER(tad.teams_user_id)
                    LIMIT $per_page OFFSET $offset"; // Alphabetical ascending order
            
            $params = array($this->teamsattendance->id, $CFG->siteguest);
            $records = $DB->get_records_sql($sql, $params);
            
            $result = array(
                'records' => array_values($records),
                'total_count' => $total_count,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total_count / $per_page),
                'has_next' => (($page + 1) * $per_page) < $total_count,
                'has_previous' => $page > 0,
                'show_all' => false
            );
        }
        
        $this->set_cached_data($cache_key, $result);
        return $result;
    }
    
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
                case 'name_suggestions':
                    if (isset($suggestions[$record->id]) && $suggestions[$record->id]['type'] === 'name') {
                        $include_record = true;
                    }
                    break;
                    
                case 'email_suggestions':
                    if (isset($suggestions[$record->id]) && $suggestions[$record->id]['type'] === 'email') {
                        $include_record = true;
                    }
                    break;
                    
                case 'without_suggestions':
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
    
    /**
     * Get suggestions for all records (with caching)
     */
    private function get_suggestions_for_all_records($records) {
        $cache_key = 'teamsattendance_all_suggestions_' . $this->teamsattendance->id;
        $cached = $this->get_cached_data($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Get available users (lightweight version)
        $available_users = $this->get_available_users_lightweight();
        
        if (empty($available_users)) {
            return array();
        }
        
        // Initialize suggestion engine
        $suggestion_engine = new suggestion_engine($available_users);
        $suggestions = $suggestion_engine->generate_suggestions($records);
        
        $this->set_cached_data($cache_key, $suggestions);
        return $suggestions;
    }
    
    /**
     * Get suggestions for a batch of records (optimized)
     */
    public function get_suggestions_for_batch($records) {
        if (empty($records)) {
            return array();
        }
        
        // Limit batch size for performance
        $records = array_slice($records, 0, self::MAX_SUGGESTION_BATCH);
        
        $cache_key = 'teamsattendance_suggestions_' . md5(serialize(array_keys($records)));
        $cached = $this->get_cached_data($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Get available users (lightweight version)
        $available_users = $this->get_available_users_lightweight();
        
        if (empty($available_users)) {
            return array();
        }
        
        // Initialize suggestion engine
        $suggestion_engine = new suggestion_engine($available_users);
        $suggestions = $suggestion_engine->generate_suggestions($records);
        
        $this->set_cached_data($cache_key, $suggestions);
        return $suggestions;
    }
    
    /**
     * Get all unassigned records (for suggestion statistics)
     */
    public function get_all_unassigned_records() {
        global $DB, $CFG;
        
        $cache_key = 'teamsattendance_all_unassigned_' . $this->teamsattendance->id;
        $cached = $this->get_cached_data($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $sql = "SELECT tad.*, u.firstname, u.lastname, u.email
                FROM {teamsattendance_data} tad
                LEFT JOIN {user} u ON u.id = tad.userid
                WHERE tad.sessionid = ? AND tad.userid = ?
                ORDER BY LOWER(tad.teams_user_id)"; // Alphabetical ascending order
        
        $params = array($this->teamsattendance->id, $CFG->siteguest);
        $records = $DB->get_records_sql($sql, $params);
        
        $this->set_cached_data($cache_key, $records);
        return $records;
    }

    /**
     * Get lightweight user list for suggestions (cached)
     */
    private function get_available_users_lightweight() {
        $cache_key = 'teamsattendance_users_' . $this->teamsattendance->id;
        $cached = $this->get_cached_data($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $DB, $CFG;
        
        $context = context_course::instance($this->course->id);
        $enrolled_users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email');
        
        // Get already assigned user IDs
        $assigned_userids = $DB->get_fieldset_select(
            'teamsattendance_data',
            'userid',
            'sessionid = ? AND userid != ?',
            array($this->teamsattendance->id, $CFG->siteguest)
        );
        
        // Filter out assigned users
        $available_users = array();
        foreach ($enrolled_users as $user) {
            if (!in_array($user->id, $assigned_userids)) {
                $available_users[$user->id] = $user;
            }
        }
        
        $this->set_cached_data($cache_key, $available_users);
        return $available_users;
    }
    
    /**
     * Get total count of unassigned records
     */
    private function get_total_unassigned_count() {
        global $DB, $CFG;
        
        $cache_key = "teamsattendance_count_{$this->teamsattendance->id}";
        $cached = $this->get_cached_data($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $count = $DB->count_records('teamsattendance_data', array(
            'sessionid' => $this->teamsattendance->id,
            'userid' => $CFG->siteguest
        ));
        
        $this->set_cached_data($cache_key, $count);
        return $count;
    }
    
    /**
     * Filter records by suggestion availability
     */
    public function filter_records_by_suggestions($paginated_data, $filter) {
        if ($filter !== 'with_suggestions' && $filter !== 'without_suggestions') {
            return $paginated_data;
        }
        
        $suggestions = $this->get_suggestions_for_batch($paginated_data['records']);
        $filtered_records = array();
        
        foreach ($paginated_data['records'] as $record) {
            $has_suggestion = isset($suggestions[$record->id]);
            
            if (($filter === 'with_suggestions' && $has_suggestion) ||
                ($filter === 'without_suggestions' && !$has_suggestion)) {
                $filtered_records[] = $record;
            }
        }
        
        $paginated_data['records'] = $filtered_records;
        $paginated_data['filtered_count'] = count($filtered_records);
        
        return $paginated_data;
    }
    
    /**
     * Estimate processing time for suggestions
     */
    private function estimate_processing_time($record_count) {
        // Based on testing: ~0.1 seconds per record for suggestion generation
        $seconds = $record_count * 0.1;
        
        if ($seconds < 60) {
            return round($seconds) . ' seconds';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . ' minutes';
        } else {
            return round($seconds / 3600, 1) . ' hours';
        }
    }
    
    /**
     * Get cached data
     */
    private function get_cached_data($key) {
        $cache_file = $this->get_cache_file_path($key);
        
        if (file_exists($cache_file)) {
            $cache_data = file_get_contents($cache_file);
            $data = unserialize($cache_data);
            
            if ($data && isset($data['expires']) && $data['expires'] > time()) {
                return $data['content'];
            } else {
                // Cache expired, delete file
                unlink($cache_file);
            }
        }
        
        return false;
    }
    
    /**
     * Set cached data
     */
    private function set_cached_data($key, $data) {
        $cache_file = $this->get_cache_file_path($key);
        $cache_dir = dirname($cache_file);
        
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        
        $cache_data = array(
            'content' => $data,
            'expires' => time() + self::CACHE_DURATION
        );
        
        file_put_contents($cache_file, serialize($cache_data));
    }
    
    /**
     * Get cache file path
     */
    private function get_cache_file_path($key) {
        global $CFG;
        $cache_dir = $CFG->tempdir . '/teamsattendance_cache';
        return $cache_dir . '/' . md5($key) . '.cache';
    }
    
    /**
     * Clear all cache for this session
     */
    public function clear_cache() {
        global $CFG;
        $cache_dir = $CFG->tempdir . '/teamsattendance_cache';
        
        if (is_dir($cache_dir)) {
            // Cancella TUTTI i file cache - approccio semplice e sicuro
            $files = glob($cache_dir . '/*.cache');
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Apply bulk assignments with progress tracking
     */
    public function apply_bulk_assignments_with_progress($assignments) {
        $total = count($assignments);
        $processed = 0;
        $successful = 0;
        $errors = array();
        
        // Process in smaller batches
        $batch_size = 10;
        $batches = array_chunk($assignments, $batch_size, true);
        
        foreach ($batches as $batch) {
            $batch_result = $this->process_assignment_batch($batch);
            $successful += $batch_result['successful'];
            $errors = array_merge($errors, $batch_result['errors']);
            $processed += count($batch);
            
            // Clear cache after each batch to prevent memory buildup
            if ($processed % 50 === 0) {
                $this->clear_cache();
            }
        }
        
        // Clear all cache after completion
        $this->clear_cache();
        
        return array(
            'total' => $total,
            'successful' => $successful,
            'failed' => count($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Process a batch of assignments
     */
    private function process_assignment_batch($batch) {
        global $DB;
        
        $successful = 0;
        $errors = array();
        
        foreach ($batch as $record_id => $user_id) {
            try {
                $record = $DB->get_record('teamsattendance_data', array('id' => $record_id));
                
                if ($record && $record->sessionid == $this->teamsattendance->id) {
                    $record->userid = $user_id;
                    $record->manually_assigned = 1;
                    
                    if ($DB->update_record('teamsattendance_data', $record)) {
                        $successful++;
                    } else {
                        $errors[] = "Failed to update record $record_id";
                    }
                } else {
                    $errors[] = "Record $record_id not found or invalid session";
                }
            } catch (Exception $e) {
                $errors[] = "Error processing record $record_id: " . $e->getMessage();
            }
        }
        
        return array(
            'successful' => $successful,
            'errors' => $errors
        );
    }
}
