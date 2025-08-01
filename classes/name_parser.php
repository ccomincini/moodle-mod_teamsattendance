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
 * Name parser utility for Teams attendance
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Handles complex name parsing scenarios for user matching
 */
class name_parser {
    
    /**
     * Clean Teams ID by removing noise (titles, organizations, etc.)
     *
     * @param string $teams_id Raw Teams user ID
     * @return string Cleaned text containing mainly names
     */
    private function clean_teams_id($teams_id) {
        $cleaned = strtolower(trim($teams_id));
        
        // Skip if it's an email address
        if (filter_var($teams_id, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        
        // Remove professional titles and prefixes
        $titles = [
            'dott\.?', 'dr\.?', 'arch\.?', 'ing\.?', 'geom\.?', 'avv\.?', 'prof\.?',
            'c\.te', 'sindaco', 'mayor', 'presidente', 'direttore'
        ];
        foreach ($titles as $title) {
            $cleaned = preg_replace('/\b' . $title . '\s+/i', '', $cleaned);
            $cleaned = preg_replace('/\s+' . $title . '\b/i', '', $cleaned);
        }
        
        // Remove organizational information
        $organizations = [
            'comune di [^,\-\n]+',
            'comune [^,\-\n]+',
            'provincia di [^,\-\n]+',
            'provincia [^,\-\n]+',
            'aipo[^,\-\n]*',
            'utc[^,\-\n]*',
            'ufficiotecnico[^,\-\n]*',
            'ufficio tecnico[^,\-\n]*',
            'protezione civile[^,\-\n]*',
            'prot\. civile[^,\-\n]*',
            'polizia locale[^,\-\n]*',
            'p\.l\.[^,\-\n]*',
            'cm [^,\-\n]*',
            'comunità montana[^,\-\n]*',
            'uclam[^,\-\n]*'
        ];
        foreach ($organizations as $org) {
            $cleaned = preg_replace('/' . $org . '/i', '', $cleaned);
        }
        
        // Remove separator characters and punctuation
        $cleaned = preg_replace('/[,\-()_\.;|]+/', ' ', $cleaned);
        
        // Remove generic words that don't help identification
        $generic_words = [
            'guest', 'meeting', 'tecnico', 'comunale', 'sindaco', 'presidente',
            'user', 'utente', 'partecipante', 'participant'
        ];
        foreach ($generic_words as $word) {
            $cleaned = preg_replace('/\b' . $word . '\b/i', '', $cleaned);
        }
        
        // Remove multiple spaces and trim
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = trim($cleaned);
        
        return $cleaned;
    }
    
    /**
     * Parse Teams display name into possible firstname/lastname combinations
     *
     * @param string $teams_name Teams display name
     * @return array Array of parsed name combinations
     */
    public function parse_teams_name($teams_name) {
        $names = array();
        
        // First, try to clean the Teams ID to remove noise
        $cleaned_name = $this->clean_teams_id($teams_name);
        
        // If cleaning resulted in empty string, fall back to original
        if (empty($cleaned_name)) {
            $clean_name = preg_replace('/[,;|]/', ' ', $teams_name);
            $clean_name = preg_replace('/\s+/', ' ', trim($clean_name));
        } else {
            $clean_name = $cleaned_name;
        }
        
        if (empty($clean_name)) {
            return $names;
        }
        
	$parts = explode(' ', $clean_name);
        $parts = array_filter($parts, function($part) {
            return strlen(trim($part)) >= 2; // Filter out single characters and empty parts
        });
        $parts = array_values($parts); // Re-index array to prevent gaps
        
        if (count($parts) >= 2) {
            // Try "LastName, FirstName" format (comma-separated) with original input
            if (strpos($teams_name, ',') !== false) {
                $comma_parts = array_map('trim', explode(',', $teams_name));
                if (count($comma_parts) >= 2 && strlen($comma_parts[0]) >= 2 && strlen($comma_parts[1]) >= 2) {
                    $names[] = array(
                        'firstname' => $comma_parts[1],
                        'lastname' => $comma_parts[0],
                        'source' => 'comma_separated_cleaned'
                    );
                }
            }
            
            // Try "FirstName LastName" format with cleaned data
            $names[] = array(
                'firstname' => $parts[0],
                'lastname' => $parts[count($parts) - 1],
                'source' => 'first_last_cleaned'
            );
            
            // Try "LastName FirstName" format (inverted) with cleaned data
            $names[] = array(
                'firstname' => $parts[count($parts) - 1],
                'lastname' => $parts[0],
                'source' => 'last_first_cleaned'
            );
            
	    // If more than 2 parts, try compound first name
            if (count($parts) > 2) {
                if (isset($parts[0]) && isset($parts[1])) {
                    $names[] = array(
                        'firstname' => $parts[0] . ' ' . $parts[1],
                        'lastname' => $parts[count($parts) - 1],
                        'source' => 'compound_first_cleaned'
                    );
                }
                
                // Try compound last name
                if (isset($parts[0])) {
                    $names[] = array(
                        'firstname' => $parts[0],
                        'lastname' => implode(' ', array_slice($parts, 1)),
                        'source' => 'compound_last_cleaned'
                    );
                }
                
                // Try middle combinations for cases like "Mario Rossi Bianchi"
                if (count($parts) >= 3 && isset($parts[0]) && isset($parts[1])) {
                    $names[] = array(
                        'firstname' => $parts[0],
                        'lastname' => $parts[1],
                        'source' => 'middle_name_cleaned'
                    );
                }
            }
        }
        
        return $this->filter_valid_names($names);
    }
    
    /**
     * Parse user names to handle various edge cases and malformed data
     *
     * @param object $user The user object
     * @return array Array of name variations to test
     */
    public function parse_user_names($user) {
        $variations = array();
        
        $original_firstname = trim($user->firstname);
        $original_lastname = trim($user->lastname);
        
        // Variation 1: Original names as-is
        $variations[] = array(
            'firstname' => $original_firstname,
            'lastname' => $original_lastname,
            'source' => 'original'
        );
        
        // Variation 2: Handle inverted names (cognome in firstname field, nome in lastname field)
        if (!empty($original_firstname) && !empty($original_lastname)) {
            $variations[] = array(
                'firstname' => $original_lastname,
                'lastname' => $original_firstname,
                'source' => 'inverted'
            );
        }
        
        // Variation 3: Handle duplicated names like "Alberto Deimann Deimann"
        $variations = array_merge($variations, $this->parse_duplicated_names($original_firstname, $original_lastname));
        
        // Variation 4: Handle case where both fields contain "nome cognome"
        $variations = array_merge($variations, $this->parse_identical_fields($original_firstname, $original_lastname));
        
        // Variation 5: Handle case where lastname contains multiple words and might be duplicated
        $variations = array_merge($variations, $this->parse_compound_names($original_firstname, $original_lastname));
        
        return $this->filter_valid_names($variations);
    }
    
    /**
     * Handle duplicated names in firstname field
     *
     * @param string $firstname Original firstname
     * @param string $lastname Original lastname
     * @return array Array of parsed variations
     */
    private function parse_duplicated_names($firstname, $lastname) {
        $variations = array();
        
        if (strpos($firstname, ' ') !== false) {
            $firstname_parts = explode(' ', $firstname);
            
            // Check if lastname appears in firstname
            if (count($firstname_parts) >= 2) {
                $last_part = end($firstname_parts);
                
                // If the last part of firstname matches lastname, extract the real firstname
                if (strcasecmp($last_part, $lastname) === 0) {
                    $real_firstname = implode(' ', array_slice($firstname_parts, 0, -1));
                    $variations[] = array(
                        'firstname' => $real_firstname,
                        'lastname' => $lastname,
                        'source' => 'duplicated_lastname_removed'
                    );
                }
                
                // Also try first word as firstname
                $variations[] = array(
                    'firstname' => $firstname_parts[0],
                    'lastname' => $lastname,
                    'source' => 'first_word_only'
                );
            }
        }
        
        return $variations;
    }
    
    /**
     * Handle case where both fields contain identical "nome cognome"
     *
     * @param string $firstname Original firstname
     * @param string $lastname Original lastname
     * @return array Array of parsed variations
     */
    private function parse_identical_fields($firstname, $lastname) {
        $variations = array();
        
        if (strpos($firstname, ' ') !== false && strpos($lastname, ' ') !== false) {
            $firstname_parts = explode(' ', $firstname);
            $lastname_parts = explode(' ', $lastname);
            
            // If they're identical, extract nome and cognome
            if (count($firstname_parts) >= 2 && strcasecmp($firstname, $lastname) === 0) {
                $variations[] = array(
                    'firstname' => $firstname_parts[0],
                    'lastname' => $firstname_parts[1],
                    'source' => 'identical_fields_parsed'
                );
            }
        }
        
        return $variations;
    }
    
    /**
     * Handle compound names and duplications in lastname field
     *
     * @param string $firstname Original firstname
     * @param string $lastname Original lastname
     * @return array Array of parsed variations
     */
    private function parse_compound_names($firstname, $lastname) {
        $variations = array();
        
        if (strpos($lastname, ' ') !== false) {
            $lastname_parts = explode(' ', $lastname);
            
            // Check for duplication in lastname
            if (count($lastname_parts) >= 2) {
                $unique_parts = array_unique($lastname_parts);
                if (count($unique_parts) < count($lastname_parts)) {
                    // There's duplication, use first occurrence
                    $variations[] = array(
                        'firstname' => $firstname,
                        'lastname' => $unique_parts[0],
                        'source' => 'lastname_deduplication'
                    );
                }
                
                // Try first part of compound lastname
                $variations[] = array(
                    'firstname' => $firstname,
                    'lastname' => $lastname_parts[0],
                    'source' => 'compound_lastname_first'
                );
            }
        }
        
        return $variations;
    }
    
    /**
     * Filter out empty or invalid name variations
     *
     * @param array $variations Array of name variations
     * @return array Filtered variations
     */
    private function filter_valid_names($variations) {
        $filtered_variations = array();
        
        foreach ($variations as $variation) {
            if (!empty($variation['firstname']) && !empty($variation['lastname'])) {
                // Remove extra whitespace
                $variation['firstname'] = trim($variation['firstname']);
                $variation['lastname'] = trim($variation['lastname']);
                
                // Skip if names are too short (likely initials only)
                if (strlen($variation['firstname']) >= 2 && strlen($variation['lastname']) >= 2) {
                    $filtered_variations[] = $variation;
                }
            }
        }
        
        // Remove duplicates based on firstname/lastname combination
        $unique_variations = array();
        $seen_combinations = array();
        
        foreach ($filtered_variations as $variation) {
            $key = strtolower($variation['firstname'] . '|' . $variation['lastname']);
            if (!in_array($key, $seen_combinations)) {
                $seen_combinations[] = $key;
                $unique_variations[] = $variation;
            }
        }
        
        return $unique_variations;
    }
    
    /**
     * Normalize name for comparison (remove accents, convert to lowercase, etc.)
     *
     * @param string $name Name to normalize
     * @return string Normalized name
     */
    public function normalize_name($name) {
        // Convert to lowercase
        $normalized = strtolower($name);
        
        // Remove accents and special characters
        $normalized = $this->remove_accents($normalized);
        
        // Remove non-alphabetic characters except spaces and hyphens
        $normalized = preg_replace('/[^a-z\s\-]/', '', $normalized);
        
        // Normalize whitespace
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));
        
        return $normalized;
    }
    
    /**
     * Remove accents from text
     *
     * @param string $text Text with accents
     * @return string Text without accents
     */
    private function remove_accents($text) {
        $accent_map = array(
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N'
        );
        
        return strtr($text, $accent_map);
    }
    
    /**
     * Get parsing statistics for debugging
     *
     * @param string $input Input name/text
     * @return array Parsing statistics
     */
    public function get_parsing_stats($input) {
        if (is_object($input)) {
            // User object
            $variations = $this->parse_user_names($input);
            $type = 'user_names';
        } else {
            // Teams name string
            $variations = $this->parse_teams_name($input);
            $type = 'teams_name';
        }
        
        $sources = array();
        foreach ($variations as $variation) {
            $sources[] = $variation['source'];
        }
        
        return array(
            'input_type' => $type,
            'total_variations' => count($variations),
            'sources_used' => array_unique($sources),
            'variations' => $variations
        );
    }
    
    /**
     * Check if two names are likely the same person
     *
     * @param array $name1 First name array (firstname, lastname)
     * @param array $name2 Second name array (firstname, lastname)
     * @return float Similarity score (0-1)
     */
    public function calculate_name_similarity($name1, $name2) {
        $firstname1 = $this->normalize_name($name1['firstname']);
        $lastname1 = $this->normalize_name($name1['lastname']);
        $firstname2 = $this->normalize_name($name2['firstname']);
        $lastname2 = $this->normalize_name($name2['lastname']);
        
        // Calculate Levenshtein distance for both names
        $firstname_similarity = $this->levenshtein_similarity($firstname1, $firstname2);
        $lastname_similarity = $this->levenshtein_similarity($lastname1, $lastname2);
        
        // Weight both names equally
        return ($firstname_similarity + $lastname_similarity) / 2;
    }
    
    /**
     * Calculate similarity using Levenshtein distance
     *
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return float Similarity score (0-1)
     */
    private function levenshtein_similarity($str1, $str2) {
        $max_len = max(strlen($str1), strlen($str2));
        if ($max_len == 0) return 1.0;
        
        $distance = levenshtein($str1, $str2);
        return 1 - ($distance / $max_len);
    }
}
