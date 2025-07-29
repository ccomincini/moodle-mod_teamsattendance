<?php
// This file is part of Moodle - http://moodle.org/
//
// Simple test for uniqueness logic with real problematic cases

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/teamsattendance/classes/email_pattern_matcher.php');

/**
 * Test cases for the uniqueness logic fix
 */
function test_uniqueness_fix() {
    echo "<h1>🧪 TEST: Email Pattern Matching - Logica Univocità</h1>\n";
    
    // Test users simulating course 57 scenario
    $test_users = array(
        (object) array('id' => 1, 'firstname' => 'Giorgio', 'lastname' => 'Calloni'),
        (object) array('id' => 2, 'firstname' => 'Giorgio', 'lastname' => 'Cabrini'), // Correct match for giorgiocabrini
        (object) array('id' => 3, 'firstname' => 'Claudio', 'lastname' => 'Marelli'),
        (object) array('id' => 4, 'firstname' => 'Stefano', 'lastname' => 'Claudio'), // S. Claudio possibility
        (object) array('id' => 5, 'firstname' => 'Sergio', 'lastname' => 'Claudio'),  // Another S. Claudio - makes it ambiguous!
        (object) array('id' => 6, 'firstname' => 'Mario', 'lastname' => 'Rossi'),
        (object) array('id' => 7, 'firstname' => 'Marco', 'lastname' => 'Rossi')
    );
    
    $matcher = new email_pattern_matcher($test_users);
    
    $test_cases = array(
        // Problematic cases from corso 57
        'giorgiocabrini@virgilio.it' => array(
            'expected_match' => 'Giorgio Cabrini (id: 2)',
            'should_not_match' => 'Giorgio Calloni (id: 1)',
            'description' => 'Deve matchare Cabrini, NON Calloni'
        ),
        
        'sclaudio@comune.castione.bg.it' => array(
            'expected_match' => null, // Should be null due to ambiguity
            'should_not_match' => 'Claudio Marelli',
            'description' => 'Ambiguo: Stefano Claudio vs Sergio Claudio - NON deve suggerire'
        ),
        
        // Test cases that should work
        'mario.rossi@example.com' => array(
            'expected_match' => 'Mario Rossi (id: 6)',
            'description' => 'Pattern esatto - deve funzionare'
        ),
        
        'rossimarco@example.com' => array(
            'expected_match' => 'Marco Rossi (id: 7)', 
            'description' => 'Cognome-first pattern - deve funzionare'
        ),
        
        'claudio@example.com' => array(
            'expected_match' => 'Claudio Marelli (id: 3)',
            'description' => 'Solo un Claudio come nome - deve funzionare'
        )
    );
    
    $passed = 0;
    $failed = 0;
    
    foreach ($test_cases as $email => $test_case) {
        echo "<h3>🔍 Testing: {$email}</h3>\n";
        echo "<p><strong>Scenario:</strong> {$test_case['description']}</p>\n";
        
        $match = $matcher->find_best_email_match($email);
        
        if ($match) {
            $match_name = $match->firstname . ' ' . $match->lastname . ' (id: ' . $match->id . ')';
            echo "<p>✅ <strong>MATCH TROVATO:</strong> {$match_name}</p>\n";
            
            if (isset($test_case['expected_match']) && $test_case['expected_match']) {
                if ($match_name === $test_case['expected_match']) {
                    echo "<p>✅ <strong>CORRETTO:</strong> Match atteso trovato</p>\n";
                    $passed++;
                } else {
                    echo "<p>❌ <strong>ERRORE:</strong> Atteso '{$test_case['expected_match']}', trovato '{$match_name}'</p>\n";
                    $failed++;
                }
            } else {
                echo "<p>❌ <strong>ERRORE:</strong> Match trovato ma NON doveva esserci</p>\n";
                $failed++;
            }
        } else {
            echo "<p>⚪ <strong>NESSUN MATCH:</strong> Nessun suggerimento trovato</p>\n";
            
            if (!isset($test_case['expected_match']) || $test_case['expected_match'] === null) {
                echo "<p>✅ <strong>CORRETTO:</strong> Nessun match era atteso (ambiguità risolta)</p>\n";
                $passed++;
            } else {
                echo "<p>❌ <strong>ERRORE:</strong> Atteso '{$test_case['expected_match']}' ma non trovato</p>\n";
                $failed++;
            }
        }
        
        // Show pattern analysis for debugging
        $analysis = $matcher->get_pattern_analysis($email);
        echo "<p><strong>Pattern analizzati:</strong> " . count($analysis['exact_patterns_tested']) . "</p>\n";
        
        echo "<hr>\n";
    }
    
    // Summary
    echo "<h2>📊 RISULTATI TEST</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>Test passati:</strong> {$passed}</li>\n";
    echo "<li><strong>Test falliti:</strong> {$failed}</li>\n";
    echo "<li><strong>Success rate:</strong> " . round(($passed / ($passed + $failed)) * 100, 1) . "%</li>\n";
    echo "</ul>\n";
    
    if ($failed === 0) {
        echo "<p>🎉 <strong>TUTTI I TEST PASSATI!</strong> La logica di univocità funziona correttamente.</p>\n";
    } else {
        echo "<p>🚨 <strong>ALCUNI TEST FALLITI!</strong> Necessarie correzioni aggiuntive.</p>\n";
    }
    
    return array('passed' => $passed, 'failed' => $failed);
}

/**
 * Quick test runner
 */
function run_uniqueness_test() {
    return test_uniqueness_fix();
}
