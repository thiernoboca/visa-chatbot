<!-- ===== HEADER (style prototype) ===== -->
<header class="sticky top-0 z-50 flex items-center justify-between whitespace-nowrap border-b border-[#f0f2f4] dark:border-gray-800 bg-white/80 dark:bg-[#101822]/90 backdrop-blur-md px-6 lg:px-10 py-3">
    <!-- Logo & Title -->
    <div class="flex items-center gap-4">
        <div class="size-8 flex items-center justify-center text-primary">
            <span class="material-symbols-outlined text-[32px]">travel_explore</span>
        </div>
        <h2 class="text-[#111418] dark:text-white text-lg font-bold leading-tight tracking-[-0.015em]">Côte d'Ivoire e-Visa</h2>
    </div>

    <!-- Action Buttons -->
    <div class="flex gap-2">
        <!-- Language Toggle -->
        <button id="lang-toggle" class="flex items-center justify-center overflow-hidden rounded-full h-10 bg-[#f6f7f8] dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-[#111418] dark:text-white px-4 gap-2 text-sm font-bold min-w-0">
            <span class="material-symbols-outlined text-[20px]">translate</span>
            <span id="lang-label" class="hidden sm:inline"><?= strtoupper(getCurrentLanguage()) ?></span>
        </button>

        <!-- Security Badge -->
        <button class="flex items-center justify-center overflow-hidden rounded-full h-10 bg-[#f6f7f8] dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-[#111418] dark:text-white px-4 gap-2 text-sm font-bold min-w-0">
            <span class="material-symbols-outlined text-[20px]">lock</span>
            <span class="hidden sm:inline">Secure</span>
        </button>

        <!-- Dark Mode Toggle -->
        <button id="theme-toggle" class="flex items-center justify-center overflow-hidden rounded-full h-10 w-10 bg-[#f6f7f8] dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-[#111418] dark:text-white">
            <span class="material-symbols-outlined text-[20px]" id="theme-icon">dark_mode</span>
        </button>
    </div>
</header>

