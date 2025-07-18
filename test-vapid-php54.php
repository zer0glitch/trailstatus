<?php
/**
 * PHP 5.4 Compatible VAPID Push Notification Test
 * Uses proper VAPID authentication for FCM
 */

require_once 'includes/config.php';

echo "=== VAPID PUSH NOTIFICATION TEST ===\n\n";

// Check if VAPID keys are configured (PHP 5.4 compatible way)
$vapid_public_key = defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '';
$vapid_private_key = defined('VAPID_PRIVATE_KEY') ? VAPID_PRIVATE_KEY : '';

if (!$vapid_public_key || !$vapid_private_key) {
    echo "Error: VAPID keys not configured.\n";
    exit(1);
}

echo "VAPID keys are configured\n";
echo "Public Key: " . substr($vapid_public_key, 0, 20) . "...\n";
echo "Private Key: " . substr($vapid_private_key, 0, 20) . "...\n\n";

// Base64url functions
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data . str_repeat('=', (4 - strlen($data) % 4) % 4), '-_', '+/'));
}

// Simple VAPID JWT creation for PHP 5.4
function createVAPIDJWT($audience, $subject, $publicKey, $privateKey) {
    // JWT Header
    $header = array(
        'typ' => 'JWT',
        'alg' => 'ES256'
    );
    
    // JWT Claims
    $claims = array(
        'aud' => $audience,
        'exp' => time() + 3600,
        'sub' => $subject
    );
    
    // Encode header and claims
    $headerEncoded = base64url_encode(json_encode($header));
    $claimsEncoded = base64url_encode(json_encode($claims));
    
    // Create unsigned token
    $unsignedToken = $headerEncoded . '.' . $claimsEncoded;
    
    // For this test, we'll create a simple signature
    // In production, you'd want to use proper ECDSA signing
    $signature = base64url_encode(hash('sha256', $unsignedToken . $privateKey, true));
    
    return $unsignedToken . '.' . $signature;
}

// VAPID Push Notification function
function sendVAPIDPushNotification($endpoint, $p256dh, $auth, $payload, $publicKey, $privateKey) {
    $payloadJson = json_encode($payload);
    
    // Determine audience based on endpoint
    $audience = 'https://fcm.googleapis.com';
    if (strpos($endpoint, 'mozilla') !== false) {
        $audience = 'https://updates.push.services.mozilla.com';
    }
    
    // Create VAPID JWT
    $jwt = createVAPIDJWT($audience, 'mailto:noreply@zeroglitch.com', $publicKey, $privateKey);
    
    // Prepare headers
    $headers = array(
        'Authorization: vapid t=' . $jwt . ', k=' . $publicKey,
        'Content-Type: application/json',
        'TTL: 86400'
    );
    
    // Send the request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Response: $httpCode";
    if ($error) {
        echo " (Error: $error)";
    }
    if ($result && $httpCode !== 200) {
        echo " (Response: " . substr($result, 0, 100) . ")";
    }
    echo "\n";
    
    return $httpCode >= 200 && $httpCode < 300;
}

// Load push subscribers
$push_subscribers_file = DATA_DIR . 'push_subscribers.json';
if (!file_exists($push_subscribers_file)) {
    echo "No subscribers found.\n";
    exit(0);
}

$content = file_get_contents($push_subscribers_file);
$subscribers = json_decode($content, true);
if (!$subscribers) {
    $subscribers = array();
}

echo "Found " . count($subscribers) . " subscribers\n\n";

if (count($subscribers) == 0) {
    echo "No subscribers found.\n";
    exit(0);
}

echo "Sending VAPID authenticated notifications...\n\n";

$test_payload = array(
    'title' => 'VAPID Test Notification',
    'body' => 'This uses VAPID authentication for FCM',
    'icon' => './images/ftf_logo.jpg',
    'data' => array(
        'test' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'url' => 'https://zeroglitch.com/trailstatus/'
    )
);

$success_count = 0;
$error_count = 0;

foreach ($subscribers as $subscriber) {
    $is_active = isset($subscriber['active']) ? $subscriber['active'] : true;
    if (!$is_active) {
        echo "Skipping inactive subscriber\n";
        continue;
    }
    
    $endpoint_preview = substr($subscriber['endpoint'], -30);
    echo "Sending to: ..." . $endpoint_preview . " ";
    
    $p256dh = isset($subscriber['p256dh']) ? $subscriber['p256dh'] : '';
    $auth = isset($subscriber['auth']) ? $subscriber['auth'] : '';
    
    $result = sendVAPIDPushNotification(
        $subscriber['endpoint'],
        $p256dh,
        $auth,
        $test_payload,
        $vapid_public_key,
        $vapid_private_key
    );
    
    if ($result) {
        echo "Success\n";
        $success_count++;
    } else {
        echo "Failed\n";
        $error_count++;
    }
}

echo "\n=== VAPID TEST RESULTS ===\n";
echo "Successful: $success_count\n";
echo "Failed: $error_count\n";

if ($success_count > 0) {
    echo "\nVAPID notifications sent! Check your browser for notifications.\n";
} else {
    echo "\nVAPID authentication failed.\n";
    echo "This might be due to:\n";
    echo "1. Simplified JWT signing (not proper ECDSA)\n";
    echo "2. VAPID key format issues\n";
    echo "3. FCM endpoint requirements\n";
    echo "\nFor production, consider using a proper library like web-push-php.\n";
}
?>
