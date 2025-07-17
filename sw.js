// Service Worker for Push Notifications
// LCFTF Trail Status App

const CACHE_NAME = 'lcftf-trail-status-v1';
const urlsToCache = [
    '/',
    '/index.php',
    '/css/style.css',
    '/images/ftf_logo.jpg'
];

// Install service worker and cache resources
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                return cache.addAll(urlsToCache);
            })
    );
});

// Fetch event - serve from cache when offline
self.addEventListener('fetch', function(event) {
    event.respondWith(
        caches.match(event.request)
            .then(function(response) {
                // Return cached version or fetch from network
                return response || fetch(event.request);
            }
        )
    );
});

// Push notification event
self.addEventListener('push', function(event) {
    let notificationData = {
        title: 'Trail Status Update',
        body: 'A trail status has changed',
        icon: '/images/ftf_logo.jpg',
        badge: '/images/ftf_logo.jpg',
        tag: 'trail-status',
        requireInteraction: true,
        actions: [
            {
                action: 'view',
                title: 'View Status',
                icon: '/images/ftf_logo.jpg'
            },
            {
                action: 'close',
                title: 'Close'
            }
        ]
    };

    if (event.data) {
        try {
            const data = event.data.json();
            notificationData = {
                ...notificationData,
                ...data
            };
        } catch (e) {
            console.error('Error parsing push data:', e);
        }
    }

    const promiseChain = self.registration.showNotification(
        notificationData.title,
        {
            body: notificationData.body,
            icon: notificationData.icon,
            badge: notificationData.badge,
            tag: notificationData.tag,
            requireInteraction: notificationData.requireInteraction,
            actions: notificationData.actions,
            data: notificationData.data
        }
    );

    event.waitUntil(promiseChain);
});

// Notification click event
self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    if (event.action === 'view' || !event.action) {
        // Open the trail status page
        event.waitUntil(
            clients.openWindow('/trailstatus/')
        );
    }
    // 'close' action just closes the notification (default behavior)
});

// Background sync for offline functionality (optional)
self.addEventListener('sync', function(event) {
    if (event.tag === 'background-sync') {
        event.waitUntil(doBackgroundSync());
    }
});

function doBackgroundSync() {
    // Implement background sync logic if needed
    return Promise.resolve();
}
