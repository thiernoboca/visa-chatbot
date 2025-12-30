/**
 * Date Picker Module - Chatbot Visa CI
 * Calendrier intelligent avec validation contextuelle
 * 
 * @version 1.0.0
 */

class DatePicker {
    /**
     * Constructeur
     * @param {Object} options - Options de configuration
     */
    constructor(options = {}) {
        this.config = {
            locale: options.locale || 'fr-FR',
            minDate: options.minDate || null,
            maxDate: options.maxDate || null,
            disabledDates: options.disabledDates || [],
            highlightedDates: options.highlightedDates || [],
            format: options.format || 'YYYY-MM-DD',
            container: options.container || null,
            onSelect: options.onSelect || null,
            type: options.type || 'default' // 'default', 'departure', 'passport-expiry', 'birth'
        };
        
        this.state = {
            currentMonth: new Date(),
            selectedDate: null,
            isOpen: false
        };
        
        this.element = null;
        this.inputElement = null;
        
        if (this.config.container) {
            this.init();
        }
    }
    
    /**
     * Initialise le date picker
     */
    init() {
        this.applyTypePresets();
        this.render();
        this.bindEvents();
    }
    
    /**
     * Applique les presets selon le type
     */
    applyTypePresets() {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        switch (this.config.type) {
            case 'departure':
                // Date de départ: à partir de demain
                const tomorrow = new Date(today);
                tomorrow.setDate(tomorrow.getDate() + 1);
                this.config.minDate = tomorrow;
                break;
                
            case 'passport-expiry':
                // Expiration passeport: au moins 6 mois dans le futur
                const minExpiry = new Date(today);
                minExpiry.setMonth(minExpiry.getMonth() + 6);
                this.config.minDate = minExpiry;
                break;
                
            case 'birth':
                // Date de naissance: dans le passé, max 120 ans
                const minBirth = new Date(today);
                minBirth.setFullYear(minBirth.getFullYear() - 120);
                this.config.minDate = minBirth;
                this.config.maxDate = today;
                break;
        }
    }
    
    /**
     * Attache le date picker à un input
     */
    attachToInput(inputElement) {
        this.inputElement = inputElement;
        
        // Créer le conteneur popup
        const popup = document.createElement('div');
        popup.className = 'datepicker-popup';
        popup.hidden = true;
        inputElement.parentNode?.insertBefore(popup, inputElement.nextSibling);
        
        this.config.container = popup;
        
        // Événements
        inputElement.addEventListener('focus', () => this.open());
        inputElement.addEventListener('click', () => this.open());
        
        // Fermer au clic extérieur
        document.addEventListener('click', (e) => {
            if (!inputElement.contains(e.target) && !popup.contains(e.target)) {
                this.close();
            }
        });
        
        this.init();
    }
    
    /**
     * Ouvre le date picker
     */
    open() {
        if (this.state.isOpen) return;
        
        this.state.isOpen = true;
        this.config.container.hidden = false;
        this.render();
    }
    
    /**
     * Ferme le date picker
     */
    close() {
        this.state.isOpen = false;
        if (this.config.container) {
            this.config.container.hidden = true;
        }
    }
    
    /**
     * Render le calendrier
     */
    render() {
        if (!this.config.container) return;
        
        const month = this.state.currentMonth.getMonth();
        const year = this.state.currentMonth.getFullYear();
        
        const html = `
            <div class="datepicker">
                <div class="datepicker-header">
                    <button type="button" class="datepicker-nav datepicker-prev" aria-label="Mois précédent">‹</button>
                    <span class="datepicker-title">${this.getMonthName(month)} ${year}</span>
                    <button type="button" class="datepicker-nav datepicker-next" aria-label="Mois suivant">›</button>
                </div>
                <div class="datepicker-weekdays">
                    ${this.getWeekdayHeaders().map(d => `<span>${d}</span>`).join('')}
                </div>
                <div class="datepicker-days">
                    ${this.generateDays(year, month)}
                </div>
                ${this.config.type === 'departure' ? `
                    <div class="datepicker-shortcuts">
                        <button type="button" data-days="7">Dans 1 semaine</button>
                        <button type="button" data-days="14">Dans 2 semaines</button>
                        <button type="button" data-days="30">Dans 1 mois</button>
                    </div>
                ` : ''}
            </div>
        `;
        
        this.config.container.innerHTML = html;
    }
    
    /**
     * Génère les jours du mois
     */
    generateDays(year, month) {
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDayOfWeek = (firstDay.getDay() + 6) % 7; // Lundi = 0
        
        let html = '';
        
        // Jours du mois précédent
        const prevMonthLastDay = new Date(year, month, 0).getDate();
        for (let i = startDayOfWeek - 1; i >= 0; i--) {
            const day = prevMonthLastDay - i;
            html += `<button type="button" class="datepicker-day other-month" disabled>${day}</button>`;
        }
        
        // Jours du mois courant
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const date = new Date(year, month, day);
            const isDisabled = this.isDateDisabled(date);
            const isToday = date.getTime() === today.getTime();
            const isSelected = this.state.selectedDate && 
                date.getTime() === this.state.selectedDate.getTime();
            const isHighlighted = this.isDateHighlighted(date);
            
            const classes = ['datepicker-day'];
            if (isDisabled) classes.push('disabled');
            if (isToday) classes.push('today');
            if (isSelected) classes.push('selected');
            if (isHighlighted) classes.push('highlighted');
            
            html += `
                <button type="button" 
                    class="${classes.join(' ')}" 
                    data-date="${date.toISOString().split('T')[0]}"
                    ${isDisabled ? 'disabled' : ''}
                    aria-label="${this.formatDateLong(date)}"
                >${day}</button>
            `;
        }
        
        // Jours du mois suivant
        const totalCells = Math.ceil((startDayOfWeek + lastDay.getDate()) / 7) * 7;
        const remainingDays = totalCells - (startDayOfWeek + lastDay.getDate());
        for (let day = 1; day <= remainingDays; day++) {
            html += `<button type="button" class="datepicker-day other-month" disabled>${day}</button>`;
        }
        
        return html;
    }
    
    /**
     * Vérifie si une date est désactivée
     */
    isDateDisabled(date) {
        if (this.config.minDate && date < this.config.minDate) return true;
        if (this.config.maxDate && date > this.config.maxDate) return true;
        
        const dateStr = date.toISOString().split('T')[0];
        return this.config.disabledDates.includes(dateStr);
    }
    
    /**
     * Vérifie si une date est en surbrillance
     */
    isDateHighlighted(date) {
        const dateStr = date.toISOString().split('T')[0];
        return this.config.highlightedDates.includes(dateStr);
    }
    
    /**
     * Lie les événements
     */
    bindEvents() {
        this.config.container?.addEventListener('click', (e) => {
            const target = e.target;
            
            // Navigation mois
            if (target.classList.contains('datepicker-prev')) {
                this.previousMonth();
            } else if (target.classList.contains('datepicker-next')) {
                this.nextMonth();
            }
            
            // Sélection jour
            if (target.classList.contains('datepicker-day') && !target.disabled) {
                const dateStr = target.dataset.date;
                if (dateStr) {
                    this.selectDate(new Date(dateStr));
                }
            }
            
            // Raccourcis
            if (target.dataset.days) {
                const days = parseInt(target.dataset.days);
                const date = new Date();
                date.setDate(date.getDate() + days);
                this.selectDate(date);
            }
        });
    }
    
    /**
     * Sélectionne une date
     */
    selectDate(date) {
        this.state.selectedDate = date;
        
        // Mettre à jour l'input si attaché
        if (this.inputElement) {
            this.inputElement.value = this.formatDate(date);
        }
        
        // Callback
        if (this.config.onSelect) {
            this.config.onSelect(date, this.formatDate(date));
        }
        
        this.render();
        this.close();
    }
    
    /**
     * Mois précédent
     */
    previousMonth() {
        this.state.currentMonth.setMonth(this.state.currentMonth.getMonth() - 1);
        this.render();
    }
    
    /**
     * Mois suivant
     */
    nextMonth() {
        this.state.currentMonth.setMonth(this.state.currentMonth.getMonth() + 1);
        this.render();
    }
    
    /**
     * Retourne le nom du mois
     */
    getMonthName(month) {
        const months = [
            'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
            'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
        ];
        return months[month];
    }
    
    /**
     * Retourne les en-têtes des jours de la semaine
     */
    getWeekdayHeaders() {
        return ['Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa', 'Di'];
    }
    
    /**
     * Formate une date
     */
    formatDate(date) {
        return date.toISOString().split('T')[0];
    }
    
    /**
     * Formate une date en texte long
     */
    formatDateLong(date) {
        return date.toLocaleDateString(this.config.locale, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    }
    
    /**
     * Définit la date minimum
     */
    setMinDate(date) {
        this.config.minDate = date instanceof Date ? date : new Date(date);
        this.render();
    }
    
    /**
     * Définit la date maximum
     */
    setMaxDate(date) {
        this.config.maxDate = date instanceof Date ? date : new Date(date);
        this.render();
    }
}

// Exposer globalement
window.DatePicker = DatePicker;

