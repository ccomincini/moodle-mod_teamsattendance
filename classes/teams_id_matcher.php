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
 * Teams ID Matcher - integrates 6-phase matching system and email pattern matcher
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teamsattendance/classes/name_parser.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/six_phase_matcher.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/email_pattern_matcher.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/accent_handler.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/name_parser_dedup.php');

/**
 * Teams ID matcher using advanced 6-phase matching system and email pattern matching
 */
class teams_id_matcher {
    
    private $available_users;
    private $name_parser;
    private $six_phase_matcher;
    private $email_pattern_matcher;
    private $accent_handler;
    private $dedup_handler;
    
    public function __construct($available_users) {
        // Apply deduplication to available users
        $this->dedup_handler = new name_parser_dedup();
        $this->available_users = $this->dedup_handler->deduplicate_user_list($available_users);
        
        $this->name_parser = new name_parser();
        $this->six_phase_matcher = new six_phase_matcher($this->available_users);
        $this->email_pattern_matcher = new email_pattern_matcher($this->available_users);
        $this->accent_handler = new accent_handler();
    }
    
    /**
     * Universal matching method - handles both Teams names and email addresses
     *
     * @param string $teams_id Teams identifier (can be name or email)
     * @return object|null Best matching user
     */
    public function find_best_teams_match($teams_id) {
        // Skip empty IDs
        if (empty($teams_id)) {
            return null;
        }
        
        // Check if it's an email address
        if (filter_var($teams_id, FILTER_VALIDATE_EMAIL)) {
            return $this->email_pattern_matcher->find_best_email_match($teams_id);
        }
        
        // Apply accent normalization for names
        $normalized_teams_id = $this->accent_handler->normalize_text($teams_id);
        
        // Use six-phase matcher for names
        return $this->six_phase_matcher->find_best_match($normalized_teams_id);
    }
    
    /**
     * Legacy method name - maintained for compatibility
     */
    public function find_by_teams_id($teams_id) {
        return $this->find_best_teams_match($teams_id);
    }
    
    /**
     * Get comprehensive matching details for any identifier
     *
     * @param string $teams_id Teams identifier (name or email)
     * @return array Detailed matching information
     */
    public function get_match_details($teams_id) {
        $details = array(
            'teams_id' => $teams_id,
            'normalized_teams_id' => $this->accent_handler->normalize_text($teams_id),
            'is_email' => filter_var($teams_id, FILTER_VALIDATE_EMAIL),
            'match_result' => null,
            'match_method' => null,
            'confidence' => 0
        );
        
        if ($details['is_email']) {
            // Email matching details
            $match = $this->email_pattern_matcher->find_best_email_match($teams_id);
            $details['match_result'] = $match;
            $details['match_method'] = 'email_pattern_matching';
            $details['email_analysis'] = $this->email_pattern_matcher->get_pattern_analysis($teams_id);
            
            if ($match) {
                $details['confidence'] = 0.95; // High confidence for email matches
            }
        } else {
            // Name matching details using six-phase system
            $match = $this->six_phase_matcher->find_best_match($details['normalized_teams_id']);
            $details['match_result'] = $match;
            $details['match_method'] = 'six_phase_name_matching';
            
            if ($match) {
                $details['confidence'] = 0.95; // Very high confidence for six-phase matches
            } else {
                // Try legacy methods for additional information
                $legacy_lastname_match = $this->find_by_lastname_first($teams_id);
                $legacy_firstname_match = $this->find_by_firstname_first($teams_id);
                
                $details['legacy_results'] = array(
                    'lastname_first_match' => $legacy_lastname_match,
                    'firstname_first_match' => $legacy_firstname_match
                );
                
                if ($legacy_lastname_match || $legacy_firstname_match) {
                    $details['confidence'] = 0.7; // Lower confidence for legacy matches
                }
            }
        }
        
        return $details;
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
}
