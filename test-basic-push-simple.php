<?php
/**
 * PHP 5.4 Compatible Push Notification Test
 */

require_once 'includes/config.php';

echo "=== SIMPLE PUSH TEST (PHP 5.4 Compatible) ===\n\n";

// Check if VAPID keys are configured
if (!defined('VAPID_PUBLIC_KEY') || empty(VAPID_PUBLIC_KEY)) {
    echo "Error: VAPID public key not configured.\n";
    echo "Please run: php generate-keys-simple.php\n";
    exit(1);
}

if (!defined('VAPID_PRIVATE_KEY') || empty(VAPID_PRIVATE_KEY)) {
    echo "Error: VAPID private key not configured.\n";
    echo "Please run: php generate-keys-simple.php\n";
    exit(1);
}

echo "VAPID keys are configured\n";
echo "Public Key: " . substr(VAPID_PUBLIC_KEY, 0, 20) . "...\n";
echo "Private Key: " . substr(VAPID_PRIVATE_KEY, 0, 20) . "...\n\n";

// Load push subscribers
$push_subscribers_file = DATA_DIR . 'push_subscribers.json';
echo "Loading subscribers from: $push_subscribers_file\n";

if (!file_exists($push_subscribers_file)) {
    echo "No subscribers file found. Creating empty file.\n";
    file_put_contents($push_subscribers_file, json_encode(array()));
    $subscribers = array();
} else {
    $content = file_get_contents($push_subscribers_file);
    $subscribers = json_decode($content, true);
    if (!$subscribers) {
        $subscribers = array();
    }
}

echo "Found " . count($subscribers) . " subscribers\n\n";

if (empty($subscribers)) {
    echo "No subscribers found. To test push notifications:\n";
    echo "1. Visit your website in a browser\n";
    echo "2. Click 'Enable Notifications'\n";
    echo "3. Allow notifications when prompted\n";
    echo "4. Run this test again\n";
    exit(0);
}

// Simple push notification function
function sendBasicPushNotification($endpoint, $payload) {
    $payloadJson = json_encode($payload);
    
    $headers = array(
        'Content-Type: application/json',
        'TTL: 86400'
    );
    
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
    echo "\n";
    
    return $httpCode >= 200 && $httpCode < 300;
}

echo "Sending test notifications...\n\n";

$test_payload = array(
    'title' => 'Test Notification',
    'body' => 'Simple test from LCFTF Trail Status',
    'icon' => './images/ftf_logo.jpg',
    'data' => array(
        'test' => true,
        'timestamp' => date('Y-m-d H:i:s')
    )
);

$success_count = 0;
$error_count = 0;

foreach ($subscribers as $subscriber) {
    if (isset($subscriber['active']) && !$subscriber['active']) {
        echo "Skipping inactive subscriber\n";
        continue;
    }
    
    $endpoint_preview = substr($subscriber['endpoint'], -30);
    echo "Sending to: ..." . $endpoint_preview . " ";
    
    $result = sendBasicPushNotification($subscriber['endpoint'], $test_payload);
    
    if ($result) {
        echo "Success\n";
        $success_count++;
    } else {
        echo "Failed\n";
        $error_count++;
    }
}

echo "\n=== TEST RESULTS ===\n";
echo "Successful: $success_count\n";
echo "Failed: $error_count\n";

if ($success_count > 0) {
    echo "\nNotifications sent! Check your browser for notifications.\n";
} else {
    echo "\nNo notifications were sent successfully.\n";
    echo "This basic test doesn't use VAPID authentication.\n";
    echo "For full FCM support, we need to fix the VAPID implementation.\n";
}

echo "\nNote: This is a basic test without VAPID authentication.\n";
echo "For production use, proper VAPID signing is required for FCM endpoints.\n";
?>
