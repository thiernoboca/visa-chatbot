<!-- ===== RIGHT COLUMN: Chat Interface (style prototype) ===== -->
<div class="lg:col-span-7 flex flex-col h-full max-h-[800px] self-center w-full">
    <!-- Chat Container Card -->
    <div class="flex flex-col flex-1 bg-surface dark:bg-[#1a2634] rounded-xl lg:rounded-2xl shadow-xl border border-light dark:border-gray-700 overflow-hidden relative">

        <!-- Mobile Header -->
        <div class="lg:hidden p-4 border-b border-light dark:border-gray-700 flex items-center gap-3">
            <div class="relative shrink-0">
                <div class="size-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined">smart_toy</span>
                </div>
                <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-500 rounded-full border-2 border-white dark:border-[#1a2634]"></div>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-base text-primary dark:text-white">Visa Assistant</h3>
                <p class="text-xs text-secondary" data-i18n="mobile_status" data-i18n-fr="En ligne • Partenaire Officiel" data-i18n-en="Online • Official Partner">En ligne • Partenaire Officiel</p>
            </div>
            <div class="w-16 h-1.5 bg-surface-secondary dark:bg-gray-700 rounded-full overflow-hidden">
                <div id="mobile-progress-bar" class="h-full bg-primary rounded-full transition-all duration-300" style="width: 0%;"></div>
            </div>
        </div>

        <!-- Chat History Area -->
        <div id="chatMessages" class="chat-messages flex-1 overflow-y-auto p-4 sm:p-6 space-y-6 scrollbar-hide flex flex-col">
            <!-- Date Separator -->
            <div id="date-separator" class="flex justify-center my-2 hidden opacity-0 animate-fade-in">
                <span class="bg-[#f6f7f8] dark:bg-gray-800 text-[#617289] dark:text-gray-400 text-xs px-3 py-1 rounded-full" data-i18n="date_today" data-i18n-fr="Aujourd'hui" data-i18n-en="Today">Aujourd'hui</span>
            </div>
            <!-- Dynamic Messages -->
        </div>

        <!-- Action Area (Input/Buttons) -->
        <div id="quickActions" class="action-area bg-surface dark:bg-[#1a2634] border-t border-light dark:border-gray-700 z-10">
            <!-- Dynamic Controls (quickActions, input, etc.) -->
        </div>

        <!-- Security Footer -->
        <div class="flex justify-center py-3 bg-surface-secondary dark:bg-gray-800/30">
            <p class="text-caption text-secondary dark:text-gray-500 flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]">lock</span>
                <span data-i18n="security_footer" data-i18n-fr="Vos données sont chiffrées et sécurisées." data-i18n-en="Your data is encrypted and secure.">Vos données sont chiffrées et sécurisées.</span>
            </p>
        </div>
    </div>
</div>

<!-- ===== PASSPORT SCANNER OVERLAY ===== -->
<div id="passportScannerOverlay" class="passport-scanner-overlay" hidden>
    <div class="passport-scanner-modal">
        <!-- Header -->
        <div class="scanner-header">
            <h3 class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">document_scanner</span>
                <span data-i18n="scanner_title" data-i18n-fr="Scanner votre passeport" data-i18n-en="Scan your passport">Scanner votre passeport</span>
            </h3>
            <button type="button" class="btn-close-scanner" id="btnCloseScanner" aria-label="Fermer / Close" data-i18n-aria="close_scanner" data-i18n-aria-fr="Fermer le scanner" data-i18n-aria-en="Close scanner">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">close</span>
            </button>
        </div>

        <!-- Body -->
        <div class="scanner-body">
            <!-- Upload Zone with Enhanced UX -->
            <div class="upload-zone enhanced-upload-zone group" id="passportUploadZone">
                <input type="file" id="passportFileInput" accept="image/*,.pdf,application/pdf" class="hidden" />
                <div class="size-14 rounded-full bg-primary/10 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-primary text-3xl">cloud_upload</span>
                </div>
                <p class="upload-text">
                    <span class="font-semibold text-primary" data-i18n="upload_click" data-i18n-fr="Cliquez pour sélectionner" data-i18n-en="Click to select">Cliquez pour sélectionner</span><br/>
                    <span class="text-[#617289]" data-i18n="upload_drag" data-i18n-fr="ou glissez-déposez votre fichier" data-i18n-en="or drag and drop your file">ou glissez-déposez votre fichier</span>
                </p>
                <p class="upload-hint text-xs text-[#617289] mt-3 bg-[#f6f7f8] dark:bg-gray-700 px-3 py-1 rounded-full" data-i18n="upload_formats" data-i18n-fr="JPG, PNG ou PDF • Max 10MB" data-i18n-en="JPG, PNG or PDF • Max 10MB">
                    JPG, PNG ou PDF • Max 10MB
                </p>
            </div>

            <!-- Tips Section (prototype style) -->
            <div class="tips-section mt-5 grid grid-cols-2 gap-3">
                <div class="tips-column">
                    <p class="text-xs font-bold text-[#617289] uppercase tracking-wide mb-2" data-i18n="tips_do" data-i18n-fr="À faire" data-i18n-en="Do">À faire</p>
                    <div class="space-y-2">
                        <div class="tip-item flex items-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-green-500 text-lg" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                            <span class="text-[#111418] dark:text-white" data-i18n="tip_lighting" data-i18n-fr="Photo bien éclairée" data-i18n-en="Well-lit photo">Photo bien éclairée</span>
                        </div>
                        <div class="tip-item flex items-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-green-500 text-lg" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                            <span class="text-[#111418] dark:text-white" data-i18n="tip_corners" data-i18n-fr="Tous les coins visibles" data-i18n-en="All corners visible">Tous les coins visibles</span>
                        </div>
                        <div class="tip-item flex items-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-green-500 text-lg" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                            <span class="text-[#111418] dark:text-white" data-i18n="tip_mrz" data-i18n-fr="MRZ lisible en bas" data-i18n-en="MRZ readable at bottom">MRZ lisible en bas</span>
                        </div>
                    </div>
                </div>
                <div class="tips-column">
                    <p class="text-xs font-bold text-[#617289] uppercase tracking-wide mb-2" data-i18n="tips_avoid" data-i18n-fr="À éviter" data-i18n-en="Avoid">À éviter</p>
                    <div class="space-y-2">
                        <div class="tip-item flex items-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-red-500 text-lg" style="font-variation-settings: 'FILL' 1;">cancel</span>
                            <span class="text-[#111418] dark:text-white" data-i18n="tip_blurry" data-i18n-fr="Photo floue" data-i18n-en="Blurry photo">Photo floue</span>
                        </div>
                        <div class="tip-item flex items-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-red-500 text-lg" style="font-variation-settings: 'FILL' 1;">cancel</span>
                            <span class="text-[#111418] dark:text-white" data-i18n="tip_glare" data-i18n-fr="Reflets ou ombres" data-i18n-en="Glare or shadows">Reflets ou ombres</span>
                        </div>
                        <div class="tip-item flex items-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-red-500 text-lg" style="font-variation-settings: 'FILL' 1;">cancel</span>
                            <span class="text-[#111418] dark:text-white" data-i18n="tip_cropped" data-i18n-fr="Document coupé" data-i18n-en="Cropped document">Document coupé</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Area (hidden initially) -->
            <div id="passportPreviewArea" class="hidden mt-4">
                <div class="relative rounded-xl overflow-hidden border border-[#f0f2f4] dark:border-gray-700">
                    <img id="passportPreviewImage" class="w-full h-auto" alt="Aperçu passeport" data-i18n-alt="preview_alt" data-i18n-alt-fr="Aperçu du passeport" data-i18n-alt-en="Passport preview" />
                    <button type="button" id="btnRemovePassport" class="absolute top-2 right-2 w-8 h-8 rounded-full bg-red-500 text-white flex items-center justify-center shadow-lg hover:bg-red-600 transition-colors" aria-label="Supprimer / Remove" data-i18n-aria="remove_image" data-i18n-aria-fr="Supprimer l'image" data-i18n-aria-en="Remove image">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                    </button>
                </div>
            </div>

            <!-- Processing Indicator -->
            <div id="passportProcessing" class="hidden mt-4">
                <div class="flex items-center justify-center gap-3 p-4 bg-primary/5 rounded-xl">
                    <div class="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                    <span class="text-sm font-medium text-[#111418] dark:text-white" data-i18n="analyzing" data-i18n-fr="Analyse en cours..." data-i18n-en="Analyzing...">Analyse en cours...</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="scanner-footer p-4 border-t border-[#f0f2f4] dark:border-gray-700 flex gap-3">
            <button type="button" id="btnCancelScanner" class="flex-1 px-4 py-3 rounded-full border border-gray-300 dark:border-gray-600 text-[#111418] dark:text-white font-medium hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" data-i18n="btn_cancel" data-i18n-fr="Annuler" data-i18n-en="Cancel">
                Annuler
            </button>
            <button type="button" id="btnConfirmPassport" class="flex-1 px-4 py-3 rounded-full bg-primary text-white font-medium hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled aria-label="Confirmer / Confirm">
                <span class="flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">check</span>
                    <span data-i18n="btn_confirm" data-i18n-fr="Confirmer" data-i18n-en="Confirm">Confirmer</span>
                </span>
            </button>
        </div>
    </div>
</div>

