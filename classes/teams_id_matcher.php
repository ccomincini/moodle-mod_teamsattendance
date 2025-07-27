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
 * Teams ID pattern matcher for non-email identifiers
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teamsattendance/classes/name_parser.php');

/**
 * Handles Teams ID pattern matching for non-email display names
 */
class teams_id_matcher {
    
    /** @var array Available users for matching */
    private $available_users;
    
    /** @var name_parser Name parser instance */
    private $name_parser;
    
    /** @var float Similarity threshold for Teams ID matching */
    const SIMILARITY_THRESHOLD = 0.75;
    
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
     * Find best Teams ID match for a non-email Teams display name
     *
     * @param string $teams_id Teams display name or ID
     * @return object|null Best matching user or null
     */
    public function find_best_teams_match($teams_id) {
        // Skip email addresses
        if (filter_var($teams_id, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        
        // Parse Teams ID into possible name combinations
        $teams_names = $this->name_parser->parse_teams_name($teams_id);
        
        $best_match = null;
        $best_score = 0;
        
        foreach ($this->available_users as $user) {
            $score = $this->calculate_teams_similarity($teams_names, $user);
            
            if ($score > $best_score && $score >= self::SIMILARITY_THRESHOLD) {
                $best_score = $score;
                $best_match = $user;
            }
        }
        
        return $best_match;
    }
    
    /**
     * Calculate similarity between Teams names and user names
     *
     * @param array $teams_names Parsed Teams name variations
     * @param object $user User object to match against
     * @return float Similarity score (0-1)
     */
    private function calculate_teams_similarity($teams_names, $user) {
        // Parse user names to handle edge cases
        $user_names = $this->name_parser->parse_user_names($user);
        
        $best_score = 0;
        
        // Test all combinations of Teams names vs User names
        foreach ($teams_names as $teams_name) {
            foreach ($user_names as $user_name) {
                $score = $this->compare_name_pairs($teams_name, $user_name);
                $best_score = max($best_score, $score);
            }
        }
        
        return $best_score;
    }
    
    /**
     * Compare two name pairs using enhanced string matching
     *
     * @param array $teams_name Teams name array (firstname, lastname)
     * @param array $user_name User name array (firstname, lastname)
     * @return float Similarity score (0-1)
     */
    private function compare_name_pairs($teams_name, $user_name) {
        // Normalize and tokenize names
        $teams_tokens = $this->tokenize_name($teams_name['firstname'] . ' ' . $teams_name['lastname']);
        $user_tokens = $this->tokenize_name($user_name['firstname'] . ' ' . $user_name['lastname']);
        
        if (empty($teams_tokens) || empty($user_tokens)) {
            return 0;
        }
        
        return $this->calculate_token_similarity($teams_tokens, $user_tokens);
    }
    
    /**
     * Tokenize name into normalized word candidates
     *
     * @param string $full_name Full name string
     * @return array Array of normalized tokens
     */
    private function tokenize_name($full_name) {
        // Convert to lowercase and split into words
        $words = preg_split('/\s+/', trim(strtolower($full_name)));
        $candidates = array();
        
        foreach ($words as $word) {
            if (empty($word)) {
                continue;
            }
            
            // Handle apostrophe substitutions for accented letters
            $apostrophe_map = [
                "a'" => 'a', "e'" => 'e', "i'" => 'i', "o'" => 'o', "u'" => 'u',
                "A'" => 'a', "E'" => 'e', "I'" => 'i', "O'" => 'o', "U'" => 'u'
            ];
            
            foreach ($apostrophe_map as $apostrophe => $replacement) {
                $word = str_replace($apostrophe, $replacement, $word);
            }
            
            // Remove accents from letters (enhanced with more characters)
            $accent_map = [
                'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
                'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
                'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
                'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
                'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
                'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
                'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a', 'Å' => 'a',
                'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e',
                'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i',
                'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o',
                'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u',
                'Ç' => 'c', 'Ñ' => 'n', 'Ý' => 'y', 'Ÿ' => 'y'
            ];
            
            $word = strtr($word, $accent_map);
            
            // Clean word but keep dots for initials like "J.C."
            $clean_word = preg_replace('/[^a-z0-9\'\.]/', '', $word);
            
            // Keep words that look like names (at least 1 char for initials)
            if (strlen($clean_word) >= 1 && !is_numeric($clean_word)) {
                // Handle compound initials like "J.C." -> "jc"
                if (preg_match('/^[a-z]\.?[a-z]\.?$/i', $clean_word)) {
                    $clean_word = str_replace('.', '', $clean_word);
                }
                $candidates[] = $clean_word;
            }
        }
        
        return array_unique($candidates);
    }
    
    /**
     * Calculate similarity between two sets of tokens
     *
     * @param array $tokens1 First set of tokens
     * @param array $tokens2 Second set of tokens
     * @return float Similarity score (0-1)
     */
    private function calculate_token_similarity($tokens1, $tokens2) {
        $total_similarity = 0;
        $comparisons = 0;
        
        foreach ($tokens1 as $token1) {
            $best_token_similarity = 0;
            
            foreach ($tokens2 as $token2) {
                $similarity = $this->string_similarity($token1, $token2);
                $best_token_similarity = max($best_token_similarity, $similarity);
            }
            
            $total_similarity += $best_token_similarity;
            $comparisons++;
        }
        
        // Average similarity across all tokens
        return $comparisons > 0 ? $total_similarity / $comparisons : 0;
    }
    
    /**
     * Calculate string similarity using Levenshtein distance with improvements
     *
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return float Similarity score (0-1)
     */
    private function string_similarity($str1, $str2) {
        // Exact match
        if ($str1 === $str2) {
            return 1.0;
        }
        
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        
        // Handle empty strings
        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }
        
        // Handle initials vs full names (e.g., "j" vs "john")
        if ($len1 === 1 || $len2 === 1) {
            $short = $len1 === 1 ? $str1 : $str2;
            $long = $len1 === 1 ? $str2 : $str1;
            
            // Initial match (first character)
            if (strtolower($short[0]) === strtolower($long[0])) {
                return 0.8; // High similarity for initial match
            }
            return 0.0;
        }
        
        // Standard Levenshtein similarity
        $max_len = max($len1, $len2);
        $distance = levenshtein($str1, $str2);
        
        return 1 - ($distance / $max_len);
    }
    
    /**
     * Get detailed matching information for debugging
     *
     * @param string $teams_id Teams ID to analyze
     * @param object $user User to compare against
     * @return array Detailed matching information
     */
    public function get_matching_details($teams_id, $user) {
        $teams_names = $this->name_parser->parse_teams_name($teams_id);
        $user_names = $this->name_parser->parse_user_names($user);
        
        $details = array(
            'teams_id' => $teams_id,
            'user_fullname' => $user->firstname . ' ' . $user->lastname,
            'teams_parsed' => $teams_names,
            'user_parsed' => $user_names,
            'comparisons' => array(),
            'best_score' => 0
        );
        
        foreach ($teams_names as $teams_name) {
            foreach ($user_names as $user_name) {
                $score = $this->compare_name_pairs($teams_name, $user_name);
                
                $comparison = array(
                    'teams_name' => $teams_name,
                    'user_name' => $user_name,
                    'score' => $score,
                    'teams_tokens' => $this->tokenize_name($teams_name['firstname'] . ' ' . $teams_name['lastname']),
                    'user_tokens' => $this->tokenize_name($user_name['firstname'] . ' ' . $user_name['lastname'])
                );
                
                $details['comparisons'][] = $comparison;
                $details['best_score'] = max($details['best_score'], $score);
            }
        }
        
        return $details;
    }
}
