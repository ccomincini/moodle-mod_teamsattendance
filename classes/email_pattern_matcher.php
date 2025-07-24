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
 * Email pattern matcher for Teams attendance
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teamsattendance/classes/name_parser.php');

/**
 * Handles email pattern matching with anti-ambiguity logic
 */
class email_pattern_matcher {
    
    /** @var array Available users for matching */
    private $available_users;
    
    /** @var name_parser Name parser instance */
    private $name_parser;
    
    /** @var float Similarity threshold for email matching */
    const SIMILARITY_THRESHOLD = 0.7;
    
    /** @var array Email patterns with their ambiguity check requirements */
    private $patterns_config = [
        0 => ['name' => 'nomecognome', 'check_ambiguity' => false],
        1 => ['name' => 'cognomenome', 'check_ambiguity' => false],
        2 => ['name' => 'n.cognome', 'check_ambiguity' => true],
        3 => ['name' => 'cognome.n', 'check_ambiguity' => true],
        4 => ['name' => 'nome.c', 'check_ambiguity' => true],
        5 => ['name' => 'nome', 'check_ambiguity' => false],
        6 => ['name' => 'cognome', 'check_ambiguity' => false],
        7 => ['name' => 'n.c', 'check_ambiguity' => true],
        8 => ['name' => 'ncognome', 'check_ambiguity' => true],
        9 => ['name' => 'nomecognome_dup', 'check_ambiguity' => false]
    ];
    
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
     * Find best email match for a Teams email address
     *
     * @param string $teams_email Full email address
     * @return object|null Best matching user or null
     */
    public function find_best_email_match($teams_email) {
        $email_parts = explode('@', strtolower($teams_email));
        if (count($email_parts) !== 2) {
            return null;
        }
        
        $local_part = $email_parts[0]; // Part before @
        
        $best_match = null;
        $best_score = 0;
        
        foreach ($this->available_users as $user) {
            $score = $this->calculate_email_similarity($local_part, $user);
            
            if ($score > $best_score && $score >= self::SIMILARITY_THRESHOLD) {
                $best_score = $score;
                $best_match = $user;
            }
        }
        
        return $best_match;
    }
    
    /**
     * Calculate email similarity with enhanced patterns and anti-ambiguity logic
     *
     * @param string $local_part Email local part (before @)
     * @param object $user User object to match against
     * @return float Similarity score (0-1)
     */
    private function calculate_email_similarity($local_part, $user) {
        // Parse user names to handle various edge cases
        $user_names = $this->name_parser->parse_user_names($user);
        
        $best_score = 0;
        
        // Test against all parsed name variations
        foreach ($user_names as $names) {
            $firstname = strtolower($names['firstname']);
            $lastname = strtolower($names['lastname']);
            
            // Remove non-alphanumeric characters and normalize
            $local_part_clean = preg_replace('/[^a-z0-9]/', '', strtolower($local_part));
            $firstname_clean = preg_replace('/[^a-z0-9]/', '', $firstname);
            $lastname_clean = preg_replace('/[^a-z0-9]/', '', $lastname);
            
            if (empty($firstname_clean) || empty($lastname_clean)) {
                continue;
            }
            
            $scores = array();
            
            // Generate all 10 email patterns
            $patterns = $this->generate_patterns($firstname_clean, $lastname_clean);
            
            // Test each pattern
            for ($i = 0; $i < count($patterns); $i++) {
                $pattern = $patterns[$i];
                
                if (empty($pattern)) {
                    continue;
                }
                
                // Calculate basic similarity
                $similarity = $this->similarity_score($local_part_clean, $pattern);
                
                // Apply anti-ambiguity logic for specific patterns
                if ($this->patterns_config[$i]['check_ambiguity'] && $similarity > 0.8) {
                    if ($this->check_name_ambiguity($pattern, $i, $user)) {
                        // Don't suggest this match - ambiguous
                        $similarity = 0;
                    }
                }
                
                if ($similarity > 0) {
                    $scores[] = $similarity;
                }
            }
            
            // Get the best score for this name variation
            if (!empty($scores)) {
                $variation_score = max($scores);
                $best_score = max($best_score, $variation_score);
            }
        }
        
        return $best_score;
    }
    
    /**
     * Generate all email patterns for given names
     *
     * @param string $firstname_clean Cleaned firstname
     * @param string $lastname_clean Cleaned lastname
     * @return array Array of patterns
     */
    private function generate_patterns($firstname_clean, $lastname_clean) {
        return array(
            $firstname_clean . $lastname_clean,                    // 0: nomecognome
            $lastname_clean . $firstname_clean,                    // 1: cognomenome
            $firstname_clean[0] . $lastname_clean,                 // 2: n.cognome (initial + lastname)
            $lastname_clean . $firstname_clean[0],                 // 3: cognome.n (lastname + initial)
            $firstname_clean . $lastname_clean[0],                 // 4: nome.c (firstname + initial)
            $firstname_clean,                                      // 5: solo nome
            $lastname_clean,                                       // 6: solo cognome
            $firstname_clean[0] . $lastname_clean[0],             // 7: n.c (initials)
            substr($firstname_clean, 0, 1) . $lastname_clean,     // 8: ncognome (initial + lastname without separator)
            $firstname_clean . $lastname_clean                     // 9: nomecognome (duplicate for explicit handling)
        );
    }
    
    /**
     * Check if a pattern would match multiple users (ambiguity check)
     *
     * @param string $pattern The email pattern to check
     * @param int $pattern_index The index of the pattern
     * @param object $current_user The user we're checking against
     * @return bool True if ambiguous (multiple matches), false if safe to suggest
     */
    private function check_name_ambiguity($pattern, $pattern_index, $current_user) {
        $matching_users = 0;
        
        foreach ($this->available_users as $user) {
            if ($user->id === $current_user->id) {
                continue; // Skip the user we're currently checking
            }
            
            $user_names = $this->name_parser->parse_user_names($user);
            
            foreach ($user_names as $names) {
                $firstname = preg_replace('/[^a-z0-9]/', '', strtolower($names['firstname']));
                $lastname = preg_replace('/[^a-z0-9]/', '', strtolower($names['lastname']));
                
                if (empty($firstname) || empty($lastname)) {
                    continue;
                }
                
                // Generate the same pattern for this user
                $user_pattern = $this->generate_pattern_for_index($pattern_index, $firstname, $lastname);
                
                if ($user_pattern === $pattern) {
                    $matching_users++;
                    if ($matching_users >= 1) {
                        // Found another user with the same pattern - ambiguous
                        return true;
                    }
                }
            }
        }
        
        return false; // No ambiguity found
    }
    
    /**
     * Generate specific pattern by index
     *
     * @param int $pattern_index Pattern index
     * @param string $firstname Cleaned firstname
     * @param string $lastname Cleaned lastname
     * @return string Generated pattern
     */
    private function generate_pattern_for_index($pattern_index, $firstname, $lastname) {
        switch ($pattern_index) {
            case 2: // n.cognome (initial + lastname)
            case 8: // ncognome (new pattern)
                return $firstname[0] . $lastname;
            case 3: // cognome.n (lastname + initial)
                return $lastname . $firstname[0];
            case 4: // nome.c (firstname + initial)
                return $firstname . $lastname[0];
            case 7: // n.c (initials)
                return $firstname[0] . $lastname[0];
            default:
                return '';
        }
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
     * Get pattern statistics for debugging/monitoring
     *
     * @param string $local_part Email local part
     * @param object $user User object
     * @return array Pattern matching details
     */
    public function get_pattern_details($local_part, $user) {
        $user_names = $this->name_parser->parse_user_names($user);
        $details = array();
        
        foreach ($user_names as $names) {
            $firstname_clean = preg_replace('/[^a-z0-9]/', '', strtolower($names['firstname']));
            $lastname_clean = preg_replace('/[^a-z0-9]/', '', strtolower($names['lastname']));
            $local_part_clean = preg_replace('/[^a-z0-9]/', '', strtolower($local_part));
            
            if (empty($firstname_clean) || empty($lastname_clean)) {
                continue;
            }
            
            $patterns = $this->generate_patterns($firstname_clean, $lastname_clean);
            
            for ($i = 0; $i < count($patterns); $i++) {
                $pattern = $patterns[$i];
                $similarity = $this->similarity_score($local_part_clean, $pattern);
                
                $details[] = array(
                    'pattern_name' => $this->patterns_config[$i]['name'],
                    'pattern_value' => $pattern,
                    'similarity' => $similarity,
                    'ambiguity_check' => $this->patterns_config[$i]['check_ambiguity'],
                    'would_suggest' => $similarity >= self::SIMILARITY_THRESHOLD
                );
            }
        }
        
        return $details;
    }
}
