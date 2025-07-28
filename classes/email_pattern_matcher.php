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
 * Email pattern matcher for Teams attendance with accent handling
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teamsattendance/classes/name_parser.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/accent_handler.php');

/**
 * Handles email pattern matching with enhanced cognome-first approach and accent handling
 */
class email_pattern_matcher {
    
    /** @var array Available users for matching */
    private $available_users;
    
    /** @var name_parser Name parser instance */
    private $name_parser;
    
    /** @var accent_handler Accent handler instance */
    private $accent_handler;
    
    /** @var float Similarity threshold for email matching */
    const SIMILARITY_THRESHOLD = 0.7;
    
    /** @var array Email patterns prioritized by cognome-first approach */
    private $patterns_config = [
        // Phase 1: Cognome-first patterns (higher priority)
        0 => ['name' => 'cognomenome', 'check_ambiguity' => false, 'priority' => 1],
        1 => ['name' => 'cognome.nome', 'check_ambiguity' => false, 'priority' => 1],
        2 => ['name' => 'cognome_nome', 'check_ambiguity' => false, 'priority' => 1],
        3 => ['name' => 'cognome.n', 'check_ambiguity' => true, 'priority' => 1],
        4 => ['name' => 'cognome-n', 'check_ambiguity' => true, 'priority' => 1],
        5 => ['name' => 'cognome', 'check_ambiguity' => false, 'priority' => 1],
        
        // Phase 2: Nome-first patterns (standard priority)
        6 => ['name' => 'nomecognome', 'check_ambiguity' => false, 'priority' => 2],
        7 => ['name' => 'nome.cognome', 'check_ambiguity' => false, 'priority' => 2],
        8 => ['name' => 'nome_cognome', 'check_ambiguity' => false, 'priority' => 2],
        9 => ['name' => 'n.cognome', 'check_ambiguity' => true, 'priority' => 2],
        10 => ['name' => 'n-cognome', 'check_ambiguity' => true, 'priority' => 2],
        11 => ['name' => 'nome.c', 'check_ambiguity' => true, 'priority' => 2],
        12 => ['name' => 'nome', 'check_ambiguity' => false, 'priority' => 2],
        
        // Phase 3: Special patterns (lower priority)
        13 => ['name' => 'n.c', 'check_ambiguity' => true, 'priority' => 3],
        14 => ['name' => 'ncognome', 'check_ambiguity' => true, 'priority' => 3],
        15 => ['name' => 'nomec', 'check_ambiguity' => true, 'priority' => 3],
        16 => ['name' => 'c.nome', 'check_ambiguity' => true, 'priority' => 3]
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
    }
    
    /**
     * Find best email match for a Teams email address with cognome-first approach
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
        
        // Phase 1: Try cognome-first patterns (priority 1)
        $match = $this->find_match_by_priority($local_part, 1);
        if ($match) {
            return $match;
        }
        
        // Phase 2: Try nome-first patterns (priority 2)
        $match = $this->find_match_by_priority($local_part, 2);
        if ($match) {
            return $match;
        }
        
        // Phase 3: Try special patterns (priority 3)
        $match = $this->find_match_by_priority($local_part, 3);
        if ($match) {
            return $match;
        }
        
        return null;
    }
    
    /**
     * Find match by pattern priority level
     *
     * @param string $local_part Email local part
     * @param int $priority Priority level to search
     * @return object|null Best matching user for this priority level
     */
    private function find_match_by_priority($local_part, $priority) {
        $best_match = null;
        $best_score = 0;
        
        foreach ($this->available_users as $user) {
            $score = $this->calculate_email_similarity_by_priority($local_part, $user, $priority);
            
            if ($score > $best_score && $score >= self::SIMILARITY_THRESHOLD) {
                $best_score = $score;
                $best_match = $user;
            }
        }
        
        return $best_match;
    }
    
    /**
     * Calculate email similarity for specific priority level patterns
     *
     * @param string $local_part Email local part (before @)
     * @param object $user User object to match against
     * @param int $priority Priority level to test
     * @return float Similarity score (0-1)
     */
    private function calculate_email_similarity_by_priority($local_part, $user, $priority) {
        // Parse user names to handle various edge cases
        $user_names = $this->name_parser->parse_user_names($user);
        
        $best_score = 0;
        
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
            
            $scores = array();
            
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
                
                // Apply anti-ambiguity logic for specific patterns
                if ($config['check_ambiguity'] && $similarity > 0.8) {
                    if ($this->check_name_ambiguity($pattern, $i, $user)) {
                        // Don't suggest this match - ambiguous
                        $similarity = 0;
                    }
                }
                
                if ($similarity > 0) {
                    $scores[] = $similarity;
                }
            }
            
            // Get the best score for this name variation at this priority
            if (!empty($scores)) {
                $variation_score = max($scores);
                $best_score = max($best_score, $variation_score);
            }
        }
        
        return $best_score;
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
                
                $details[] = array(
                    'pattern_name' => $config['name'],
                    'pattern_value' => $pattern,
                    'similarity' => $similarity,
                    'priority' => $config['priority'],
                    'ambiguity_check' => $config['check_ambiguity'],
                    'would_suggest' => $similarity >= self::SIMILARITY_THRESHOLD,
                    'accent_normalized' => true
                );
            }
        }
        
        // Sort by priority and similarity
        usort($details, function($a, $b) {
            if ($a['priority'] != $b['priority']) {
                return $a['priority'] - $b['priority'];
            }
            return $b['similarity'] - $a['similarity'];
        });
        
        return $details;
    }
    
    /**
     * Legacy method for backwards compatibility
     *
     * @param string $local_part Email local part (before @)
     * @param object $user User object to match against
     * @return float Similarity score (0-1)
     */
    private function calculate_email_similarity($local_part, $user) {
        // Use new priority-based approach but return single score
        $max_score = 0;
        
        for ($priority = 1; $priority <= 3; $priority++) {
            $score = $this->calculate_email_similarity_by_priority($local_part, $user, $priority);
            $max_score = max($max_score, $score);
            
            // Early exit on high confidence match
            if ($score >= 0.9) {
                break;
            }
        }
        
        return $max_score;
    }
}
