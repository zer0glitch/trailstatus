<?php
/**
 * Fixed VAPID Push Test - Correct Header Format
 */

require_once 'includes/config.php';

echo "=== FIXED VAPID PUSH TEST ===\n\n";

// Check if VAPID keys are configured
$vapid_public_key = defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '';
$vapid_private_key = defined('VAPID_PRIVATE_KEY') ? VAPID_PRIVATE_KEY : '';

if (!$vapid_public_key || !$vapid_private_key) {
    echo "Error: VAPID keys not configured.\n";
    exit(1);
}

echo "VAPID keys are configured\n";
echo "Public Key: " . substr($vapid_public_key, 0, 30) . "...\n";
echo "Private Key: " . substr($vapid_private_key, 0, 30) . "...\n\n";

// Base64url functions
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data . str_repeat('=', (4 - strlen($data) % 4) % 4), '-_', '+/'));
}

// Create simple JWT (this is the main issue - we need proper ECDSA signing)
function createSimpleJWT($audience, $subject, $publicKey, $privateKey) {
    // JWT Header
    $header = json_encode(array(
        'typ' => 'JWT',
        'alg' => 'ES256'
    ));
    
    // JWT Claims
    $claims = json_encode(array(
        'aud' => $audience,
        'exp' => time() + 3600,
        'sub' => $subject
    ));
    
    // Encode header and claims
    $headerEncoded = base64url_encode($header);
    $claimsEncoded = base64url_encode($claims);
    
    // Create unsigned token
    $unsignedToken = $headerEncoded . '.' . $claimsEncoded;
    
    // Simple signature (this won't work with FCM, but let's test the header format first)
    $signature = base64url_encode(hash('sha256', $unsignedToken . $privateKey, true));
    
    return $unsignedToken . '.' . $signature;
}

// Fixed VAPID Push function with correct header format
function sendFixedVAPIDPush($endpoint, $p256dh, $auth, $payload, $publicKey, $privateKey) {
    // Determine audience
    $audience = 'https://fcm.googleapis.com';
    if (strpos($endpoint, 'mozilla') !== false) {
        $audience = 'https://updates.push.services.mozilla.com';
    }
    
    // Create JWT
    $jwt = createSimpleJWT($audience, 'mailto:noreply@zeroglitch.com', $publicKey, $privateKey);
    
    // Prepare payload
    $payloadJson = json_encode($payload);
    
    // FIXED: Use semicolon instead of comma in Authorization header
    $authHeader = 'Authorization: vapid t=' . $jwt . '; k=' . $publicKey;
    
    $headers = array(
        $authHeader,
        'Content-Type: application/json',
        'TTL: 86400'
    );
    
    echo "JWT Length: " . strlen($jwt) . "\n";
    echo "Auth Header: " . substr($authHeader, 0, 80) . "...\n";
    
    // Send request
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
        echo " (cURL Error: $error)";
    }
    if ($result && $httpCode !== 200 && $httpCode !== 201) {
        echo " (Response: " . substr($result, 0, 300) . ")";
    }
    echo "\n\n";
    
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
    $subscribers = array();
}

echo "Found " . count($subscribers) . " subscribers\n\n";

$test_payload = array(
    'title' => 'Fixed Header Test',
    'body' => 'Testing corrected authorization header format',
    'icon' => './images/ftf_logo.jpg',
    'data' => array(
        'test' => true,
        'timestamp' => date('Y-m-d H:i:s')
    )
);

$success_count = 0;
$error_count = 0;

foreach ($subscribers as $i => $subscriber) {
    echo "=== SUBSCRIBER " . ($i + 1) . " ===\n";
    
    $is_active = isset($subscriber['active']) ? $subscriber['active'] : true;
    if (!$is_active) {
        echo "Skipping inactive subscriber\n\n";
        continue;
    }
    
    $endpoint_preview = substr($subscriber['endpoint'], -30);
    echo "Endpoint: ..." . $endpoint_preview . "\n";
    
    $p256dh = isset($subscriber['p256dh']) ? $subscriber['p256dh'] : '';
    $auth = isset($subscriber['auth']) ? $subscriber['auth'] : '';
    
    $result = sendFixedVAPIDPush(
        $subscriber['endpoint'],
        $p256dh,
        $auth,
        $test_payload,
        $vapid_public_key,
        $vapid_private_key
    );
    
    if ($result) {
        echo "✓ SUCCESS\n";
        $success_count++;
    } else {
        echo "✗ FAILED\n";
        $error_count++;
    }
}

echo "=== FINAL RESULTS ===\n";
echo "Successful: $success_count\n";
echo "Failed: $error_count\n\n";

if ($success_count > 0) {
    echo "✓ Fixed header format worked! Push notifications are working!\n";
} else {
    echo "✗ Still failing. The issue is likely the JWT signature.\n";
    echo "FCM requires proper ECDSA P-256 signatures, not simple hash signatures.\n\n";
    echo "NEXT STEPS:\n";
    echo "1. The header format is now correct (semicolon instead of comma)\n";
    echo "2. We need proper ECDSA P-256 JWT signing\n";
    echo "3. Consider using web-push-php library for production\n";
    echo "4. Or use Firebase's REST API directly with server keys\n";
}
?>
