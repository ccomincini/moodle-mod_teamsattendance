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
 * Email pattern matcher for Teams attendance with accent handling and uniqueness control
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teamsattendance/classes/name_parser.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/accent_handler.php');

/**
 * Handles email pattern matching with enhanced cognome-first approach, accent handling and uniqueness control
 */
class email_pattern_matcher {
    
    /** @var array Available users for matching */
    private $available_users;
    
    /** @var name_parser Name parser instance */
    private $name_parser;
    
    /** @var accent_handler Accent handler instance */
    private $accent_handler;
    
    /** @var float Similarity threshold for email matching - raised to reduce false positives */
    const SIMILARITY_THRESHOLD = 0.85;
    
    /** @var float Confidence threshold for ambiguity check */
    const CONFIDENCE_THRESHOLD = 0.9;
    
    /** @var float Minimum score difference to prefer one match over another */
    const SCORE_DIFFERENCE_THRESHOLD = 0.15;
    
    /** @var array Cache for already processed matches to ensure uniqueness */
    private $match_cache = array();
    
    /** @var array Email patterns prioritized by cognome-first approach */
    private $patterns_config = [
        // Phase 1: Cognome-first patterns (higher priority)
        0 => ['name' => 'cognomenome', 'check_ambiguity' => false, 'priority' => 1, 'weight' => 1.0],
        1 => ['name' => 'cognome.nome', 'check_ambiguity' => false, 'priority' => 1, 'weight' => 1.0],
        2 => ['name' => 'cognome_nome', 'check_ambiguity' => false, 'priority' => 1, 'weight' => 1.0],
        3 => ['name' => 'cognome.n', 'check_ambiguity' => true, 'priority' => 1, 'weight' => 0.9],
        4 => ['name' => 'cognome-n', 'check_ambiguity' => true, 'priority' => 1, 'weight' => 0.9],
        5 => ['name' => 'cognome', 'check_ambiguity' => true, 'priority' => 1, 'weight' => 0.8],
        
        // Phase 2: Nome-first patterns (standard priority)
        6 => ['name' => 'nomecognome', 'check_ambiguity' => false, 'priority' => 2, 'weight' => 1.0],
        7 => ['name' => 'nome.cognome', 'check_ambiguity' => false, 'priority' => 2, 'weight' => 1.0],
        8 => ['name' => 'nome_cognome', 'check_ambiguity' => false, 'priority' => 2, 'weight' => 1.0],
        9 => ['name' => 'n.cognome', 'check_ambiguity' => true, 'priority' => 2, 'weight' => 0.9],
        10 => ['name' => 'n-cognome', 'check_ambiguity' => true, 'priority' => 2, 'weight' => 0.9],
        11 => ['name' => 'nome.c', 'check_ambiguity' => true, 'priority' => 2, 'weight' => 0.8],
        12 => ['name' => 'nome', 'check_ambiguity' => true, 'priority' => 2, 'weight' => 0.7],
        
        // Phase 3: Special patterns (lower priority - more restrictive)
        13 => ['name' => 'n.c', 'check_ambiguity' => true, 'priority' => 3, 'weight' => 0.6],
        14 => ['name' => 'ncognome', 'check_ambiguity' => true, 'priority' => 3, 'weight' => 0.7],
        15 => ['name' => 'nomec', 'check_ambiguity' => true, 'priority' => 3, 'weight' => 0.7],
        16 => ['name' => 'c.nome', 'check_ambiguity' => true, 'priority' => 3, 'weight' => 0.6]
    ];
    
    /**
     * Constructor
     *
     * @param array $available_users Array of available users
     */
    public function __construct($available_users) {
        $this->available_users = $available_users;
        $this->name_parser = new name_parser();
        $this->accent_handler = new accent_handler();
        $this->match_cache = array();
    }
    
    /**
     * Find best email match for a Teams email address with uniqueness control
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
        
        // Check if we already processed this email
        if (isset($this->match_cache[$teams_email])) {
            return $this->match_cache[$teams_email];
        }
        
        // Collect all potential matches with scores
        $all_candidates = $this->collect_all_candidates($local_part);
        
        // Apply uniqueness control
        $best_match = $this->apply_uniqueness_control($all_candidates, $teams_email);
        
        // Cache the result
        $this->match_cache[$teams_email] = $best_match;
        
        return $best_match;
    }
    
    /**
     * Collect all potential matches across all priority levels
     *
     * @param string $local_part Email local part
     * @return array Array of candidate matches with scores and metadata
     */
    private function collect_all_candidates($local_part) {
        $candidates = array();
        
        foreach ($this->available_users as $user) {
            // Calculate scores for all priority levels
            for ($priority = 1; $priority <= 3; $priority++) {
                $score_data = $this->calculate_detailed_similarity($local_part, $user, $priority);
                
                if ($score_data['score'] >= self::SIMILARITY_THRESHOLD) {
                    $candidates[] = array(
                        'user' => $user,
                        'score' => $score_data['score'],
                        'priority' => $priority,
                        'pattern_used' => $score_data['pattern_used'],
                        'is_ambiguous' => $score_data['is_ambiguous'],
                        'confidence' => $score_data['confidence']
                    );
                }
            }
        }
        
        // Sort by priority first, then by score
        usort($candidates, function($a, $b) {
            if ($a['priority'] != $b['priority']) {
                return $a['priority'] - $b['priority'];
            }
            return $b['score'] - $a['score'];
        });
        
        return $candidates;
    }
    
    /**
     * Apply uniqueness control to prevent false positives
     *
     * @param array $candidates All candidate matches
     * @param string $teams_email Original teams email for logging
     * @return object|null Best unique match
     */
    private function apply_uniqueness_control($candidates, $teams_email) {
        if (empty($candidates)) {
            return null;
        }
        
        // If only one candidate, check if it's confident enough
        if (count($candidates) === 1) {
            $candidate = $candidates[0];
            if ($candidate['is_ambiguous'] && $candidate['confidence'] < self::CONFIDENCE_THRESHOLD) {
                // Single match but low confidence and potentially ambiguous
                return null;
            }
            return $candidate['user'];
        }
        
        // Multiple candidates - apply disambiguation logic
        $best_candidate = $candidates[0];
        $second_best = $candidates[1];
        
        // Check if the best candidate is significantly better than the second
        $score_difference = $best_candidate['score'] - $second_best['score'];
        
        // If both candidates are of the same priority level
        if ($best_candidate['priority'] === $second_best['priority']) {
            // Require significant score difference
            if ($score_difference < self::SCORE_DIFFERENCE_THRESHOLD) {
                // Too close - ambiguous match
                return null;
            }
        }
        
        // Check for name ambiguity on the best candidate
        if ($best_candidate['is_ambiguous']) {
            if ($this->check_advanced_name_ambiguity($candidates[0], $candidates)) {
                return null;
            }
        }
        
        // Additional check: ensure the user hasn't been matched by another email
        if ($this->is_user_already_matched($best_candidate['user'], $teams_email)) {
            return null;
        }
        
        return $best_candidate['user'];
    }
    
    /**
     * Calculate detailed similarity with enhanced metadata
     *
     * @param string $local_part Email local part (before @)
     * @param object $user User object to match against
     * @param int $priority Priority level to test
     * @return array Detailed similarity data
     */
    private function calculate_detailed_similarity($local_part, $user, $priority) {
        // Parse user names to handle various edge cases
        $user_names = $this->name_parser->parse_user_names($user);
        
        $best_score = 0;
        $best_pattern = '';
        $is_ambiguous = false;
        $confidence = 0;
        
        // Test against all parsed name variations
        foreach ($user_names as $names) {
            // Apply accent normalization to user names
            $firstname = $this->accent_handler->normalize_text($names['firstname']);
            $lastname = $this->accent_handler->normalize_text($names['lastname']);
            
            // Apply accent normalization to email part
            $local_part_normalized = $this->accent_handler->normalize_text($local_part);
            
            // Remove non-alphanumeric characters and normalize
            $local_part_clean = preg_replace('/[^a-z0-9]/', '', strtolower($local_part_normalized));
            $firstname_clean = preg_replace('/[^a-z0-9]/', '', strtolower($firstname));
            $lastname_clean = preg_replace('/[^a-z0-9]/', '', strtolower($lastname));
            
            if (empty($firstname_clean) || empty($lastname_clean)) {
                continue;
            }
            
            // Test only patterns for this priority level
            foreach ($this->patterns_config as $i => $config) {
                if ($config['priority'] !== $priority) {
                    continue;
                }
                
                $pattern = $this->generate_pattern_by_index($i, $firstname_clean, $lastname_clean);
                
                if (empty($pattern)) {
                    continue;
                }
                
                // Calculate basic similarity
                $similarity = $this->similarity_score($local_part_clean, $pattern);
                
                // Apply pattern weight
                $weighted_score = $similarity * $config['weight'];
                
                if ($weighted_score > $best_score) {
                    $best_score = $weighted_score;
                    $best_pattern = $config['name'];
                    $is_ambiguous = $config['check_ambiguity'];
                    
                    // Calculate confidence based on pattern type and score
                    $confidence = $this->calculate_confidence($similarity, $config);
                }
            }
        }
        
        return array(
            'score' => $best_score,
            'pattern_used' => $best_pattern,
            'is_ambiguous' => $is_ambiguous,
            'confidence' => $confidence
        );
    }
    
    /**
     * Calculate confidence score for a match
     *
     * @param float $similarity Basic similarity score
     * @param array $config Pattern configuration
     * @return float Confidence score (0-1)
     */
    private function calculate_confidence($similarity, $config) {
        $base_confidence = $similarity;
        
        // Adjust based on pattern characteristics
        if (!$config['check_ambiguity']) {
            // Non-ambiguous patterns get higher confidence
            $base_confidence *= 1.1;
        }
        
        // Adjust based on pattern weight
        $base_confidence *= $config['weight'];
        
        // Ensure confidence is within bounds
        return min(1.0, max(0.0, $base_confidence));
    }
    
    /**
     * Advanced name ambiguity check with multiple candidates context
     *
     * @param array $candidate Primary candidate to check
     * @param array $all_candidates All candidates for context
     * @return bool True if ambiguous (should reject), false if safe
     */
    private function check_advanced_name_ambiguity($candidate, $all_candidates) {
        // Count how many users would match with similar patterns and scores
        $similar_matches = 0;
        $threshold = $candidate['score'] - 0.1; // Allow some tolerance
        
        foreach ($all_candidates as $other_candidate) {
            if ($other_candidate['user']->id === $candidate['user']->id) {
                continue;
            }
            
            if ($other_candidate['score'] >= $threshold && 
                $other_candidate['pattern_used'] === $candidate['pattern_used']) {
                $similar_matches++;
            }
        }
        
        // If we have multiple users with very similar scores and same pattern type
        return $similar_matches >= 1;
    }
    
    /**
     * Check if a user has already been matched by another email
     *
     * @param object $user User to check
     * @param string $current_email Current email being processed
     * @return bool True if user already matched elsewhere
     */
    private function is_user_already_matched($user, $current_email) {
        foreach ($this->match_cache as $email => $matched_user) {
            if ($email !== $current_email && 
                $matched_user && 
                $matched_user->id === $user->id) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Find match by pattern priority level (legacy method - kept for compatibility)
     *
     * @param string $local_part Email local part
     * @param int $priority Priority level to search
     * @return object|null Best matching user for this priority level
     */
    private function find_match_by_priority($local_part, $priority) {
        $candidates = array();
        
        foreach ($this->available_users as $user) {
            $score_data = $this->calculate_detailed_similarity($local_part, $user, $priority);
            
            if ($score_data['score'] >= self::SIMILARITY_THRESHOLD) {
                $candidates[] = array(
                    'user' => $user,
                    'score' => $score_data['score'],
                    'is_ambiguous' => $score_data['is_ambiguous'],
                    'confidence' => $score_data['confidence']
                );
            }
        }
        
        if (empty($candidates)) {
            return null;
        }
        
        // Sort by score
        usort($candidates, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        $best = $candidates[0];
        
        // Apply ambiguity check for single priority level
        if ($best['is_ambiguous'] && $best['confidence'] < self::CONFIDENCE_THRESHOLD) {
            return null;
        }
        
        return $best['user'];
    }
    
    /**
     * Generate specific pattern by index with enhanced cognome-first patterns
     *
     * @param int $pattern_index Pattern index
     * @param string $firstname_clean Cleaned firstname
     * @param string $lastname_clean Cleaned lastname
     * @return string Generated pattern
     */
    private function generate_pattern_by_index($pattern_index, $firstname_clean, $lastname_clean) {
        switch ($pattern_index) {
            // Cognome-first patterns (Priority 1)
            case 0: // cognomenome
                return $lastname_clean . $firstname_clean;
            case 1: // cognome.nome 
                return $lastname_clean . $firstname_clean; // Separator handled in matching
            case 2: // cognome_nome
                return $lastname_clean . $firstname_clean;
            case 3: // cognome.n (lastname + initial)
                return $lastname_clean . $firstname_clean[0];
            case 4: // cognome-n 
                return $lastname_clean . $firstname_clean[0];
            case 5: // cognome (solo cognome)
                return $lastname_clean;
                
            // Nome-first patterns (Priority 2)
            case 6: // nomecognome
                return $firstname_clean . $lastname_clean;
            case 7: // nome.cognome
                return $firstname_clean . $lastname_clean;
            case 8: // nome_cognome
                return $firstname_clean . $lastname_clean;
            case 9: // n.cognome (initial + lastname)
                return $firstname_clean[0] . $lastname_clean;
            case 10: // n-cognome
                return $firstname_clean[0] . $lastname_clean;
            case 11: // nome.c (firstname + initial)
                return $firstname_clean . $lastname_clean[0];
            case 12: // nome (solo nome)
                return $firstname_clean;
                
            // Special patterns (Priority 3)
            case 13: // n.c (initials)
                return $firstname_clean[0] . $lastname_clean[0];
            case 14: // ncognome (initial + lastname without separator)
                return substr($firstname_clean, 0, 1) . $lastname_clean;
            case 15: // nomec (firstname + lastname initial)
                return $firstname_clean . substr($lastname_clean, 0, 1);
            case 16: // c.nome (lastname initial + firstname)
                return $lastname_clean[0] . $firstname_clean;
                
            default:
                return '';
        }
    }
    
    /**
     * Enhanced similarity scoring with separator tolerance
     *
     * @param string $local_part Email local part
     * @param string $pattern Generated pattern
     * @return float Similarity score (0-1)
     */
    private function similarity_score($local_part, $pattern) {
        // Direct match
        if ($local_part === $pattern) {
            return 1.0;
        }
        
        // Try with common separators
        $local_variants = [
            $local_part,
            str_replace(['.', '-', '_'], '', $local_part), // Remove all separators
            str_replace(['.', '-', '_'], '.', $local_part), // Normalize to dots
            str_replace(['.', '-', '_'], '_', $local_part)  // Normalize to underscores
        ];
        
        $best_similarity = 0;
        
        foreach ($local_variants as $variant) {
            $max_len = max(strlen($variant), strlen($pattern));
            if ($max_len == 0) {
                $similarity = 1.0;
            } else {
                $distance = levenshtein($variant, $pattern);
                $similarity = 1 - ($distance / $max_len);
            }
            $best_similarity = max($best_similarity, $similarity);
        }
        
        return $best_similarity;
    }
    
    /**
     * Legacy check if a pattern would match multiple users (kept for compatibility)
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
                // Apply accent normalization
                $firstname = $this->accent_handler->normalize_text($names['firstname']);
                $lastname = $this->accent_handler->normalize_text($names['lastname']);
                
                $firstname = preg_replace('/[^a-z0-9]/', '', strtolower($firstname));
                $lastname = preg_replace('/[^a-z0-9]/', '', strtolower($lastname));
                
                if (empty($firstname) || empty($lastname)) {
                    continue;
                }
                
                // Generate the same pattern for this user
                $user_pattern = $this->generate_pattern_by_index($pattern_index, $firstname, $lastname);
                
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
     * Get pattern statistics for debugging/monitoring with enhanced details
     *
     * @param string $local_part Email local part
     * @param object $user User object
     * @return array Pattern matching details
     */
    public function get_pattern_details($local_part, $user) {
        $user_names = $this->name_parser->parse_user_names($user);
        $details = array();
        
        foreach ($user_names as $names) {
            // Apply accent normalization
            $firstname = $this->accent_handler->normalize_text($names['firstname']);
            $lastname = $this->accent_handler->normalize_text($names['lastname']);
            $local_part_normalized = $this->accent_handler->normalize_text($local_part);
            
            $firstname_clean = preg_replace('/[^a-z0-9]/', '', strtolower($firstname));
            $lastname_clean = preg_replace('/[^a-z0-9]/', '', strtolower($lastname));
            $local_part_clean = preg_replace('/[^a-z0-9]/', '', strtolower($local_part_normalized));
            
            if (empty($firstname_clean) || empty($lastname_clean)) {
                continue;
            }
            
            foreach ($this->patterns_config as $i => $config) {
                $pattern = $this->generate_pattern_by_index($i, $firstname_clean, $lastname_clean);
                $similarity = $this->similarity_score($local_part_clean, $pattern);
                $weighted_score = $similarity * $config['weight'];
                
                $details[] = array(
                    'pattern_name' => $config['name'],
                    'pattern_value' => $pattern,
                    'similarity' => $similarity,
                    'weighted_score' => $weighted_score,
                    'priority' => $config['priority'],
                    'weight' => $config['weight'],
                    'ambiguity_check' => $config['check_ambiguity'],
                    'would_suggest' => $weighted_score >= self::SIMILARITY_THRESHOLD,
                    'accent_normalized' => true,
                    'confidence' => $this->calculate_confidence($similarity, $config)
                );
            }
        }
        
        // Sort by priority and weighted score
        usort($details, function($a, $b) {
            if ($a['priority'] != $b['priority']) {
                return $a['priority'] - $b['priority'];
            }
            return $b['weighted_score'] - $a['weighted_score'];
        });
        
        return $details;
    }
    
    /**
     * Get matching statistics for debugging
     *
     * @return array Statistics about current matching state
     */
    public function get_matching_statistics() {
        return array(
            'cached_matches' => count($this->match_cache),
            'similarity_threshold' => self::SIMILARITY_THRESHOLD,
            'confidence_threshold' => self::CONFIDENCE_THRESHOLD,
            'score_difference_threshold' => self::SCORE_DIFFERENCE_THRESHOLD,
            'total_patterns' => count($this->patterns_config),
            'patterns_by_priority' => array(
                1 => count(array_filter($this->patterns_config, function($p) { return $p['priority'] === 1; })),
                2 => count(array_filter($this->patterns_config, function($p) { return $p['priority'] === 2; })),
                3 => count(array_filter($this->patterns_config, function($p) { return $p['priority'] === 3; }))
            )
        );
    }
    
    /**
     * Clear match cache (useful for testing)
     */
    public function clear_cache() {
        $this->match_cache = array();
    }
    
    /**
     * Legacy method for backwards compatibility
     *
     * @param string $local_part Email local part (before @)
     * @param object $user User object to match against
     * @return float Similarity score (0-1)
     */
    private function calculate_email_similarity($local_part, $user) {
        // Use new detailed approach but return single score
        $max_score = 0;
        
        for ($priority = 1; $priority <= 3; $priority++) {
            $score_data = $this->calculate_detailed_similarity($local_part, $user, $priority);
            $max_score = max($max_score, $score_data['score']);
            
            // Early exit on high confidence match
            if ($score_data['score'] >= 0.9) {
                break;
            }
        }
        
        return $max_score;
    }
}
