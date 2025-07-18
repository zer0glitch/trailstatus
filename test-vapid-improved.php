<?php
/**
 * Improved VAPID Push Notification Test for PHP 5.4
 * Better JWT formatting for FCM compatibility
 */

require_once 'includes/config.php';

echo "=== IMPROVED VAPID PUSH TEST ===\n\n";

// Check if VAPID keys are configured
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

// Attempt proper ECDSA signing using OpenSSL
function signJWTWithOpenSSL($data, $privateKeyBase64) {
    try {
        // Decode the private key
        $privateKeyRaw = base64url_decode($privateKeyBase64);
        
        if (strlen($privateKeyRaw) !== 32) {
            throw new Exception("Invalid private key length");
        }
        
        // Create a basic EC private key PEM structure
        // This is a simplified version - may not work with all FCM requirements
        $der_header = "\x30\x77\x02\x01\x01\x04\x20";
        $der_params = "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
        $der_pubkey = "\xa1\x44\x03\x42\x00\x04" . str_repeat("\x00", 64);
        
        $der = $der_header . $privateKeyRaw . $der_params . $der_pubkey;
        $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----";
        
        // Try to sign with OpenSSL
        $signature = '';
        if (openssl_sign($data, $signature, $pem, OPENSSL_ALGO_SHA256)) {
            // Convert DER signature to raw format (simplified)
            if (strlen($signature) > 64) {
                // Extract r and s from DER (very basic parsing)
                $r = substr($signature, 4, 32);
                $s = substr($signature, -32);
                return base64url_encode($r . $s);
            }
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// Create proper VAPID JWT
function createProperVAPIDJWT($audience, $subject, $publicKey, $privateKey) {
    // JWT Header
    $header = json_encode(array(
        'typ' => 'JWT',
        'alg' => 'ES256'
    ), JSON_UNESCAPED_SLASHES);
    
    // JWT Claims
    $claims = json_encode(array(
        'aud' => $audience,
        'exp' => time() + 3600,
        'sub' => $subject
    ), JSON_UNESCAPED_SLASHES);
    
    // Encode header and claims
    $headerEncoded = base64url_encode($header);
    $claimsEncoded = base64url_encode($claims);
    
    // Create unsigned token
    $unsignedToken = $headerEncoded . '.' . $claimsEncoded;
    
    // Try OpenSSL signing first
    $signature = signJWTWithOpenSSL($unsignedToken, $privateKey);
    
    if ($signature === false) {
        // Fallback to simple signing (will likely fail with FCM)
        $signature = base64url_encode(hash('sha256', $unsignedToken . $privateKey, true));
    }
    
    return $unsignedToken . '.' . $signature;
}

// Improved VAPID Push function
function sendImprovedVAPIDPush($endpoint, $p256dh, $auth, $payload, $publicKey, $privateKey) {
    // Determine audience
    $audience = 'https://fcm.googleapis.com';
    if (strpos($endpoint, 'mozilla') !== false) {
        $audience = 'https://updates.push.services.mozilla.com';
    }
    
    // Create VAPID JWT
    $jwt = createProperVAPIDJWT($audience, 'mailto:noreply@zeroglitch.com', $publicKey, $privateKey);
    
    // Prepare payload
    $payloadJson = json_encode($payload);
    
    // Prepare headers with proper VAPID format
    $headers = array(
        'Authorization: vapid t=' . $jwt . ', k=' . $publicKey,
        'Content-Type: application/json',
        'TTL: 86400'
    );
    
    echo "JWT Length: " . strlen($jwt) . "\n";
    echo "Auth Header: Authorization: vapid t=" . substr($jwt, 0, 50) . "..., k=" . substr($publicKey, 0, 20) . "...\n";
    
    // Send request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Response: $httpCode";
    if ($error) {
        echo " (cURL Error: $error)";
    }
    if ($result && $httpCode !== 200) {
        echo " (Response: " . substr($result, 0, 200) . ")";
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
    'title' => 'Improved VAPID Test',
    'body' => 'Testing improved VAPID authentication',
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
    
    $result = sendImprovedVAPIDPush(
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
    echo "✓ Push notifications are working!\n";
} else {
    echo "✗ Push notifications still failing.\n";
    echo "\nRECOMMENDATION:\n";
    echo "For reliable FCM push notifications, install web-push-php:\n";
    echo "1. Install Composer on your server\n";
    echo "2. Run: composer require minishlink/web-push\n";
    echo "3. Use proper ECDSA P-256 signing\n";
    echo "\nAlternatively, use Firebase Admin SDK or a push service that doesn't require VAPID.\n";
}
?>
