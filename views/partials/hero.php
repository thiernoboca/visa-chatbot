<!-- ===== LEFT COLUMN: Hero & Progress (style prototype) ===== -->
<div id="hero-section" class="hidden lg:flex lg:col-span-5 flex-col justify-center gap-8 py-8 animate-fade-in">

    <!-- Hero Text -->
    <div class="flex flex-col gap-4">
        <!-- Badge officiel -->
        <div class="inline-flex self-start items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-primary text-sm font-bold">
            <span class="material-symbols-outlined text-[18px] filled" style="font-variation-settings: 'FILL' 1;">verified</span>
            <span data-i18n="badge_official" data-i18n-fr="Service Officiel" data-i18n-en="Official Service">Service Officiel</span>
        </div>

        <!-- Titre -->
        <h1 class="font-display text-4xl xl:text-5xl font-black leading-[1.1] tracking-[-0.033em] text-[#111418] dark:text-white">
            Côte d'Ivoire<br />
            <span class="text-primary">e-Visa Assistant</span>
        </h1>

        <p data-i18n="hero_description" data-i18n-fr="Bienvenue sur le portail officiel. Vivez un processus de demande simple, rapide et conversationnel." data-i18n-en="Welcome to the official portal. Experience a simple, fast and conversational application process." class="text-[#617289] dark:text-gray-400 text-lg font-normal leading-relaxed max-w-md">
            Bienvenue sur le portail officiel. Vivez un processus de demande simple, rapide et conversationnel.
        </p>
    </div>

    <!-- Visual Context Card avec hover zoom -->
    <div class="relative w-full aspect-[4/3] rounded-2xl overflow-hidden shadow-xl group">
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent z-10"></div>
        <div class="absolute bottom-0 left-0 p-6 z-20 text-white">
            <p data-i18n="hero_location_title" data-i18n-fr="Centre-ville d'Abidjan" data-i18n-en="Abidjan City Center" class="text-xl font-bold mb-1">Centre-ville d'Abidjan</p>
            <p class="text-sm opacity-90 flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">location_on</span>
                <span data-i18n="hero_location_country" data-i18n-fr="Côte d'Ivoire" data-i18n-en="Ivory Coast">Côte d'Ivoire</span>
            </p>
        </div>
        <div class="w-full h-full bg-cover bg-center transition-transform duration-700 group-hover:scale-105"
             style="background-image: url('https://images.unsplash.com/photo-1611348524140-53c9a25263d6?w=800&q=80');">
        </div>
    </div>

    <!-- Progress Card -->
    <div id="progress-card" class="card progress-card">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-xl">rocket_launch</span>
                </div>
                <div>
                    <h3 data-i18n="progress_label" data-i18n-fr="Progression" data-i18n-en="Progress" class="text-caption text-secondary">Progression</h3>
                    <p data-i18n="progress_title" data-i18n-fr="Vérification d'identité" data-i18n-en="Identity Verification" class="text-title text-primary dark:text-white">Vérification d'identité</p>
                </div>
            </div>
            <span id="progress-percent" class="text-2xl font-black text-primary">0%</span>
        </div>

        <!-- Progress Bar -->
        <div class="h-2 w-full bg-surface-secondary dark:bg-gray-700 rounded-full overflow-hidden mb-5">
            <div id="progress-bar" class="h-full bg-primary rounded-full transition-all duration-500 ease-out" style="width: 0%;"></div>
        </div>

        <!-- Timeline -->
        <div id="step-timeline" class="space-y-2.5 max-h-[180px] overflow-y-auto pr-2 scrollbar-hide">
            <!-- Steps rendered by JS -->
        </div>
    </div>

    <!-- Helper Tip Card -->
    <div id="helper-tip-card" class="hidden flex-col gap-2 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-100 dark:border-blue-900/30">
        <div class="flex items-center gap-2 text-primary dark:text-blue-400">
            <span class="material-symbols-outlined text-xl">lightbulb</span>
            <span data-i18n="helper_why" data-i18n-fr="Pourquoi c'est important ?" data-i18n-en="Why is this important?" class="text-sm font-bold">Pourquoi c'est important ?</span>
        </div>
        <p id="helper-tip-text" data-i18n="helper_tip_default" data-i18n-fr="Des données précises garantissent un traitement rapide de votre demande." data-i18n-en="Accurate data ensures fast processing of your application." class="text-xs text-[#617289] dark:text-gray-400 leading-normal">
            Des données précises garantissent un traitement rapide de votre demande.
        </p>
    </div>

    <!-- Document Checklist Card -->
    <div id="document-checklist-card" class="card document-card hidden">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-green-600 dark:text-green-400">
                    <span class="material-symbols-outlined text-xl">folder_open</span>
                </div>
                <div>
                    <h3 data-i18n="docs_label" data-i18n-fr="Documents" data-i18n-en="Documents" class="text-caption text-secondary">Documents</h3>
                    <p data-i18n="docs_title" data-i18n-fr="Pièces requises" data-i18n-en="Required Documents" class="text-title text-primary dark:text-white">Pièces requises</p>
                </div>
            </div>
            <span id="docs-count" class="text-xl font-black text-green-600 dark:text-green-400">0/0</span>
        </div>

        <!-- Document Progress Bar -->
        <div class="h-2 w-full bg-[#dbe0e6] dark:bg-gray-700 rounded-full overflow-hidden mb-4">
            <div id="docs-progress-bar" class="h-full bg-green-500 rounded-full transition-all duration-500" style="width: 0%;"></div>
        </div>

        <!-- Document List -->
        <div id="document-checklist" class="space-y-2">
            <!-- Documents rendered by JS -->
        </div>
    </div>
</div>

