/**
 * Service Worker - Chatbot Visa CI
 * Gère le cache et le fonctionnement hors-ligne
 * 
 * @version 1.0.0
 */

const CACHE_NAME = 'visa-chatbot-v1';
const RUNTIME_CACHE = 'visa-chatbot-runtime';

// Fichiers à mettre en cache au moment de l'installation
const STATIC_ASSETS = [
    './',
    './index.html',
    './css/chatbot.css',
    './css/forms.css',
    './js/chatbot.js',
    './js/workflow-client.js',
    './js/passport-scanner.js',
    './js/autosave.js',
    './js/validators.js',
    './js/date-picker.js',
    './js/document-checklist.js',
    './manifest.json'
];

// URLs à toujours récupérer du réseau (API endpoints)
const NETWORK_ONLY = [
    '/php/',
    '/api-handler.php'
];

/**
 * Installation du Service Worker
 */
self.addEventListener('install', (event) => {
    console.log('[SW] Installation...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Cache des fichiers statiques');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('[SW] Installation terminée');
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('[SW] Erreur installation:', error);
            })
    );
});

/**
 * Activation du Service Worker
 */
self.addEventListener('activate', (event) => {
    console.log('[SW] Activation...');
    
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        // Supprimer les anciens caches
                        if (cacheName !== CACHE_NAME && cacheName !== RUNTIME_CACHE) {
                            console.log('[SW] Suppression ancien cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('[SW] Activation terminée');
                return self.clients.claim();
            })
    );
});

/**
 * Interception des requêtes
 */
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    
    // Ignorer les requêtes non-GET
    if (event.request.method !== 'GET') {
        return;
    }
    
    // Requêtes API - Network First avec fallback
    if (isNetworkOnly(url.pathname)) {
        event.respondWith(networkFirst(event.request));
        return;
    }
    
    // Fichiers statiques - Cache First
    event.respondWith(cacheFirst(event.request));
});

/**
 * Vérifie si l'URL doit être récupérée uniquement du réseau
 */
function isNetworkOnly(pathname) {
    return NETWORK_ONLY.some(pattern => pathname.includes(pattern));
}

/**
 * Stratégie Cache First
 * Essaie le cache, puis le réseau
 */
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) {
        // Mettre à jour le cache en arrière-plan
        updateCache(request);
        return cached;
    }
    
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        console.log('[SW] Erreur fetch, fallback offline:', error);
        return offlineFallback(request);
    }
}

/**
 * Stratégie Network First
 * Essaie le réseau, puis le cache
 */
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        
        // Mettre en cache si c'est OK
        if (response.ok) {
            const cache = await caches.open(RUNTIME_CACHE);
            cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        console.log('[SW] Réseau indisponible, fallback cache:', error);
        
        const cached = await caches.match(request);
        if (cached) {
            return cached;
        }
        
        // Retourner une réponse JSON d'erreur pour les APIs
        return new Response(JSON.stringify({
            success: false,
            error: 'Vous êtes hors-ligne. Certaines fonctionnalités ne sont pas disponibles.',
            offline: true
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

/**
 * Met à jour le cache en arrière-plan
 */
async function updateCache(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response);
        }
    } catch (error) {
        // Silently fail - we're just updating cache in background
    }
}

/**
 * Fallback hors-ligne
 */
function offlineFallback(request) {
    // Pour les pages HTML, retourner la page d'accueil
    if (request.headers.get('Accept')?.includes('text/html')) {
        return caches.match('./index.html');
    }
    
    // Pour les autres requêtes, retourner une réponse vide
    return new Response('', { status: 503 });
}

/**
 * Écoute les messages du client
 */
self.addEventListener('message', (event) => {
    if (event.data.action === 'skipWaiting') {
        self.skipWaiting();
    }
    
    if (event.data.action === 'clearCache') {
        caches.keys().then((names) => {
            names.forEach((name) => caches.delete(name));
        });
    }
});

/**
 * Push notifications (pour plus tard)
 */
self.addEventListener('push', (event) => {
    if (!event.data) return;
    
    const data = event.data.json();
    
    const options = {
        body: data.body || 'Mise à jour de votre demande de visa',
        icon: './icons/icon-192.png',
        badge: './icons/badge-72.png',
        vibrate: [100, 50, 100],
        data: {
            url: data.url || './'
        },
        actions: [
            { action: 'open', title: 'Voir' },
            { action: 'close', title: 'Fermer' }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title || 'Visa CI', options)
    );
});

/**
 * Clic sur notification
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    
    if (event.action === 'close') return;
    
    const url = event.notification.data?.url || './';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((windowClients) => {
                // Si une fenêtre est déjà ouverte, la focus
                for (const client of windowClients) {
                    if (client.url.includes('visa-chatbot') && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Sinon ouvrir une nouvelle fenêtre
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

console.log('[SW] Service Worker chargé');

