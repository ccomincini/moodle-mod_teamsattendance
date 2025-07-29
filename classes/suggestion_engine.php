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
 * Core suggestion engine for Teams attendance matching
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teamsattendance/classes/six_phase_matcher.php');


/**
 * Main suggestion engine that coordinates matching logic
 */
class suggestion_engine {
    
    /** @var six_phase_matcher Six-phase matching engine */
    private $six_phase_matcher;
    
    /** @var array Available users for assignment */
    private $available_users;
    
    /** @var float Similarity threshold for email matching */
    const EMAIL_SIMILARITY_THRESHOLD = 0.7;
    
    /**
     * Constructor
     *
     * @param array $available_users Array of available users
     */
    public function __construct($available_users) {
        $this->available_users = $available_users;
        $this->six_phase_matcher = new six_phase_matcher($available_users);
    }
    
    /**
     * Generate suggestions for unassigned records
     *
     * @param array $unassigned_records Array of unassigned attendance records
     * @return array Suggestions organized by type
     */
    public function generate_suggestions($unassigned_records) {
        return $this->six_phase_matcher->process_all_records($unassigned_records);
    }
    
    /**
     * Get matching statistics from six-phase engine
     *
     * @param array $suggestions Array of suggestions
     * @return array Statistics array
     */
    public function get_matching_statistics($suggestions) {
        return $this->six_phase_matcher->get_statistics();
    }
    
    /**
     * Get email-based suggestions for unassigned records
     *
     * @param array $unassigned_records Array of unassigned records  
     * @param array $name_suggestions Existing name suggestions to avoid duplicates
     * @return array Email-based suggestions
     */
    private function get_email_based_suggestions($unassigned_records, $name_suggestions) {
        return array();
    }
    
    /**
     * Merge name and email suggestions with type information
     *
     * @param array $name_suggestions Name-based suggestions
     * @param array $email_suggestions Email-based suggestions
     * @return array Merged suggestions with type metadata
     */
    private function merge_suggestions_with_types($name_suggestions, $email_suggestions) {
        $merged = array();
        
        // Add name-based suggestions
        foreach ($name_suggestions as $record_id => $user) {
            $merged[$record_id] = array(
                'user' => $user,
                'type' => 'name',
                'priority' => 1,
                'confidence' => 'high'
            );
        }
        
        // Add email-based suggestions
        foreach ($email_suggestions as $record_id => $user) {
            $merged[$record_id] = array(
                'user' => $user,
                'type' => 'email',
                'priority' => 2,
                'confidence' => 'medium'
            );
        }
        
        return $merged;
    }
    
    /**
     * Check if suggestion was already applied for a record
     *
     * @param int $record_id Record ID
     * @return bool True if suggestion was applied
     */
    private function was_suggestion_applied($record_id) {
        $preference_name = 'teamsattendance_suggestion_applied_' . $record_id;
        $applied_user_id = get_user_preferences($preference_name, null);
        
        return !is_null($applied_user_id);
    }
    
    /**
     * Get suggestion statistics
     *
     * @param array $suggestions Array of suggestions
     * @return array Statistics array
     */
    public function get_suggestion_statistics($suggestions) {
        $stats = array(
            'total' => count($suggestions),
            'name_based' => 0,
            'email_based' => 0,
            'high_confidence' => 0,
            'medium_confidence' => 0
        );
        
        foreach ($suggestions as $suggestion) {
            if ($suggestion['type'] === 'name') {
                $stats['name_based']++;
            } else {\n                $stats['email_based']++;
            }
            
            if ($suggestion['confidence'] === 'high') {
                $stats['high_confidence']++;
            } else {
                $stats['medium_confidence']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Sort records by suggestion type priority
     *
     * @param array $unassigned_records Unassigned records
     * @param array $suggestions Generated suggestions
     * @return array Sorted records
     */
    public function sort_records_by_suggestion_types($unassigned_records, $suggestions) {
        $name_suggested = array();
        $email_suggested = array();
        $not_suggested = array();
        
        foreach ($unassigned_records as $record) {
            if (isset($suggestions[$record->id])) {
                $suggestion_type = $suggestions[$record->id]['type'];
                if ($suggestion_type === 'name') {
                    $name_suggested[] = $record;
                } else {
                    $email_suggested[] = $record;
                }
            } else {
                $not_suggested[] = $record;
            }
        }
        
        // Merge arrays: name suggestions first, then email suggestions, then not suggested
        return array_merge($name_suggested, $email_suggested, $not_suggested);
    }
}
