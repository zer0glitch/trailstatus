<?php
/**
 * Proper VAPID Key Generator for FCM compatibility
 * This creates valid ECDSA P-256 keys that work with Firebase Cloud Messaging
 */

echo "Generating proper VAPID keys for FCM...\n\n";

// Check if OpenSSL is available
if (!function_exists('openssl_pkey_new')) {
    die("Error: OpenSSL extension is required but not available.\n");
}

// Generate a proper P-256 key pair
$config = array(
    'curve_name' => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
);

echo "Generating ECDSA P-256 key pair...\n";
$res = openssl_pkey_new($config);

if (!$res) {
    die("Error: Failed to generate key pair. " . openssl_error_string() . "\n");
}

// Extract the private key
$privateKey = '';
openssl_pkey_export($res, $privateKey);

// Extract the public key
$publicKeyResource = openssl_pkey_get_details($res);
$publicKeyDER = $publicKeyResource['key'];

// Parse the DER-encoded public key to extract the raw public key bytes
$publicKeyInfo = openssl_pkey_get_details($res);
$publicKeyRaw = $publicKeyInfo['ec']['pub'];

// Convert to base64url format
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Extract private key raw bytes (this is complex, so we'll use a simpler approach)
// For VAPID, we need the raw 32-byte private key value
preg_match('/-----BEGIN EC PRIVATE KEY-----\n(.+)\n-----END EC PRIVATE KEY-----/s', $privateKey, $matches);
$privateKeyBase64 = str_replace("\n", '', $matches[1]);
$privateKeyDER = base64_decode($privateKeyBase64);

// Parse the DER structure to extract the 32-byte private key
// The private key starts at offset ~8 in the DER structure
$privateKeyRaw = substr($privateKeyDER, 7, 32);

// Format keys as base64url
$publicKeyB64 = base64url_encode($publicKeyRaw);
$privateKeyB64 = base64url_encode($privateKeyRaw);

echo "Generated VAPID Keys:\n";
echo "Public Key:  " . $publicKeyB64 . "\n";
echo "Private Key: " . $privateKeyB64 . "\n\n";

// Output the config format
echo "Copy these values to your config.local.php file:\n\n";
echo "define('VAPID_PUBLIC_KEY', '" . $publicKeyB64 . "');\n";
echo "define('VAPID_PRIVATE_KEY', '" . $privateKeyB64 . "');\n\n";

// Try to update config.local.php automatically
$config_file = 'config.local.php';
if (file_exists($config_file) && is_writable($config_file)) {
    $config_content = file_get_contents($config_file);
    if ($config_content !== false) {
        // Update the keys
        $config_content = preg_replace(
            "/define\('VAPID_PUBLIC_KEY', '.*?'\);/",
            "define('VAPID_PUBLIC_KEY', '" . $publicKeyB64 . "');",
            $config_content
        );
        $config_content = preg_replace(
            "/define\('VAPID_PRIVATE_KEY', '.*?'\);/",
            "define('VAPID_PRIVATE_KEY', '" . $privateKeyB64 . "');",
            $config_content
        );
        
        if (file_put_contents($config_file, $config_content) !== false) {
            echo "✓ Keys have been automatically updated in config.local.php!\n";
        } else {
            echo "✗ Could not auto-update config.local.php. Please update manually.\n";
        }
    }
} else {
    echo "✗ config.local.php not found or not writable. Please update manually.\n";
}

echo "\nNext steps:\n";
echo "1. Test the push notifications with: php test-push.php\n";
echo "2. Check browser console for any subscription errors\n";
echo "3. Verify your website is served over HTTPS (required for push notifications)\n";
echo "4. Make sure your service worker is properly registered\n";

// Clean up
openssl_free_key($res);
?>
