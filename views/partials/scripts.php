<!-- ===== SCRIPTS ===== -->

<!-- QR Code Library for Mobile Capture -->
<script src="<?= e(getConfig('external_assets.qrcode')) ?>"></script>
<script>
    // Adapter QRCode pour la compatibilité
    window.QRCode = function(element, options) {
        const qr = qrcode(0, 'M');
        qr.addData(typeof options === 'string' ? options : options.text);
        qr.make();
        const size = options.width || 128;
        element.innerHTML = qr.createSvgTag({
            scalable: true,
            margin: 2
        });
        const svg = element.querySelector('svg');
        if (svg) {
            svg.setAttribute('width', size);
            svg.setAttribute('height', size);
        }
    };
    QRCode.CorrectLevel = {
        L: 'L',
        M: 'M',
        Q: 'Q',
        H: 'H'
    };
</script>

<!-- Innovatrics DOT Document Capture -->
<script type="module" src="<?= asset('innovatrics/index.umd.js') ?>"></script>
<script type="module" src="<?= asset('innovatrics/document.umd.js') ?>"></script>

<!-- Innovatrics DOT Face Capture -->
<script src="<?= asset('innovatrics/face-capture.umd.js') ?>"></script>
<script src="<?= asset('innovatrics/face-ui.umd.js') ?>"></script>

<!-- Camera Capture Modules -->
<script src="<?= url('js/innovatrics-camera.js') ?>"></script>

<!-- Coherence UI - Cross-Document Validation -->
<script src="<?= url('js/coherence-ui.js') ?>"></script>

<!-- Multi-Document Uploader -->
<script src="<?= url('js/multi-document-uploader.js') ?>"></script>

<!-- Verification Modal -->
<script src="<?= url('js/verification-modal.js') ?>"></script>

<!-- Micro-Interactions -->
<script src="<?= url('js/micro-interactions.js') ?>"></script>

<!-- Premium UX Module -->
<script src="<?= url('js/premium-ux.js') ?>?v=<?= @filemtime(CHATBOT_ROOT . '/js/premium-ux.js') ?: time() ?>"></script>

<!-- Analytics & A/B Testing Clients -->
<script src="<?= url('js/analytics-client.js') ?>"></script>
<script src="<?= url('js/ab-testing-client.js') ?>"></script>

<!-- Pass PHP config to JavaScript -->
<script>
    window.APP_CONFIG = {
        baseUrl: '<?= BASE_URL ?>',
        assetsUrl: '<?= ASSETS_URL ?>',
        language: '<?= getCurrentLanguage() ?>',
        embassy: '<?= e(getConfig('app.embassy')) ?>',
        jurisdictionCountries: <?= json_encode(getJurisdictionCountries()) ?>,
        debug: <?= json_encode(defined('DEBUG_MODE') && DEBUG_MODE) ?>,
        // Géolocalisation du demandeur (détectée via IP)
        geolocation: <?= json_encode($geolocation ?? ['detected' => false]) ?>,
        // Données du workflow pré-chargées (session, message initial, suggestions)
        workflow: <?= json_encode($workflowData ?? null) ?>
    };

    // Flag indiquant si les données sont pré-chargées
    window.WORKFLOW_PRELOADED = <?= json_encode(isset($workflowData) && ($workflowData['preloaded'] ?? false)) ?>;
    window.CHATBOT_DEBUG = window.APP_CONFIG.debug;

    // Log en mode debug
    if (window.APP_CONFIG.debug) {
        if (window.APP_CONFIG.geolocation.detected) {
            console.log('🌍 IP Geolocation:', window.APP_CONFIG.geolocation);
        }
        if (window.WORKFLOW_PRELOADED) {
            console.log('⚡ Workflow Preloaded:', {
                session_id: window.APP_CONFIG.workflow?.session_id,
                current_step: window.APP_CONFIG.workflow?.current_step,
                has_initial_message: !!window.APP_CONFIG.workflow?.initial_message,
                suggestions_count: window.APP_CONFIG.workflow?.suggestions?.length || 0
            });
        }
    }
</script>

<!-- Main Chatbot Application (Full Module) -->
<script type="module">
    import {
        VisaChatbot
    } from '<?= url('js/modules/chatbot.js') ?>?v=<?= filemtime(CHATBOT_ROOT . '/js/modules/chatbot.js') ?>';

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.chatbot = new VisaChatbot({
                language: window.APP_CONFIG?.language || 'fr',
                debug: window.APP_CONFIG?.debug || false
            });
        });
    } else {
        window.chatbot = new VisaChatbot({
            language: window.APP_CONFIG?.language || 'fr',
            debug: window.APP_CONFIG?.debug || false
        });
    }
</script>

<!-- Theme Toggle Handler -->
<script type="module">
    // Attach theme toggle immediately when DOM is ready
    function attachThemeToggle() {
        const themeToggle = document.getElementById('theme-toggle');
        if (!themeToggle) {
            console.warn('[Theme] Toggle button not found');
            return;
        }

        // Wait for chatbot to be initialized
        const checkChatbot = setInterval(() => {
            if (window.chatbot && window.chatbot.ui && window.chatbot.ui.toggleTheme) {
                themeToggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    window.chatbot.ui.toggleTheme();
                    console.log('[Theme] Toggled via button');
                });
                console.log('[Theme] Toggle handler attached');
                clearInterval(checkChatbot);
            }
        }, 100);

        // Timeout after 5 seconds
        setTimeout(() => clearInterval(checkChatbot), 5000);
    }

    // Try immediately if DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachThemeToggle);
    } else {
        attachThemeToggle();
    }
</script>

<!-- Fallback for browsers without ES6 module support -->
<script nomodule>
    console.warn('Your browser does not support ES6 modules. Loading legacy version...');
    document.write('<script src="<?= url('js/legacy/chatbot.legacy.js') ?>"><\/script>');
</script>