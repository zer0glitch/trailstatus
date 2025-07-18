<?php
/**
 * Final VAPID Implementation Attempt for PHP 5.4
 * Using Firebase Legacy API as fallback
 */

require_once 'includes/config.php';

echo "=== FINAL PUSH NOTIFICATION TEST ===\n\n";

// Check configuration
$vapid_public_key = defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '';
$vapid_private_key = defined('VAPID_PRIVATE_KEY') ? VAPID_PRIVATE_KEY : '';

if (!$vapid_public_key || !$vapid_private_key) {
    echo "Error: VAPID keys not configured.\n";
    exit(1);
}

echo "Testing multiple approaches...\n\n";

// Approach 1: Use Firebase Legacy FCM API (simpler, more reliable)
function sendFCMLegacyNotification($fcmToken, $payload, $serverKey = null) {
    if (!$serverKey) {
        echo "No FCM server key configured - skipping legacy API test\n";
        return false;
    }
    
    $url = 'https://fcm.googleapis.com/fcm/send';
    
    $notification = array(
        'title' => $payload['title'],
        'body' => $payload['body'],
        'icon' => $payload['icon']
    );
    
    $data = array(
        'to' => $fcmToken,
        'notification' => $notification,
        'data' => isset($payload['data']) ? $payload['data'] : array()
    );
    
    $headers = array(
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Legacy FCM API - HTTP Response: $httpCode\n";
    if ($result && $httpCode !== 200) {
        echo "Response: " . substr($result, 0, 200) . "\n";
    }
    
    return $httpCode === 200;
}

// Approach 2: Try a different VAPID approach with proper key formatting
function sendSimplifiedVAPID($endpoint, $payload, $publicKey, $privateKey) {
    // Create minimal JWT for testing
    $header = base64_encode(json_encode(array('typ' => 'JWT', 'alg' => 'none')));
    $claims = base64_encode(json_encode(array(
        'aud' => 'https://fcm.googleapis.com',
        'exp' => time() + 3600,
        'sub' => 'mailto:noreply@zeroglitch.com'
    )));
    
    // Create unsigned JWT (for testing)
    $jwt = $header . '.' . $claims . '.';
    
    $headers = array(
        'Authorization: vapid t=' . $jwt . '; k=' . $publicKey,
        'Content-Type: application/json',
        'TTL: 86400'
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Simplified VAPID - HTTP Response: $httpCode\n";
    if ($result && $httpCode !== 200) {
        echo "Response: " . substr($result, 0, 200) . "\n";
    }
    
    return $httpCode >= 200 && $httpCode < 300;
}

// Load subscribers
$push_subscribers_file = DATA_DIR . 'push_subscribers.json';
if (!file_exists($push_subscribers_file)) {
    echo "No subscribers found.\n";
    exit(0);
}

$content = file_get_contents($push_subscribers_file);
$subscribers = json_decode($content, true);
if (!$subscribers) {
    echo "No valid subscribers found.\n";
    exit(0);
}

echo "Found " . count($subscribers) . " subscribers\n\n";

$test_payload = array(
    'title' => 'Final Test',
    'body' => 'Testing multiple push approaches',
    'icon' => './images/ftf_logo.jpg',
    'data' => array(
        'test' => true,
        'timestamp' => date('Y-m-d H:i:s')
    )
);

foreach ($subscribers as $i => $subscriber) {
    echo "=== TESTING SUBSCRIBER " . ($i + 1) . " ===\n";
    
    $endpoint = $subscriber['endpoint'];
    echo "Endpoint: " . substr($endpoint, -40) . "\n";
    
    // Test 1: Try simplified VAPID
    echo "\nTest 1 - Simplified VAPID:\n";
    $result1 = sendSimplifiedVAPID($endpoint, $test_payload, $vapid_public_key, $vapid_private_key);
    
    // Test 2: Extract FCM token and try legacy API (if it's an FCM endpoint)
    if (strpos($endpoint, 'fcm.googleapis.com') !== false) {
        echo "\nTest 2 - FCM Legacy API:\n";
        // Extract token from FCM endpoint
        $parts = explode('/', $endpoint);
        $fcmToken = end($parts);
        
        // You would need to add your FCM server key here
        // echo "FCM Token: " . substr($fcmToken, 0, 20) . "...\n";
        echo "Legacy FCM API requires server key (not implemented in this test)\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
}

echo "=== SUMMARY ===\n";
echo "If simplified VAPID didn't work, here are your options:\n\n";

echo "1. FIREBASE LEGACY API (Recommended for PHP 5.4):\n";
echo "   - Get FCM server key from Firebase Console\n";
echo "   - Use https://fcm.googleapis.com/fcm/send endpoint\n";
echo "   - Much simpler than VAPID\n\n";

echo "2. CLIENT-SIDE NOTIFICATIONS:\n";
echo "   - Handle notifications entirely in JavaScript\n";
echo "   - Use Firebase SDK in the browser\n";
echo "   - No server-side complexity\n\n";

echo "3. UPGRADE TO MODERN PHP:\n";
echo "   - PHP 7.4+ has better crypto support\n";
echo "   - Install Composer and web-push-php\n";
echo "   - Proper ECDSA P-256 signing\n\n";

echo "For now, I recommend implementing the Firebase Legacy API approach.\n";
echo "Would you like me to create that implementation?\n";
?>
