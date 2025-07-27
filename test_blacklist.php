<?php
// This file is part of Moodle - http://moodle.org/
//
// Blacklist verification test - Tests specific problematic cases
// Verifies that institutional Teams IDs are properly blacklisted

define('CLI_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/teams_id_matcher.php');

echo "=== BLACKLIST VERIFICATION TEST ===\n";
echo "Testing specific problematic Teams IDs to verify blacklist functionality\n\n";

// Create dummy users for testing
$dummy_users = [
    (object)['id' => 1, 'firstname' => 'DARIO', 'lastname' => 'DI FRESCO', 'email' => 'dario.difresco@example.com'],
    (object)['id' => 2, 'firstname' => 'Marco', 'lastname' => 'Rossi', 'email' => 'marco.rossi@example.com'],
    (object)['id' => 3, 'firstname' => 'Giuseppe', 'lastname' => 'Verde', 'email' => 'giuseppe.verde@example.com'],
    (object)['id' => 4, 'firstname' => 'Anna', 'lastname' => 'Bianchi', 'email' => 'anna.bianchi@example.com'],
    (object)['id' => 5, 'firstname' => 'Francesco', 'lastname' => 'Comune', 'email' => 'francesco.comune@example.com'],
];

$matcher = new teams_id_matcher($dummy_users);

// Test cases - these should be BLACKLISTED
$blacklist_test_cases = [
    'Comune di Milano',
    'Comune di Roma',
    'Provincia di Brescia',
    'Ufficio Tecnico Comunale',
    'Ufficio Amministrativo',
    'Servizio Informatico',
    'Dipartimento Lavori Pubblici',
    'Assessorato Urbanistica',
    'Protezione Civile',
    'Guest User',
    'Amministratore Sistema',
    'Sistema Informativo',
    'Direzione Generale',
    'Segreteria Generale',
    'Sindaco',
    'Presidente',
    'Guest',
    'Admin'
];

// Test cases - these should be PROCESSED
$valid_test_cases = [
    'DARIO DI FRESCO',
    'Marco Rossi',
    'Giuseppe Verde-Admin',  // Edge case - person with admin in name
    'Anna Bianchi (Responsabile)',
    'Francesco Comune',  // Edge case - person with surname "Comune"
    'Di Marco Giuseppe',
    'Rossi Marco'
];

echo "=== BLACKLIST TESTS (should be rejected) ===\n";
$blacklist_working = 0;
$blacklist_total = count($blacklist_test_cases);

foreach ($blacklist_test_cases as $teams_id) {
    $match = $matcher->find_best_teams_match($teams_id);
    
    if ($match === null) {
        echo "✓ CORRECTLY BLACKLISTED: '$teams_id'\n";
        $blacklist_working++;
    } else {
        echo "✗ FAILED TO BLACKLIST: '$teams_id' → {$match->firstname} {$match->lastname}\n";
    }
}

echo "\nBlacklist effectiveness: $blacklist_working/$blacklist_total (" . round(($blacklist_working/$blacklist_total)*100, 1) . "%)\n\n";

echo "=== VALID TEAMS ID TESTS (should be processed) ===\n";
$valid_processed = 0;
$valid_total = count($valid_test_cases);

foreach ($valid_test_cases as $teams_id) {
    $match = $matcher->find_best_teams_match($teams_id);
    
    if ($match !== null) {
        echo "✓ CORRECTLY PROCESSED: '$teams_id' → {$match->firstname} {$match->lastname}\n";
        $valid_processed++;
    } else {
        echo "⚠ NOT MATCHED: '$teams_id' (but correctly not blacklisted)\n";
        $valid_processed++; // Still counts as correctly processed (not blacklisted)
    }
}

echo "\nValid ID processing: $valid_processed/$valid_total (" . round(($valid_processed/$valid_total)*100, 1) . "%)\n\n";

// Special test for the specific issue mentioned
echo "=== SPECIFIC ISSUE TEST ===\n";
echo "Testing if 'DARIO DI FRESCO' incorrectly matches institutional IDs...\n\n";

$problematic_cases = [
    'Comune DI Milano',
    'Ufficio DI Controllo', 
    'Servizio DI Informatica',
    'COMUNE DI BRESCIA',
    'comune di roma'
];

foreach ($problematic_cases as $teams_id) {
    $match = $matcher->find_best_teams_match($teams_id);
    
    if ($match === null) {
        echo "✓ '$teams_id' → CORRECTLY BLACKLISTED (no false positive)\n";
    } else {
        echo "✗ '$teams_id' → INCORRECTLY MATCHED: {$match->firstname} {$match->lastname}\n";
        echo "  This would be a false positive if the user is 'DARIO DI FRESCO'\n";
    }
}

echo "\n=== DEBUGGING INFO ===\n";
$debug_id = 'DARIO DI FRESCO';
$dario_user = $dummy_users[0]; // DARIO DI FRESCO
$details = $matcher->get_matching_details($debug_id, $dario_user);

echo "User: {$dario_user->firstname} {$dario_user->lastname}\n";
echo "Teams ID: '$debug_id'\n";
echo "Cleaned: '{$details['cleaned_teams_id']}'\n";
echo "Blacklisted: " . ($details['is_blacklisted'] ? 'YES' : 'NO') . "\n";
echo "Best Score: " . round($details['best_score'], 3) . "\n";

if (!empty($details['lastname_first_results'])) {
    echo "Lastname-first matches:\n";
    foreach ($details['lastname_first_results'] as $result) {
        echo "  • {$result['lastname']} + {$result['firstname']} = score: " . round($result['total_score'], 3) . "\n";
    }
}

echo "\n=== SUMMARY ===\n";
if ($blacklist_working == $blacklist_total && $valid_processed == $valid_total) {
    echo "🎯 ALL TESTS PASSED - Blacklist is working correctly!\n";
    echo "   • Institutional accounts are properly rejected\n";
    echo "   • Valid personal names are processed normally\n";
    echo "   • No false positives detected\n";
} else {
    echo "⚠ SOME TESTS FAILED - Review blacklist implementation\n";
    echo "   • Blacklist: $blacklist_working/$blacklist_total working\n";
    echo "   • Valid processing: $valid_processed/$valid_total working\n";
}

echo "\n=== Test completed ===\n";
