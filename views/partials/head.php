<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <meta name="description" content="Demande de Visa e-Visa - Ambassade de Côte d'Ivoire">

    <!-- Content Security Policy pour autoriser WebAssembly (requis pour Innovatrics DOT) -->
    <meta http-equiv="Content-Security-Policy"
        content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' 'wasm-unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob: https:; connect-src 'self' blob: https:; worker-src 'self' blob:; child-src 'self' blob:;">
    <title><?= e($title ?? getConfig('app.name')) ?> | Service Officiel</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= getFaviconSvg() ?>">

    <!-- Google Fonts - Professional Typography -->
    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin href="https://fonts.gstatic.com" rel="preconnect" />
    <link href="<?= e(getConfig('external_assets.fonts.google_fonts')) ?>" rel="stylesheet" />
    <!-- Premium Display Font -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;800;900&display=swap" rel="stylesheet" />

    <!-- Material Symbols Outlined (style prototype) -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS avec plugins -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <!-- Theme Configuration -->
    <script>
        <?php require_once CONFIG_PATH . '/tailwind.php'; ?>
        <?= getTailwindConfig() ?>
    </script>

    <!-- Animations et styles prototype -->
    <style>
        /* Material Symbols settings */
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .material-symbols-outlined.filled {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        /* Animation fadeIn du prototype */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        .delay-100 { animation-delay: 0.1s; opacity: 0; }
        .delay-200 { animation-delay: 0.2s; opacity: 0; }
        .delay-300 { animation-delay: 0.3s; opacity: 0; }

        /* Animation fadeOut */
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-10px); }
        }

        /* Scrollbar personnalisée (style prototype) */
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
    </style>

    <!-- Main Styles (unified entry point) -->
    <link rel="stylesheet" href="<?= url('css/main.css') ?>?v=<?= filemtime(CHATBOT_ROOT . '/css/main.css') ?>">

    <!-- UX Enhancements (premium experience) -->
    <link rel="stylesheet" href="<?= url('css/ux-enhancements.css') ?>?v=<?= filemtime(CHATBOT_ROOT . '/css/ux-enhancements.css') ?>">

    <!-- Gamification v6.0 (progress tracker, celebrations, animations) -->
    <link rel="stylesheet" href="<?= url('css/gamification.css') ?>?v=<?= @filemtime(CHATBOT_ROOT . '/css/gamification.css') ?: time() ?>">
</head>

