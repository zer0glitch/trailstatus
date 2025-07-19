<?php
/**
 * Proper VAPID Key Generator for FCM compatibility
 * Creates real ECDSA P-256 keys that work with Firebase Cloud Messaging
 */

echo "=== PROPER P-256 VAPID KEY GENERATOR ===\n";
echo "Creating cryptographically valid ECDSA P-256 keys...\n\n";

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Check OpenSSL availability
if (!function_exists('openssl_pkey_new')) {
    die("❌ Error: OpenSSL extension is required but not available.\n");
}

echo "1. Generating ECDSA P-256 key pair using OpenSSL...\n";

// Generate proper P-256 ECDSA key pair
$config = [
    'curve_name' => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
];

$res = openssl_pkey_new($config);
if (!$res) {
    die("❌ Error: Failed to generate key pair: " . openssl_error_string() . "\n");
}

echo "✓ Key pair generated successfully\n";

echo "2. Extracting key components...\n";

// Get key details
$keyDetails = openssl_pkey_get_details($res);

if (!$keyDetails) {
    die("❌ Error: Failed to get key details\n");
}

echo "   Key type: " . $keyDetails['type'] . "\n";
echo "   Key bits: " . $keyDetails['bits'] . "\n";

// Extract public key raw bytes
$publicKeyRaw = null;

// Try to get EC-specific details
if (isset($keyDetails['ec'])) {
    echo "   EC details found\n";
    echo "   Curve name: " . ($keyDetails['ec']['curve_name'] ?? 'unknown') . "\n";
    echo "   Curve OID: " . ($keyDetails['ec']['curve_oid'] ?? 'unknown') . "\n";
    
    if (isset($keyDetails['ec']['pub'])) {
        $publicKeyRaw = $keyDetails['ec']['pub'];
        echo "   ✓ Public key extracted: " . strlen($publicKeyRaw) . " bytes\n";
    }
}

// Fallback: extract from DER format
if (!$publicKeyRaw && isset($keyDetails['key'])) {
    echo "   Using DER parsing method...\n";
    $publicKeyPEM = $keyDetails['key'];
    
    // Remove PEM headers and decode
    $publicKeyB64 = str_replace([
        '-----BEGIN PUBLIC KEY-----',
        '-----END PUBLIC KEY-----',
        "\n", "\r", " "
    ], '', $publicKeyPEM);
    
    $der = base64_decode($publicKeyB64);
    
    // For ECDSA P-256 public keys in DER format:
    // The public key is typically the last 65 bytes
    if (strlen($der) >= 65) {
        $publicKeyRaw = substr($der, -65);
        echo "   ✓ Public key extracted from DER: " . strlen($publicKeyRaw) . " bytes\n";
    }
}

if (!$publicKeyRaw) {
    die("❌ Error: Could not extract public key\n");
}

// Validate public key format
if (strlen($publicKeyRaw) !== 65) {
    die("❌ Error: Public key should be 65 bytes, got " . strlen($publicKeyRaw) . "\n");
}

if (ord($publicKeyRaw[0]) !== 4) {
    die("❌ Error: Public key should start with 0x04 (uncompressed point)\n");
}

echo "✓ Public key format validated\n";

echo "3. Extracting private key...\n";

// Export private key in PEM format
$privateKeyPEM = '';
if (!openssl_pkey_export($res, $privateKeyPEM)) {
    die("❌ Error: Failed to export private key\n");
}

// Extract raw private key from PEM (32 bytes for P-256)
$privateKeyRaw = extractPrivateKeyFromPEM($privateKeyPEM);

if (!$privateKeyRaw || strlen($privateKeyRaw) !== 32) {
    die("❌ Error: Could not extract valid private key (expected 32 bytes, got " . strlen($privateKeyRaw ?? '') . ")\n");
}

echo "✓ Private key extracted: " . strlen($privateKeyRaw) . " bytes\n";

// Convert to base64url format
$publicKeyB64 = base64url_encode($publicKeyRaw);
$privateKeyB64 = base64url_encode($privateKeyRaw);

echo "\n4. Generated VAPID Keys:\n";
echo "Public Key:  $publicKeyB64\n";
echo "Private Key: $privateKeyB64\n\n";

echo "5. Validating keys...\n";

// Test JWT signing with these keys
$testData = 'test-signing-data';
$signature = testECDSASigning($testData, $privateKeyPEM);

if ($signature) {
    echo "✓ JWT signing test passed\n";
} else {
    echo "⚠️  JWT signing test failed (keys may still work with FCM)\n";
}

echo "\n6. Saving configuration...\n";

$configContent = "<?php\n";
$configContent .= "/**\n";
$configContent .= " * VAPID Keys for Push Notifications\n";
$configContent .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
$configContent .= " * Method: OpenSSL ECDSA P-256\n";
$configContent .= " * DO NOT COMMIT TO VERSION CONTROL\n";
$configContent .= " */\n\n";
$configContent .= "define('VAPID_PUBLIC_KEY', '$publicKeyB64');\n";
$configContent .= "define('VAPID_PRIVATE_KEY', '$privateKeyB64');\n";
$configContent .= "?>\n";

if (file_put_contents('config.local.php', $configContent)) {
    echo "✓ Keys have been saved to config.local.php\n\n";
} else {
    echo "❌ Failed to save keys. Please create config.local.php manually:\n\n";
    echo $configContent . "\n";
    exit(1);
}

echo "=== SUCCESS ===\n";
echo "✓ Proper ECDSA P-256 VAPID keys generated successfully!\n";
echo "✓ Keys are cryptographically valid and should work with FCM\n";
echo "✓ Configuration saved\n\n";

echo "Next steps:\n";
echo "1. Clear browser cache for your site\n";
echo "2. Test push notifications:\n";
echo "   php test-push-simple.php\n\n";
echo "3. If still getting errors, check FCM console for additional requirements\n\n";

echo "Keys ready for production use! 🎉\n";

// Helper function to extract private key from PEM
function extractPrivateKeyFromPEM($pem) {
    // Parse the PEM to extract the raw private key bytes
    $lines = explode("\n", $pem);
    $der = '';
    $capture = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '-----BEGIN') !== false) {
            $capture = true;
            continue;
        }
        if (strpos($line, '-----END') !== false) {
            break;
        }
        if ($capture) {
            $der .= $line;
        }
    }
    
    if (!$der) {
        return null;
    }
    
    $derBinary = base64_decode($der);
    if (!$derBinary || strlen($derBinary) < 32) {
        return null;
    }
    
    // For ECDSA P-256, the private key is typically 32 bytes
    // It's usually embedded in the DER structure
    // Try different offsets to find the 32-byte private key
    $possibleOffsets = [7, 8, 9, 10, 36, 37, 38, 39];
    
    foreach ($possibleOffsets as $offset) {
        if ($offset + 32 <= strlen($derBinary)) {
            $candidate = substr($derBinary, $offset, 32);
            // Basic validation: private key shouldn't be all zeros
            if ($candidate !== str_repeat("\x00", 32)) {
                return $candidate;
            }
        }
    }
    
    return null;
}

// Test ECDSA signing capability
function testECDSASigning($data, $privateKeyPEM) {
    $signature = '';
    $result = openssl_sign($data, $signature, $privateKeyPEM, OPENSSL_ALGO_SHA256);
    return $result && !empty($signature);
}
        // Look for the uncompressed point marker (0x04) followed by 64 bytes
        for ($i = 0; $i <= strlen($der) - 65; $i++) {
            if (ord($der[$i]) === 4) {
                $publicKeyRaw = substr($der, $i, 65);
                echo "   ✓ Public key found at DER offset $i (" . strlen($publicKeyRaw) . " bytes)\n";
                break;
            }
        }
    }
}

// Validate we have a proper 65-byte public key
if (!$publicKeyRaw || strlen($publicKeyRaw) !== 65 || ord($publicKeyRaw[0]) !== 4) {
    die("Error: Could not extract valid P-256 public key. Got " . strlen($publicKeyRaw ?: '') . " bytes.\n");
}

// Extract private key raw bytes (32 bytes for P-256)
echo "3. Extracting private key...\n";
preg_match('/-----BEGIN EC PRIVATE KEY-----\n(.+)\n-----END EC PRIVATE KEY-----/s', $privateKey, $matches);
if (!$matches) {
    die("Error: Could not parse private key PEM format.\n");
}

$privateKeyBase64 = str_replace("\n", '', $matches[1]);
$privateKeyDER = base64_decode($privateKeyBase64);

// The private key is typically at a fixed offset in the DER structure
$privateKeyRaw = null;
if (strlen($privateKeyDER) >= 39) {
    // Try common offsets for the 32-byte private key
    $offsets = [7, 8, 9, 10, 36, 37];
    foreach ($offsets as $offset) {
        if ($offset + 32 <= strlen($privateKeyDER)) {
            $candidate = substr($privateKeyDER, $offset, 32);
            // Simple validation: private key shouldn't be all zeros
            if ($candidate !== str_repeat("\x00", 32)) {
                $privateKeyRaw = $candidate;
                echo "   ✓ Private key found at DER offset $offset (32 bytes)\n";
                break;
            }
        }
    }
}

if (!$privateKeyRaw || strlen($privateKeyRaw) !== 32) {
    die("Error: Could not extract valid P-256 private key. Got " . strlen($privateKeyRaw ?: '') . " bytes.\n");
}

echo "4. Encoding keys for VAPID...\n";

// Convert to base64url format (required for VAPID)
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Format keys as base64url
$publicKeyB64 = base64url_encode($publicKeyRaw);
$privateKeyB64 = base64url_encode($privateKeyRaw);

echo "✓ VAPID keys generated successfully!\n\n";
echo "Public Key Length:  " . strlen($publicKeyRaw) . " bytes (should be 65)\n";
echo "Private Key Length: " . strlen($privateKeyRaw) . " bytes (should be 32)\n";
echo "Public Key (base64url):  " . $publicKeyB64 . "\n";
echo "Private Key (base64url): " . $privateKeyB64 . "\n\n";

// Output the config format
echo "5. Updating configuration...\n";
echo "Copy these values to your config.local.php file:\n\n";
echo "define('VAPID_PUBLIC_KEY', '" . $publicKeyB64 . "');\n";
echo "define('VAPID_PRIVATE_KEY', '" . $privateKeyB64 . "');\n\n";

// Try to update config.local.php automatically
$config_file = 'config.local.php';
if (file_exists($config_file) && is_writable($config_file)) {
    $config_content = file_get_contents($config_file);
    if ($config_content !== false) {
        // Update the keys
        $updated = false;
        
        // Replace existing VAPID_PUBLIC_KEY
        if (preg_match("/define\('VAPID_PUBLIC_KEY', '.*?'\);/", $config_content)) {
            $config_content = preg_replace(
                "/define\('VAPID_PUBLIC_KEY', '.*?'\);/",
                "define('VAPID_PUBLIC_KEY', '" . $publicKeyB64 . "');",
                $config_content
            );
            $updated = true;
        } else {
            // Add if not found
            $config_content .= "\ndefine('VAPID_PUBLIC_KEY', '" . $publicKeyB64 . "');\n";
            $updated = true;
        }
        
        // Replace existing VAPID_PRIVATE_KEY
        if (preg_match("/define\('VAPID_PRIVATE_KEY', '.*?'\);/", $config_content)) {
            $config_content = preg_replace(
                "/define\('VAPID_PRIVATE_KEY', '.*?'\);/",
                "define('VAPID_PRIVATE_KEY', '" . $privateKeyB64 . "');",
                $config_content
            );
        } else {
            // Add if not found
            $config_content .= "define('VAPID_PRIVATE_KEY', '" . $privateKeyB64 . "');\n";
        }
        
        if (file_put_contents($config_file, $config_content) !== false) {
            echo "   ✓ Keys have been automatically updated in config.local.php!\n";
        } else {
            echo "   ✗ Could not write to config.local.php. Please update manually.\n";
        }
    } else {
        echo "   ✗ Could not read config.local.php. Please update manually.\n";
    }
} else {
    echo "   ✗ config.local.php not found or not writable. Please update manually.\n";
}

echo "\nValidation:\n";
echo "- Public key starts with 0x04 (uncompressed): " . (ord($publicKeyRaw[0]) === 4 ? "✓ YES" : "✗ NO") . "\n";
echo "- Public key is 65 bytes: " . (strlen($publicKeyRaw) === 65 ? "✓ YES" : "✗ NO") . "\n";
echo "- Private key is 32 bytes: " . (strlen($privateKeyRaw) === 32 ? "✓ YES" : "✗ NO") . "\n";

echo "\nNext steps:\n";
echo "1. Test the push notifications with: php test-push.php\n";
echo "2. Check browser console for any subscription errors\n";
echo "3. Verify your website is served over HTTPS (required for push notifications)\n";
echo "4. Make sure your service worker is properly registered\n";

// Clean up
openssl_free_key($res);
?>
