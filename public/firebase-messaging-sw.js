// Firebase Cloud Messaging Service Worker
// Uses compat SDK because service workers cannot use ES modules
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');

let firebaseConfig = null;

// Receive config from the main app
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'FIREBASE_CONFIG') {
        firebaseConfig = event.data.config;
        initFirebase();
    }
});

function initFirebase() {
    if (!firebaseConfig) return;

    try {
        firebase.initializeApp(firebaseConfig);
        const messaging = firebase.messaging();

        messaging.onBackgroundMessage((payload) => {
            const notificationTitle = payload.notification?.title || 'New Notification';
            const notificationOptions = {
                body: payload.notification?.body || '',
                icon: '/favicon.ico',
                data: {
                    action_url: payload.data?.action_url || payload.fcmOptions?.link || '/',
                },
            };

            self.registration.showNotification(notificationTitle, notificationOptions);
        });
    } catch (e) {
        // Firebase initialization may fail silently in SW
    }
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const actionUrl = event.notification.data?.action_url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // Focus existing window if available
            for (const client of clientList) {
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    client.focus();
                    client.navigate(actionUrl);
                    return;
                }
            }
            // Open new window
            if (clients.openWindow) {
                return clients.openWindow(actionUrl);
            }
        })
    );
});
