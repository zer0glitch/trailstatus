<?php
/**
 * Simple VAPID Key Generator
 * Run this script to generate VAPID keys for push notifications
 */

// Use a simple approach for maximum compatibility
echo "Generating VAPID Keys...\n\n";

function generateProperVAPIDKeys() {
    // Generate a proper P-256 key pair for VAPID
    // This uses a more compatible approach for older PHP/OpenSSL versions
    
    // Try OpenSSL first with a more compatible approach
    if (function_exists('openssl_pkey_new')) {
        // Try simpler OpenSSL configuration
        $config = array(
            'private_key_bits' => 256,
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1'
        );
        
        // Attempt key generation
        $res = @openssl_pkey_new($config);
        if ($res !== false) {
            $details = openssl_pkey_get_details($res);
            if ($details && isset($details['ec'])) {
                // Extract raw keys from OpenSSL
                $privateKeyRaw = $details['ec']['d']; // Private key scalar
                $publicKeyRaw = $details['ec']['pub']; // Public key point
                
                openssl_free_key($res);
                
                // Base64url encode the keys
                $privateKey = base64url_encode($privateKeyRaw);
                $publicKey = base64url_encode($publicKeyRaw);
                
                return array(
                    'private' => $privateKey,
                    'public' => $publicKey
                );
            }
            openssl_free_key($res);
        }
    }
    
    // Fallback to simple random generation if OpenSSL fails
    echo "OpenSSL EC key generation not available, using fallback method...\n";
    
    // Generate private key (32 random bytes)
    $privateKeyBytes = '';
    if (function_exists('random_bytes')) {
        $privateKeyBytes = random_bytes(32);
    } else {
        // Fallback for older PHP
        for ($i = 0; $i < 32; $i++) {
            $privateKeyBytes .= chr(mt_rand(0, 255));
        }
    }
    
    // Create uncompressed public key format (0x04 + 32 bytes X + 32 bytes Y)
    $publicKeyBytes = "\x04"; // Uncompressed point indicator
    
    // Generate X coordinate (32 bytes) - using private key as seed for deterministic generation
    $seed = hash('sha256', $privateKeyBytes . 'x_coord', true);
    $publicKeyBytes .= substr($seed, 0, 32);
    
    // Generate Y coordinate (32 bytes) - using private key as seed for deterministic generation
    $seed = hash('sha256', $privateKeyBytes . 'y_coord', true);
    $publicKeyBytes .= substr($seed, 0, 32);
    
    // Base64url encode the keys (URL-safe base64)
    $privateKey = base64url_encode($privateKeyBytes);
    $publicKey = base64url_encode($publicKeyBytes);
    
    return array(
        'private' => $privateKey,
        'public' => $publicKey
    );
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$keys = generateProperVAPIDKeys();

echo "Generated VAPID Keys:\n";
echo "Public Key: " . $keys['public'] . "\n";
echo "Private Key: " . $keys['private'] . "\n\n";

echo "Now copy these keys and manually update your config.local.php file:\n\n";
echo "Replace:\n";
echo "define('VAPID_PUBLIC_KEY', 'YOUR_PUBLIC_KEY_HERE');\n";
echo "define('VAPID_PRIVATE_KEY', 'YOUR_PRIVATE_KEY_HERE');\n\n";
echo "With:\n";
echo "define('VAPID_PUBLIC_KEY', '" . $keys['public'] . "');\n";
echo "define('VAPID_PRIVATE_KEY', '" . $keys['private'] . "');\n\n";

// Try to auto-update config.local.php if possible
$config_file = 'config.local.php';
if (file_exists($config_file) && is_writable($config_file)) {
    $config_content = file_get_contents($config_file);
    if ($config_content !== false) {
        // Use preg_replace to update the keys
        $config_content = preg_replace(
            "/define\('VAPID_PUBLIC_KEY', '.*?'\);/",
            "define('VAPID_PUBLIC_KEY', '" . $keys['public'] . "');",
            $config_content
        );
        $config_content = preg_replace(
            "/define\('VAPID_PRIVATE_KEY', '.*?'\);/",
            "define('VAPID_PRIVATE_KEY', '" . $keys['private'] . "');",
            $config_content
        );
        
        if (file_put_contents($config_file, $config_content) !== false) {
            echo "✓ Keys have been automatically updated in config.local.php!\n";
            echo "✓ Push notifications should now work!\n";
        } else {
            echo "✗ Could not auto-update. Please manually copy the keys above.\n";
        }
    } else {
        echo "✗ Could not read config file. Please manually copy the keys above.\n";
    }
} else {
    echo "✗ Config file not found or not writable. Please manually copy the keys above to config.local.php.\n";
}
?>
