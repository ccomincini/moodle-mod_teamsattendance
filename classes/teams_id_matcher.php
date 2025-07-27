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
 * Handles Teams ID pattern matching using improved word extraction and pattern generation
 */
class teams_id_matcher {
    
    /** @var array Available users for matching */
    private $available_users;
    
    /** @var name_parser Name parser instance */
    private $name_parser;
    
    /** @var float Similarity threshold for matching - increased to reduce false positives */
    const SIMILARITY_THRESHOLD = 0.85;
    
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
            if (strlen($word) < 2) {
                continue;
            }
            
            // Skip common noise words
            if ($this->is_noise_word($word)) {
                continue;
            }
            
            // Clean word (remove dots, keep letters and numbers)
            $clean_word = preg_replace('/[^a-z0-9]/', '', $word);
            
            // Keep words that look like names (at least 2 chars, not all numbers)
            if (strlen($clean_word) >= 2 && !is_numeric($clean_word)) {
                $candidates[] = $clean_word;
            }
        }
        
        return array_unique($candidates);
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
     * Calculate Teams ID similarity using improved pattern matching
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
            
            $firstname_clean = preg_replace('/[^a-z0-9]/', '', $firstname);
            $lastname_clean = preg_replace('/[^a-z0-9]/', '', $lastname);
            
            if (empty($firstname_clean) || empty($lastname_clean)) {
                continue;
            }
            
            // Try exact word matches first (highest priority)
            $exact_score = $this->calculate_exact_word_match($candidate_words, $firstname_clean, $lastname_clean);
            if ($exact_score > 0) {
                $best_score = max($best_score, $exact_score);
                continue;
            }
            
            // Try pattern combinations
            $pattern_score = $this->calculate_pattern_score($candidate_words, $firstname_clean, $lastname_clean);
            $best_score = max($best_score, $pattern_score);
        }
        
        return $best_score;
    }
    
    /**
     * Calculate exact word match score (both first and last name must match)
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
        
        // Both names must be found for high score
        if ($firstname_found && $lastname_found) {
            return 0.95; // Very high score for exact matches
        }
        
        return 0; // No score if not both names found
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
                
                // Pattern 2: word1=lastname, word2=firstname
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
