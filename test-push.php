<?php
/**
 * Test Push Notifications
 * Simple script to test if push notifications are working
 */

require_once 'includes/config.php';
require_once 'includes/notifications.php';

// Prevent caching to ensure fresh test results
preventCaching();

echo "Testing Push Notifications...\n\n";

// Load push subscribers
$subscribers = loadPushSubscribers();

echo "Found " . count($subscribers) . " push subscribers:\n";

if (empty($subscribers)) {
    echo "No push subscribers found. Please subscribe to push notifications first.\n";
    exit;
}

// Display subscribers
foreach ($subscribers as $i => $subscriber) {
    echo ($i + 1) . ". " . substr($subscriber['endpoint'], 0, 50) . "...\n";
    echo "   User Agent: " . substr($subscriber['user_agent'], 0, 50) . "...\n";
    echo "   Created: " . $subscriber['created_at'] . "\n\n";
}

// Send a test notification
echo "Sending test notification to all subscribers...\n";

// Test notification data
$test_payload = array(
    'title' => 'Test Notification',
    'body' => 'This is a test push notification from LCFTF Trail Status',
    'icon' => './images/ftf_logo.jpg',
    'badge' => './images/ftf_logo.jpg',
    'data' => array(
        'test' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'url' => 'https://zeroglitch.com/trailstatus/'
    )
);

$success_count = 0;
$error_count = 0;

foreach ($subscribers as $subscriber) {
    if (!$subscriber['active']) {
        echo "Skipping inactive subscriber\n";
        continue;
    }
    
    echo "Sending to: " . substr($subscriber['endpoint'], -20) . "... ";
    
    $result = sendPushNotification(
        $subscriber['endpoint'], 
        $subscriber['p256dh'], 
        $subscriber['auth'], 
        $test_payload
    );
    
    if ($result) {
        echo "✓ Success\n";
        $success_count++;
    } else {
        echo "✗ Failed\n";
        $error_count++;
    }
}

echo "\nTest completed:\n";
echo "Successful: $success_count\n";
echo "Failed: $error_count\n";

if ($success_count > 0) {
    echo "\nIf notifications were sent successfully, you should receive them within a few seconds.\n";
    echo "Check your browser notifications or notification center.\n";
} else {
    echo "\nNo notifications were sent successfully. Check the push notification implementation.\n";
}
?>
