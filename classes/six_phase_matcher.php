<?php
// This file is part of Moodle - http://moodle.org/
//
// New 6-phase matching system for Teams attendance

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teamsattendance/classes/name_parser.php');

/**
 * 6-phase matching system as requested
 */
class six_phase_matcher {
    
    private $available_users;
    private $name_parser;
    private $matched_users = array(); // Track already matched users
    private $stop_words = ['di', 'da', 'de', 'del', 'della', 'delle', 'dei', 'degli', 'lo', 'la', 'le', 'il', 'a', 'in', 'con', 'su', 'per', 'tra', 'fra'];
    
    public function __construct($available_users) {
        $this->available_users = $available_users;
        $this->name_parser = new name_parser();
    }
    
    /**
     * Phase 1: Cerca cognome poi nome per matching completo
     */
    public function phase1_lastname_firstname($teams_id) {
        if ($this->is_email($teams_id)) return null;
        
        foreach ($this->available_users as $user) {
            if (in_array($user->id, $this->matched_users)) continue;
            
            $lastname = $this->normalize($user->lastname);
            $firstname = $this->normalize($user->firstname);
            
            if ($this->find_word($teams_id, $lastname) && $this->find_word($teams_id, $firstname)) {
                $this->matched_users[] = $user->id;
                return $user;
            }
        }
        return null;
    }
    
    /**
     * Phase 2: Cerca nome poi cognome (inverso di fase 1)
     */
    public function phase2_firstname_lastname($teams_id) {
        if ($this->is_email($teams_id)) return null;
        
        foreach ($this->available_users as $user) {
            if (in_array($user->id, $this->matched_users)) continue;
            
            $lastname = $this->normalize($user->lastname);
            $firstname = $this->normalize($user->firstname);
            
            if ($this->find_word($teams_id, $firstname) && $this->find_word($teams_id, $lastname)) {
                $this->matched_users[] = $user->id;
                return $user;
            }
        }
        return null;
    }
    
    /**
     * Phase 4: Cerca cognome + iniziale nome (con controllo ambiguità)
     */
    public function phase4_lastname_initial($teams_id) {
        if ($this->is_email($teams_id)) return null;
        
        foreach ($this->available_users as $user) {
            if (in_array($user->id, $this->matched_users)) continue;
            
            $lastname = $this->normalize($user->lastname);
            $initial = substr($this->normalize($user->firstname), 0, 1);
            
            if ($this->find_word($teams_id, $lastname) && $this->find_initial($teams_id, $initial)) {
                // Controllo ambiguità
                if (!$this->is_ambiguous_lastname_initial($lastname, $initial)) {
                    $this->matched_users[] = $user->id;
                    return $user;
                }
            }
        }
        return null;
    }
    
    /**
     * Phase 5: Cerca nome + iniziale cognome (con controllo ambiguità)
     */
    public function phase5_firstname_initial($teams_id) {
        if ($this->is_email($teams_id)) return null;
        
        foreach ($this->available_users as $user) {
            if (in_array($user->id, $this->matched_users)) continue;
            
            $firstname = $this->normalize($user->firstname);
            $initial = substr($this->normalize($user->lastname), 0, 1);
            
            if ($this->find_word($teams_id, $firstname) && $this->find_initial($teams_id, $initial)) {
                // Controllo ambiguità
                if (!$this->is_ambiguous_firstname_initial($firstname, $initial)) {
                    $this->matched_users[] = $user->id;
                    return $user;
                }
            }
        }
        return null;
    }
    
    /**
     * Esegui tutte le 6 fasi in sequenza
     */
    public function find_best_match($teams_id) {
        // Reset matched users for this search
        $this->matched_users = array();
        
        // Phase 1
        $match = $this->phase1_lastname_firstname($teams_id);
        if ($match) return $match;
        
        // Phase 2
        $match = $this->phase2_firstname_lastname($teams_id);
        if ($match) return $match;
        
        // Phase 3: Skip matched users from phases 1-2
        
        // Phase 4
        $match = $this->phase4_lastname_initial($teams_id);
        if ($match) return $match;
        
        // Phase 5
        $match = $this->phase5_firstname_initial($teams_id);
        if ($match) return $match;
        
        // Phase 6: Skip matched users from phases 1-5
        
        return null;
    }
    
    private function is_email($text) {
        return filter_var($text, FILTER_VALIDATE_EMAIL);
    }
    
    private function normalize($text) {
        return strtolower(trim(preg_replace('/[^a-zA-Z\s]/', '', $text)));
    }
    
    private function find_word($text, $word) {
        if (in_array($word, $this->stop_words)) return false;
        return preg_match('/\b' . preg_quote($word, '/') . '\b/i', $text);
    }
    
    private function find_initial($text, $initial) {
        return preg_match('/\b' . preg_quote($initial, '/') . '\.?\b/i', $text);
    }
    
    private function is_ambiguous_lastname_initial($lastname, $initial) {
        $count = 0;
        foreach ($this->available_users as $user) {
            if ($this->normalize($user->lastname) === $lastname && 
                substr($this->normalize($user->firstname), 0, 1) === $initial) {
                $count++;
                if ($count > 1) return true;
            }
        }
        return false;
    }
    
    private function is_ambiguous_firstname_initial($firstname, $initial) {
        $count = 0;
        foreach ($this->available_users as $user) {
            if ($this->normalize($user->firstname) === $firstname && 
                substr($this->normalize($user->lastname), 0, 1) === $initial) {
                $count++;
                if ($count > 1) return true;
            }
        }
        return false;
    }
}
