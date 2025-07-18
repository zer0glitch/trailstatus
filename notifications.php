<?php
require_once 'includes/config.php';
require_once 'includes/notifications.php';

$message = '';
$error = '';

// Load trails for selection
$trails = loadJsonData(TRAILS_FILE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trail Status Notifications - LCFTF</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-logo">
                <img src="https://www.zeroglitch.com/trailstatus/images/ftf_logo.jpg" alt="FTF Logo" />
                <div>
                    <h1>🚵‍♂️ LCFTF Trail Status</h1>
                    <p>Notification Subscriptions</p>
                </div>
            </div>
        </header>

        <nav class="nav">
            <ul>
                <li><a href="index.php">Trail Status</a></li>
                <li><a href="notifications.php" class="active">Notifications</a></li>
                <?php if (isLoggedIn()): ?>
                    <li><a href="admin.php">Admin Panel</a></li>
                    <li><a href="logout.php" class="btn-logout">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                <?php else: ?>
                    <li><a href="login.php">Admin Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <main>
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <!-- Push Notifications Section -->
            <?php if (ENABLE_PUSH_NOTIFICATIONS): ?>
            <div class="form-container" style="margin-bottom: 30px;">
                <h2 style="color: var(--ftf-primary);">🔔 Push Notifications</h2>
                <p style="margin-bottom: 20px; color: var(--ftf-secondary);">Get instant notifications on your device when trail status changes.</p>
                
                <div id="push-notification-section">
                    <div id="push-unsupported" style="display: none; background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px;">
                        <strong>Not Supported:</strong> Your browser doesn't support push notifications.
                    </div>
                    
                    <div id="push-blocked" style="display: none; background: #fff3e0; color: #f57c00; padding: 15px; border-radius: 8px;">
                        <strong>Blocked:</strong> Push notifications are currently blocked. Please enable them in your browser settings.
                    </div>
                    
                    <div id="push-default" style="display: none;">
                        <button id="enable-push" class="btn btn-primary" style="width: 100%;">
                            🔔 Enable Push Notifications
                        </button>
                        <p style="margin-top: 10px; font-size: 0.9rem; color: #666;">
                            Click to receive instant notifications when trail status changes.
                        </p>
                    </div>
                    
                    <div id="push-subscribed" style="display: none; background: #e8f5e8; color: #2e7d32; padding: 15px; border-radius: 8px;">
                        <strong>✓ Subscribed:</strong> You'll receive push notifications for trail status changes.
                        <button id="disable-push" class="btn btn-danger" style="margin-top: 10px; width: 100%;">
                            Disable Push Notifications
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Information Section -->
            <div class="form-container" style="margin-top: 30px;">
                <h3 style="color: var(--ftf-primary);">📋 About Notifications</h3>
                <div style="color: var(--ftf-secondary); line-height: 1.6;">
                    <ul style="margin-left: 20px;">
                        <li><strong>Push Notifications:</strong> Instant alerts directly to your device</li>
                        <li><strong>Works Offline:</strong> Notifications appear even when the browser is closed</li>
                        <li><strong>Real-time Updates:</strong> Get notified immediately when trail status changes</li>
                        <li><strong>Privacy:</strong> Your information is only used for trail status notifications</li>
                    </ul>
                    
                    <div style="background: var(--ftf-light); padding: 15px; border-radius: 8px; margin-top: 20px; border-left: 4px solid var(--ftf-accent);">
                        <strong>Sample Notification:</strong><br>
                        <em>"Trail Status Change: Blue Trail is now Closed"</em><br>
                        <small>Includes trail name, old status, new status, who updated it, and when.</small>
                    </div>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> LCFTF Mountain Bike Club</p>
        </footer>
    </div>

    <script>
        // Push Notification Support
        <?php if (ENABLE_PUSH_NOTIFICATIONS): ?>
        if ('serviceWorker' in navigator && 'PushManager' in window) {
            // Check current push notification status
            checkPushSubscription();
            
            // Enable push notifications
            document.getElementById('enable-push').addEventListener('click', async function() {
                try {
                    const registration = await navigator.serviceWorker.register('./sw.js');
                    const permission = await Notification.requestPermission();
                    
                    if (permission === 'granted') {
                        const subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: urlBase64ToUint8Array('<?php echo VAPID_PUBLIC_KEY; ?>')
                        });
                        
                        // Send subscription to server
                        const response = await fetch('push-subscribe.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'subscribe',
                                subscription: subscription
                            })
                        });
                        
                        if (response.ok) {
                            checkPushSubscription();
                        }
                    } else {
                        showPushStatus('blocked');
                    }
                } catch (error) {
                    console.error('Push subscription failed:', error);
                    alert('Failed to enable push notifications. Please try again.');
                }
            });
            
            // Disable push notifications
            document.getElementById('disable-push').addEventListener('click', async function() {
                try {
                    const registration = await navigator.serviceWorker.getRegistration();
                    if (registration) {
                        const subscription = await registration.pushManager.getSubscription();
                        if (subscription) {
                            await subscription.unsubscribe();
                            
                            // Tell server to remove subscription
                            await fetch('push-subscribe.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    action: 'unsubscribe',
                                    endpoint: subscription.endpoint
                                })
                            });
                        }
                    }
                    checkPushSubscription();
                } catch (error) {
                    console.error('Push unsubscribe failed:', error);
                }
            });
        } else {
            showPushStatus('unsupported');
        }
        
        async function checkPushSubscription() {
            try {
                const registration = await navigator.serviceWorker.getRegistration();
                if (registration) {
                    const subscription = await registration.pushManager.getSubscription();
                    if (subscription) {
                        showPushStatus('subscribed');
                    } else {
                        const permission = Notification.permission;
                        if (permission === 'denied') {
                            showPushStatus('blocked');
                        } else {
                            showPushStatus('default');
                        }
                    }
                } else {
                    showPushStatus('default');
                }
            } catch (error) {
                showPushStatus('default');
            }
        }
        
        function showPushStatus(status) {
            const sections = ['push-unsupported', 'push-blocked', 'push-default', 'push-subscribed'];
            sections.forEach(id => {
                document.getElementById(id).style.display = 'none';
            });
            document.getElementById('push-' + status).style.display = 'block';
        }
        
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }
        <?php endif; ?>

        // Handle "All Trails" checkbox for email subscriptions
        <?php if (ENABLE_EMAIL_SUBSCRIPTIONS): ?>
        const allTrailsCheckbox = document.querySelector('input[value="all"]');
        if (allTrailsCheckbox) {
            allTrailsCheckbox.addEventListener('change', function() {
                const otherCheckboxes = document.querySelectorAll('input[name="trails[]"]:not([value="all"])');
                if (this.checked) {
                    otherCheckboxes.forEach(cb => cb.checked = false);
                }
            });

            // Handle individual trail checkboxes
            document.querySelectorAll('input[name="trails[]"]:not([value="all"])').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        document.querySelector('input[value="all"]').checked = false;
                    }
                });
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>
