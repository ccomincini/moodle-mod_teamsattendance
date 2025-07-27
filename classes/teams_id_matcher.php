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
 * Handles Teams ID pattern matching with enhanced normalization and initials support
 */
class teams_id_matcher {
    
    /** @var array Available users for matching */
    private $available_users;
    
    /** @var name_parser Name parser instance */
    private $name_parser;
    
    /** @var float Similarity threshold for matching */
    const SIMILARITY_THRESHOLD = 0.85;
    
    /** @var float Similarity threshold for initial matches */
    const INITIAL_SIMILARITY_THRESHOLD = 0.90;
    
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
        // Skip emails
        if (filter_var($teams_id, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        
        $candidate_words = $this->extract_candidate_words($teams_id);
        
        if (count($candidate_words) < 2) {
            return null; // Need at least 2 words for name matching
        }
        
        $best_match = null;
        $best_score = 0;
        
        foreach ($this->available_users as $user) {
            $score = $this->calculate_teams_similarity($candidate_words, $user);
            
            if ($score > $best_score && $score >= self::SIMILARITY_THRESHOLD) {
                $best_score = $score;
                $best_match = $user;
            }
        }
        
        return $best_match;
    }
    
    /**
     * Extract candidate words from Teams ID (preserve names, remove noise)
     *
     * @param string $teams_id Raw Teams ID
     * @return array Array of candidate words
     */
    private function extract_candidate_words($teams_id) {
        $text = strtolower(trim($teams_id));
        
        // Split on common separators
        $text = preg_replace('/[,\-_()|\[\]{}]/', ' ', $text);
        
        // Split into words
        $words = preg_split('/\s+/', $text);
        
        $candidates = [];
        
        foreach ($words as $word) {
            $word = trim($word);
            
            // Skip if too short
            if (strlen($word) < 1) {
                continue;
            }
            
            // Skip common noise words
            if ($this->is_noise_word($word)) {
                continue;
            }
            
            // Clean word with enhanced normalization
            $clean_word = $this->normalize_word($word);
            
            // Keep words that look like names (at least 1 char for initials)
            if (strlen($clean_word) >= 1 && !is_numeric($clean_word)) {
                $candidates[] = $clean_word;
            }
        }
        
        return array_unique($candidates);
    }
    
    /**
     * Normalize word with accent handling and apostrophe substitution
     *
     * @param string $word Word to normalize
     * @return string Normalized word
     */
    private function normalize_word($word) {
        // Remove dots but keep letters, numbers, apostrophes
        $word = preg_replace('/[^a-z0-9\']/', '', $word);
        
        // Handle apostrophe substitutions for accented letters
        $apostrophe_map = [
            "a'" => 'a', "e'" => 'e', "i'" => 'i', "o'" => 'o', "u'" => 'u',
            "A'" => 'a', "E'" => 'e', "I'" => 'i', "O'" => 'o', "U'" => 'u'
        ];
        
        foreach ($apostrophe_map as $apostrophe => $replacement) {
            $word = str_replace($apostrophe, $replacement, $word);
        }
        
        // Remove accents from letters
        $accent_map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n'
        ];
        
        return strtr($word, $accent_map);
    }
    
    /**
     * Check if word is noise (titles, organizations, etc)
     *
     * @param string $word Word to check
     * @return bool True if noise word
     */
    private function is_noise_word($word) {
        $noise_words = [
            // Titles
            'dott', 'dr', 'arch', 'ing', 'geom', 'avv', 'prof', 'sig', 'dott.ssa',
            'sindaco', 'presidente', 'direttore', 'assessore',
            
            // Organizations
            'comune', 'provincia', 'regione', 'aipo', 'utc', 'uclam',
            'ufficio', 'tecnico', 'ufficiotecnico', 'protezione', 'civile',
            'polizia', 'locale', 'cm', 'comunita', 'montana',
            
            // Generic
            'di', 'da', 'del', 'della', 'dei', 'delle', 'il', 'la', 'lo', 'gli', 'le',
            'e', 'ed', 'o', 'od', 'per', 'con', 'su', 'in', 'a', 'tra', 'fra',
            'guest', 'meeting', 'partecipante', 'participant', 'user', 'utente'
        ];
        
        return in_array(strtolower($word), $noise_words);
    }
    
    /**
     * Calculate Teams ID similarity using enhanced pattern matching
     *
     * @param array $candidate_words Extracted candidate words
     * @param object $user User object to match against
     * @return float Similarity score (0-1)
     */
    private function calculate_teams_similarity($candidate_words, $user) {
        $user_names = $this->name_parser->parse_user_names($user);
        
        $best_score = 0;
        
        foreach ($user_names as $names) {
            $firstname = strtolower($names['firstname']);
            $lastname = strtolower($names['lastname']);
            
            $firstname_clean = $this->normalize_word($firstname);
            $lastname_clean = $this->normalize_word($lastname);
            
            if (empty($firstname_clean) || empty($lastname_clean)) {
                continue;
            }
            
            // Try exact word matches first (highest priority)
            $exact_score = $this->calculate_exact_word_match($candidate_words, $firstname_clean, $lastname_clean);
            if ($exact_score > 0) {
                $best_score = max($best_score, $exact_score);
                continue;
            }
            
            // Try initial combinations with anti-ambiguity check
            $initial_score = $this->calculate_initial_match($candidate_words, $firstname_clean, $lastname_clean, $user);
            if ($initial_score > 0) {
                $best_score = max($best_score, $initial_score);
                continue;
            }
            
            // Try pattern combinations
            $pattern_score = $this->calculate_pattern_score($candidate_words, $firstname_clean, $lastname_clean);
            $best_score = max($best_score, $pattern_score);
        }
        
        return $best_score;
    }
    
    /**
     * Calculate exact word match score (both first and last name must match, order flexible)
     *
     * @param array $candidate_words Words from Teams ID
     * @param string $firstname_clean Clean firstname
     * @param string $lastname_clean Clean lastname
     * @return float Score (0-1)
     */
    private function calculate_exact_word_match($candidate_words, $firstname_clean, $lastname_clean) {
        $firstname_found = false;
        $lastname_found = false;
        
        foreach ($candidate_words as $word) {
            // Check firstname match
            if ($this->similarity_score($word, $firstname_clean) >= 0.9) {
                $firstname_found = true;
            }
            
            // Check lastname match
            if ($this->similarity_score($word, $lastname_clean) >= 0.9) {
                $lastname_found = true;
            }
        }
        
        // Both names must be found for high score (order doesn't matter)
        if ($firstname_found && $lastname_found) {
            return 0.95; // Very high score for exact matches
        }
        
        return 0; // No score if not both names found
    }
    
    /**
     * Calculate initial match score with anti-ambiguity check
     *
     * @param array $candidate_words Words from Teams ID
     * @param string $firstname_clean Clean firstname
     * @param string $lastname_clean Clean lastname
     * @param object $user Current user being matched
     * @return float Score (0-1)
     */
    private function calculate_initial_match($candidate_words, $firstname_clean, $lastname_clean, $user) {
        foreach ($candidate_words as $i => $word1) {
            foreach ($candidate_words as $j => $word2) {
                if ($i === $j) continue;
                
                // Pattern 1: full firstname + initial lastname
                if ($this->similarity_score($word1, $firstname_clean) >= 0.9 && 
                    strlen($word2) === 1 && $word2 === $lastname_clean[0]) {
                    
                    if (!$this->is_initial_ambiguous($firstname_clean, $word2, 'lastname', $user)) {
                        return 0.90;
                    }
                }
                
                // Pattern 2: initial firstname + full lastname
                if (strlen($word1) === 1 && $word1 === $firstname_clean[0] && 
                    $this->similarity_score($word2, $lastname_clean) >= 0.9) {
                    
                    if (!$this->is_initial_ambiguous($word1, $lastname_clean, 'firstname', $user)) {
                        return 0.90;
                    }
                }
                
                // Pattern 3: full lastname + initial firstname (inverted)
                if ($this->similarity_score($word1, $lastname_clean) >= 0.9 && 
                    strlen($word2) === 1 && $word2 === $firstname_clean[0]) {
                    
                    if (!$this->is_initial_ambiguous($lastname_clean, $word2, 'firstname', $user)) {
                        return 0.90;
                    }
                }
                
                // Pattern 4: initial lastname + full firstname (inverted)
                if (strlen($word1) === 1 && $word1 === $lastname_clean[0] && 
                    $this->similarity_score($word2, $firstname_clean) >= 0.9) {
                    
                    if (!$this->is_initial_ambiguous($word1, $firstname_clean, 'lastname', $user)) {
                        return 0.90;
                    }
                }
            }
        }
        
        return 0;
    }
    
    /**
     * Check if initial match would be ambiguous with other users
     *
     * @param string $full_name The full name that matches
     * @param string $initial The initial letter
     * @param string $initial_type Whether initial is for 'firstname' or 'lastname'
     * @param object $current_user Current user being matched
     * @return bool True if ambiguous
     */
    private function is_initial_ambiguous($full_name, $initial, $initial_type, $current_user) {
        $matching_users = 0;
        
        foreach ($this->available_users as $user) {
            if ($user->id === $current_user->id) {
                continue;
            }
            
            $user_names = $this->name_parser->parse_user_names($user);
            
            foreach ($user_names as $names) {
                $user_firstname = $this->normalize_word(strtolower($names['firstname']));
                $user_lastname = $this->normalize_word(strtolower($names['lastname']));
                
                if ($initial_type === 'firstname') {
                    // Check if another user has same lastname + different firstname with same initial
                    if ($this->similarity_score($user_lastname, $full_name) >= 0.9 && 
                        !empty($user_firstname) && $user_firstname[0] === $initial &&
                        $this->similarity_score($user_firstname, $full_name) < 0.9) {
                        $matching_users++;
                    }
                } else {
                    // Check if another user has same firstname + different lastname with same initial
                    if ($this->similarity_score($user_firstname, $full_name) >= 0.9 && 
                        !empty($user_lastname) && $user_lastname[0] === $initial &&
                        $this->similarity_score($user_lastname, $full_name) < 0.9) {
                        $matching_users++;
                    }
                }
                
                if ($matching_users >= 1) {
                    return true; // Ambiguous
                }
            }
        }
        
        return false; // Not ambiguous
    }
    
    /**
     * Calculate pattern-based score (fallback for non-exact matches)
     *
     * @param array $candidate_words Words from Teams ID
     * @param string $firstname_clean Clean firstname
     * @param string $lastname_clean Clean lastname
     * @return float Score (0-1)
     */
    private function calculate_pattern_score($candidate_words, $firstname_clean, $lastname_clean) {
        $max_score = 0;
        
        // Try all possible 2-word combinations
        for ($i = 0; $i < count($candidate_words); $i++) {
            for ($j = 0; $j < count($candidate_words); $j++) {
                if ($i === $j) continue;
                
                $word1 = $candidate_words[$i];
                $word2 = $candidate_words[$j];
                
                // Pattern 1: word1=firstname, word2=lastname
                $score1 = ($this->similarity_score($word1, $firstname_clean) + 
                          $this->similarity_score($word2, $lastname_clean)) / 2;
                
                // Pattern 2: word1=lastname, word2=firstname (order flexible)
                $score2 = ($this->similarity_score($word1, $lastname_clean) + 
                          $this->similarity_score($word2, $firstname_clean)) / 2;
                
                // Take best pattern, but reduce score to prevent false positives
                $pattern_score = max($score1, $score2) * 0.8; // Penalty for non-exact
                
                // Require both words to have reasonable similarity
                $min_word_score = min(
                    max($this->similarity_score($word1, $firstname_clean), $this->similarity_score($word1, $lastname_clean)),
                    max($this->similarity_score($word2, $firstname_clean), $this->similarity_score($word2, $lastname_clean))
                );
                
                // Only consider if both words have some similarity
                if ($min_word_score >= 0.7) {
                    $max_score = max($max_score, $pattern_score);
                }
            }
        }
        
        return $max_score;
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
