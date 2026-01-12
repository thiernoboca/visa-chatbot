/**
 * ESLint Configuration
 * @see https://eslint.org/docs/latest/use/configure/configuration-files-new
 */
import js from '@eslint/js';

export default [
    js.configs.recommended,
    {
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                // Browser globals
                window: 'readonly',
                document: 'readonly',
                console: 'readonly',
                fetch: 'readonly',
                FormData: 'readonly',
                File: 'readonly',
                FileReader: 'readonly',
                Blob: 'readonly',
                URL: 'readonly',
                URLSearchParams: 'readonly',
                localStorage: 'readonly',
                sessionStorage: 'readonly',
                navigator: 'readonly',
                CustomEvent: 'readonly',
                MutationObserver: 'readonly',
                ResizeObserver: 'readonly',
                IntersectionObserver: 'readonly',
                requestAnimationFrame: 'readonly',
                cancelAnimationFrame: 'readonly',
                setTimeout: 'readonly',
                setInterval: 'readonly',
                clearTimeout: 'readonly',
                clearInterval: 'readonly',
                HTMLElement: 'readonly',
                Element: 'readonly',
                Event: 'readonly',
                MediaRecorder: 'readonly',
                AudioContext: 'readonly',
                DataTransfer: 'readonly',
                performance: 'readonly',
                Intl: 'readonly',
                // App-specific globals (lazy-loaded modules)
                CoherenceUI: 'readonly',
                VerificationModal: 'readonly',
                MultiDocumentUploader: 'readonly',
                // Vite defines
                __APP_VERSION__: 'readonly',
                __BUILD_TIME__: 'readonly'
            }
        },
        rules: {
            // Relax some rules for existing codebase
            'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
            'no-console': 'off',
            'no-debugger': 'warn'
        }
    },
    {
        ignores: ['dist/**', 'node_modules/**', 'vendor/**']
    }
];
