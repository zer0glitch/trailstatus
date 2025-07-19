<?php
/**
 * Push Notification Subscription Handler
 * Handles push notification subscription and unsubscription requests
 */

require_once 'includes/config.php';
require_once 'includes/notifications.php';

// Prevent caching for subscription API responses
preventCaching();

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => 'Method not allowed'));
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(array('error' => 'Invalid JSON'));
    exit;
}

$action = isset($data['action']) ? $data['action'] : '';

try {
    if ($action === 'subscribe') {
        if (!isset($data['subscription'])) {
            http_response_code(400);
            echo json_encode(array('error' => 'Subscription data required'));
            exit;
        }
        
        $subscription = $data['subscription'];
        $endpoint = isset($subscription['endpoint']) ? $subscription['endpoint'] : '';
        
        if (empty($endpoint)) {
            http_response_code(400);
            echo json_encode(array('error' => 'Invalid subscription endpoint'));
            exit;
        }
        
        // Extract keys
        $p256dh = '';
        $auth = '';
        
        if (isset($subscription['keys'])) {
            $p256dh = isset($subscription['keys']['p256dh']) ? $subscription['keys']['p256dh'] : '';
            $auth = isset($subscription['keys']['auth']) ? $subscription['keys']['auth'] : '';
        }
        
        // Get user agent for tracking
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        // Default to all trails
        $trails = array('all');
        
        if (addPushSubscriber($endpoint, $p256dh, $auth, $user_agent, $trails)) {
            echo json_encode(array('success' => true, 'message' => 'Push subscription added successfully'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Subscription already exists or failed to save'));
        }
        
    } elseif ($action === 'unsubscribe') {
        $endpoint = isset($data['endpoint']) ? $data['endpoint'] : '';
        
        if (empty($endpoint)) {
            http_response_code(400);
            echo json_encode(array('error' => 'Endpoint required for unsubscription'));
            exit;
        }
        
        if (removePushSubscriber($endpoint)) {
            echo json_encode(array('success' => true, 'message' => 'Push subscription removed successfully'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Subscription not found or failed to remove'));
        }
        
    } else {
        http_response_code(400);
        echo json_encode(array('error' => 'Invalid action'));
    }
    
} catch (Exception $e) {
    error_log("Push subscription error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(array('error' => 'Internal server error'));
}
?>
