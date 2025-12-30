/**
 * Test script for module imports
 * Run with: node --experimental-vm-modules test-imports.js
 * Or check in browser console
 */

const testResults = {
    passed: [],
    failed: []
};

async function testImports() {
    console.log('='.repeat(60));
    console.log('Testing Module Imports');
    console.log('='.repeat(60));

    const modules = [
        // Phase 1
        { name: 'requirements-matrix', path: './modules/requirements-matrix.js' },
        { name: 'document-flow', path: './modules/document-flow.js' },
        { name: 'validation-ui', path: './modules/validation-ui.js' },

        // Phase 2
        { name: 'ocr-fallback', path: './modules/ocr-fallback.js' },

        // Phase 3
        { name: 'optional-docs', path: './modules/optional-docs.js' },
        { name: 'accompanist', path: './modules/accompanist.js' },

        // Phase 4
        { name: 'health-declaration', path: './modules/health-declaration.js' },
        { name: 'payment-flow', path: './modules/payment-flow.js' },

        // Phase 5
        { name: 'signature', path: './modules/signature.js' },
        { name: 'pdf-generator', path: './modules/pdf-generator.js' },

        // Phase 6
        { name: 'flow-integration', path: './modules/flow-integration.js' },

        // Index
        { name: 'index', path: './modules/index.js' }
    ];

    for (const mod of modules) {
        try {
            const imported = await import(mod.path);
            const exports = Object.keys(imported);
            console.log(`✅ ${mod.name}: ${exports.length} exports`);
            testResults.passed.push({ name: mod.name, exports });
        } catch (error) {
            console.log(`❌ ${mod.name}: ${error.message}`);
            testResults.failed.push({ name: mod.name, error: error.message });
        }
    }

    console.log('');
    console.log('='.repeat(60));
    console.log(`Results: ${testResults.passed.length} passed, ${testResults.failed.length} failed`);
    console.log('='.repeat(60));

    return testResults;
}

// Export for browser/Node
if (typeof window !== 'undefined') {
    window.testImports = testImports;
} else if (typeof module !== 'undefined') {
    module.exports = { testImports };
}

// Auto-run if in browser
if (typeof document !== 'undefined') {
    testImports().then(results => {
        console.log('Test complete:', results);
    });
}
