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
 * Email pattern matcher with proper uniqueness logic for Teams attendance
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teamsattendance/classes/name_parser.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/accent_handler.php');

/**
 * Handles email pattern matching with proper uniqueness validation
 */
class email_pattern_matcher {
    
    /** @var array Available users for matching */
    private $available_users;
    
    /** @var name_parser Name parser instance */
    private $name_parser;
    
    /**
     * Constructor
     *
     * @param array $available_users Array of available users
     */
    public function __construct($available_users) {
        $this->available_users = $available_users;
        $this->name_parser = new name_parser();
    }
    
    /**
     * Find best email match with proper uniqueness validation
     *
     * @param string $teams_email Full email address
     * @return object|null Best matching user or null if not unique
     */
    public function find_best_email_match($teams_email) {
        $email_parts = explode('@', strtolower($teams_email));
        if (count($email_parts) !== 2) {
            return null;
        }
        
        $local_part = $email_parts[0]; // Part before @
        
        // Try exact pattern matches first (full names)
        $exact_match = $this->find_exact_pattern_match($local_part);
        if ($exact_match) {
            return $exact_match;
        }
        
        // Try initial patterns with uniqueness validation
        return $this->find_initial_pattern_match($local_part);
    }
    
    /**
     * Find exact pattern matches (full names without ambiguity)
     *
     * @param string $local_part Email local part
     * @return object|null Best match or null
     */
    private function find_exact_pattern_match($local_part) {
        $local_normalized = accent_handler::normalize($local_part);
        $local_clean = preg_replace('/[^a-z0-9]/', '', strtolower($local_normalized));
        
        foreach ($this->available_users as $user) {
            $user_names = $this->name_parser->parse_user_names($user);
            
            foreach ($user_names as $names) {
                $firstname = accent_handler::normalize($names['firstname']);
                $lastname = accent_handler::normalize($names['lastname']);
                
                $firstname_clean = preg_replace('/[^a-z0-9]/', '', strtolower($firstname));
                $lastname_clean = preg_replace('/[^a-z0-9]/', '', strtolower($lastname));
                
                if (empty($firstname_clean) || empty($lastname_clean)) {
                    continue;
                }
                
                // Test exact patterns (cognome-first priority)
                $patterns = [
                    $lastname_clean . $firstname_clean,       // cognomenome
                    $lastname_clean . '.' . $firstname_clean, // cognome.nome  
                    $lastname_clean . '_' . $firstname_clean, // cognome_nome
                    $firstname_clean . $lastname_clean,       // nomecognome
                    $firstname_clean . '.' . $lastname_clean, // nome.cognome
                    $firstname_clean . '_' . $lastname_clean  // nome_cognome
                ];
                
                foreach ($patterns as $pattern) {
                    if ($this->patterns_match($local_clean, $pattern)) {
                        return $user;
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Find initial pattern matches with uniqueness validation
     *
     * @param string $local_part Email local part
     * @return object|null Best match or null if ambiguous
     */
    private function find_initial_pattern_match($local_part) {
        $local_normalized = accent_handler::normalize($local_part);
        $local_clean = preg_replace('/[^a-z0-9]/', '', strtolower($local_normalized));
        
        // Test initial patterns
        $initial_patterns = [
            'cognome_initial' => '/^([a-z]+)([a-z])$/',     // cognome + initial
            'initial_cognome' => '/^([a-z])([a-z]+)$/',     // initial + cognome  
            'cognome_only' => '/^([a-z]+)$/',               // solo cognome
            'nome_only' => '/^([a-z]+)$/'                   // solo nome
        ];
        
        foreach ($initial_patterns as $pattern_type => $regex) {
            if (preg_match($regex, $local_clean, $matches)) {
                $candidate_users = $this->find_users_for_initial_pattern($pattern_type, $matches);
                
                // UNIQUENESS CHECK: return only if exactly 1 user matches
                if (count($candidate_users) === 1) {
                    return $candidate_users[0];
                }
                // If 0 or multiple users match, continue to next pattern
            }
        }
        
        return null; // No unique match found
    }
    
    /**
     * Find users matching an initial pattern
     *
     * @param string $pattern_type Type of pattern
     * @param array $matches Regex matches
     * @return array Array of matching users
     */
    private function find_users_for_initial_pattern($pattern_type, $matches) {
        $matching_users = [];
        
        foreach ($this->available_users as $user) {
            $user_names = $this->name_parser->parse_user_names($user);
            
            foreach ($user_names as $names) {
                $firstname = accent_handler::normalize($names['firstname']);
                $lastname = accent_handler::normalize($names['lastname']);
                
                $firstname_clean = preg_replace('/[^a-z0-9]/', '', strtolower($firstname));
                $lastname_clean = preg_replace('/[^a-z0-9]/', '', strtolower($lastname));
                
                if (empty($firstname_clean) || empty($lastname_clean)) {
                    continue;
                }
                
                $user_matches = false;
                
                switch ($pattern_type) {
                    case 'cognome_initial':
                        // Pattern: cognome + first letter of nome
                        if ($matches[1] === $lastname_clean && 
                            $matches[2] === substr($firstname_clean, 0, 1)) {
                            $user_matches = true;
                        }
                        break;
                        
                    case 'initial_cognome':
                        // Pattern: first letter of nome + cognome  
                        if ($matches[1] === substr($firstname_clean, 0, 1) && 
                            $matches[2] === $lastname_clean) {
                            $user_matches = true;
                        }
                        break;
                        
                    case 'cognome_only':
                        // Pattern: solo cognome
                        if ($matches[1] === $lastname_clean) {
                            $user_matches = true;
                        }
                        break;
                        
                    case 'nome_only':
                        // Pattern: solo nome
                        if ($matches[1] === $firstname_clean) {
                            $user_matches = true;
                        }
                        break;
                }
                
                if ($user_matches) {
                    $matching_users[] = $user;
                    break; // Stop checking other name variations for this user
                }
            }
        }
        
        return $matching_users;
    }
    
    /**
     * Check if two patterns match with separator tolerance
     *
     * @param string $local_clean Clean local part
     * @param string $pattern Generated pattern
     * @return bool True if patterns match
     */
    private function patterns_match($local_clean, $pattern) {
        // Direct match
        if ($local_clean === $pattern) {
            return true;
        }
        
        // Try with separator variations
        $local_variants = [
            str_replace(['.', '-', '_'], '', $local_clean),  // Remove separators
            str_replace(['-', '_'], '.', $local_clean),      // Normalize to dots
            str_replace(['.', '_'], '-', $local_clean),      // Normalize to dashes  
            str_replace(['.', '-'], '_', $local_clean)       // Normalize to underscores
        ];
        
        foreach ($local_variants as $variant) {
            if ($variant === $pattern) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get detailed pattern analysis for debugging
     *
     * @param string $teams_email Email to analyze
     * @return array Analysis details
     */
    public function get_pattern_analysis($teams_email) {
        $email_parts = explode('@', strtolower($teams_email));
        if (count($email_parts) !== 2) {
            return ['error' => 'Invalid email format'];
        }
        
        $local_part = $email_parts[0];
        $local_normalized = accent_handler::normalize($local_part);
        $local_clean = preg_replace('/[^a-z0-9]/', '', strtolower($local_normalized));
        
        $analysis = [
            'original' => $teams_email,
            'local_part' => $local_part,
            'local_clean' => $local_clean,
            'exact_patterns_tested' => [],
            'initial_patterns_tested' => [],
            'potential_matches' => []
        ];
        
        // Test exact patterns
        foreach ($this->available_users as $user) {
            $user_names = $this->name_parser->parse_user_names($user);
            
            foreach ($user_names as $names) {
                $firstname = accent_handler::normalize($names['firstname']);
                $lastname = accent_handler::normalize($names['lastname']);
                
                $firstname_clean = preg_replace('/[^a-z0-9]/', '', strtolower($firstname));
                $lastname_clean = preg_replace('/[^a-z0-9]/', '', strtolower($lastname));
                
                if (empty($firstname_clean) || empty($lastname_clean)) {
                    continue;
                }
                
                $exact_patterns = [
                    'cognomenome' => $lastname_clean . $firstname_clean,
                    'nome.cognome' => $firstname_clean . '.' . $lastname_clean,
                    'cognome.nome' => $lastname_clean . '.' . $firstname_clean
                ];
                
                foreach ($exact_patterns as $pattern_name => $pattern) {
                    $matches = $this->patterns_match($local_clean, $pattern);
                    $analysis['exact_patterns_tested'][] = [
                        'user' => $user->firstname . ' ' . $user->lastname,
                        'pattern_name' => $pattern_name,
                        'pattern_value' => $pattern,
                        'matches' => $matches
                    ];
                    
                    if ($matches) {
                        $analysis['potential_matches'][] = [
                            'user' => $user,
                            'type' => 'exact',
                            'pattern' => $pattern_name
                        ];
                    }
                }
            }
        }
        
        return $analysis;
    }
}
