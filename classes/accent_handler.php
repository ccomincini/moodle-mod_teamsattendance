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
 * Accent handler utility for Teams attendance
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Handles accent normalization and apostrophe variations
 */
class accent_handler {
    
    /**
     * Normalize text by removing accents and handling apostrophes
     *
     * @param string $text Text to normalize
     * @return string Normalized text
     */
    public function normalize_text($text) {
        $normalized = strtolower(trim($text));
        
        // Remove accents
        $normalized = $this->remove_accents($normalized);
        
        // Handle apostrophes
        $normalized = $this->normalize_apostrophes($normalized);
        
        return $normalized;
    }
    
    /**
     * Remove accents from text
     *
     * @param string $text Text with accents
     * @return string Text without accents
     */
    private function remove_accents($text) {
        $accent_map = [
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
        ];
        
        return strtr($text, $accent_map);
    }
    
    /**
     * Normalize apostrophes and similar characters
     *
     * @param string $text Text with apostrophes
     * @return string Text with normalized apostrophes
     */
    private function normalize_apostrophes($text) {
        // Normalize different apostrophe types
        $apostrophe_variants = [''', ''', '`', '´'];
        
        foreach ($apostrophe_variants as $variant) {
            $text = str_replace($variant, "'", $text);
        }
        
        return $text;
    }
    
    /**
     * Create variations of name with/without apostrophes
     *
     * @param string $name Name to create variations for
     * @return array Array of name variations
     */
    public function create_name_variations($name) {
        $variations = [$name];
        $normalized = $this->normalize_text($name);
        
        if ($normalized !== $name) {
            $variations[] = $normalized;
        }
        
        // Handle D'Angelo <-> DAngelo variations
        if (strpos($name, "'") !== false) {
            $without_apostrophe = str_replace("'", "", $name);
            $variations[] = $without_apostrophe;
            $variations[] = $this->normalize_text($without_apostrophe);
        } else {
            // Try adding apostrophes to common prefixes
            $prefixes = ['d', 'l', 'dal', 'del', 'dell'];
            foreach ($prefixes as $prefix) {
                if (stripos($name, $prefix) === 0 && strlen($name) > strlen($prefix)) {
                    $with_apostrophe = $prefix . "'" . substr($name, strlen($prefix));
                    $variations[] = $with_apostrophe;
                    $variations[] = $this->normalize_text($with_apostrophe);
                }
            }
        }
        
        return array_unique($variations);
    }
    
    /**
     * Check if two names match considering accent variations
     *
     * @param string $name1 First name
     * @param string $name2 Second name
     * @return bool True if names match
     */
    public function names_match($name1, $name2) {
        $norm1 = $this->normalize_text($name1);
        $norm2 = $this->normalize_text($name2);
        
        return $norm1 === $norm2;
    }
}
