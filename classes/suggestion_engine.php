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

require_once($CFG->dirroot . '/mod/teamsattendance/classes/email_pattern_matcher.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/name_parser.php');

/**
 * Main suggestion engine that coordinates matching logic
 */
class suggestion_engine {
    
    /** @var email_pattern_matcher Email pattern matching component */
    private $email_matcher;
    
    /** @var name_parser Name parsing component */
    private $name_parser;
    
    /** @var array Available users for assignment */
    private $available_users;
    
    /** @var float Similarity threshold for name matching */
    const NAME_SIMILARITY_THRESHOLD = 0.8;
    
    /** @var float Similarity threshold for email matching */
    const EMAIL_SIMILARITY_THRESHOLD = 0.7;
    
    /**
     * Constructor
     *
     * @param array $available_users Array of available users
     */
    public function __construct($available_users) {
        $this->available_users = $available_users;
        $this->email_matcher = new email_pattern_matcher($available_users);
        $this->name_parser = new name_parser();
    }
    
    /**
     * Generate suggestions for unassigned records
     *
     * @param array $unassigned_records Array of unassigned attendance records
     * @return array Suggestions organized by type
     */
    public function generate_suggestions($unassigned_records) {
        $name_suggestions = $this->get_name_based_suggestions($unassigned_records);
        $email_suggestions = $this->get_email_based_suggestions($unassigned_records, $name_suggestions);
        
        return $this->merge_suggestions_with_types($name_suggestions, $email_suggestions);
    }
    
    /**
     * Get name-based suggestions for unassigned records
     *
     * @param array $unassigned_records Array of unassigned records
     * @return array Name-based suggestions
     */
    private function get_name_based_suggestions($unassigned_records) {
        $suggestions = array();
        
        foreach ($unassigned_records as $record) {
            if ($this->was_suggestion_applied($record->id)) {
                continue;
            }
            
            $teams_name = trim($record->teams_user_id);
            $parsed_names = $this->name_parser->parse_teams_name($teams_name);
            
            if (!empty($parsed_names)) {
                $best_match = $this->find_best_name_match($parsed_names);
                if ($best_match) {
                    $suggestions[$record->id] = $best_match;
                }
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Get email-based suggestions for unassigned records
     *
     * @param array $unassigned_records Array of unassigned records  
     * @param array $name_suggestions Existing name suggestions to avoid duplicates
     * @return array Email-based suggestions
     */
    private function get_email_based_suggestions($unassigned_records, $name_suggestions) {
        $suggestions = array();
        
        foreach ($unassigned_records as $record) {
            // Skip if already has a name-based suggestion
            if (isset($name_suggestions[$record->id])) {
                continue;
            }
            
            if ($this->was_suggestion_applied($record->id)) {
                continue;
            }
            
            $teams_user_id = trim($record->teams_user_id);
            
            // Check if teams_user_id looks like an email
            if (filter_var($teams_user_id, FILTER_VALIDATE_EMAIL)) {
                $best_match = $this->email_matcher->find_best_email_match($teams_user_id);
                if ($best_match) {
                    $suggestions[$record->id] = $best_match;
                }
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Find best name match from parsed name variations
     *
     * @param array $parsed_names Array of parsed name combinations
     * @return object|null Best matching user or null
     */
    private function find_best_name_match($parsed_names) {
        $best_match = null;
        $best_score = 0;
        
        foreach ($parsed_names as $name_combo) {
            foreach ($this->available_users as $user) {
                $score = $this->calculate_name_similarity($name_combo, $user);
                
                if ($score > $best_score && $score >= self::NAME_SIMILARITY_THRESHOLD) {
                    $best_score = $score;
                    $best_match = $user;
                }
            }
        }
        
        return $best_match;
    }
    
    /**
     * Calculate similarity between parsed name and user
     *
     * @param array $parsed_name Parsed name array with firstname/lastname
     * @param object $user User object
     * @return float Similarity score (0-1)
     */
    private function calculate_name_similarity($parsed_name, $user) {
        $firstname_similarity = $this->similarity_score(
            strtolower($parsed_name['firstname']), 
            strtolower($user->firstname)
        );
        
        $lastname_similarity = $this->similarity_score(
            strtolower($parsed_name['lastname']), 
            strtolower($user->lastname)
        );
        
        // Weight both names equally
        return ($firstname_similarity + $lastname_similarity) / 2;
    }
    
    /**
     * Calculate similarity score using Levenshtein distance
     *
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return float Similarity score (0-1)
     */
    private function similarity_score($str1, $str2) {
        $max_len = max(strlen($str1), strlen($str2));
        if ($max_len == 0) return 1.0;
        
        $distance = levenshtein($str1, $str2);
        return 1 - ($distance / $max_len);
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
            } else {
                $stats['email_based']++;
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
