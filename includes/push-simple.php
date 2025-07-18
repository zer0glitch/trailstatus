<?php
/**
 * Simplified and Reliable Push Notification Implementation
 * Uses a more straightforward approach that works better with FCM
 */

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

// Base64url encoding/decoding functions
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data . str_repeat('=', (4 - strlen($data) % 4) % 4), '-_', '+/'));
}

// Simplified push notification sending
function sendSimplePushNotification($endpoint, $p256dh, $auth, $payload) {
    // Basic validation
    if (empty($endpoint)) {
        error_log("Push notification: Empty endpoint");
        return false;
    }
    
    // For Chrome/FCM endpoints, we need VAPID authentication
    if (strpos($endpoint, 'fcm.googleapis.com') !== false) {
        return sendFCMNotification($endpoint, $p256dh, $auth, $payload);
    }
    
    // For other push services (Mozilla, etc.)
    return sendGenericPushNotification($endpoint, $p256dh, $auth, $payload);
}

// FCM-specific notification sending
function sendFCMNotification($endpoint, $p256dh, $auth, $payload) {
    if (empty(VAPID_PUBLIC_KEY) || empty(VAPID_PRIVATE_KEY)) {
        error_log("FCM Push: VAPID keys not configured");
        return false;
    }
    
    // Create a simpler JWT token for VAPID
    $header = json_encode(['typ' => 'JWT', 'alg' => 'ES256']);
    $claims = json_encode([
        'aud' => 'https://fcm.googleapis.com',
        'exp' => time() + 3600,
        'sub' => VAPID_SUBJECT
    ]);
    
    $headerEncoded = base64url_encode($header);
    $claimsEncoded = base64url_encode($claims);
    $unsignedToken = $headerEncoded . '.' . $claimsEncoded;
    
    // Try to sign the JWT
    $signature = createSimpleSignature($unsignedToken, VAPID_PRIVATE_KEY);
    if ($signature === false) {
        error_log("FCM Push: Failed to create signature");
        return false;
    }
    
    $jwt = $unsignedToken . '.' . $signature;
    
    // Prepare the payload
    $payloadJson = json_encode($payload);
    
    // Prepare headers for FCM
    $headers = [
        'Authorization: vapid t=' . $jwt . ', k=' . VAPID_PUBLIC_KEY,
        'Content-Type: application/json',
        'TTL: 86400'
    ];
    
    // Send the request
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_VERBOSE => false
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log the result for debugging
    error_log("FCM Push Result: HTTP $httpCode, Response: $result" . ($error ? ", Error: $error" : ""));
    
    return $httpCode >= 200 && $httpCode < 300;
}

// Generic push notification for non-FCM endpoints
function sendGenericPushNotification($endpoint, $p256dh, $auth, $payload) {
    $payloadJson = json_encode($payload);
    
    $headers = [
        'Content-Type: application/json',
        'TTL: 86400'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Generic Push Result: HTTP $httpCode, Response: $result");
    
    return $httpCode >= 200 && $httpCode < 300;
}

// Simplified signature creation
function createSimpleSignature($data, $privateKeyBase64) {
    try {
        // Decode the private key
        $privateKeyRaw = base64url_decode($privateKeyBase64);
        
        if (strlen($privateKeyRaw) !== 32) {
            error_log("Invalid private key length: " . strlen($privateKeyRaw));
            return false;
        }
        
        // Create a temporary key file approach for older PHP versions
        $tempKeyFile = tempnam(sys_get_temp_dir(), 'vapid_key_');
        $pemKey = createECPrivateKeyPEM($privateKeyRaw);
        file_put_contents($tempKeyFile, $pemKey);
        
        // Sign the data
        $signature = '';
        $success = openssl_sign($data, $signature, file_get_contents($tempKeyFile), OPENSSL_ALGO_SHA256);
        
        // Clean up
        unlink($tempKeyFile);
        
        if ($success) {
            // Convert DER signature to raw format for JWT
            $rawSignature = convertDERSignatureToRaw($signature);
            return base64url_encode($rawSignature);
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Signature creation error: " . $e->getMessage());
        return false;
    }
}

// Create PEM key from raw bytes
function createECPrivateKeyPEM($privateKeyRaw) {
    // Minimal EC private key DER structure for P-256
    $version = "\x02\x01\x01"; // INTEGER 1
    $privateKeyOctet = "\x04\x20" . $privateKeyRaw; // OCTET STRING (32 bytes)
    $curveOID = "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // P-256 OID
    
    // Simple DER sequence
    $content = $version . $privateKeyOctet . $curveOID;
    $der = "\x30" . chr(strlen($content)) . $content;
    
    // Convert to PEM
    $base64 = base64_encode($der);
    $pem = "-----BEGIN EC PRIVATE KEY-----\n";
    $pem .= chunk_split($base64, 64, "\n");
    $pem .= "-----END EC PRIVATE KEY-----";
    
    return $pem;
}

// Convert DER signature to raw format for JWT
function convertDERSignatureToRaw($derSignature) {
    // Parse DER to extract r and s values
    $offset = 2; // Skip SEQUENCE header
    
    // Get r value
    if (ord($derSignature[$offset]) !== 0x02) return false;
    $offset++;
    $rLength = ord($derSignature[$offset]);
    $offset++;
    $r = substr($derSignature, $offset, $rLength);
    $offset += $rLength;
    
    // Get s value  
    if (ord($derSignature[$offset]) !== 0x02) return false;
    $offset++;
    $sLength = ord($derSignature[$offset]);
    $offset++;
    $s = substr($derSignature, $offset, $sLength);
    
    // Ensure both are exactly 32 bytes
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    
    return $r . $s;
}

// Test function to verify the implementation
function testPushNotificationSetup() {
    echo "Testing push notification setup...\n";
    
    // Check VAPID keys
    if (empty(VAPID_PUBLIC_KEY) || empty(VAPID_PRIVATE_KEY)) {
        echo "✗ VAPID keys not configured\n";
        return false;
    }
    
    echo "✓ VAPID keys configured\n";
    
    // Test signature creation
    $testSignature = createSimpleSignature("test", VAPID_PRIVATE_KEY);
    if ($testSignature !== false) {
        echo "✓ Signature creation working\n";
        return true;
    } else {
        echo "✗ Signature creation failed\n";
        return false;
    }
}
?>
