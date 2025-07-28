    /**
     * Normalize text handling accents and apostrophes variations
     *
     * @param string $text Text to normalize
     * @return string Normalized text
     */
    public function normalize_text_advanced($text) {
        $normalized = strtolower(trim($text));
        
        // Handle apostrophe substitutions (à->a', è->e', etc.)
        $apostrophe_map = [
            "a'" => 'a', "e'" => 'e', "i'" => 'i', "o'" => 'o', "u'" => 'u',
            "A'" => 'a', "E'" => 'e', "I'" => 'i', "O'" => 'o', "U'" => 'u'
        ];
        
        foreach ($apostrophe_map as $apostrophe => $replacement) {
            $normalized = str_replace($apostrophe, $replacement, $normalized);
        }
        
        // Remove accents
        $normalized = $this->remove_accents($normalized);
        
        return $normalized;
    }
    
    /**
     * Create regex patterns for name matching with accent/apostrophe variations
     *
     * @param string $name Name to create patterns for
     * @return array Array of regex patterns
     */
    public function create_accent_patterns($name) {
        $patterns = array();
        $base_name = $this->normalize_text_advanced($name);
        
        // Pattern 1: Exact normalized match
        $patterns[] = '\b' . preg_quote($base_name, '/') . '\b';
        
        // Pattern 2: With apostrophes (d'ambrosio)
        $apostrophe_name = $this->create_apostrophe_variant($base_name);
        if ($apostrophe_name !== $base_name) {
            $patterns[] = '\b' . preg_quote($apostrophe_name, '/') . '\b';
        }
        
        // Pattern 3: With separators (d.ambrosio, d-ambrosio, d_ambrosio)
        $separator_patterns = $this->create_separator_variants($base_name);
        foreach ($separator_patterns as $pattern) {
            $patterns[] = $pattern;
        }
        
        return array_unique($patterns);
    }
    
    /**
     * Create apostrophe variant of name
     *
     * @param string $name Normalized name
     * @return string Name with apostrophe if applicable
     */
    private function create_apostrophe_variant($name) {
        // Common Italian apostrophe prefixes
        $prefixes = ['d', 'l', 'dall', 'dell', 'sul', 'nel'];
        
        foreach ($prefixes as $prefix) {
            if (strpos($name, $prefix) === 0 && strlen($name) > strlen($prefix)) {
                return $prefix . "'" . substr($name, strlen($prefix));
            }
        }
        
        return $name;
    }
    
    /**
     * Create separator variants for names with apostrophes
     *
     * @param string $name Base name
     * @return array Array of regex patterns
     */
    private function create_separator_variants($name) {
        $patterns = array();
        
        // Find potential apostrophe positions
        $prefixes = ['d', 'l', 'dall', 'dell', 'sul', 'nel'];
        
        foreach ($prefixes as $prefix) {
            if (strpos($name, $prefix) === 0 && strlen($name) > strlen($prefix)) {
                $suffix = substr($name, strlen($prefix));
                
                // Create patterns with different separators
                $separators = ['\\.', '-', '_', ''];
                
                foreach ($separators as $sep) {
                    $pattern = '\b' . preg_quote($prefix, '/') . $sep . preg_quote($suffix, '/') . '\b';
                    $patterns[] = $pattern;
                }
            }
        }
        
        return $patterns;
    }
    
    /**
     * Check if name matches with accent/apostrophe tolerance
     *
     * @param string $search_text Text to search in
     * @param string $name Name to find
     * @return bool True if found with any variation
     */
    public function find_name_with_accents($search_text, $name) {
        $patterns = $this->create_accent_patterns($name);
        $normalized_text = $this->normalize_text_advanced($search_text);
        
        foreach ($patterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $normalized_text)) {
                return true;
            }
        }
        
        return false;
    }