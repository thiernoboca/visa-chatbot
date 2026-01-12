<!-- ===== MODALS ===== -->

<!-- Upload Modal (Glassmorphism) -->
<div id="upload-modal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 transition-opacity duration-300" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="upload-modal-title">
    <div class="glass-panel w-full max-w-lg rounded-3xl p-6 sm:p-8 shadow-2xl relative animate-enter">

        <button id="close-upload-modal" class="absolute top-4 right-4 p-2 rounded-full hover:bg-gray-100/50 dark:hover:bg-white/10 transition-colors text-gray-500" aria-label="Fermer / Close" data-i18n-aria="close_upload_modal" data-i18n-aria-fr="Fermer la fenêtre d'import" data-i18n-aria-en="Close upload window">
            <span class="material-symbols-outlined" aria-hidden="true">close</span>
        </button>

        <div class="text-center mb-8">
            <div class="size-16 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4 text-primary">
                <span class="material-symbols-outlined text-3xl">cloud_upload</span>
            </div>
            <h3 id="upload-modal-title" class="text-2xl font-display font-bold text-gray-900 dark:text-white mb-2">
                Importer Document
            </h3>
            <p class="text-gray-500 dark:text-gray-400 text-sm">
                JPG, PNG ou PDF acceptés (Max 5MB)
            </p>
        </div>

        <!-- Drop Zone -->
        <div id="drop-zone" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-2xl p-8 mb-6 text-center hover:border-primary hover:bg-primary/5 transition-all cursor-pointer relative group">
            <input type="file" id="file-input" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept="image/*,.pdf" />
            <div class="group-hover:scale-105 transition-transform duration-300">
                <span class="material-symbols-outlined text-4xl text-gray-400 group-hover:text-primary mb-3 block">folder_open</span>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-300">
                    Glissez votre fichier ici ou <span class="text-primary underline">cliquez pour parcourir</span>
                </p>
            </div>
        </div>

        <!-- Preview & Processing Handled by JS appending here -->
        <div id="upload-preview" class="hidden mb-6 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 relative group">
            <img id="preview-image" src="" alt="Aperçu" class="w-full h-48 object-contain bg-gray-50 dark:bg-gray-800" />
            <button id="clear-preview" class="absolute top-2 right-2 size-8 bg-black/50 hover:bg-black/70 text-white rounded-full flex items-center justify-center backdrop-blur-md transition-all opacity-0 group-hover:opacity-100" aria-label="Supprimer / Clear" data-i18n-aria="clear_preview" data-i18n-aria-fr="Supprimer l'aperçu" data-i18n-aria-en="Clear preview">
                <span class="material-symbols-outlined text-sm" aria-hidden="true">close</span>
            </button>
        </div>

        <div id="upload-processing" class="hidden flex flex-col items-center justify-center py-6">
            <div class="size-10 border-4 border-primary/30 border-t-primary rounded-full animate-spin mb-3"></div>
            <p class="text-sm font-medium text-primary">Analyse intelligente en cours...</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <button id="webcam-btn" class="py-3.5 px-6 rounded-xl border border-gray-200 dark:border-gray-700/50 font-bold text-sm hover:bg-gray-50 dark:hover:bg-white/5 transition-all flex items-center justify-center gap-2 text-gray-700 dark:text-gray-200" aria-label="Webcam" data-i18n-aria="webcam_capture" data-i18n-aria-fr="Capturer avec la webcam" data-i18n-aria-en="Capture with webcam">
                <span class="material-symbols-outlined" aria-hidden="true">photo_camera</span>
                <span data-i18n="btn_webcam" data-i18n-fr="Webcam" data-i18n-en="Webcam">Webcam</span>
            </button>
            <button id="confirm-upload-btn" class="btn-premium py-3.5 px-6 rounded-xl font-bold text-sm text-white shadow-glow disabled:opacity-50 disabled:shadow-none transition-all flex items-center justify-center gap-2" disabled aria-label="Confirmer / Confirm" data-i18n-aria="confirm_upload" data-i18n-aria-fr="Confirmer l'import" data-i18n-aria-en="Confirm upload">
                <span class="material-symbols-outlined" aria-hidden="true">check</span>
                <span data-i18n="btn_confirm" data-i18n-fr="Confirmer" data-i18n-en="Confirm">Confirmer</span>
            </button>
        </div>
    </div>
</div>

<!-- Passport Data Confirmation Modal -->
<div id="passport-confirm-modal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 overflow-y-auto" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="passport-modal-title">
    <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-3xl shadow-2xl relative animate-enter overflow-hidden my-8">

        <!-- Header -->
        <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800">
            <h2 id="passport-modal-title" class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-primary" aria-hidden="true">badge</span>
                <span data-i18n="passport_details_title" data-i18n-fr="Détails du Passeport" data-i18n-en="Passport Details">Détails du Passeport</span>
            </h2>
            <button id="close-passport-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 p-2 rounded-full transition-colors" aria-label="Fermer / Close" data-i18n-aria="close_passport_modal" data-i18n-aria-fr="Fermer les détails du passeport" data-i18n-aria-en="Close passport details">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>

        <!-- Document Preview Snippet -->
        <div class="px-6 pt-6 pb-2">
            <p class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wide">Document Source</p>
            <div id="passport-preview-container" class="relative w-full h-24 rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 group cursor-zoom-in">
                <img id="passport-preview-image" src="" alt="Aperçu passeport" class="w-full h-full object-cover opacity-90 group-hover:scale-105 transition-transform duration-500" />
                <div class="absolute inset-0 bg-black/10 group-hover:bg-black/0 transition-colors"></div>
                <div class="absolute bottom-2 right-2 bg-black/60 text-white text-[10px] px-2 py-1 rounded backdrop-blur-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-xs">zoom_in</span> Cliquer pour agrandir
                </div>
            </div>
        </div>

        <!-- Form Fields -->
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5" id="passport-fields-container">
            <!-- Field: Surname -->
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide" for="ocr-surname">Nom</label>
                <div class="relative group">
                    <input class="ocr-field block w-full rounded-xl border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-white px-4 py-3 font-medium focus:border-primary focus:ring-primary focus:bg-white dark:focus:bg-gray-700 transition-all pr-10"
                           id="ocr-surname" name="surname" type="text" data-field="surname" />
                    <span class="confidence-indicator absolute right-3 top-3 text-success material-symbols-outlined text-lg hidden" title="Haute confiance">check_circle</span>
                </div>
            </div>

            <!-- Field: Given Names -->
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide" for="ocr-given-names">Prénoms</label>
                <div class="relative group">
                    <input class="ocr-field block w-full rounded-xl border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-white px-4 py-3 font-medium focus:border-primary focus:ring-primary focus:bg-white dark:focus:bg-gray-700 transition-all pr-10"
                           id="ocr-given-names" name="given_names" type="text" data-field="given_names" />
                    <span class="confidence-indicator absolute right-3 top-3 text-success material-symbols-outlined text-lg hidden">check_circle</span>
                </div>
            </div>

            <!-- Field: Passport Number -->
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide" for="ocr-passport-number">Numéro de Passeport</label>
                <div class="relative group">
                    <input class="ocr-field block w-full rounded-xl border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-white px-4 py-3 font-medium focus:border-primary focus:ring-primary focus:bg-white dark:focus:bg-gray-700 transition-all pr-10"
                           id="ocr-passport-number" name="passport_number" type="text" data-field="passport_number" />
                    <span class="confidence-indicator absolute right-3 top-3 text-success material-symbols-outlined text-lg hidden">check_circle</span>
                </div>
            </div>

            <!-- Field: Nationality -->
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide" for="ocr-nationality">Nationalité</label>
                <div class="relative group">
                    <div class="absolute left-3 top-3.5 flex items-center pointer-events-none">
                        <span class="material-symbols-outlined text-gray-400 text-lg">public</span>
                    </div>
                    <input class="ocr-field block w-full rounded-xl border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-white pl-10 pr-4 py-3 font-medium focus:border-primary focus:ring-primary focus:bg-white dark:focus:bg-gray-700 transition-all"
                           id="ocr-nationality" name="nationality" type="text" data-field="nationality" />
                </div>
            </div>

            <!-- Field: Date of Birth -->
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide" for="ocr-dob">Date de Naissance</label>
                <div class="relative group">
                    <input class="ocr-field block w-full rounded-xl border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-white px-4 py-3 font-medium focus:border-primary focus:ring-primary focus:bg-white dark:focus:bg-gray-700 transition-all"
                           id="ocr-dob" name="date_of_birth" type="date" data-field="date_of_birth" />
                </div>
            </div>

            <!-- Field: Expiry Date -->
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide" for="ocr-expiry">Date d'Expiration</label>
                <div class="relative group">
                    <input class="ocr-field block w-full rounded-xl border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-white px-4 py-3 font-medium focus:border-primary focus:ring-primary focus:bg-white dark:focus:bg-gray-700 transition-all"
                           id="ocr-expiry" name="expiry_date" type="date" data-field="expiry_date" />
                </div>
            </div>

            <!-- Field: Sex -->
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide" for="ocr-sex">Sexe</label>
                <div class="relative group">
                    <select class="ocr-field block w-full rounded-xl border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-white px-4 py-3 font-medium focus:border-primary focus:ring-primary focus:bg-white dark:focus:bg-gray-700 transition-all"
                            id="ocr-sex" name="sex" data-field="sex">
                        <option value="">Sélectionner</option>
                        <option value="M">Masculin</option>
                        <option value="F">Féminin</option>
                    </select>
                </div>
            </div>

            <!-- Field: Place of Birth -->
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide" for="ocr-pob">Lieu de Naissance</label>
                <div class="relative group">
                    <input class="ocr-field block w-full rounded-xl border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-white px-4 py-3 font-medium focus:border-primary focus:ring-primary focus:bg-white dark:focus:bg-gray-700 transition-all"
                           id="ocr-pob" name="place_of_birth" type="text" data-field="place_of_birth" />
                </div>
            </div>
        </div>

        <!-- Validation Messages Container -->
        <div id="ocr-validation-messages" class="px-6 pb-4 hidden">
            <div class="p-3 bg-warning/10 border border-warning/20 rounded-xl text-warning text-sm flex items-start gap-2">
                <span class="material-symbols-outlined text-lg shrink-0">warning</span>
                <span id="ocr-validation-text">Veuillez vérifier les champs marqués.</span>
            </div>
        </div>

        <!-- Action Footer -->
        <div class="px-6 py-5 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row gap-4 justify-between items-center">
            <button id="ocr-edit-btn" class="w-full sm:w-auto px-6 py-3 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 font-bold hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center justify-center gap-2" aria-label="Corriger / Edit" data-i18n-aria="edit_passport_data" data-i18n-aria-fr="Modifier les informations du passeport" data-i18n-aria-en="Edit passport information">
                <span class="material-symbols-outlined text-lg" aria-hidden="true">edit</span>
                <span data-i18n="btn_edit_data" data-i18n-fr="Non, corriger" data-i18n-en="No, edit">Non, corriger</span>
            </button>
            <button id="ocr-confirm-btn" class="w-full sm:w-auto px-8 py-3 rounded-full bg-primary text-white font-bold hover:bg-primary-dark transition-colors shadow-lg shadow-primary/30 flex items-center justify-center gap-2" aria-label="Confirmer / Confirm" data-i18n-aria="confirm_passport_data" data-i18n-aria-fr="Confirmer les informations du passeport" data-i18n-aria-en="Confirm passport information">
                <span class="material-symbols-outlined text-lg" aria-hidden="true">check</span>
                <span data-i18n="btn_confirm_data" data-i18n-fr="Oui, c'est correct" data-i18n-en="Yes, it's correct">Oui, c'est correct</span>
            </button>
        </div>

        <!-- Disclaimer -->
        <div class="px-6 py-4 bg-gray-50/50 dark:bg-gray-900/30">
            <p class="text-center text-xs text-gray-400 dark:text-gray-500">
                En confirmant, vous certifiez que les informations correspondent à votre document de voyage.
            </p>
        </div>
    </div>
</div>

<!-- Notifications -->
<div id="notification-container" class="fixed top-24 right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

