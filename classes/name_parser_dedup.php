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
 * Name parser deduplication utility
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Handles user name deduplication from registration errors
 */
class name_parser_dedup {
    
    /**
     * Deduplicate user list removing duplicate users
     *
     * @param array $available_users Array of user objects
     * @return array Cleaned user list
     */
    public function deduplicate_user_list($available_users) {
        $cleaned_users = array();
        $seen_users = array();
        
        foreach ($available_users as $user) {
            $user_key = $this->create_user_key($user);
            
            if (!in_array($user_key, $seen_users)) {
                $cleaned_user = $this->clean_user_names($user);
                $cleaned_users[] = $cleaned_user;
                $seen_users[] = $user_key;
            }
        }
        
        return $cleaned_users;
    }
    
    /**
     * Create unique key for user identification
     *
     * @param object $user User object
     * @return string Unique key
     */
    private function create_user_key($user) {
        $firstname = strtolower(trim($user->firstname));
        $lastname = strtolower(trim($user->lastname));
        return $firstname . '|' . $lastname;
    }
    
    /**
     * Clean user names removing duplications
     *
     * @param object $user User object
     * @return object Cleaned user object
     */
    private function clean_user_names($user) {
        $cleaned_user = clone $user;
        
        // Clean firstname
        $cleaned_user->firstname = $this->remove_duplicate_words($user->firstname, $user->lastname);
        
        // Clean lastname  
        $cleaned_user->lastname = $this->remove_duplicate_words($user->lastname, $user->firstname);
        
        return $cleaned_user;
    }
    
    /**
     * Remove duplicate words between two fields
     *
     * @param string $primary_field The field to clean
     * @param string $other_field The field to check for duplicates
     * @return string Cleaned primary field
     */
    private function remove_duplicate_words($primary_field, $other_field) {
        $primary = trim($primary_field);
        $other = trim($other_field);
        
        if (empty($primary) || empty($other)) {
            return $primary;
        }
        
        $primary_words = explode(' ', $primary);
        $other_words = explode(' ', strtolower($other));
        
        $cleaned_words = array();
        
        foreach ($primary_words as $word) {
            $word_lower = strtolower(trim($word));
            
            // Keep word if it's not in other field or is too short
            if (strlen($word_lower) < 2 || !in_array($word_lower, $other_words)) {
                $cleaned_words[] = trim($word);
            }
        }
        
        // If we removed all words, keep the original
        if (empty($cleaned_words)) {
            return $primary;
        }
        
        return implode(' ', $cleaned_words);
    }
}
