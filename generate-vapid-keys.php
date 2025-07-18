<?php
/**
 * VAPID Key Generator - Production Ready
 * Generates proper ECDSA P-256 key pairs for FCM push notifications
 */

require_once 'includes/config.php';

// Check if OpenSSL is available
if (!extension_loaded('openssl')) {
    die("OpenSSL extension is required for VAPID key generation.\n");
}

function generateVAPIDKeys() {
    // Generate ECDSA P-256 private key
    $private_key_resource = openssl_pkey_new(array(
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ));

    if (!$private_key_resource) {
        throw new Exception("Failed to generate private key: " . openssl_error_string());
    }

    // Extract private key in PEM format
    $private_key_pem = '';
    if (!openssl_pkey_export($private_key_resource, $private_key_pem)) {
        throw new Exception("Failed to export private key: " . openssl_error_string());
    }

    // Get public key details
    $key_details = openssl_pkey_get_details($private_key_resource);
    if (!$key_details) {
        throw new Exception("Failed to get key details: " . openssl_error_string());
    }

    // Extract public key (uncompressed format)
    $public_key_der = $key_details['ec']['key'];

    // Remove the first byte (0x04) which indicates uncompressed format
    $public_key_raw = substr($public_key_der, 1);

    // Base64url encode the public key
    $public_key_base64url = base64url_encode($public_key_raw);

    // Convert private key to base64url format for VAPID
    // Extract the private key part from the PEM
    preg_match('/-----BEGIN (?:EC )?PRIVATE KEY-----\s*(.+?)\s*-----END (?:EC )?PRIVATE KEY-----/s', $private_key_pem, $matches);
    if (!isset($matches[1])) {
        throw new Exception("Failed to parse private key PEM format");
    }

    $private_key_der = base64_decode(str_replace(array("\n", "\r", " "), '', $matches[1]));

    // For ECDSA P-256, extract the actual private key value
    $private_key_raw = extractPrivateKeyFromDER($private_key_der);
    $private_key_base64url = base64url_encode($private_key_raw);

    // Clean up
    openssl_pkey_free($private_key_resource);

    return array(
        'private' => $private_key_base64url,
        'public' => $public_key_base64url
    );
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function extractPrivateKeyFromDER($der) {
    // Simple DER parser to extract the private key value
    // For P-256, look for a 32-byte sequence that's the private key
    $length = strlen($der);
    for ($i = 0; $i < $length - 32; $i++) {
        // Look for the private key marker in DER structure
        if (ord($der[$i]) == 0x04 && ord($der[$i + 1]) == 0x20) {
            // Found a 32-byte (0x20) octet string, this should be our private key
            return substr($der, $i + 2, 32);
        }
    }
    
    // Fallback: take the last 32 bytes (common for simple DER encodings)
    return substr($der, -32);
}

// Only allow access to logged-in admins
if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    die('Access denied. Please log in as admin.');
}

$keys_generated = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_keys'])) {
    try {
        $keys = generateVAPIDKeys();
        
        // Save keys to config.local.php
        $config_local_file = 'config.local.php';
        $config_content = "<?php\n";
        $config_content .= "// VAPID Keys for Push Notifications - Generated " . date('Y-m-d H:i:s') . "\n";
        $config_content .= "define('VAPID_PUBLIC_KEY', '" . $keys['public'] . "');\n";
        $config_content .= "define('VAPID_PRIVATE_KEY', '" . $keys['private'] . "');\n";
        $config_content .= "?>\n";
        
        // Save config.local.php
        if (file_put_contents($config_local_file, $config_content) !== false) {
            $keys_generated = true;
            // Force reload of config
            if (file_exists($config_local_file)) {
                include $config_local_file;
            }
        } else {
            throw new Exception('Could not save config.local.php file');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VAPID Key Generator - LCFTF Admin</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-content">
                <img src="images/ftf_logo.jpg" alt="FTF Logo" class="logo">
                <h1>LCFTF Trail Status</h1>
                <nav class="nav">
                    <a href="index.php">Trail Status</a>
                    <a href="admin.php">Admin Panel</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </header>

        <main class="main">
            <div class="admin-panel">
                <h1>VAPID Key Generator</h1>
                <p>Generate VAPID keys required for push notifications.</p>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message" style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        Error: <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($keys_generated): ?>
                    <div class="success-message" style="background: #e8f5e8; color: #2e7d32; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        ✅ VAPID keys generated and saved successfully!<br>
                        Push notifications are now ready to use.
                    </div>
                <?php endif; ?>

                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h3>Current VAPID Configuration</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 8px; font-weight: bold;">Public Key:</td>
                            <td style="padding: 8px; word-break: break-all; font-family: monospace;">
                                <?php echo (defined('VAPID_PUBLIC_KEY') && !empty(VAPID_PUBLIC_KEY)) ? htmlspecialchars(VAPID_PUBLIC_KEY) : '<span style="color: red;">NOT SET</span>'; ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 8px; font-weight: bold;">Private Key:</td>
                            <td style="padding: 8px;">
                                <?php echo (defined('VAPID_PRIVATE_KEY') && !empty(VAPID_PRIVATE_KEY)) ? '<span style="color: green;">SET (hidden for security)</span>' : '<span style="color: red;">NOT SET</span>'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; font-weight: bold;">Push Notifications:</td>
                            <td style="padding: 8px;">
                                <?php echo (ENABLE_PUSH_NOTIFICATIONS && !empty(VAPID_PUBLIC_KEY)) ? '<span style="color: green;">ENABLED</span>' : '<span style="color: orange;">DISABLED (keys needed)</span>'; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php if (empty(VAPID_PUBLIC_KEY) || empty(VAPID_PRIVATE_KEY)): ?>
                <form method="POST" action="generate-vapid-keys.php" style="margin-bottom: 20px;">
                    <button type="submit" name="generate_keys" class="btn btn-primary" onclick="return confirm('This will generate new VAPID keys. Continue?')">
                        🔑 Generate VAPID Keys
                    </button>
                </form>
                <?php endif; ?>

                <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px;">
                    <h4>About VAPID Keys:</h4>
                    <ul style="margin-left: 20px;">
                        <li><strong>VAPID (Voluntary Application Server Identification)</strong> keys are required for push notifications</li>
                        <li>They help identify your application to push services like Google FCM</li>
                        <li>Generate these keys once - they'll be saved to your configuration file</li>
                        <li>Keep your private key secure and never share it publicly</li>
                        <li>Once generated, push notifications will be fully functional</li>
                    </ul>
                    
                    <?php if (!empty(VAPID_PUBLIC_KEY)): ?>
                    <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.8); border-radius: 4px;">
                        <strong>✅ Setup Complete:</strong> Your push notification system is ready to use!
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> LCFTF Mountain Bike Club</p>
        </footer>
    </div>
</body>
</html>
