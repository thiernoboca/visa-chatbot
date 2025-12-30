/**
 * UX Enhancements Module
 * Premium user experience features
 *
 * @version 1.0.0
 * @module UXEnhancements
 */

// ============================================
// 1. TYPING INDICATOR
// ============================================

export class TypingIndicator {
    constructor(container) {
        this.container = container;
        this.element = null;
        this.isVisible = false;
    }

    show(name = 'Aya') {
        if (this.isVisible) return;

        this.element = document.createElement('div');
        this.element.className = 'typing-indicator';
        this.element.innerHTML = `
            <div class="typing-indicator-avatar">
                <div class="avatar-circle">
                    <span class="material-symbols-outlined">smart_toy</span>
                </div>
                <div class="status-dot"></div>
            </div>
            <div class="typing-indicator-content">
                <span class="typing-indicator-name">${name} est en train d'écrire...</span>
                <div class="typing-indicator-bubble">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        `;

        this.container?.appendChild(this.element);
        this.isVisible = true;
        this.scrollIntoView();
    }

    hide() {
        if (!this.isVisible || !this.element) return;

        this.element.style.animation = 'fadeOut 0.2s ease-out forwards';
        setTimeout(() => {
            this.element?.remove();
            this.element = null;
            this.isVisible = false;
        }, 200);
    }

    scrollIntoView() {
        this.element?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }
}

// ============================================
// 2. CONFETTI CELEBRATION
// ============================================

export class ConfettiCelebration {
    constructor() {
        this.colors = [
            '#0D5C46', '#10B981', '#F59E0B', '#EF4444',
            '#3B82F6', '#8B5CF6', '#EC4899', '#06B6D4'
        ];
        this.shapes = ['square', 'circle', 'triangle'];
    }

    celebrate(duration = 3000, particleCount = 100) {
        const container = document.createElement('div');
        container.className = 'confetti-container';
        document.body.appendChild(container);

        for (let i = 0; i < particleCount; i++) {
            setTimeout(() => {
                this.createParticle(container);
            }, Math.random() * 500);
        }

        setTimeout(() => {
            container.remove();
        }, duration + 1000);
    }

    createParticle(container) {
        const particle = document.createElement('div');
        const shape = this.shapes[Math.floor(Math.random() * this.shapes.length)];
        const color = this.colors[Math.floor(Math.random() * this.colors.length)];

        particle.className = `confetti ${shape}`;
        particle.style.backgroundColor = color;
        particle.style.left = `${Math.random() * 100}%`;
        particle.style.animationDuration = `${2 + Math.random() * 2}s`;
        particle.style.animationDelay = `${Math.random() * 0.5}s`;

        container.appendChild(particle);

        setTimeout(() => particle.remove(), 4000);
    }

    showSuccess(message = 'Parfait !') {
        // Show confetti
        this.celebrate(3000, 80);

        // Show celebration badge
        const celebration = document.createElement('div');
        celebration.className = 'success-celebration';
        celebration.innerHTML = `
            <div class="success-celebration-icon">
                <span class="material-symbols-outlined">check</span>
            </div>
            <div class="success-celebration-text">${message}</div>
        `;

        document.body.appendChild(celebration);

        setTimeout(() => {
            celebration.style.animation = 'fadeOut 0.5s ease-out forwards';
            setTimeout(() => celebration.remove(), 500);
        }, 2000);
    }
}

// ============================================
// 3. DOCUMENT CHECKLIST
// ============================================

export class DocumentChecklist {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            title: 'Votre dossier',
            ...options
        };
        this.items = [];
        this.element = null;
    }

    init(documents) {
        this.items = documents.map(doc => ({
            id: doc.id,
            name: doc.name,
            required: doc.required !== false,
            completed: doc.completed || false,
            current: doc.current || false
        }));
        this.render();
    }

    render() {
        const completedCount = this.items.filter(i => i.completed).length;
        const totalCount = this.items.length;
        const progress = totalCount > 0 ? Math.round((completedCount / totalCount) * 100) : 0;

        const html = `
            <div class="document-checklist">
                <div class="checklist-header">
                    <div class="checklist-header-title">
                        <span class="material-symbols-outlined">folder_open</span>
                        ${this.options.title}
                    </div>
                    <div class="checklist-header-count">${completedCount}/${totalCount}</div>
                </div>
                <div class="checklist-progress">
                    <div class="checklist-progress-bar">
                        <div class="checklist-progress-fill" style="width: ${progress}%"></div>
                    </div>
                    <div class="checklist-progress-text">
                        <span>${progress}% complété</span>
                        <span>${this.getTimeEstimate()} restantes</span>
                    </div>
                </div>
                <div class="checklist-items">
                    ${this.items.map(item => this.renderItem(item)).join('')}
                </div>
            </div>
        `;

        if (this.element) {
            this.element.outerHTML = html;
        } else {
            this.container.innerHTML = html;
        }
        this.element = this.container.querySelector('.document-checklist');
    }

    renderItem(item) {
        const statusClass = item.completed ? 'completed' : (item.current ? 'current' : '');
        const icon = item.completed ? 'check' : (item.current ? 'arrow_forward' : '');
        const badge = item.required
            ? '<span class="checklist-item-badge required">Requis</span>'
            : '<span class="checklist-item-badge optional">Optionnel</span>';

        return `
            <div class="checklist-item ${statusClass}" data-id="${item.id}">
                <div class="checklist-item-checkbox">
                    ${icon ? `<span class="material-symbols-outlined">${icon}</span>` : ''}
                </div>
                <div class="checklist-item-content">
                    <div class="checklist-item-name">${item.name}</div>
                    <div class="checklist-item-status">
                        ${item.completed ? 'Validé' : (item.current ? 'En cours' : 'À compléter')}
                    </div>
                </div>
                ${badge}
            </div>
        `;
    }

    markComplete(id) {
        const item = this.items.find(i => i.id === id);
        if (item) {
            item.completed = true;
            item.current = false;
            this.render();
        }
    }

    setCurrent(id) {
        this.items.forEach(item => {
            item.current = item.id === id;
        });
        this.render();
    }

    getTimeEstimate() {
        const remaining = this.items.filter(i => !i.completed).length;
        const minutesPerItem = 1.5;
        const totalMinutes = Math.ceil(remaining * minutesPerItem);

        if (totalMinutes < 1) return 'moins d\'1 min';
        if (totalMinutes === 1) return '1 min';
        return `${totalMinutes} min`;
    }

    getProgress() {
        const completed = this.items.filter(i => i.completed).length;
        return Math.round((completed / this.items.length) * 100);
    }
}

// ============================================
// 4. VALIDATION DISPLAY
// ============================================

export class ValidationDisplay {
    constructor() {
        this.statusIcons = {
            valid: 'check_circle',
            warning: 'warning',
            invalid: 'cancel',
            pending: 'hourglass_empty'
        };
    }

    createStatusBadge(status, text) {
        const icon = this.statusIcons[status] || 'help';
        return `
            <span class="validation-status ${status}">
                <span class="material-symbols-outlined validation-icon animated">${icon}</span>
                ${text}
            </span>
        `;
    }

    createValidationCard(data) {
        const {
            title = 'Document vérifié',
            subtitle = '',
            icon = 'verified',
            items = [],
            actions = []
        } = data;

        const itemsHtml = items.map(item => `
            <div class="validation-item">
                <div class="validation-item-icon ${item.status}">
                    <span class="material-symbols-outlined">
                        ${this.statusIcons[item.status] || 'check'}
                    </span>
                </div>
                <span class="validation-item-text">${item.text}</span>
            </div>
        `).join('');

        const actionsHtml = actions.map(action => `
            <button class="btn ${action.primary ? 'btn-primary' : 'btn-secondary'} btn-press"
                    data-action="${action.action}">
                ${action.icon ? `<span class="material-symbols-outlined">${action.icon}</span>` : ''}
                ${action.label}
            </button>
        `).join('');

        return `
            <div class="validation-card">
                <div class="validation-card-header">
                    <div class="validation-card-icon">
                        <span class="material-symbols-outlined">${icon}</span>
                    </div>
                    <div>
                        <div class="validation-card-title">${title}</div>
                        ${subtitle ? `<div class="validation-card-subtitle">${subtitle}</div>` : ''}
                    </div>
                </div>
                <div class="validation-card-body">
                    ${itemsHtml}
                </div>
                ${actions.length > 0 ? `<div class="validation-card-actions">${actionsHtml}</div>` : ''}
            </div>
        `;
    }

    createPassportValidation(passportData, validationResults) {
        return this.createValidationCard({
            title: 'Passeport vérifié',
            subtitle: `${passportData.surname} ${passportData.given_names}`,
            icon: 'badge',
            items: [
                {
                    status: validationResults.expiry ? 'valid' : 'invalid',
                    text: validationResults.expiry
                        ? 'Validité supérieure à 6 mois'
                        : 'Passeport expiré ou bientôt expiré'
                },
                {
                    status: validationResults.mrz ? 'valid' : 'warning',
                    text: validationResults.mrz ? 'MRZ lisible' : 'MRZ partiellement lisible'
                },
                {
                    status: 'valid',
                    text: 'Photo conforme'
                }
            ],
            actions: [
                { label: 'Modifier', action: 'modify', icon: 'edit' },
                { label: 'Confirmer', action: 'confirm', icon: 'check', primary: true }
            ]
        });
    }
}

// ============================================
// 5. ENHANCED UPLOAD
// ============================================

export class EnhancedUpload {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            accept: 'image/*,.pdf',
            maxSize: 10 * 1024 * 1024, // 10MB
            title: 'Glissez votre fichier ici',
            subtitle: 'ou cliquez pour sélectionner',
            onUpload: () => {},
            onError: () => {},
            ...options
        };
        this.element = null;
        this.fileInput = null;
        this.currentFile = null;
    }

    render() {
        this.element = document.createElement('div');
        this.element.className = 'upload-zone';
        this.element.innerHTML = `
            <div class="upload-icon-wrapper">
                <span class="material-symbols-outlined upload-icon">cloud_upload</span>
            </div>
            <div class="upload-title">
                <span>${this.options.title}</span>
            </div>
            <div class="upload-subtitle">${this.options.subtitle}</div>
            <div class="upload-formats">
                <span class="upload-format-badge">JPG</span>
                <span class="upload-format-badge">PNG</span>
                <span class="upload-format-badge">PDF</span>
                <span style="color: #94a3b8;">max 10MB</span>
            </div>
            <input type="file" class="hidden" accept="${this.options.accept}">
        `;

        this.fileInput = this.element.querySelector('input[type="file"]');
        this.bindEvents();
        this.container.appendChild(this.element);
        return this;
    }

    bindEvents() {
        // Click to upload
        this.element.addEventListener('click', () => {
            this.fileInput.click();
        });

        // File selected
        this.fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) this.handleFile(file);
        });

        // Drag events
        this.element.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.element.classList.add('drag-over');
        });

        this.element.addEventListener('dragleave', () => {
            this.element.classList.remove('drag-over');
        });

        this.element.addEventListener('drop', (e) => {
            e.preventDefault();
            this.element.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (file) this.handleFile(file);
        });
    }

    handleFile(file) {
        // Validate size
        if (file.size > this.options.maxSize) {
            this.showError('Fichier trop volumineux (max 10MB)');
            return;
        }

        // Validate type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        if (!validTypes.includes(file.type)) {
            this.showError('Format non supporté');
            return;
        }

        this.currentFile = file;
        this.showPreview(file);
        this.options.onUpload(file);
    }

    showPreview(file) {
        const preview = document.createElement('div');
        preview.className = 'upload-preview';

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                preview.innerHTML = `
                    <img src="${e.target.result}" class="upload-preview-image" alt="Preview">
                    <div class="upload-preview-info">
                        <div class="upload-preview-name">${file.name}</div>
                        <div class="upload-preview-size">${this.formatSize(file.size)}</div>
                    </div>
                    <button class="upload-preview-remove" type="button">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                `;
                this.addPreviewEvents(preview);
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = `
                <div class="upload-preview-image" style="display: flex; align-items: center; justify-content: center; background: #f1f5f9;">
                    <span class="material-symbols-outlined" style="font-size: 32px; color: #94a3b8;">description</span>
                </div>
                <div class="upload-preview-info">
                    <div class="upload-preview-name">${file.name}</div>
                    <div class="upload-preview-size">${this.formatSize(file.size)}</div>
                </div>
                <button class="upload-preview-remove" type="button">
                    <span class="material-symbols-outlined">close</span>
                </button>
            `;
            this.addPreviewEvents(preview);
        }

        // Remove existing preview
        const existingPreview = this.container.querySelector('.upload-preview');
        existingPreview?.remove();

        this.element.after(preview);
        this.element.classList.add('success');
    }

    addPreviewEvents(preview) {
        const removeBtn = preview.querySelector('.upload-preview-remove');
        removeBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.clearFile();
        });
    }

    clearFile() {
        this.currentFile = null;
        this.fileInput.value = '';
        this.container.querySelector('.upload-preview')?.remove();
        this.element.classList.remove('success', 'error');
    }

    showError(message) {
        this.element.classList.add('error');
        setTimeout(() => {
            this.element.classList.remove('error');
        }, 2000);
        this.options.onError(message);
    }

    showProgress(percent) {
        let progressContainer = this.container.querySelector('.upload-progress-container');

        if (!progressContainer) {
            progressContainer = document.createElement('div');
            progressContainer.className = 'upload-progress-container';
            progressContainer.innerHTML = `
                <div class="upload-progress-bar">
                    <div class="upload-progress-fill" style="width: 0%"></div>
                </div>
                <div class="upload-progress-text">
                    <span>Analyse en cours...</span>
                    <span class="progress-percent">0%</span>
                </div>
            `;
            this.element.after(progressContainer);
        }

        const fill = progressContainer.querySelector('.upload-progress-fill');
        const percentText = progressContainer.querySelector('.progress-percent');

        fill.style.width = `${percent}%`;
        percentText.textContent = `${percent}%`;

        if (percent >= 100) {
            setTimeout(() => progressContainer.remove(), 500);
        }
    }

    formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
}

// ============================================
// 6. TIME ESTIMATE WIDGET
// ============================================

export class TimeEstimate {
    constructor(container) {
        this.container = container;
        this.element = null;
        this.startTime = Date.now();
    }

    render(minutes) {
        this.element = document.createElement('div');
        this.element.className = 'time-estimate';
        this.update(minutes);
        this.container.appendChild(this.element);
        return this;
    }

    update(minutes) {
        if (!this.element) return;

        const text = minutes < 1
            ? "Moins d'1 minute"
            : `Environ ${minutes} min`;

        this.element.innerHTML = `
            <span class="material-symbols-outlined time-estimate-icon">schedule</span>
            <span>${text} restantes</span>
        `;
    }

    remove() {
        this.element?.remove();
    }
}

// ============================================
// 7. TOAST NOTIFICATIONS
// ============================================

export class ToastNotification {
    constructor() {
        this.container = this.getOrCreateContainer();
    }

    getOrCreateContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = `
                position: fixed;
                bottom: 24px;
                right: 24px;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 12px;
            `;
            document.body.appendChild(container);
        }
        return container;
    }

    show(message, type = 'info', duration = 4000) {
        const icons = {
            success: 'check_circle',
            error: 'error',
            warning: 'warning',
            info: 'info'
        };

        const colors = {
            success: 'var(--ux-success)',
            error: 'var(--ux-error)',
            warning: 'var(--ux-warning)',
            info: 'var(--ux-info)'
        };

        const toast = document.createElement('div');
        toast.style.cssText = `
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.33, 1, 0.68, 1);
            max-width: 360px;
        `;

        toast.innerHTML = `
            <span class="material-symbols-outlined" style="color: ${colors[type]}; font-size: 24px;">
                ${icons[type]}
            </span>
            <span style="flex: 1; font-size: 14px; color: #111418;">${message}</span>
            <button style="background: none; border: none; cursor: pointer; padding: 4px; color: #94a3b8;">
                <span class="material-symbols-outlined" style="font-size: 20px;">close</span>
            </button>
        `;

        const closeBtn = toast.querySelector('button');
        closeBtn.addEventListener('click', () => this.dismiss(toast));

        this.container.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(0)';
            toast.style.opacity = '1';
        });

        // Auto dismiss
        if (duration > 0) {
            setTimeout(() => this.dismiss(toast), duration);
        }

        return toast;
    }

    dismiss(toast) {
        toast.style.transform = 'translateX(100%)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }

    success(message) { return this.show(message, 'success'); }
    error(message) { return this.show(message, 'error'); }
    warning(message) { return this.show(message, 'warning'); }
    info(message) { return this.show(message, 'info'); }
}

// ============================================
// 8. MICRO INTERACTIONS
// ============================================

export class MicroInteractions {
    static init() {
        // Add ripple effect to buttons
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.ripple');
            if (btn) {
                const rect = btn.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                const ripple = document.createElement('span');
                ripple.style.cssText = `
                    position: absolute;
                    background: rgba(255,255,255,0.3);
                    border-radius: 50%;
                    transform: scale(0);
                    animation: ripple-effect 0.6s ease-out;
                    pointer-events: none;
                    left: ${x}px;
                    top: ${y}px;
                    width: 100px;
                    height: 100px;
                    margin-left: -50px;
                    margin-top: -50px;
                `;

                btn.style.position = 'relative';
                btn.style.overflow = 'hidden';
                btn.appendChild(ripple);

                setTimeout(() => ripple.remove(), 600);
            }
        });

        // Add keyframe animation if not exists
        if (!document.getElementById('ripple-styles')) {
            const style = document.createElement('style');
            style.id = 'ripple-styles';
            style.textContent = `
                @keyframes ripple-effect {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    static haptic(type = 'light') {
        if ('vibrate' in navigator) {
            const patterns = {
                light: [10],
                medium: [20],
                heavy: [30],
                success: [10, 50, 10],
                error: [50, 50, 50]
            };
            navigator.vibrate(patterns[type] || patterns.light);
        }
    }
}

// ============================================
// 9. MAIN UX MANAGER
// ============================================

export class UXManager {
    constructor() {
        this.typing = null;
        this.confetti = new ConfettiCelebration();
        this.checklist = null;
        this.validation = new ValidationDisplay();
        this.toast = new ToastNotification();
        this.timeEstimate = null;
    }

    init(chatContainer) {
        this.typing = new TypingIndicator(chatContainer);
        MicroInteractions.init();
        return this;
    }

    // Typing indicator methods
    showTyping(name) {
        this.typing?.show(name);
    }

    hideTyping() {
        this.typing?.hide();
    }

    // Celebration methods
    celebrate(message) {
        this.confetti.showSuccess(message);
    }

    celebrateStep(stepName) {
        this.toast.success(`${stepName} complété !`);
        MicroInteractions.haptic('success');
    }

    // Checklist methods
    initChecklist(container, documents) {
        this.checklist = new DocumentChecklist(container);
        this.checklist.init(documents);
    }

    updateChecklist(documentId, completed = true) {
        if (completed) {
            this.checklist?.markComplete(documentId);
        }
    }

    setCurrentStep(stepId) {
        this.checklist?.setCurrent(stepId);
    }

    // Toast methods
    showToast(message, type = 'info') {
        return this.toast.show(message, type);
    }

    // Time estimate
    showTimeEstimate(container, minutes) {
        this.timeEstimate = new TimeEstimate(container);
        this.timeEstimate.render(minutes);
    }

    updateTimeEstimate(minutes) {
        this.timeEstimate?.update(minutes);
    }
}

// Export singleton instance
export const uxManager = new UXManager();

// Export all classes
export default {
    TypingIndicator,
    ConfettiCelebration,
    DocumentChecklist,
    ValidationDisplay,
    EnhancedUpload,
    TimeEstimate,
    ToastNotification,
    MicroInteractions,
    UXManager,
    uxManager
};
