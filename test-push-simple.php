<?php
/**
 * Simple Push Notification Test
 * Uses the simplified implementation to test notifications
 */

require_once 'includes/config.php';
require_once 'includes/push-simple.php';

echo "=== SIMPLE PUSH NOTIFICATION TEST ===\n\n";

// Test the setup first
echo "1. Testing Push Notification Setup:\n";
$setupOk = testPushNotificationSetup();

if (!$setupOk) {
    echo "\nSetup test failed. Please run 'php generate-proper-vapid.php' to generate VAPID keys.\n";
    exit(1);
}

echo "\n2. Loading Subscribers:\n";

// Load subscribers from the existing system
$push_subscribers_file = DATA_DIR . 'push_subscribers.json';
if (!file_exists($push_subscribers_file)) {
    echo "No subscribers file found. Creating empty file.\n";
    file_put_contents($push_subscribers_file, json_encode([]));
    $subscribers = [];
} else {
    $content = file_get_contents($push_subscribers_file);
    $subscribers = json_decode($content, true) ?: [];
}

echo "Found " . count($subscribers) . " subscribers\n\n";

if (empty($subscribers)) {
    echo "No subscribers found. Please:\n";
    echo "1. Visit your website\n";
    echo "2. Click 'Enable Notifications'\n";
    echo "3. Allow notifications in your browser\n";
    echo "4. Run this test again\n";
    exit(0);
}

echo "3. Sending Test Notifications:\n";

$test_payload = [
    'title' => 'Simple Test Notification',
    'body' => 'This is a test from the simplified push system',
    'icon' => './images/ftf_logo.jpg',
    'badge' => './images/ftf_logo.jpg',
    'data' => [
        'test' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'url' => 'https://zeroglitch.com/trailstatus/'
    ]
];

$success_count = 0;
$error_count = 0;

foreach ($subscribers as $subscriber) {
    if (!isset($subscriber['active']) || !$subscriber['active']) {
        echo "Skipping inactive subscriber\n";
        continue;
    }
    
    $endpoint_preview = substr($subscriber['endpoint'], -30);
    echo "Sending to: ..." . $endpoint_preview . " ";
    
    $result = sendSimplePushNotification(
        $subscriber['endpoint'],
        isset($subscriber['p256dh']) ? $subscriber['p256dh'] : '',
        isset($subscriber['auth']) ? $subscriber['auth'] : '',
        $test_payload
    );
    
    if ($result) {
        echo "✓\n";
        $success_count++;
    } else {
        echo "✗\n";
        $error_count++;
    }
}

echo "\n=== TEST RESULTS ===\n";
echo "Successful: $success_count\n";
echo "Failed: $error_count\n";

if ($success_count > 0) {
    echo "\nIf notifications were sent successfully, you should receive them within a few seconds.\n";
    echo "Check your browser notifications or notification center.\n";
} else {
    echo "\nNo notifications were sent successfully.\n";
    echo "Possible issues:\n";
    echo "- VAPID keys are not properly configured\n";
    echo "- Website is not served over HTTPS\n";
    echo "- Service worker is not properly registered\n";
    echo "- Browser notifications are blocked\n";
}

echo "\nFor troubleshooting, run: php diagnose-push.php\n";
?>
