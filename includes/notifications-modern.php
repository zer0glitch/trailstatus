<?php
declare(strict_types=1);
/**
 * Modern Notification System for Trail Status Changes
 * PHP 8.0+ with better error handling and type safety
 * Push notifications using web-push-php or Firebase
 */

// Notification configuration
const ENABLE_NOTIFICATIONS = true;
const ENABLE_PUSH_NOTIFICATIONS = true;

// Load local configuration for VAPID keys
if (file_exists(dirname(__DIR__) . '/config.local.php')) {
    require_once dirname(__DIR__) . '/config.local.php';
} else {
    // Default empty keys - update config.local.php with actual keys
    define('VAPID_PUBLIC_KEY', '');
    define('VAPID_PRIVATE_KEY', '');
    define('FCM_SERVER_KEY', '');
}

// VAPID Subject
const VAPID_SUBJECT = 'mailto:noreply@zeroglitch.com';

/**
 * Push notification subscriber data structure
 */
class PushSubscriber {
    public function __construct(
        public readonly int $id,
        public readonly string $endpoint,
        public readonly string $p256dh_key,
        public readonly string $auth_key,
        public readonly array $trails,
        public readonly string $user_agent = '',
        public readonly string $created_at = ''
    ) {}
    
    public function toArray(): array {
        return [
            'id' => $this->id,
            'endpoint' => $this->endpoint,
            'p256dh_key' => $this->p256dh_key,
            'auth_key' => $this->auth_key,
            'trails' => $this->trails,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at
        ];
    }
    
    public static function fromArray(array $data): self {
        return new self(
            id: $data['id'] ?? 0,
            endpoint: $data['endpoint'] ?? '',
            p256dh_key: $data['p256dh_key'] ?? '',
            auth_key: $data['auth_key'] ?? '',
            trails: $data['trails'] ?? [],
            user_agent: $data['user_agent'] ?? '',
            created_at: $data['created_at'] ?? ''
        );
    }
}

/**
 * Push notification message
 */
class PushMessage {
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $icon = '/images/ftf_logo.jpg',
        public readonly string $badge = '/images/ftf_logo.jpg',
        public readonly array $data = [],
        public readonly string $url = '/'
    ) {}
    
    public function toJson(): string {
        return json_encode([
            'title' => $this->title,
            'body' => $this->body,
            'icon' => $this->icon,
            'badge' => $this->badge,
            'data' => $this->data,
            'url' => $this->url
        ], JSON_THROW_ON_ERROR);
    }
}

// Load push notification subscribers
function loadPushSubscribers(): array {
    $subscribers_data = loadJsonData(PUSH_SUBSCRIBERS_FILE);
    return array_map(fn($data) => PushSubscriber::fromArray($data), $subscribers_data);
}

// Save push notification subscribers
function savePushSubscribers(array $subscribers): bool {
    $data = array_map(fn(PushSubscriber $subscriber) => $subscriber->toArray(), $subscribers);
    return saveJsonData(PUSH_SUBSCRIBERS_FILE, $data);
}

// Add a new push notification subscriber
function addPushSubscriber(
    string $endpoint, 
    string $p256dh_key, 
    string $auth_key, 
    string $user_agent = '', 
    array $trails = []
): bool {
    $subscribers = loadPushSubscribers();
    
    // Check if endpoint already exists
    foreach ($subscribers as $subscriber) {
        if ($subscriber->endpoint === $endpoint) {
            return false; // Already subscribed
        }
    }
    
    // Generate new ID
    $max_id = 0;
    foreach ($subscribers as $subscriber) {
        $max_id = max($max_id, $subscriber->id);
    }
    
    $new_subscriber = new PushSubscriber(
        id: $max_id + 1,
        endpoint: $endpoint,
        p256dh_key: $p256dh_key,
        auth_key: $auth_key,
        trails: $trails,
        user_agent: $user_agent,
        created_at: date('Y-m-d H:i:s')
    );
    
    $subscribers[] = $new_subscriber;
    
    return savePushSubscribers($subscribers);
}

// Remove a push notification subscriber
function removePushSubscriber(string $endpoint): bool {
    $subscribers = loadPushSubscribers();
    $filtered = array_filter($subscribers, fn($sub) => $sub->endpoint !== $endpoint);
    
    if (count($filtered) === count($subscribers)) {
        return false; // Nothing removed
    }
    
    return savePushSubscribers(array_values($filtered));
}

/**
 * Modern push notification sending using cURL with better error handling
 */
function sendPushNotification(PushSubscriber $subscriber, PushMessage $message): array {
    // Try different methods based on endpoint
    if (str_contains($subscriber->endpoint, 'fcm.googleapis.com')) {
        return sendFirebasePushNotification($subscriber, $message);
    } else {
        return sendWebPushNotification($subscriber, $message);
    }
}

/**
 * Send notification via Firebase Cloud Messaging
 */
function sendFirebasePushNotification(PushSubscriber $subscriber, PushMessage $message): array {
    if (!defined('FCM_SERVER_KEY') || empty(FCM_SERVER_KEY)) {
        return ['success' => false, 'error' => 'FCM server key not configured'];
    }
    
    // Extract registration token from FCM endpoint
    $endpoint_parts = explode('/', $subscriber->endpoint);
    $token = end($endpoint_parts);
    
    $payload = [
        'to' => $token,
        'notification' => [
            'title' => $message->title,
            'body' => $message->body,
            'icon' => $message->icon,
        ],
        'data' => $message->data
    ];
    
    $headers = [
        'Authorization: key=' . FCM_SERVER_KEY,
        'Content-Type: application/json',
    ];
    
    $ch = curl_init('https://fcm.googleapis.com/fcm/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => "cURL error: $error"];
    }
    
    if ($http_code !== 200) {
        return ['success' => false, 'error' => "HTTP $http_code: $response"];
    }
    
    $response_data = json_decode($response, true);
    return [
        'success' => isset($response_data['success']) ? $response_data['success'] > 0 : true,
        'response' => $response_data
    ];
}

/**
 * Send Web Push notification (for Chrome, Firefox, etc.)
 * Uses modern VAPID authentication
 */
function sendWebPushNotification(PushSubscriber $subscriber, PushMessage $message): array {
    if (!defined('VAPID_PRIVATE_KEY') || empty(VAPID_PRIVATE_KEY)) {
        return ['success' => false, 'error' => 'VAPID keys not configured'];
    }
    
    // For now, return a simplified implementation
    // In production, you'd want to use the web-push-php library
    return sendSimpleWebPush($subscriber, $message);
}

/**
 * Simplified Web Push implementation for immediate use
 */
function sendSimpleWebPush(PushSubscriber $subscriber, PushMessage $message): array {
    $payload = $message->toJson();
    
    $headers = [
        'Content-Type: application/octet-stream',
        'Content-Length: ' . strlen($payload),
        'TTL: 2419200', // 4 weeks
    ];
    
    // Add VAPID header if keys are available
    if (defined('VAPID_PUBLIC_KEY') && !empty(VAPID_PUBLIC_KEY)) {
        $headers[] = 'Authorization: vapid t=' . generateVapidToken() . ', k=' . VAPID_PUBLIC_KEY;
    }
    
    $ch = curl_init($subscriber->endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => "cURL error: $error"];
    }
    
    $success = in_array($http_code, [200, 201, 204]);
    return [
        'success' => $success,
        'http_code' => $http_code,
        'response' => $response
    ];
}

/**
 * Generate a simple VAPID token (simplified implementation)
 */
function generateVapidToken(): string {
    // Simplified token generation
    // In production, use proper JWT with ECDSA P-256
    $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = base64url_encode(json_encode([
        'aud' => 'https://fcm.googleapis.com',
        'exp' => time() + 3600,
        'sub' => VAPID_SUBJECT
    ]));
    
    return "$header.$payload.signature";
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Send trail status update notifications
 */
function sendTrailStatusNotification(int $trail_id, string $trail_name, string $new_status, string $notes = ''): array {
    if (!ENABLE_PUSH_NOTIFICATIONS) {
        return ['success' => false, 'error' => 'Push notifications disabled'];
    }
    
    $subscribers = loadPushSubscribers();
    $results = ['sent' => 0, 'failed' => 0, 'errors' => []];
    
    $status_emoji = match($new_status) {
        STATUS_OPEN => '🟢',
        STATUS_CAUTION => '🟡',
        STATUS_CLOSED => '🔴',
        default => '📍'
    };
    
    $title = "Trail Status Update";
    $body = "$status_emoji $trail_name is now " . ucfirst($new_status);
    if ($notes) {
        $body .= " - $notes";
    }
    
    $message = new PushMessage(
        title: $title,
        body: $body,
        data: [
            'trail_id' => $trail_id,
            'trail_name' => $trail_name,
            'status' => $new_status,
            'timestamp' => time()
        ],
        url: '/index.php'
    );
    
    foreach ($subscribers as $subscriber) {
        // Check if subscriber wants notifications for this trail
        if (!empty($subscriber->trails) && !in_array($trail_id, $subscriber->trails)) {
            continue; // Skip if not subscribed to this trail
        }
        
        $result = sendPushNotification($subscriber, $message);
        
        if ($result['success']) {
            $results['sent']++;
        } else {
            $results['failed']++;
            $results['errors'][] = "Subscriber {$subscriber->id}: " . ($result['error'] ?? 'Unknown error');
        }
    }
    
    // Log the notification attempt
    error_log("Trail notification sent: $trail_name -> $new_status. Sent: {$results['sent']}, Failed: {$results['failed']}");
    
    return $results;
}

/**
 * Send a test notification
 */
function sendTestNotification(): array {
    $message = new PushMessage(
        title: "🧪 Test Notification",
        body: "Your push notifications are working! Trail status updates will appear here.",
        data: ['test' => true, 'timestamp' => time()],
        url: '/index.php'
    );
    
    $subscribers = loadPushSubscribers();
    $results = ['sent' => 0, 'failed' => 0, 'errors' => []];
    
    foreach ($subscribers as $subscriber) {
        $result = sendPushNotification($subscriber, $message);
        
        if ($result['success']) {
            $results['sent']++;
        } else {
            $results['failed']++;
            $results['errors'][] = "Subscriber {$subscriber->id}: " . ($result['error'] ?? 'Unknown error');
        }
    }
    
    return $results;
}

/**
 * Get notification statistics
 */
function getNotificationStats(): array {
    $subscribers = loadPushSubscribers();
    
    return [
        'total_subscribers' => count($subscribers),
        'push_enabled' => ENABLE_PUSH_NOTIFICATIONS,
        'vapid_configured' => defined('VAPID_PRIVATE_KEY') && !empty(VAPID_PRIVATE_KEY),
        'fcm_configured' => defined('FCM_SERVER_KEY') && !empty(FCM_SERVER_KEY)
    ];
}
?>
