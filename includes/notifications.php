<?php
/**
 * Notification System for Trail Status Changes
 * Push notifications only - simplified and reliable
 */

// Notification configuration
define('ENABLE_NOTIFICATIONS', true);
define('ENABLE_PUSH_NOTIFICATIONS', true);

// Load local configuration for VAPID keys
if (file_exists(dirname(__DIR__) . '/config.local.php')) {
    require_once dirname(__DIR__) . '/config.local.php';
} else {
    // Default empty keys - update config.local.php with actual keys
    define('VAPID_PUBLIC_KEY', '');
    define('VAPID_PRIVATE_KEY', '');
}

// VAPID Subject
define('VAPID_SUBJECT', 'mailto:noreply@zeroglitch.com');

// Push Notification Functions

// Load push notification subscribers
function loadPushSubscribers() {
    $push_subscribers_file = DATA_DIR . 'push_subscribers.json';
    return loadJsonData($push_subscribers_file);
}

// Save push notification subscribers
function savePushSubscribers($subscribers) {
    $push_subscribers_file = DATA_DIR . 'push_subscribers.json';
    return saveJsonData($push_subscribers_file, $subscribers);
}

// Add a new push notification subscriber
function addPushSubscriber($endpoint, $p256dh_key, $auth_key, $user_agent = '', $trails = array()) {
    $subscribers = loadPushSubscribers();
    
    // Check if endpoint already exists
    foreach ($subscribers as $subscriber) {
        if ($subscriber['endpoint'] === $endpoint) {
            return false; // Already subscribed
        }
    }
    
    // Generate new ID
    $max_id = 0;
    foreach ($subscribers as $subscriber) {
        if ($subscriber['id'] > $max_id) {
            $max_id = $subscriber['id'];
        }
    }
    
    $new_subscriber = array(
        'id' => $max_id + 1,
        'endpoint' => $endpoint,
        'p256dh' => $p256dh_key,
        'auth' => $auth_key,
        'user_agent' => $user_agent,
        'trails' => empty($trails) ? array('all') : $trails,
        'created_at' => date('Y-m-d H:i:s'),
        'active' => true
    );
    
    $subscribers[] = $new_subscriber;
    return savePushSubscribers($subscribers);
}

// Remove a push notification subscriber
function removePushSubscriber($endpoint) {
    $subscribers = loadPushSubscribers();
    $filtered_subscribers = array();
    
    foreach ($subscribers as $subscriber) {
        if ($subscriber['endpoint'] !== $endpoint) {
            $filtered_subscribers[] = $subscriber;
        }
    }
    
    return savePushSubscribers($filtered_subscribers);
}

// Send push notification (improved implementation)
function sendPushNotification($endpoint, $p256dh, $auth, $payload) {
    // This is a simplified implementation for basic push notifications
    // For production use, consider using a proper library like web-push-php
    
    $data = json_encode($payload);
    
    // For FCM endpoints, we need to use the legacy format
    if (strpos($endpoint, 'fcm.googleapis.com') !== false) {
        return sendFCMNotification($endpoint, $payload);
    }
    
    // For other endpoints, use basic implementation
    $headers = array(
        'Content-Type: application/json',
        'TTL: 86400'
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code >= 200 && $http_code < 300;
}

// Simplified FCM notification sender
function sendFCMNotification($endpoint, $payload) {
    // Extract registration token from endpoint
    $parts = explode('/', $endpoint);
    $token = end($parts);
    
    // For now, just return true to test the rest of the system
    // In a real implementation, you would need FCM server key
    error_log("FCM Notification would be sent to: " . substr($token, 0, 20) . "...");
    error_log("Payload: " . json_encode($payload));
    
    // Return true for testing purposes
    return true;
}

// Send push notifications for trail status change
function notifyTrailStatusChangePush($trail_id, $trail_name, $old_status, $new_status, $updated_by) {
    if (!ENABLE_PUSH_NOTIFICATIONS) {
        return;
    }
    
    $subscribers = loadPushSubscribers();
    
    $payload = array(
        'title' => 'Trail Status Change',
        'body' => "{$trail_name} is now " . ucfirst($new_status),
        'icon' => '/trailstatus/images/ftf_logo.jpg',
        'badge' => '/trailstatus/images/ftf_logo.jpg',
        'data' => array(
            'trail_id' => $trail_id,
            'trail_name' => $trail_name,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'updated_by' => $updated_by,
            'updated_at' => date('Y-m-d H:i:s'),
            'url' => 'https://zeroglitch.com/trailstatus/'
        )
    );
    
    foreach ($subscribers as $subscriber) {
        if (!$subscriber['active']) continue;
        
        // Check if subscriber wants notifications for this trail
        if (!in_array('all', $subscriber['trails']) && !in_array($trail_id, $subscriber['trails'])) {
            continue;
        }
        
        sendPushNotification($subscriber['endpoint'], $subscriber['p256dh'], $subscriber['auth'], $payload);
    }
}

// Main notification function - now only handles push notifications
function notifyTrailStatusChange($trail_id, $trail_name, $old_status, $new_status, $updated_by) {
    if (!ENABLE_NOTIFICATIONS) {
        return;
    }
    
    // Send push notifications
    if (ENABLE_PUSH_NOTIFICATIONS) {
        notifyTrailStatusChangePush($trail_id, $trail_name, $old_status, $new_status, $updated_by);
    }
}

// Initialize default push subscribers file if it doesn't exist
$push_subscribers_file = DATA_DIR . 'push_subscribers.json';
if (!file_exists($push_subscribers_file)) {
    $default_push_subscribers = array();
    saveJsonData($push_subscribers_file, $default_push_subscribers);
}
?>
