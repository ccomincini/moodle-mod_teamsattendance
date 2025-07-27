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
 * Handles Teams ID pattern matching using word extraction and pattern generation
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
     * Calculate Teams ID similarity using pattern matching
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
            
            // Test all possible combinations of candidate words
            $combinations = $this->generate_word_combinations($candidate_words);
            
            foreach ($combinations as $combo) {
                if (count($combo) < 2) continue;
                
                $scores = [];
                
                // Generate patterns from this word combination
                $patterns = $this->generate_patterns($combo[0], $combo[1]);
                
                // Test patterns against user
                $user_patterns = $this->generate_patterns($firstname_clean, $lastname_clean);
                
                for ($i = 0; $i < count($patterns); $i++) {
                    $pattern = $patterns[$i];
                    $user_pattern = $user_patterns[$i];
                    
                    if (empty($pattern) || empty($user_pattern)) {
                        continue;
                    }
                    
                    $similarity = $this->similarity_score($pattern, $user_pattern);
                    
                    if ($this->patterns_config[$i]['check_ambiguity'] && $similarity > 0.8) {
                        if ($this->check_pattern_ambiguity($user_pattern, $i, $user)) {
                            $similarity = 0;
                        }
                    }
                    
                    if ($similarity > 0) {
                        $scores[] = $similarity;
                    }
                }
                
                if (!empty($scores)) {
                    $combo_score = max($scores);
                    $best_score = max($best_score, $combo_score);
                }
            }
        }
        
        return $best_score;
    }
    
    /**
     * Generate all 2-word combinations from candidate words
     *
     * @param array $words Array of candidate words
     * @return array Array of word combinations
     */
    private function generate_word_combinations($words) {
        $combinations = [];
        
        // Add all possible 2-word combinations
        for ($i = 0; $i < count($words); $i++) {
            for ($j = 0; $j < count($words); $j++) {
                if ($i !== $j) {
                    $combinations[] = [$words[$i], $words[$j]];
                }
            }
        }
        
        return $combinations;
    }
    
    /**
     * Generate all patterns for given names
     *
     * @param string $firstname_clean Cleaned firstname
     * @param string $lastname_clean Cleaned lastname
     * @return array Array of patterns
     */
    private function generate_patterns($firstname_clean, $lastname_clean) {
        if (empty($firstname_clean) || empty($lastname_clean)) {
            return array_fill(0, 10, '');
        }
        
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
                
                $user_patterns = $this->generate_patterns($firstname, $lastname);
                if (isset($user_patterns[$pattern_index]) && $user_patterns[$pattern_index] === $pattern) {
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
