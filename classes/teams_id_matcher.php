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
    const SIMILARITY_THRESHOLD = 0.85; // Increased from 0.75
    
    /** @var array Italian prepositions and articles to handle separately */
    private $italian_particles = ['di', 'da', 'de', 'del', 'della', 'delle', 'dei', 'degli', 'lo', 'la', 'le', 'il'];
    
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
        
        // Clean and normalize the Teams ID
        $cleaned_teams_id = $this->normalize_teams_id($teams_id);
        
        // Phase 1: Search by lastname first (more reliable)
        $match = $this->find_by_lastname_first($cleaned_teams_id);
        if ($match) {
            return $match;
        }
        
        // Phase 2: Search by firstname first (fallback)
        $match = $this->find_by_firstname_first($cleaned_teams_id);
        if ($match) {
            return $match;
        }
        
        return null;
    }
    
    /**
     * Phase 1: Find match by searching lastname first, then firstname/initial
     *
     * @param string $teams_id Cleaned Teams ID
     * @return object|null Best matching user
     */
    private function find_by_lastname_first($teams_id) {
        $best_match = null;
        $best_score = 0;
        
        foreach ($this->available_users as $user) {
            // Get all name variations for this user
            $user_names = $this->name_parser->parse_user_names($user);
            
            foreach ($user_names as $name_variation) {
                $lastname = $this->normalize_name($name_variation['lastname']);
                $firstname = $this->normalize_name($name_variation['firstname']);
                
                // Skip if names are too short
                if (strlen($lastname) < 2 || strlen($firstname) < 1) {
                    continue;
                }
                
                // Try to find lastname with two-phase approach
                $lastname_match = $this->find_lastname_in_text($teams_id, $lastname);
                
                if ($lastname_match['found']) {
                    // Found lastname, now check for firstname or initial
                    $firstname_score = $this->calculate_firstname_compatibility($teams_id, $firstname, $lastname);
                    
                    // Boost score for complete lastname matches vs partial
                    $total_score = $firstname_score * $lastname_match['quality_multiplier'];
                    
                    if ($total_score > $best_score && $total_score >= self::SIMILARITY_THRESHOLD) {
                        $best_score = $total_score;
                        $best_match = $user;
                    }
                }
            }
        }
        
        return $best_match;
    }
    
    /**
     * Phase 2: Find match by searching firstname first, then lastname/initial
     *
     * @param string $teams_id Cleaned Teams ID
     * @return object|null Best matching user
     */
    private function find_by_firstname_first($teams_id) {
        $best_match = null;
        $best_score = 0;
        
        foreach ($this->available_users as $user) {
            // Get all name variations for this user
            $user_names = $this->name_parser->parse_user_names($user);
            
            foreach ($user_names as $name_variation) {
                $lastname = $this->normalize_name($name_variation['lastname']);
                $firstname = $this->normalize_name($name_variation['firstname']);
                
                // Skip if names are too short
                if (strlen($lastname) < 2 || strlen($firstname) < 2) {
                    continue;
                }
                
                // Check if firstname appears as whole word in teams_id
                if ($this->find_whole_word($teams_id, $firstname)) {
                    // Found firstname, now check for lastname or initial
                    $lastname_score = $this->calculate_lastname_compatibility($teams_id, $lastname, $firstname);
                    
                    if ($lastname_score > $best_score && $lastname_score >= self::SIMILARITY_THRESHOLD) {
                        $best_score = $lastname_score;
                        $best_match = $user;
                    }
                }
            }
        }
        
        return $best_match;
    }
    
    /**
     * Find lastname in text with two-phase approach
     *
     * @param string $text Text to search in
     * @param string $lastname Lastname to search for
     * @return array Result with 'found' boolean and 'quality_multiplier'
     */
    private function find_lastname_in_text($text, $lastname) {
        // Phase 1: Try to find complete lastname
        if ($this->find_whole_word($text, $lastname)) {
            return ['found' => true, 'quality_multiplier' => 1.0]; // Perfect match
        }
        
        // Phase 2: Try without particles (di, da, de, etc.)
        $lastname_parts = explode(' ', strtolower($lastname));
        $main_parts = [];
        
        foreach ($lastname_parts as $part) {
            if (!in_array(trim($part), $this->italian_particles) && strlen(trim($part)) > 2) {
                $main_parts[] = trim($part);
            }
        }
        
        // Check if any main part is found
        foreach ($main_parts as $main_part) {
            if ($this->find_whole_word($text, $main_part)) {
                return ['found' => true, 'quality_multiplier' => 0.9]; // Good match but not perfect
            }
        }
        
        return ['found' => false, 'quality_multiplier' => 0];
    }
    
    /**
     * Find whole word in text (not substring)
     *
     * @param string $text Text to search in
     * @param string $word Word to find
     * @return bool True if word found as whole word
     */
    private function find_whole_word($text, $word) {
        if (strlen($word) < 2) {
            return false;
        }
        
        // Use word boundaries to match complete words only
        $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
        return preg_match($pattern, $text);
    }
    
    /**
     * Calculate firstname compatibility when lastname is found
     *
     * @param string $teams_id Teams ID to search in
     * @param string $firstname Normalized firstname to look for
     * @param string $found_lastname Lastname that was already found
     * @return float Compatibility score (0-1)
     */
    private function calculate_firstname_compatibility($teams_id, $firstname, $found_lastname) {
        // Check for full firstname match
        if ($this->find_whole_word($teams_id, $firstname)) {
            return 0.95; // High score for full name match
        }
        
        // Check for initial match (first character)
        $initial = substr($firstname, 0, 1);
        
        // Look for patterns like "J.C." or "J C" or "J" followed by lastname
        $patterns = [
            '/\b' . preg_quote($initial, '/') . '\.?\s*' . preg_quote($found_lastname, '/') . '\b/i',
            '/\b' . preg_quote($found_lastname, '/') . '\s+' . preg_quote($initial, '/') . '\.?\b/i',
            '/\b' . preg_quote($initial, '/') . '\.?\b.*' . preg_quote($found_lastname, '/') . '/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $teams_id)) {
                return 0.90; // Good score for initial match
            }
        }
        
        // Check for compound initials like "J.C."
        if (strlen($firstname) >= 2) {
            $compound_initial = substr($firstname, 0, 1) . '.' . substr($firstname, 1, 1) . '.';
            if (strpos($teams_id, $compound_initial) !== false || 
                strpos($teams_id, substr($firstname, 0, 1) . substr($firstname, 1, 1)) !== false) {
                return 0.90; // Good score for compound initial
            }
        }
        
        return 0.85; // Minimum score when only lastname matches
    }
    
    /**
     * Calculate lastname compatibility when firstname is found
     *
     * @param string $teams_id Teams ID to search in
     * @param string $lastname Normalized lastname to look for
     * @param string $found_firstname Firstname that was already found
     * @return float Compatibility score (0-1)
     */
    private function calculate_lastname_compatibility($teams_id, $lastname, $found_firstname) {
        // Try to find lastname with two-phase approach
        $lastname_match = $this->find_lastname_in_text($teams_id, $lastname);
        
        if ($lastname_match['found']) {
            return 0.95 * $lastname_match['quality_multiplier']; // High score for lastname match
        }
        
        // Check for lastname initial
        $initial = substr($lastname, 0, 1);
        
        // Look for patterns with lastname initial
        $patterns = [
            '/\b' . preg_quote($found_firstname, '/') . '\s+' . preg_quote($initial, '/') . '\.?\b/i',
            '/\b' . preg_quote($initial, '/') . '\.?\s*' . preg_quote($found_firstname, '/') . '\b/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $teams_id)) {
                return 0.88; // Good score for initial match
            }
        }
        
        return 0.85; // Minimum score when only firstname matches
    }
    
    /**
     * Normalize Teams ID by cleaning and removing organizational noise
     *
     * @param string $teams_id Raw Teams ID
     * @return string Normalized Teams ID
     */
    private function normalize_teams_id($teams_id) {
        $normalized = strtolower(trim($teams_id));
        
        // Remove organizational suffixes/prefixes
        $organizational_patterns = [
            '/-\s*(comune|provincia|cm|aipo|uclam|prot\.?\s*civile).*$/i',
            '/\s*-\s*(guest|sindaco|mayor|presidente|direttore).*$/i',
            '/\((guest|ospite)\)$/i',
            '/^(dott\.?|dr\.?|arch\.?|ing\.?|geom\.?|avv\.?|prof\.?|c\.te)\s+/i',
            '/\s+(guest|ospite)$/i'
        ];
        
        foreach ($organizational_patterns as $pattern) {
            $normalized = preg_replace($pattern, '', $normalized);
        }
        
        // Handle accented characters and apostrophes
        $normalized = $this->normalize_name($normalized);
        
        return trim($normalized);
    }
    
    /**
     * Normalize name by handling accents, apostrophes and special characters
     *
     * @param string $name Name to normalize
     * @return string Normalized name
     */
    private function normalize_name($name) {
        $normalized = strtolower(trim($name));
        
        // Handle apostrophe substitutions for accented letters
        $apostrophe_map = [
            "a'" => 'a', "e'" => 'e', "i'" => 'i', "o'" => 'o', "u'" => 'u',
            "A'" => 'a', "E'" => 'e', "I'" => 'i', "O'" => 'o', "U'" => 'u'
        ];
        
        foreach ($apostrophe_map as $apostrophe => $replacement) {
            $normalized = str_replace($apostrophe, $replacement, $normalized);
        }
        
        // Remove accents from letters
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
        
        $normalized = strtr($normalized, $accent_map);
        
        // Remove punctuation but keep spaces and basic characters
        $normalized = preg_replace('/[^\w\s]/', ' ', $normalized);
        
        // Normalize whitespace
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));
        
        return $normalized;
    }
    
    /**
     * Get detailed matching information for debugging
     *
     * @param string $teams_id Teams ID to analyze
     * @param object $user User to compare against
     * @return array Detailed matching information
     */
    public function get_matching_details($teams_id, $user) {
        $cleaned_teams_id = $this->normalize_teams_id($teams_id);
        $user_names = $this->name_parser->parse_user_names($user);
        
        $details = array(
            'teams_id' => $teams_id,
            'cleaned_teams_id' => $cleaned_teams_id,
            'user_fullname' => $user->firstname . ' ' . $user->lastname,
            'user_parsed' => $user_names,
            'lastname_first_results' => array(),
            'firstname_first_results' => array(),
            'best_score' => 0
        );
        
        // Test lastname-first approach
        foreach ($user_names as $name_variation) {
            $lastname = $this->normalize_name($name_variation['lastname']);
            $firstname = $this->normalize_name($name_variation['firstname']);
            
            $lastname_match = $this->find_lastname_in_text($cleaned_teams_id, $lastname);
            if ($lastname_match['found']) {
                $score = $this->calculate_firstname_compatibility($cleaned_teams_id, $firstname, $lastname);
                $total_score = $score * $lastname_match['quality_multiplier'];
                
                $details['lastname_first_results'][] = array(
                    'lastname' => $lastname,
                    'firstname' => $firstname,
                    'found_lastname' => true,
                    'lastname_quality' => $lastname_match['quality_multiplier'],
                    'score' => $score,
                    'total_score' => $total_score
                );
                $details['best_score'] = max($details['best_score'], $total_score);
            }
        }
        
        // Test firstname-first approach
        foreach ($user_names as $name_variation) {
            $lastname = $this->normalize_name($name_variation['lastname']);
            $firstname = $this->normalize_name($name_variation['firstname']);
            
            if ($this->find_whole_word($cleaned_teams_id, $firstname)) {
                $score = $this->calculate_lastname_compatibility($cleaned_teams_id, $lastname, $firstname);
                $details['firstname_first_results'][] = array(
                    'lastname' => $lastname,
                    'firstname' => $firstname,
                    'found_firstname' => true,
                    'score' => $score
                );
                $details['best_score'] = max($details['best_score'], $score);
            }
        }
        
        return $details;
    }
    
    // Legacy methods maintained for compatibility
    private function calculate_teams_similarity($teams_names, $user) {
        $cleaned_teams_id = '';
        foreach ($teams_names as $name) {
            $cleaned_teams_id .= ' ' . $name['firstname'] . ' ' . $name['lastname'];
        }
        $cleaned_teams_id = $this->normalize_teams_id($cleaned_teams_id);
        
        // Use new matching logic
        $match = $this->find_by_lastname_first($cleaned_teams_id);
        if ($match && $match->id == $user->id) {
            return 0.9;
        }
        
        $match = $this->find_by_firstname_first($cleaned_teams_id);
        if ($match && $match->id == $user->id) {
            return 0.8;
        }
        
        return 0;
    }
}
