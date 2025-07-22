            // If more than 2 parts, try compound first name
            if (count($parts) > 2) {
                if (isset($parts[1])) {
                    $names[] = array(
                        'firstname' => $parts[0] . ' ' . $parts[1],
                        'lastname' => $parts[count($parts) - 1],
                        'source' => 'compound_first_cleaned'
                    );
                }
                
                // Try compound last name
                $names[] = array(
                    'firstname' => $parts[0],
                    'lastname' => implode(' ', array_slice($parts, 1)),
                    'source' => 'compound_last_cleaned'
                );
                
                // Try middle combinations for cases like "Mario Rossi Bianchi"
                if (count($parts) >= 3 && isset($parts[1])) {
                    $names[] = array(
                        'firstname' => $parts[0],
                        'lastname' => $parts[1],
                        'source' => 'middle_name_cleaned'
                    );
                }
            }