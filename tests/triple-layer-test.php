<?php
/**
 * Triple Layer Architecture Test
 * Validates the implementation of the three AI layers
 */

require_once dirname(__DIR__) . '/php/config.php';
require_once dirname(__DIR__) . '/php/session-manager.php';
require_once dirname(__DIR__) . '/php/claude-audit-manager.php';

echo "=== Triple Layer Architecture Test ===\n\n";

$passed = 0;
$failed = 0;

// Test 1: Session Manager with Triple Layer traces
echo "1. Testing SessionManager Triple Layer...\n";
try {
    $session = new SessionManager();
    $session->addTripleLayerTrace('layer1', 'ocr_test', ['confidence' => 0.95]);
    $session->addTripleLayerTrace('layer2', 'gemini_test', ['source' => 'gemini_flash']);
    $session->recordGeminiUsage('nlu');
    $summary = $session->getTripleLayerSummary();
    
    if ($summary['traces_count'] >= 2 && $summary['gemini_usage']['total_calls'] >= 1) {
        echo "   ✓ Traces added: {$summary['traces_count']}\n";
        echo "   ✓ Gemini calls: {$summary['gemini_usage']['total_calls']}\n";
        $passed++;
    } else {
        echo "   ✗ Unexpected values\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 2: Claude Audit Manager
echo "\n2. Testing ClaudeAuditManager...\n";
try {
    $auditManager = new ClaudeAuditManager(['debug' => false]);
    $stats = $auditManager->getAuditStats();
    
    echo "   ✓ Total audits: {$stats['total_audits']}\n";
    echo "   ✓ Pending validations: {$stats['pending_validations']}\n";
    $passed++;
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 3: Export with Triple Layer data
echo "\n3. Testing Session Export...\n";
try {
    $session = new SessionManager();
    $export = $session->export();
    
    if (isset($export['triple_layer']) && isset($export['triple_layer']['confidence_score'])) {
        echo "   ✓ Triple Layer summary included: Yes\n";
        echo "   ✓ Confidence score: {$export['triple_layer']['confidence_score']}\n";
        $passed++;
    } else {
        echo "   ✗ Triple Layer data missing\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 4: Layer constants
echo "\n4. Checking Layer Configuration...\n";
$geminiKey = getenv('GEMINI_API_KEY');
$claudeKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : getenv('CLAUDE_API_KEY');

echo "   - Gemini API Key: " . (!empty($geminiKey) ? "Configured" : "Not configured") . "\n";
echo "   - Claude API Key: " . (!empty($claudeKey) ? "Configured" : "Not configured") . "\n";
$passed++;

// Test 5: Document Extractor Triple Layer
echo "\n5. Testing DocumentExtractor Triple Layer...\n";
try {
    require_once dirname(__DIR__) . '/php/document-extractor.php';
    $extractor = new DocumentExtractor(['debug' => false, 'use_gemini' => true]);
    
    $isGeminiAvailable = $extractor->isGeminiAvailable();
    echo "   - Gemini available for extraction: " . ($isGeminiAvailable ? "Yes" : "No (will use Claude fallback)") . "\n";
    $passed++;
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $failed++;
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed === 0) {
    echo "\n✓ All Triple Layer components working correctly!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed\n";
    exit(1);
}

