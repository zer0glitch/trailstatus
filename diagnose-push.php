<?php
/**
 * Comprehensive Push Notification Diagnostic Tool
 * This script checks all aspects of your push notification setup
 */

require_once 'includes/config.php';
require_once 'includes/notifications.php';

echo "=== PUSH NOTIFICATION DIAGNOSTIC TOOL ===\n\n";

// 1. Check VAPID configuration
echo "1. Checking VAPID Configuration:\n";
echo "   Public Key: " . (defined('VAPID_PUBLIC_KEY') && !empty(VAPID_PUBLIC_KEY) ? "✓ Configured" : "✗ Missing") . "\n";
echo "   Private Key: " . (defined('VAPID_PRIVATE_KEY') && !empty(VAPID_PRIVATE_KEY) ? "✓ Configured" : "✗ Missing") . "\n";
echo "   Subject: " . (defined('VAPID_SUBJECT') ? VAPID_SUBJECT : "✗ Missing") . "\n\n";

if (empty(VAPID_PUBLIC_KEY) || empty(VAPID_PRIVATE_KEY)) {
    echo "ERROR: VAPID keys are not configured. Run 'php generate-proper-vapid.php' to generate them.\n\n";
}

// 2. Check OpenSSL functionality
echo "2. Checking OpenSSL Support:\n";
echo "   OpenSSL Extension: " . (extension_loaded('openssl') ? "✓ Available" : "✗ Missing") . "\n";
echo "   OpenSSL Functions: " . (function_exists('openssl_sign') ? "✓ Available" : "✗ Missing") . "\n";

if (extension_loaded('openssl')) {
    echo "   OpenSSL Version: " . OPENSSL_VERSION_TEXT . "\n";
}
echo "\n";

// 3. Check cURL functionality
echo "3. Checking cURL Support:\n";
echo "   cURL Extension: " . (extension_loaded('curl') ? "✓ Available" : "✗ Missing") . "\n";
if (extension_loaded('curl')) {
    $curl_version = curl_version();
    echo "   cURL Version: " . $curl_version['version'] . "\n";
    echo "   SSL Support: " . (isset($curl_version['ssl_version']) ? "✓ " . $curl_version['ssl_version'] : "✗ Missing") . "\n";
}
echo "\n";

// 4. Check subscribers
echo "4. Checking Push Subscribers:\n";
$subscribers = loadPushSubscribers();
echo "   Total Subscribers: " . count($subscribers) . "\n";

if (!empty($subscribers)) {
    $active_count = 0;
    foreach ($subscribers as $subscriber) {
        if ($subscriber['active']) $active_count++;
    }
    echo "   Active Subscribers: $active_count\n";
    
    echo "   Subscriber Details:\n";
    foreach ($subscribers as $i => $subscriber) {
        echo "   " . ($i + 1) . ". Endpoint: " . substr($subscriber['endpoint'], 0, 60) . "...\n";
        echo "      Active: " . ($subscriber['active'] ? 'Yes' : 'No') . "\n";
        echo "      Created: " . $subscriber['created_at'] . "\n";
        echo "      User Agent: " . substr($subscriber['user_agent'], 0, 50) . "...\n\n";
    }
}
echo "\n";

// 5. Test JWT signing (if keys are available)
if (!empty(VAPID_PUBLIC_KEY) && !empty(VAPID_PRIVATE_KEY)) {
    echo "5. Testing JWT Signing:\n";
    
    $test_data = "test.payload";
    $signature = signJWT($test_data, VAPID_PRIVATE_KEY);
    
    if ($signature !== false) {
        echo "   JWT Signing: ✓ Working\n";
        echo "   Sample Signature: " . substr($signature, 0, 20) . "...\n";
    } else {
        echo "   JWT Signing: ✗ Failed\n";
    }
    echo "\n";
}

// 6. Test notification sending (if subscribers exist)
if (!empty($subscribers)) {
    echo "6. Testing Notification Sending:\n";
    
    $test_payload = array(
        'title' => 'Diagnostic Test',
        'body' => 'This is a diagnostic test notification',
        'icon' => './images/ftf_logo.jpg',
        'data' => array('test' => true, 'timestamp' => time())
    );
    
    $test_subscriber = $subscribers[0]; // Test with first subscriber
    echo "   Testing with endpoint: " . substr($test_subscriber['endpoint'], -30) . "...\n";
    
    $result = sendPushNotification(
        $test_subscriber['endpoint'],
        $test_subscriber['p256dh'],
        $test_subscriber['auth'],
        $test_payload
    );
    
    echo "   Test Result: " . ($result ? "✓ Success" : "✗ Failed") . "\n";
    echo "   (Check browser for actual notification delivery)\n\n";
}

// 7. Configuration recommendations
echo "7. Configuration Recommendations:\n";
echo "   - Ensure your website is served over HTTPS\n";
echo "   - Verify service worker is properly registered\n";
echo "   - Check browser console for subscription errors\n";
echo "   - Ensure VAPID keys are properly formatted\n";
echo "   - Test on multiple browsers and devices\n\n";

// 8. Quick setup commands
echo "8. Quick Setup Commands:\n";
echo "   Generate new VAPID keys: php generate-proper-vapid.php\n";
echo "   Test push notifications: php test-push.php\n";
echo "   Check environment: php test-environment.php\n\n";

echo "=== DIAGNOSTIC COMPLETE ===\n";
?>
