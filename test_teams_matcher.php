<?php
// This file is part of Moodle - http://moodle.org/
//
// Test script for teams_id_matcher functionality
// Run this script via CLI to test the new pattern matching algorithm

define('CLI_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/teams_id_matcher.php');

// Test data setup
$test_users = [
    (object) ['id' => 1, 'firstname' => 'Mario', 'lastname' => 'Rossi'],
    (object) ['id' => 2, 'firstname' => 'Giuseppe', 'lastname' => 'Verdi'],
    (object) ['id' => 3, 'firstname' => 'Alberto', 'lastname' => 'Locatelli'],
    (object) ['id' => 4, 'firstname' => 'Luigi', 'lastname' => 'Battistella'],
    (object) ['id' => 5, 'firstname' => 'Gabriella', 'lastname' => 'Verdi'],
    (object) ['id' => 6, 'firstname' => 'Andrea', 'lastname' => 'Rossi'],
    (object) ['id' => 7, 'firstname' => 'Michela', 'lastname' => 'Favini'],
];

$test_teams_ids = [
    'dott. Mario Rossi - Comune di Milano',
    'arch. Giuseppe Verdi, AIPO',
    'Alberto M. Locatelli, Comune di Bosisio Parini',
    'Arch. Luigi Battistella - Comune di Besnate',
    'verdig',
    'a.rossi',
    'gabriv',
    'michela.favini@domain.com',
    'giuseppe123',
    'Rossi Mario',
    'UTC - Mario R.',
    'Protezione Civile - Verdi G.',
];

echo "=== TEAMS ID PATTERN MATCHING TEST ===\n\n";

$matcher = new teams_id_matcher($test_users);

foreach ($test_teams_ids as $teams_id) {
    echo "Teams ID: '$teams_id'\n";
    
    $match = $matcher->find_best_teams_match($teams_id);
    
    if ($match) {
        echo "  ✓ MATCH: {$match->firstname} {$match->lastname} (ID: {$match->id})\n";
    } else {
        echo "  ✗ No match found\n";
    }
    echo "\n";
}

echo "=== Test completed ===\n";
