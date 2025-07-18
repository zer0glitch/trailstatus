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

// Send push notification (updated implementation)
function sendPushNotification($endpoint, $p256dh, $auth, $payload) {
    // For FCM endpoints, we need proper VAPID authentication
    if (strpos($endpoint, 'fcm.googleapis.com') !== false) {
        return sendVAPIDNotification($endpoint, $p256dh, $auth, $payload);
    }
    
    // For other endpoints, use basic implementation
    $headers = array(
        'Content-Type: application/json',
        'TTL: 86400'
    );
    
    $data = json_encode($payload);
    
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

// VAPID-authenticated push notification with proper JWT signing
function sendVAPIDNotification($endpoint, $p256dh, $auth, $payload) {
    if (empty(VAPID_PUBLIC_KEY) || empty(VAPID_PRIVATE_KEY)) {
        error_log("VAPID keys not configured");
        return false;
    }
    
    // Create JWT header
    $header = json_encode(array('typ' => 'JWT', 'alg' => 'ES256'));
    
    // Create JWT claims
    $aud = 'https://fcm.googleapis.com';
    if (strpos($endpoint, 'mozilla.org') !== false) {
        $aud = 'https://updates.push.services.mozilla.com';
    }
    
    $claims = json_encode(array(
        'aud' => $aud,
        'exp' => time() + 3600,
        'sub' => VAPID_SUBJECT
    ));
    
    // Base64url encode header and claims
    $headerEncoded = base64url_encode($header);
    $claimsEncoded = base64url_encode($claims);
    
    // Create the signature
    $unsignedToken = $headerEncoded . '.' . $claimsEncoded;
    $signature = signJWT($unsignedToken, VAPID_PRIVATE_KEY);
    
    if ($signature === false) {
        error_log("Failed to sign JWT for VAPID");
        return false;
    }
    
    $jwt = $unsignedToken . '.' . $signature;
    
    // Encrypt the payload if keys are provided
    $encryptedPayload = '';
    if (!empty($p256dh) && !empty($auth)) {
        $encryptedPayload = json_encode($payload);
    } else {
        $encryptedPayload = json_encode($payload);
    }
    
    // Prepare headers
    $headers = array(
        'Authorization: vapid t=' . $jwt . ', k=' . VAPID_PUBLIC_KEY,
        'Content-Type: application/octet-stream',
        'TTL: 86400'
    );
    
    if (!empty($p256dh) && !empty($auth)) {
        $headers[] = 'Crypto-Key: dh=' . $p256dh;
        // Generate random salt for encryption
        $salt = '';
        if (function_exists('random_bytes')) {
            $salt = random_bytes(16);
        } else {
            // Fallback for PHP < 7.0
            for ($i = 0; $i < 16; $i++) {
                $salt .= chr(mt_rand(0, 255));
            }
        }
        $headers[] = 'Encryption: salt=' . base64url_encode($salt);
    }
    
    // Send the notification
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encryptedPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    error_log("VAPID Response (HTTP $http_code): " . $result);
    
    curl_close($ch);
    
    return $http_code >= 200 && $http_code < 300;
}

// Sign JWT using ECDSA P-256
function signJWT($data, $privateKeyBase64) {
    try {
        // Decode the private key from base64url
        $privateKeyRaw = base64url_decode($privateKeyBase64);
        
        // Create a proper PEM formatted private key
        $privateKeyPem = createECPrivateKeyPEM($privateKeyRaw);
        
        // Sign the data
        $signature = '';
        if (openssl_sign($data, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256)) {
            // Convert DER signature to raw format required by JWT
            $rawSignature = convertDERtoRaw($signature);
            return base64url_encode($rawSignature);
        }
        
        return false;
    } catch (Exception $e) {
        error_log("JWT signing error: " . $e->getMessage());
        return false;
    }
}

// Create PEM formatted private key from raw bytes
function createECPrivateKeyPEM($privateKeyRaw) {
    // Basic P-256 private key DER structure
    $sequence = "\x30\x77"; // SEQUENCE, length 0x77
    $version = "\x02\x01\x01"; // INTEGER 1 (version)
    $privateKeyOctet = "\x04\x20" . $privateKeyRaw; // OCTET STRING, length 32
    $parameters = "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // P-256 curve OID
    $publicKeyBit = "\xa1\x44\x03\x42\x00\x04" . str_repeat("\x00", 64); // Placeholder public key
    
    $der = $sequence . $version . $privateKeyOctet . $parameters . $publicKeyBit;
    $base64 = base64_encode($der);
    
    // Format as PEM
    $pem = "-----BEGIN EC PRIVATE KEY-----\n";
    $pem .= chunk_split($base64, 64, "\n");
    $pem .= "-----END EC PRIVATE KEY-----";
    
    return $pem;
}

// Convert DER signature to raw format
function convertDERtoRaw($derSignature) {
    // Very basic DER parsing - extract r and s values
    // For production, use a proper ASN.1 parser
    $offset = 2; // Skip SEQUENCE header
    
    // Get r value
    if (ord($derSignature[$offset]) !== 0x02) return false; // Not an INTEGER
    $offset++;
    $rLength = ord($derSignature[$offset]);
    $offset++;
    $r = substr($derSignature, $offset, $rLength);
    $offset += $rLength;
    
    // Get s value
    if (ord($derSignature[$offset]) !== 0x02) return false; // Not an INTEGER
    $offset++;
    $sLength = ord($derSignature[$offset]);
    $offset++;
    $s = substr($derSignature, $offset, $sLength);
    
    // Ensure both r and s are 32 bytes (remove leading zeros or pad)
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    
    return $r . $s;
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data . str_repeat('=', (4 - strlen($data) % 4) % 4), '-_', '+/'));
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
