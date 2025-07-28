    /**
     * Deduplicate names from user registration errors
     * Removes duplicate words that may result from incorrect field entry
     *
     * @param array $available_users Array of user objects
     * @return array Cleaned user list with deduplicated names
     */
    public function deduplicate_user_names($available_users) {
        $cleaned_users = array();
        
        foreach ($available_users as $user) {
            $cleaned_user = clone $user;
            
            // Clean firstname
            $cleaned_user->firstname = $this->remove_duplicate_words($user->firstname, $user->lastname);
            
            // Clean lastname  
            $cleaned_user->lastname = $this->remove_duplicate_words($user->lastname, $user->firstname);
            
            $cleaned_users[] = $cleaned_user;
        }
        
        return $cleaned_users;
    }
    
    /**
     * Remove duplicate words between two fields
     * Handles cases like: firstname="Mario Rossi", lastname="Rossi" -> firstname="Mario"
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
            
            // Keep word if it's not in other field or is too short to be meaningful
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