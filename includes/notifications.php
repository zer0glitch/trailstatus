<?php
declare(strict_types=1);
/**
 * Modern Push Notification System for Trail Status Changes
 * PHP 8.0+ compatible with proper error handling and security
 * Supports both legacy VAPID and modern FCM V1 API
 */

// Notification configuration
const ENABLE_NOTIFICATIONS = true;
const ENABLE_PUSH_NOTIFICATIONS = true;
const VAPID_SUBJECT = 'https://zeroglitch.com/trailstatus';

// Load local configuration for VAPID keys and FCM
if (file_exists(dirname(__DIR__) . '/config.local.php')) {
    require_once dirname(__DIR__) . '/config.local.php';
} else {
    // Default empty keys - update config.local.php with actual keys
    if (!defined('VAPID_PUBLIC_KEY')) define('VAPID_PUBLIC_KEY', '');
    if (!defined('VAPID_PRIVATE_KEY')) define('VAPID_PRIVATE_KEY', '');
}

// Load FCM V1 API if available
$fcm_v1_available = defined('FCM_PROJECT_ID') && defined('FCM_SERVICE_ACCOUNT_PATH') && file_exists(FCM_SERVICE_ACCOUNT_PATH);
if ($fcm_v1_available) {
    require_once __DIR__ . '/fcm-v1-notifications.php';
}

/**
 * Push notification subscriber management
 */

// Load push notification subscribers
function loadPushSubscribers(): array {
    $push_subscribers_file = PUSH_SUBSCRIBERS_FILE;
    return loadJsonData($push_subscribers_file);
}

// Save push notification subscribers  
function savePushSubscribers(array $subscribers): bool {
    $push_subscribers_file = PUSH_SUBSCRIBERS_FILE;
    return saveJsonData($push_subscribers_file, $subscribers);
}

// Add a new push notification subscriber
function addPushSubscriber(string $endpoint, string $p256dh_key, string $auth_key, string $user_agent = '', array $trails = []): bool {
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
        if (($subscriber['id'] ?? 0) > $max_id) {
            $max_id = $subscriber['id'];
        }
    }
    
    $new_subscriber = [
        'id' => $max_id + 1,
        'endpoint' => $endpoint,
        'p256dh' => $p256dh_key,
        'auth' => $auth_key,
        'user_agent' => $user_agent,
        'trails' => empty($trails) ? ['all'] : $trails,
        'created_at' => date('Y-m-d H:i:s'),
        'active' => true
    ];
    
    $subscribers[] = $new_subscriber;
    return savePushSubscribers($subscribers);
}

// Remove a push notification subscriber
function removePushSubscriber(string $endpoint): bool {
    $subscribers = loadPushSubscribers();
    $filtered_subscribers = array_filter($subscribers, function($subscriber) use ($endpoint) {
        return $subscriber['endpoint'] !== $endpoint;
    });
    
    return savePushSubscribers(array_values($filtered_subscribers));
}

/**
 * Push notification sending functions
 */

// Main push notification sender with proper error handling
function sendPushNotification(string $endpoint, string $p256dh, string $auth, array $payload): bool {
    global $fcm_v1_available;
    
    try {
        // Validate inputs
        if (empty($endpoint) || empty($payload)) {
            error_log("Invalid push notification parameters");
            return false;
        }
        
        // Route to appropriate push service based on endpoint
        if (strpos($endpoint, 'fcm.googleapis.com') !== false) {
            // FCM endpoints - use FCM V1 API if available
            if ($fcm_v1_available) {
                return sendFcmV1PushNotification($endpoint, $p256dh, $auth, $payload);
            } else {
                error_log("FCM endpoint detected but FCM V1 API not available");
                return false;
            }
        } else {
            // Non-FCM endpoints (Mozilla, Safari, Edge, etc.) - use VAPID/Web Push
            if (empty(VAPID_PUBLIC_KEY) || empty(VAPID_PRIVATE_KEY)) {
                error_log("VAPID keys not configured for Web Push notifications");
                return false;
            }
            
            return sendVAPIDNotification($endpoint, $p256dh, $auth, $payload);
        }
        
    } catch (Exception $e) {
        error_log("Push notification error: " . $e->getMessage());
        return false;
    }
}

// VAPID-authenticated push notification with proper JWT signing
function sendVAPIDNotification(string $endpoint, string $p256dh, string $auth, array $payload): bool {
    try {
        // Create JWT for VAPID authentication
        $jwt = createVAPIDJWT($endpoint);
        if (!$jwt) {
            error_log("Failed to create VAPID JWT");
            return false;
        }
        
        // Prepare the payload
        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);
        
        // Create headers
        $headers = [
            'Authorization: vapid t=' . $jwt . ', k=' . VAPID_PUBLIC_KEY,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payloadJson),
            'TTL: 86400'
        ];
        
        // If encryption keys are provided, add encryption headers
        if (!empty($p256dh) && !empty($auth)) {
            $headers[] = 'Crypto-Key: dh=' . $p256dh . ';p256ecdsa=' . VAPID_PUBLIC_KEY;
            $headers[] = 'Encryption: salt=' . base64url_encode(random_bytes(16));
        } else {
            // Even without encryption, FCM requires the public key in Crypto-Key header
            $headers[] = 'Crypto-Key: p256ecdsa=' . VAPID_PUBLIC_KEY;
        }
        
        // Send the notification
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'LCFTF Trail Status/1.0'
        ]);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("cURL error in push notification: " . $error);
            return false;
        }
        
        // Debug: Log the full request details
        error_log("Push notification debug:");
        error_log("- Endpoint: " . $endpoint);
        error_log("- HTTP Code: " . $http_code);
        error_log("- JWT: " . substr($jwt, 0, 50) . "..." . substr($jwt, -20));
        error_log("- Payload: " . $payloadJson);
        error_log("- Response: " . ($result ?: 'empty'));
        error_log("- Headers sent: " . implode("; ", $headers));
        
        // Log response for debugging
        if ($http_code < 200 || $http_code >= 300) {
            $errorMsg = "Push notification failed (HTTP $http_code)";
            if ($result) {
                $errorMsg .= ": " . $result;
            }
            error_log($errorMsg);
            echo $errorMsg . "\n";
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("VAPID notification error: " . $e->getMessage());
        return false;
    }
}

// Create JWT for VAPID authentication  
function createVAPIDJWT(string $endpoint): string|false {
    try {
        // Determine audience based on endpoint
        $aud = 'https://fcm.googleapis.com'; // Default for FCM
        
        if (strpos($endpoint, 'mozilla.com') !== false) {
            $aud = 'https://updates.push.services.mozilla.com';
        } elseif (strpos($endpoint, 'windows.com') !== false || strpos($endpoint, 'microsoft.com') !== false) {
            $aud = 'https://login.microsoftonline.com';
        } elseif (strpos($endpoint, 'apple.com') !== false) {
            // Safari push notifications would go here
            $aud = 'https://api.push.apple.com';
        }
        
        error_log("VAPID JWT: Using audience: " . $aud . " for endpoint: " . substr($endpoint, 0, 50) . "...");
        
        // Create JWT header
        $header = json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR);
        
        // Create JWT claims
        $claims = json_encode([
            'aud' => $aud,
            'exp' => time() + 3600, // 1 hour expiry
            'sub' => VAPID_SUBJECT
        ], JSON_THROW_ON_ERROR);
        
        // Base64url encode header and claims
        $headerEncoded = base64url_encode($header);
        $claimsEncoded = base64url_encode($claims);
        
        // Create the signature
        $unsignedToken = $headerEncoded . '.' . $claimsEncoded;
        $signature = signVAPIDJWT($unsignedToken);
        
        if (!$signature) {
            return false;
        }
        
        return $unsignedToken . '.' . $signature;
        
    } catch (Exception $e) {
        error_log("JWT creation error: " . $e->getMessage());
        return false;
    }
}

// Sign JWT using the VAPID private key
function signVAPIDJWT(string $data): string|false {
    try {
        // Decode the VAPID private key from base64url
        $privateKeyRaw = base64url_decode(VAPID_PRIVATE_KEY);
        
        if (strlen($privateKeyRaw) !== 32) {
            error_log("Invalid VAPID private key length: " . strlen($privateKeyRaw));
            return false;
        }
        
        // Create hash of the data to sign
        $hash = hash('sha256', $data, true);
        
        // Use OpenSSL to sign with ECDSA P-256
        // First, we need to create a proper OpenSSL private key resource
        $privateKeyPem = createP256PrivateKeyPEM($privateKeyRaw);
        if (!$privateKeyPem) {
            error_log("Failed to create private key PEM");
            return false;
        }
        
        $privateKeyResource = openssl_pkey_get_private($privateKeyPem);
        if (!$privateKeyResource) {
            error_log("Failed to load private key: " . openssl_error_string());
            return false;
        }
        
        // Sign the hash with ECDSA
        $signature = '';
        if (!openssl_sign($hash, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256)) {
            error_log("Failed to sign JWT: " . openssl_error_string());
            openssl_free_key($privateKeyResource);
            return false;
        }
        
        openssl_free_key($privateKeyResource);
        
        // Convert DER signature to raw format (64 bytes for P-256)
        $rawSignature = convertDERSignatureToRaw($signature);
        if (!$rawSignature) {
            error_log("Failed to convert signature to raw format");
            return false;
        }
        
        return base64url_encode($rawSignature);
        
    } catch (Exception $e) {
        error_log("JWT signing error: " . $e->getMessage());
        return false;
    }
}

// Helper function to create a PEM private key from raw bytes
function createP256PrivateKeyPEM(string $privateKeyRaw): string|false {
    // This is a simplified approach - create an EC private key structure
    // For production, you'd want to use a proper crypto library
    
    // P-256 private key DER structure template
    $template = hex2bin('3041020100301306072a8648ce3d020106082a8648ce3d030107042730250201010420') 
              . $privateKeyRaw 
              . hex2bin('a00a06082a8648ce3d030107');
    
    $pem = "-----BEGIN EC PRIVATE KEY-----\n";
    $pem .= chunk_split(base64_encode($template), 64, "\n");
    $pem .= "-----END EC PRIVATE KEY-----\n";
    
    return $pem;
}

// Convert DER signature to raw 64-byte format for VAPID
function convertDERSignatureToRaw(string $derSignature): string|false {
    // Parse DER signature structure to extract r and s values
    // This is simplified - for production use a proper ASN.1 parser
    
    if (strlen($derSignature) < 8) {
        return false;
    }
    
    // Skip SEQUENCE tag and length
    $offset = 2;
    
    // Extract r value
    if (ord($derSignature[$offset]) !== 0x02) { // INTEGER tag
        return false;
    }
    $offset++;
    $rLength = ord($derSignature[$offset]);
    $offset++;
    $r = substr($derSignature, $offset, $rLength);
    $offset += $rLength;
    
    // Extract s value  
    if (ord($derSignature[$offset]) !== 0x02) { // INTEGER tag
        return false;
    }
    $offset++;
    $sLength = ord($derSignature[$offset]);
    $offset++;
    $s = substr($derSignature, $offset, $sLength);
    
    // Pad r and s to 32 bytes each
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    
    return $r . $s;
}

// Base64url encoding functions
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string|false {
    $pad = 4 - (strlen($data) % 4);
    if ($pad < 4) {
        $data .= str_repeat('=', $pad);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Trail status notification functions
 */

// Send push notifications for trail status change
function notifyTrailStatusChangePush(int $trail_id, string $trail_name, string $old_status, string $new_status, string $updated_by): void {
    if (!ENABLE_PUSH_NOTIFICATIONS) {
        return;
    }
    
    $subscribers = loadPushSubscribers();
    if (empty($subscribers)) {
        return;
    }
    
    $payload = [
        'title' => 'Trail Status Update',
        'body' => sprintf('%s is now %s', $trail_name, ucfirst($new_status)),
        'icon' => '/trailstatus/images/ftf_logo.jpg',
        'badge' => '/trailstatus/images/ftf_logo.jpg',
        'data' => [
            'trail_id' => $trail_id,
            'trail_name' => $trail_name,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'updated_by' => $updated_by,
            'updated_at' => date('Y-m-d H:i:s'),
            'url' => 'https://zeroglitch.com/trailstatus/'
        ],
        'requireInteraction' => $new_status === STATUS_CLOSED,
        'tag' => 'trail-status-' . $trail_id
    ];
    
    $success_count = 0;
    $failed_count = 0;
    
    foreach ($subscribers as $subscriber) {
        if (!($subscriber['active'] ?? true)) {
            continue;
        }
        
        // Check if subscriber wants notifications for this trail
        $subscriber_trails = $subscriber['trails'] ?? ['all'];
        if (!in_array('all', $subscriber_trails) && !in_array($trail_id, $subscriber_trails)) {
            continue;
        }
        
        $success = sendPushNotification(
            $subscriber['endpoint'] ?? '',
            $subscriber['p256dh'] ?? '',
            $subscriber['auth'] ?? '',
            $payload
        );
        
        if ($success) {
            $success_count++;
        } else {
            $failed_count++;
        }
    }
    
    error_log("Push notifications sent: $success_count successful, $failed_count failed");
}

// Main notification function
function notifyTrailStatusChange(int $trail_id, string $trail_name, string $old_status, string $new_status, string $updated_by): void {
    if (!ENABLE_NOTIFICATIONS) {
        return;
    }
    
    // Send push notifications
    if (ENABLE_PUSH_NOTIFICATIONS) {
        notifyTrailStatusChangePush($trail_id, $trail_name, $old_status, $new_status, $updated_by);
    }
}

// Initialize default push subscribers file if it doesn't exist
if (!file_exists(PUSH_SUBSCRIBERS_FILE)) {
    savePushSubscribers([]);
}
?>
