<!-- ===== HEADER (style prototype) ===== -->
<header class="sticky top-0 z-50 flex items-center justify-between whitespace-nowrap border-b border-light dark:border-gray-700 bg-white/80 dark:bg-[#101822]/90 backdrop-blur-md px-6 lg:px-10 py-3">
    <!-- Logo & Title -->
    <div class="flex items-center gap-4">
        <div class="size-8 flex items-center justify-center text-primary">
            <span class="material-symbols-outlined text-[32px]">travel_explore</span>
        </div>
        <h2 class="text-primary dark:text-white text-lg font-bold leading-tight tracking-[-0.015em]">Côte d'Ivoire e-Visa</h2>
    </div>

    <!-- Action Buttons -->
    <div class="flex gap-2">
        <!-- Language Toggle -->
        <button id="lang-toggle"
                aria-label="<?= getCurrentLanguage() === 'fr' ? 'Changer la langue / Switch language' : 'Switch language / Changer la langue' ?>"
                class="flex items-center justify-center overflow-hidden rounded-full h-10 bg-surface-secondary dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-primary dark:text-white px-4 gap-2 text-sm font-bold min-w-0">
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">translate</span>
            <span id="lang-label" class="hidden sm:inline"><?= strtoupper(getCurrentLanguage()) ?></span>
        </button>

        <!-- Security Badge -->
        <button aria-label="<?= getCurrentLanguage() === 'fr' ? 'Connexion sécurisée' : 'Secure connection' ?>"
                class="flex items-center justify-center overflow-hidden rounded-full h-10 bg-surface-secondary dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-primary dark:text-white px-4 gap-2 text-sm font-bold min-w-0">
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">lock</span>
            <span class="hidden sm:inline">Secure</span>
        </button>

        <!-- Dark Mode Toggle -->
        <button id="theme-toggle"
                aria-label="<?= isDarkMode() ? (getCurrentLanguage() === 'fr' ? 'Activer le mode clair' : 'Switch to light mode') : (getCurrentLanguage() === 'fr' ? 'Activer le mode sombre' : 'Switch to dark mode') ?>"
                class="flex items-center justify-center overflow-hidden rounded-full h-10 w-10 bg-surface-secondary dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-primary dark:text-white">
            <span class="material-symbols-outlined text-[20px]" id="theme-icon" aria-hidden="true"><?= isDarkMode() ? 'light_mode' : 'dark_mode' ?></span>
        </button>
    </div>
</header>

