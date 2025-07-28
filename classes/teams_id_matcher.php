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
 * Teams ID Matcher - integrates 6-phase matching system
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teamsattendance/classes/name_parser.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/six_phase_matcher.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/accent_handler.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/name_parser_dedup.php');

/**
 * Teams ID matcher using advanced 6-phase matching system
 */
class teams_id_matcher {
    
    private $available_users;
    private $name_parser;
    private $six_phase_matcher;
    private $accent_handler;
    private $dedup_handler;
    
    public function __construct($available_users) {
        // Apply deduplication to available users
        $this->dedup_handler = new name_parser_dedup();
        $this->available_users = $this->dedup_handler->deduplicate_user_list($available_users);
        
        $this->name_parser = new name_parser();
        $this->six_phase_matcher = new six_phase_matcher($this->available_users);
        $this->accent_handler = new accent_handler();
    }
    
    /**
     * Find best match using 6-phase system
     */
    public function find_by_teams_id($teams_id) {
        // Skip empty or email-only IDs
        if (empty($teams_id) || filter_var($teams_id, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        
        // Apply accent normalization
        $normalized_teams_id = $this->accent_handler->normalize_text($teams_id);
        
        // Use six-phase matcher to find best match
        return $this->six_phase_matcher->find_best_match($normalized_teams_id);
    }
    
    /**
     * Legacy method - lastname first approach
     */
    public function find_by_lastname_first($teams_id) {
        $teams_names = $this->name_parser->parse_teams_name($teams_id);
        
        foreach ($teams_names as $name) {
            foreach ($this->available_users as $user) {
                $user_names = $this->name_parser->parse_user_names($user);
                
                foreach ($user_names as $user_name) {
                    if ($this->matches_lastname_first($name, $user_name)) {
                        return $user;
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Legacy method - firstname first approach
     */
    public function find_by_firstname_first($teams_id) {
        $teams_names = $this->name_parser->parse_teams_name($teams_id);
        
        foreach ($teams_names as $name) {
            foreach ($this->available_users as $user) {
                $user_names = $this->name_parser->parse_user_names($user);
                
                foreach ($user_names as $user_name) {
                    if ($this->matches_firstname_first($name, $user_name)) {
                        return $user;
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get detailed matching information for debugging
     */
    public function get_match_details($teams_id) {
        $details = array(
            'teams_id' => $teams_id,
            'normalized_teams_id' => $this->accent_handler->normalize_text($teams_id),
            'is_email' => filter_var($teams_id, FILTER_VALIDATE_EMAIL),
            'six_phase_result' => null,
            'lastname_first_results' => array(),
            'firstname_first_results' => array(),
            'best_score' => 0
        );
        
        if ($details['is_email']) {
            return $details;
        }
        
        // Try six-phase matcher
        $six_phase_match = $this->find_by_teams_id($teams_id);
        if ($six_phase_match) {
            $details['six_phase_result'] = array(
                'user_id' => $six_phase_match->id,
                'firstname' => $six_phase_match->firstname,
                'lastname' => $six_phase_match->lastname,
                'match_method' => 'six_phase_system'
            );
            $details['best_score'] = 1.0;
        }
        
        // Legacy lastname-first scoring for comparison
        $teams_names = $this->name_parser->parse_teams_name($teams_id);
        foreach ($teams_names as $name) {
            foreach ($this->available_users as $user) {
                $user_names = $this->name_parser->parse_user_names($user);
                
                foreach ($user_names as $user_name) {
                    if ($this->matches_lastname_first($name, $user_name)) {
                        $score = $this->calculate_match_score($name, $user_name);
                        $details['lastname_first_results'][] = array(
                            'user_id' => $user->id,
                            'lastname' => $user->lastname,
                            'firstname' => $user->firstname,
                            'teams_lastname' => $name['lastname'],
                            'teams_firstname' => $name['firstname'],
                            'score' => $score
                        );
                        $details['best_score'] = max($details['best_score'], $score);
                    }
                }
            }
        }
        
        // Legacy firstname-first scoring for comparison
        foreach ($teams_names as $name) {
            foreach ($this->available_users as $user) {
                $user_names = $this->name_parser->parse_user_names($user);
                
                foreach ($user_names as $user_name) {
                    if ($this->matches_firstname_first($name, $user_name)) {
                        $score = $this->calculate_match_score($name, $user_name);
                        $details['firstname_first_results'][] = array(
                            'user_id' => $user->id,
                            'lastname' => $user->lastname,
                            'firstname' => $user->firstname,
                            'teams_lastname' => $name['lastname'],
                            'teams_firstname' => $name['firstname'],
                            'score' => $score
                        );
                        $details['best_score'] = max($details['best_score'], $score);
                    }
                }
            }
        }
        
        return $details;
    }
    
    /**
     * Legacy method maintained for compatibility
     */
    private function calculate_teams_similarity($teams_names, $user) {
        $cleaned_teams_id = '';
        foreach ($teams_names as $name) {
            $cleaned_teams_id .= ' ' . $name['firstname'] . ' ' . $name['lastname'];
        }
        $cleaned_teams_id = trim($cleaned_teams_id);
        
        // Use new matching logic
        $match = $this->find_by_teams_id($cleaned_teams_id);
        if ($match && $match->id == $user->id) {
            return 0.95; // High confidence with new system
        }
        
        // Fallback to legacy methods
        $match = $this->find_by_lastname_first($cleaned_teams_id);
        if ($match && $match->id == $user->id) {
            return 0.9;
        }
        
        $match = $this->find_by_firstname_first($cleaned_teams_id);
        if ($match && $match->id == $user->id) {
            return 0.8;
        }
        
        return 0;
    }
    
    /**
     * Check if lastname-first pattern matches
     */
    private function matches_lastname_first($teams_name, $user_name) {
        $teams_lastname = $this->accent_handler->normalize_text($teams_name['lastname']);
        $teams_firstname = $this->accent_handler->normalize_text($teams_name['firstname']);
        $user_lastname = $this->accent_handler->normalize_text($user_name['lastname']);
        $user_firstname = $this->accent_handler->normalize_text($user_name['firstname']);
        
        return (stripos($teams_lastname, $user_lastname) !== false || 
                stripos($user_lastname, $teams_lastname) !== false) &&
               (stripos($teams_firstname, $user_firstname) !== false || 
                stripos($user_firstname, $teams_firstname) !== false);
    }
    
    /**
     * Check if firstname-first pattern matches
     */
    private function matches_firstname_first($teams_name, $user_name) {
        $teams_lastname = $this->accent_handler->normalize_text($teams_name['lastname']);
        $teams_firstname = $this->accent_handler->normalize_text($teams_name['firstname']);
        $user_lastname = $this->accent_handler->normalize_text($user_name['lastname']);
        $user_firstname = $this->accent_handler->normalize_text($user_name['firstname']);
        
        return (stripos($teams_firstname, $user_firstname) !== false || 
                stripos($user_firstname, $teams_firstname) !== false) &&
               (stripos($teams_lastname, $user_lastname) !== false || 
                stripos($user_lastname, $teams_lastname) !== false);
    }
    
    /**
     * Calculate basic match score
     */
    private function calculate_match_score($teams_name, $user_name) {
        $lastname_sim = $this->string_similarity(
            $this->accent_handler->normalize_text($teams_name['lastname']),
            $this->accent_handler->normalize_text($user_name['lastname'])
        );
        $firstname_sim = $this->string_similarity(
            $this->accent_handler->normalize_text($teams_name['firstname']),
            $this->accent_handler->normalize_text($user_name['firstname'])
        );
        
        return ($lastname_sim + $firstname_sim) / 2;
    }
    
    /**
     * Calculate string similarity using Levenshtein distance
     */
    private function string_similarity($str1, $str2) {
        $max_len = max(strlen($str1), strlen($str2));
        if ($max_len == 0) return 1.0;
        
        $distance = levenshtein($str1, $str2);
        return 1 - ($distance / $max_len);
    }
    
    /**
     * Normalize Teams ID for matching
     */
    private function normalize_teams_id($teams_id) {
        return $this->accent_handler->normalize_text($teams_id);
    }
}
