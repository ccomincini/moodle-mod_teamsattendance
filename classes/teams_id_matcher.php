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
 * Teams ID pattern matcher for Teams attendance
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teamsattendance/classes/name_parser.php');

/**
 * Handles Teams ID pattern matching using email pattern logic
 */
class teams_id_matcher {
    
    /** @var array Available users for matching */
    private $available_users;
    
    /** @var name_parser Name parser instance */
    private $name_parser;
    
    /** @var float Similarity threshold for matching */
    const SIMILARITY_THRESHOLD = 0.7;
    
    /** @var array Patterns with their ambiguity check requirements */
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
     * Find best match for a Teams ID
     *
     * @param string $teams_id Teams user ID
     * @return object|null Best matching user or null
     */
    public function find_best_teams_match($teams_id) {
        $clean_id = $this->extract_clean_identifier($teams_id);
        
        if (empty($clean_id) || strlen($clean_id) < 3) {
            return null;
        }
        
        $best_match = null;
        $best_score = 0;
        
        foreach ($this->available_users as $user) {
            $score = $this->calculate_teams_similarity($clean_id, $user);
            
            if ($score > $best_score && $score >= self::SIMILARITY_THRESHOLD) {
                $best_score = $score;
                $best_match = $user;
            }
        }
        
        return $best_match;
    }
    
    /**
     * Extract clean identifier from Teams ID (equivalent to email local_part)
     *
     * @param string $teams_id Raw Teams ID
     * @return string Clean identifier
     */
    private function extract_clean_identifier($teams_id) {
        $clean = strtolower(trim($teams_id));
        
        // Skip if it's an email
        if (filter_var($teams_id, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        
        // Remove common separators and keep alphanumeric
        $clean = preg_replace('/[^a-z0-9\s]/', ' ', $clean);
        
        // Remove multiple spaces
        $clean = preg_replace('/\s+/', ' ', $clean);
        
        // Extract meaningful parts (words >= 2 chars)
        $parts = array_filter(explode(' ', $clean), function($part) {
            return strlen($part) >= 2;
        });
        
        // Join parts without spaces for pattern matching
        return implode('', $parts);
    }
    
    /**
     * Calculate Teams ID similarity using pattern matching
     *
     * @param string $clean_id Clean Teams identifier
     * @param object $user User object to match against
     * @return float Similarity score (0-1)
     */
    private function calculate_teams_similarity($clean_id, $user) {
        $user_names = $this->name_parser->parse_user_names($user);
        
        $best_score = 0;
        
        foreach ($user_names as $names) {
            $firstname = strtolower($names['firstname']);
            $lastname = strtolower($names['lastname']);
            
            $firstname_clean = preg_replace('/[^a-z0-9]/', '', $firstname);
            $lastname_clean = preg_replace('/[^a-z0-9]/', '', $lastname);
            
            if (empty($firstname_clean) || empty($lastname_clean)) {
                continue;
            }
            
            $scores = array();
            
            $patterns = $this->generate_patterns($firstname_clean, $lastname_clean);
            
            for ($i = 0; $i < count($patterns); $i++) {
                $pattern = $patterns[$i];
                
                if (empty($pattern)) {
                    continue;
                }
                
                $similarity = $this->similarity_score($clean_id, $pattern);
                
                if ($this->patterns_config[$i]['check_ambiguity'] && $similarity > 0.8) {
                    if ($this->check_pattern_ambiguity($pattern, $i, $user)) {
                        $similarity = 0;
                    }
                }
                
                if ($similarity > 0) {
                    $scores[] = $similarity;
                }
            }
            
            if (!empty($scores)) {
                $variation_score = max($scores);
                $best_score = max($best_score, $variation_score);
            }
        }
        
        return $best_score;
    }
    
    /**
     * Generate all patterns for given names
     *
     * @param string $firstname_clean Cleaned firstname
     * @param string $lastname_clean Cleaned lastname
     * @return array Array of patterns
     */
    private function generate_patterns($firstname_clean, $lastname_clean) {
        return array(
            $firstname_clean . $lastname_clean,
            $lastname_clean . $firstname_clean,
            $firstname_clean[0] . $lastname_clean,
            $lastname_clean . $firstname_clean[0],
            $firstname_clean . $lastname_clean[0],
            $firstname_clean,
            $lastname_clean,
            $firstname_clean[0] . $lastname_clean[0],
            substr($firstname_clean, 0, 1) . $lastname_clean,
            $firstname_clean . $lastname_clean
        );
    }
    
    /**
     * Check if pattern would match multiple users
     *
     * @param string $pattern Pattern to check
     * @param int $pattern_index Pattern index
     * @param object $current_user Current user
     * @return bool True if ambiguous
     */
    private function check_pattern_ambiguity($pattern, $pattern_index, $current_user) {
        $matching_users = 0;
        
        foreach ($this->available_users as $user) {
            if ($user->id === $current_user->id) {
                continue;
            }
            
            $user_names = $this->name_parser->parse_user_names($user);
            
            foreach ($user_names as $names) {
                $firstname = preg_replace('/[^a-z0-9]/', '', strtolower($names['firstname']));
                $lastname = preg_replace('/[^a-z0-9]/', '', strtolower($names['lastname']));
                
                if (empty($firstname) || empty($lastname)) {
                    continue;
                }
                
                $user_pattern = $this->generate_pattern_for_index($pattern_index, $firstname, $lastname);
                
                if ($user_pattern === $pattern) {
                    $matching_users++;
                    if ($matching_users >= 1) {
                        return true;
                    }
                }
            }
        }
        
        return false;
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
            case 2:
            case 8:
                return $firstname[0] . $lastname;
            case 3:
                return $lastname . $firstname[0];
            case 4:
                return $firstname . $lastname[0];
            case 7:
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
}
