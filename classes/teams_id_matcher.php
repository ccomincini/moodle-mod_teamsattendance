($cleaned_teams_id, $lastname, $firstname);
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
