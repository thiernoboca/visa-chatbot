/**
 * Admin Dashboard JavaScript
 * Gestion de l'interface d'administration du Chatbot Visa CI
 * 
 * @version 1.0.0
 */

// Configuration
const CONFIG = {
    apiEndpoint: '../php/admin-api.php',
    refreshInterval: 30000, // 30 seconds
    pageSize: 10
};

// State
let state = {
    currentPage: 1,
    totalPages: 1,
    currentView: 'dashboard',
    selectedApplication: null,
    filters: {
        status: '',
        workflow: '',
        search: ''
    },
    data: {
        applications: [],
        alerts: [],
        stats: {}
    }
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initFilters();
    loadDashboardData();
    
    // Auto-refresh
    setInterval(loadDashboardData, CONFIG.refreshInterval);
});

// Navigation
function initNavigation() {
    const navItems = document.querySelectorAll('.sidebar-item');
    
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const view = item.getAttribute('href').replace('#', '');
            switchView(view);
        });
    });
    
    // Handle hash changes
    window.addEventListener('hashchange', () => {
        const view = window.location.hash.replace('#', '') || 'dashboard';
        switchView(view);
    });
    
    // Check initial hash
    if (window.location.hash) {
        const view = window.location.hash.replace('#', '');
        switchView(view);
    }
}

function switchView(view) {
    state.currentView = view;
    
    // Update nav
    document.querySelectorAll('.sidebar-item').forEach(item => {
        item.classList.toggle('active', item.getAttribute('href') === '#' + view);
    });
    
    // Hide all views
    document.querySelectorAll('[id^="view-"]').forEach(v => v.classList.add('hidden'));
    
    // Show requested view
    const viewEl = document.getElementById('view-' + view);
    if (viewEl) {
        viewEl.classList.remove('hidden');
    }
    
    // Update title
    const titles = {
        dashboard: { title: 'Dashboard', subtitle: 'Vue d\'ensemble des demandes de visa' },
        applications: { title: 'Demandes', subtitle: 'Gestion des demandes de visa' },
        alerts: { title: 'Alertes Claude', subtitle: 'Anomalies détectées par le superviseur IA' },
        stats: { title: 'Statistiques', subtitle: 'Métriques et performances' },
        settings: { title: 'Paramètres', subtitle: 'Configuration du système' }
    };
    
    const t = titles[view] || { title: view, subtitle: '' };
    document.getElementById('page-title').textContent = t.title;
    document.getElementById('page-subtitle').textContent = t.subtitle;
    
    // Load view-specific data
    if (view === 'alerts') {
        loadAlerts();
    }
}

// Filters
function initFilters() {
    document.getElementById('filter-status')?.addEventListener('change', (e) => {
        state.filters.status = e.target.value;
        state.currentPage = 1;
        loadApplications();
    });
    
    document.getElementById('filter-workflow')?.addEventListener('change', (e) => {
        state.filters.workflow = e.target.value;
        state.currentPage = 1;
        loadApplications();
    });
    
    document.getElementById('search-input')?.addEventListener('input', debounce((e) => {
        state.filters.search = e.target.value;
        state.currentPage = 1;
        loadApplications();
    }, 300));
}

// Data Loading
async function loadDashboardData() {
    try {
        await Promise.all([
            loadStats(),
            loadApplications()
        ]);
    } catch (error) {
        console.error('Error loading dashboard data:', error);
    }
}

async function loadStats() {
    try {
        const response = await fetch(`${CONFIG.apiEndpoint}?action=stats`);
        const result = await response.json();
        
        if (result.success) {
            state.data.stats = result.data;
            updateStatsUI(result.data);
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadApplications() {
    try {
        const params = new URLSearchParams({
            action: 'list',
            page: state.currentPage,
            limit: CONFIG.pageSize,
            status: state.filters.status,
            workflow: state.filters.workflow,
            search: state.filters.search
        });
        
        const response = await fetch(`${CONFIG.apiEndpoint}?${params}`);
        const result = await response.json();
        
        if (result.success) {
            state.data.applications = result.data;
            state.totalPages = result.pagination?.total_pages || 1;
            updateApplicationsUI(result.data);
            updatePaginationUI(result.pagination);
        }
    } catch (error) {
        console.error('Error loading applications:', error);
        // Show mock data for testing
        showMockData();
    }
}

async function loadAlerts() {
    try {
        const response = await fetch(`${CONFIG.apiEndpoint}?action=alerts`);
        const result = await response.json();
        
        if (result.success) {
            state.data.alerts = result.data;
            updateAlertsUI(result.data);
        }
    } catch (error) {
        console.error('Error loading alerts:', error);
        showMockAlerts();
    }
}

// UI Updates
function updateStatsUI(stats) {
    document.getElementById('stat-total').textContent = stats.total || 0;
    document.getElementById('stat-pending').textContent = stats.pending || 0;
    document.getElementById('stat-approved').textContent = stats.approved || 0;
    document.getElementById('stat-priority').textContent = stats.priority || 0;
    document.getElementById('pending-count').textContent = stats.pending || 0;
    document.getElementById('alert-count').textContent = stats.alerts || 0;
}

function updateApplicationsUI(applications) {
    const tbody = document.getElementById('applications-table');
    
    if (!applications || applications.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                    <span class="material-symbols-outlined text-4xl mb-2">folder_off</span>
                    <p>Aucune demande trouvée</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = applications.map(app => `
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
            <td class="px-6 py-4">
                <span class="font-mono font-medium text-primary">${app.reference || app.id}</span>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center text-gray-500 font-medium">
                        ${getInitials(app.name)}
                    </div>
                    <span class="font-medium">${app.name || 'N/A'}</span>
                </div>
            </td>
            <td class="px-6 py-4">${app.nationality || '-'}</td>
            <td class="px-6 py-4">
                ${app.workflow === 'PRIORITY' 
                    ? '<span class="inline-flex items-center gap-1 text-orange-600 dark:text-orange-400 font-medium"><span class="material-symbols-outlined text-sm">bolt</span> PRIORITY</span>'
                    : '<span class="text-gray-600 dark:text-gray-400">STANDARD</span>'
                }
            </td>
            <td class="px-6 py-4">
                <span class="status-badge status-${app.status || 'pending'}">${getStatusLabel(app.status)}</span>
            </td>
            <td class="px-6 py-4 text-gray-500">${formatDate(app.created_at)}</td>
            <td class="px-6 py-4">
                <div class="flex gap-2">
                    <button onclick="viewApplication('${app.id}')" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors" title="Voir détails">
                        <span class="material-symbols-outlined text-lg">visibility</span>
                    </button>
                    <button onclick="downloadReceipt('${app.id}')" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors" title="Télécharger récépissé">
                        <span class="material-symbols-outlined text-lg">download</span>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function updateAlertsUI(alerts) {
    const container = document.getElementById('alerts-container');
    
    if (!alerts || alerts.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12 text-gray-500">
                <span class="material-symbols-outlined text-4xl mb-2">check_circle</span>
                <p>Aucune alerte en cours</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = alerts.map(alert => `
        <div class="border ${alert.severity === 'high' ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20' : 'border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-900/20'} rounded-xl p-4">
            <div class="flex items-start gap-4">
                <div class="p-2 ${alert.severity === 'high' ? 'bg-red-100 dark:bg-red-900/30 text-red-600' : 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600'} rounded-lg">
                    <span class="material-symbols-outlined">${alert.severity === 'high' ? 'error' : 'warning'}</span>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="font-mono font-medium text-primary">${alert.reference}</span>
                        <span class="text-sm text-gray-500">${formatDate(alert.detected_at)}</span>
                    </div>
                    <p class="font-medium mb-1">${alert.type}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">${alert.message}</p>
                    ${alert.details ? `<p class="text-xs text-gray-500 mt-2">${alert.details}</p>` : ''}
                </div>
                <div class="flex gap-2">
                    <button onclick="viewApplication('${alert.application_id}')" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Examiner
                    </button>
                    <button onclick="dismissAlert('${alert.id}')" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        Ignorer
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

function updatePaginationUI(pagination) {
    if (!pagination) return;
    
    const { page, total, total_pages } = pagination;
    const start = (page - 1) * CONFIG.pageSize + 1;
    const end = Math.min(page * CONFIG.pageSize, total);
    
    document.getElementById('showing-start').textContent = total > 0 ? start : 0;
    document.getElementById('showing-end').textContent = end;
    document.getElementById('total-count').textContent = total;
    
    document.getElementById('btn-prev').disabled = page <= 1;
    document.getElementById('btn-next').disabled = page >= total_pages;
}

// Application Actions
async function viewApplication(id) {
    try {
        const response = await fetch(`${CONFIG.apiEndpoint}?action=detail&id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            state.selectedApplication = result.data;
            showDetailModal(result.data);
        }
    } catch (error) {
        console.error('Error loading application details:', error);
        // Show mock detail for testing
        showMockDetail(id);
    }
}

function showDetailModal(app) {
    const content = document.getElementById('detail-content');
    
    content.innerHTML = `
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-2xl font-bold">${app.reference || app.id}</h4>
                    <p class="text-sm text-gray-500">Créé le ${formatDate(app.created_at, true)}</p>
                </div>
                <div>
                    <span class="status-badge status-${app.status || 'pending'}">${getStatusLabel(app.status)}</span>
                    ${app.workflow === 'PRIORITY' 
                        ? '<span class="ml-2 inline-flex items-center gap-1 px-3 py-1 bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 rounded-full text-sm font-medium"><span class="material-symbols-outlined text-sm">bolt</span> PRIORITY</span>'
                        : ''
                    }
                </div>
            </div>
            
            <!-- Personal Info -->
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <h5 class="font-semibold mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">person</span>
                    Informations personnelles
                </h5>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 uppercase">Nom complet</p>
                        <p class="font-medium">${app.name || 'N/A'}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">Nationalité</p>
                        <p class="font-medium">${app.nationality || 'N/A'}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">N° Passeport</p>
                        <p class="font-mono font-medium">${app.passport_number || 'N/A'}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">Type de passeport</p>
                        <p class="font-medium">${app.passport_type || 'ORDINAIRE'}</p>
                    </div>
                </div>
            </div>
            
            <!-- Contact -->
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <h5 class="font-semibold mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">mail</span>
                    Contact
                </h5>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 uppercase">Email</p>
                        <p class="font-medium">${app.email || 'N/A'}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">Téléphone</p>
                        <p class="font-medium">${app.phone || 'N/A'}</p>
                    </div>
                </div>
            </div>
            
            <!-- Trip -->
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <h5 class="font-semibold mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">flight</span>
                    Voyage
                </h5>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 uppercase">Arrivée</p>
                        <p class="font-medium">${app.arrival_date || 'N/A'}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">Départ</p>
                        <p class="font-medium">${app.departure_date || 'N/A'}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">Motif</p>
                        <p class="font-medium">${app.purpose || 'TOURISME'}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">Pays de résidence</p>
                        <p class="font-medium">${app.residence_country || 'N/A'}</p>
                    </div>
                </div>
            </div>
            
            <!-- Documents -->
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <h5 class="font-semibold mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">folder</span>
                    Documents
                </h5>
                <div class="grid grid-cols-3 gap-3">
                    ${(app.documents || []).map(doc => `
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="material-symbols-outlined text-gray-400">${getDocIcon(doc.type)}</span>
                                <span class="text-sm font-medium">${doc.type}</span>
                            </div>
                            <button class="text-xs text-primary hover:underline">Voir</button>
                        </div>
                    `).join('') || '<p class="text-gray-500 col-span-3">Aucun document</p>'}
                </div>
            </div>
            
            <!-- AI Analysis -->
            ${app.claude_validation ? `
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
                    <h5 class="font-semibold mb-3 flex items-center gap-2 text-blue-700 dark:text-blue-400">
                        <span class="material-symbols-outlined">psychology</span>
                        Analyse Claude
                    </h5>
                    <p class="text-sm">${app.claude_validation.summary || 'Analyse en cours...'}</p>
                    ${app.claude_validation.warnings?.length > 0 ? `
                        <div class="mt-3 space-y-2">
                            ${app.claude_validation.warnings.map(w => `
                                <div class="flex items-center gap-2 text-yellow-600 dark:text-yellow-400 text-sm">
                                    <span class="material-symbols-outlined text-sm">warning</span>
                                    ${w}
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('detail-modal').classList.remove('hidden');
}

function closeDetailModal() {
    document.getElementById('detail-modal').classList.add('hidden');
    state.selectedApplication = null;
}

async function approveApplication() {
    if (!state.selectedApplication) return;
    
    try {
        const response = await fetch(CONFIG.apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_status',
                id: state.selectedApplication.id,
                status: 'approved'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeDetailModal();
            loadApplications();
            showNotification('Demande approuvée avec succès', 'success');
        }
    } catch (error) {
        console.error('Error approving application:', error);
        showNotification('Erreur lors de l\'approbation', 'error');
    }
}

async function rejectApplication() {
    if (!state.selectedApplication) return;
    
    const reason = prompt('Motif du rejet:');
    if (!reason) return;
    
    try {
        const response = await fetch(CONFIG.apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_status',
                id: state.selectedApplication.id,
                status: 'rejected',
                reason: reason
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeDetailModal();
            loadApplications();
            showNotification('Demande rejetée', 'info');
        }
    } catch (error) {
        console.error('Error rejecting application:', error);
        showNotification('Erreur lors du rejet', 'error');
    }
}

async function dismissAlert(id) {
    try {
        const response = await fetch(CONFIG.apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'dismiss_alert', id })
        });
        
        if (response.ok) {
            loadAlerts();
        }
    } catch (error) {
        console.error('Error dismissing alert:', error);
    }
}

function downloadReceipt(id) {
    window.open(`${CONFIG.apiEndpoint}?action=download_receipt&id=${id}`, '_blank');
}

// Pagination
function prevPage() {
    if (state.currentPage > 1) {
        state.currentPage--;
        loadApplications();
    }
}

function nextPage() {
    if (state.currentPage < state.totalPages) {
        state.currentPage++;
        loadApplications();
    }
}

// Refresh
function refreshData() {
    loadDashboardData();
    showNotification('Données actualisées', 'info');
}

// Theme
function toggleTheme() {
    document.documentElement.classList.toggle('dark');
    const isDark = document.documentElement.classList.contains('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    document.getElementById('theme-icon').textContent = isDark ? 'light_mode' : 'dark_mode';
}

// Load saved theme
if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
    document.getElementById('theme-icon').textContent = 'light_mode';
}

// Utilities
function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
}

function getStatusLabel(status) {
    const labels = {
        pending: 'En attente',
        processing: 'En cours',
        approved: 'Approuvé',
        rejected: 'Rejeté'
    };
    return labels[status] || status;
}

function getDocIcon(type) {
    const icons = {
        passport: 'badge',
        photo: 'photo_camera',
        vaccination: 'medical_services',
        ticket: 'airplane_ticket',
        hotel: 'hotel'
    };
    return icons[type?.toLowerCase()] || 'description';
}

function formatDate(dateStr, full = false) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    if (full) {
        return date.toLocaleDateString('fr-FR', { 
            day: '2-digit', month: 'long', year: 'numeric', 
            hour: '2-digit', minute: '2-digit' 
        });
    }
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function showNotification(message, type = 'info') {
    // Simple notification - could be enhanced
    console.log(`[${type.toUpperCase()}] ${message}`);
}

// Mock data for testing when API not available
function showMockData() {
    const mockApps = [
        { id: 'CIV-2025-A1B2C3', reference: 'CIV-2025-A1B2C3', name: 'Jean DUPONT', nationality: 'Éthiopie', workflow: 'STANDARD', status: 'pending', created_at: new Date().toISOString() },
        { id: 'CIV-2025-D4E5F6', reference: 'CIV-2025-D4E5F6', name: 'Marie KONÉ', nationality: 'Kenya', workflow: 'PRIORITY', status: 'processing', created_at: new Date(Date.now() - 86400000).toISOString() },
        { id: 'CIV-2025-G7H8I9', reference: 'CIV-2025-G7H8I9', name: 'Ahmed HASSAN', nationality: 'Djibouti', workflow: 'STANDARD', status: 'approved', created_at: new Date(Date.now() - 172800000).toISOString() },
    ];
    
    state.data.applications = mockApps;
    updateApplicationsUI(mockApps);
    updatePaginationUI({ page: 1, total: 3, total_pages: 1 });
    updateStatsUI({ total: 3, pending: 1, approved: 1, priority: 1, alerts: 0 });
}

function showMockAlerts() {
    const mockAlerts = [
        { id: '1', reference: 'CIV-2025-X1Y2Z3', application_id: 'X1Y2Z3', type: 'Incohérence de données', message: 'Le nom sur le passeport diffère du nom sur le billet d\'avion', severity: 'high', detected_at: new Date().toISOString() },
        { id: '2', reference: 'CIV-2025-A9B8C7', application_id: 'A9B8C7', type: 'Document suspect', message: 'La qualité du scan du passeport est anormalement basse', severity: 'medium', detected_at: new Date(Date.now() - 3600000).toISOString() },
    ];
    
    state.data.alerts = mockAlerts;
    updateAlertsUI(mockAlerts);
}

function showMockDetail(id) {
    const mockApp = {
        id: id,
        reference: 'CIV-2025-' + id.toUpperCase(),
        name: 'Jean DUPONT',
        nationality: 'Éthiopie',
        passport_number: 'AB1234567',
        passport_type: 'ORDINAIRE',
        email: 'jean.dupont@example.com',
        phone: '+251 912 345 678',
        arrival_date: '15/01/2025',
        departure_date: '30/01/2025',
        purpose: 'TOURISME',
        residence_country: 'Éthiopie',
        workflow: 'STANDARD',
        status: 'pending',
        created_at: new Date().toISOString(),
        documents: [
            { type: 'Passport' },
            { type: 'Photo' },
            { type: 'Vaccination' }
        ],
        claude_validation: {
            summary: 'Dossier complet. Aucune anomalie détectée.',
            warnings: []
        }
    };
    
    state.selectedApplication = mockApp;
    showDetailModal(mockApp);
}

