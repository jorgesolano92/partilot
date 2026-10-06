// Firebase Notifications Service
class FirebaseNotifications {
    constructor() {
        this.messaging = null;
        this.isSupported = false;
        this.baseUrl = this.getBaseUrl();
        this.init();
    }

    // Get the base URL from the current page
    getBaseUrl() {
        const path = window.location.pathname;
        // Extract base path (e.g., /partilot/public from /partilot/public/dashboard)
        const match = path.match(/^(\/[^\/]+\/[^\/]+)/);
        const basePath = match ? match[1] : '';
        console.log('🔍 Base path detectado:', basePath);
        return basePath;
    }

    async init() {
        try {
            if (window.partilotDeferFirebaseUntilCookieBanner && typeof window.partilotWaitForCookieConsent === 'function') {
                await new Promise((resolve) => window.partilotWaitForCookieConsent(resolve));
            }

            // Check if service worker is supported
            if ('serviceWorker' in navigator) {
                // Register service worker manually with correct path
                // If baseUrl is empty, use relative path
                const swUrl = this.baseUrl ? `${this.baseUrl}/firebase-messaging-sw.js` : '/firebase-messaging-sw.js';
                const scope = this.baseUrl ? `${this.baseUrl}/` : '/';
                
                console.log('🔍 Registrando Service Worker...');
                console.log('   URL:', swUrl);
                console.log('   Scope:', scope);
                
                const registration = await navigator.serviceWorker.register(swUrl, {
                    scope: scope
                });
                console.log('✅ Service Worker registrado:', registration.scope);

                // Import Firebase modules
                const { initializeApp } = await import('https://www.gstatic.com/firebasejs/12.4.0/firebase-app.js');
                const { getMessaging, getToken, onMessage } = await import('https://www.gstatic.com/firebasejs/12.4.0/firebase-messaging.js');

                // Get Firebase config from Laravel
                const config = await this.getFirebaseConfig();
                
                // Initialize Firebase with service worker registration
                const app = initializeApp(config);
                this.messaging = getMessaging(app);
                this.isSupported = true;

                // Store functions for later use
                this.getToken = getToken;
                this.onMessage = onMessage;
                this.serviceWorkerRegistration = registration;

                // Request permission and get token
                await this.requestPermission();
                
                // Listen for foreground messages
                this.listenForMessages();
                
                console.log('Firebase Notifications initialized successfully');
            } else {
                console.warn('Service Worker not supported');
            }
        } catch (error) {
            console.error('Error initializing Firebase Notifications:', error);
        }
    }

    async getFirebaseConfig() {
        try {
            const response = await fetch(`${this.baseUrl}/notifications/firebase-config`);
            return await response.json();
        } catch (error) {
            console.error('Error getting Firebase config:', error);
            // Fallback to hardcoded config
            return {
                apiKey: "AIzaSyABsAHy3BtYUkcV4z3gjCl3NNU35ye4LFs",
                authDomain: "inicio-de-sesion-94ddc.firebaseapp.com",
                databaseURL: "https://inicio-de-sesion-94ddc.firebaseio.com",
                projectId: "inicio-de-sesion-94ddc",
                storageBucket: "inicio-de-sesion-94ddc.firebasestorage.app",
                messagingSenderId: "204683025370",
                appId: "1:204683025370:web:c424b261eff8d566be7ee3"
            };
        }
    }

    async requestPermission() {
        try {
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                console.log('Notification permission granted');
                await this.getFCMToken();
            } else {
                console.log('Notification permission denied');
            }
        } catch (error) {
            console.error('Error requesting permission:', error);
        }
    }

    async getFCMToken() {
        try {
            if (!this.messaging || !this.getToken || !this.serviceWorkerRegistration) return null;

            const token = await this.getToken(this.messaging, {
                vapidKey: 'BLM73awUlpn-eZx9osSf_usO1PYU93Eb2FjV37RoYivoBIdA1jRirM7ErlwE6pyLU-jYhe9TnhfUYM2YRiqQ58U',
                serviceWorkerRegistration: this.serviceWorkerRegistration
            });

            if (token) {
                console.log('FCM Token:', token);
                // Send token to server to associate with user
                await this.sendTokenToServer(token);
                return token;
            } else {
                console.log('No registration token available');
            }
        } catch (error) {
            console.error('Error getting FCM token:', error);
        }
    }

    async sendTokenToServer(token) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (!csrfToken) {
                console.error('CSRF token not found');
                return;
            }

            const response = await fetch(`${this.baseUrl}/notifications/register-token`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken.getAttribute('content')
                },
                body: JSON.stringify({ token })
            });

            if (response.ok) {
                console.log('Token successfully registered on server');
            } else {
                const errorText = await response.text();
                console.error('Failed to register token on server:', response.status, errorText);
            }
        } catch (error) {
            console.error('Error sending token to server:', error);
        }
    }

    listenForMessages() {
        if (!this.messaging || !this.onMessage) return;

        this.onMessage(this.messaging, (payload) => {
            console.log('Message received:', payload);
            
            // Show desktop notification even in foreground
            this.showDesktopNotification(payload);
            
            // Show toast notification
            this.showToast(payload.notification);
            
            // Update UI if needed
            this.updateNotificationUI(payload);
        });
    }

    showNotification(payload) {
        const notification = payload.notification;
        const data = payload.data;

        if (Notification.permission === 'granted') {
            const notificationOptions = {
                body: notification.body,
                icon: '/favicon.ico',
                badge: '/favicon.ico',
                tag: data.notification_id || 'notification',
                data: data
                // Removed actions - they only work with Service Worker notifications
            };

            const notificationInstance = new Notification(notification.title, notificationOptions);
            
            // Handle notification click
            notificationInstance.onclick = () => {
                window.focus();
                this.handleNotificationClick(data);
                notificationInstance.close();
            };
        }
    }

    showDesktopNotification(payload) {
        const notification = payload.notification;
        const data = payload.data;

        if (Notification.permission === 'granted') {
            // Try to use Service Worker notification for better Chrome compatibility
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(registration => {
                    // Use Service Worker to show notification (works better in Chrome)
                    registration.showNotification(notification.title, {
                        body: notification.body,
                        icon: '/favicon.ico',
                        badge: '/favicon.ico',
                        tag: data.notification_id || 'notification',
                        data: data,
                        requireInteraction: true,
                        silent: false,
                        actions: [
                            {
                                action: 'view',
                                title: 'Ver'
                            },
                            {
                                action: 'dismiss',
                                title: 'Cerrar'
                            }
                        ]
                    });
                }).catch(() => {
                    // Fallback to native notification if Service Worker fails
                    this.showNativeNotification(notification, data);
                });
            } else {
                // Fallback to native notification
                this.showNativeNotification(notification, data);
            }
        }
    }

    showNativeNotification(notification, data) {
        const notificationOptions = {
            body: notification.body,
            icon: '/favicon.ico',
            badge: '/favicon.ico',
            tag: data.notification_id || 'notification',
            data: data,
            requireInteraction: true,
            silent: false
        };

        const notificationInstance = new Notification(notification.title, notificationOptions);
        
        // Handle notification click
        notificationInstance.onclick = () => {
            window.focus();
            this.handleNotificationClick(data);
            notificationInstance.close();
        };

        // Auto close after 10 seconds if user doesn't interact
        setTimeout(() => {
            notificationInstance.close();
        }, 10000);
    }

    handleNotificationClick(data) {
        // Handle notification click based on data
        if (data.type === 'notification') {
            // Redirect to notifications page or specific notification
            window.location.href = `${this.baseUrl}/notifications`;
        }
    }

    updateNotificationUI(payload) {
        // Update notification badge or counter
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            const currentCount = parseInt(badge.textContent) || 0;
            badge.textContent = currentCount + 1;
            badge.style.display = 'block';
        }

        // Show toast notification
        this.showToast(payload.notification);
    }

    showToast(notification) {
        // Play notification sound
        this.playNotificationSound();

        // Create toast notification
        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.innerHTML = `
            <div class="toast-header">
                <strong>${notification.title}</strong>
                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
            <div class="toast-body">
                ${notification.body}
            </div>
        `;

        // Add styles
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            max-width: 300px;
            animation: slideIn 0.3s ease-out;
        `;

        document.body.appendChild(toast);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 5000);
    }

    playNotificationSound() {
        try {
            // Create audio context for notification sound
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            
            // Create a more pleasant notification sound (soft bell-like tone)
            const oscillator1 = audioContext.createOscillator();
            const oscillator2 = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            const filter = audioContext.createBiquadFilter();

            // Connect nodes
            oscillator1.connect(filter);
            oscillator2.connect(filter);
            filter.connect(gainNode);
            gainNode.connect(audioContext.destination);

            // Configure filter for softer sound
            filter.type = 'lowpass';
            filter.frequency.setValueAtTime(2000, audioContext.currentTime);

            // Configure oscillators for pleasant chord
            oscillator1.type = 'sine';
            oscillator1.frequency.setValueAtTime(523, audioContext.currentTime); // C5 note
            oscillator2.type = 'sine';
            oscillator2.frequency.setValueAtTime(659, audioContext.currentTime); // E5 note
            
            // Configure volume envelope (softer, longer)
            gainNode.gain.setValueAtTime(0, audioContext.currentTime);
            gainNode.gain.linearRampToValueAtTime(0.15, audioContext.currentTime + 0.05);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.8);

            // Play the sound
            oscillator1.start(audioContext.currentTime);
            oscillator2.start(audioContext.currentTime);
            oscillator1.stop(audioContext.currentTime + 0.8);
            oscillator2.stop(audioContext.currentTime + 0.8);
        } catch (error) {
            console.log('No se pudo reproducir el sonido:', error);
        }
    }

    // Subscribe to entity notifications
    async subscribeToEntity(entityId) {
        if (!this.messaging) return;

        try {
            // This would require additional setup in Firebase Console
            // For now, we'll just log the subscription
            console.log(`Subscribing to entity ${entityId} notifications`);
        } catch (error) {
            console.error('Error subscribing to entity:', error);
        }
    }

    // Subscribe to administration notifications
    async subscribeToAdministration(administrationId) {
        if (!this.messaging) return;

        try {
            console.log(`Subscribing to administration ${administrationId} notifications`);
        } catch (error) {
            console.error('Error subscribing to administration:', error);
        }
    }
}

// Initialize Firebase Notifications when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.firebaseNotifications = new FirebaseNotifications();
});

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .toast-notification {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .toast-header {
        padding: 12px 16px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .toast-body {
        padding: 12px 16px;
        color: #666;
    }
    
    .btn-close {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        color: #999;
    }
`;
document.head.appendChild(style);
