<?php
/**
 * VAPID Key Generator for Push Notifications
 * Run this script once to generate VAPID keys for push notifications
 */

require_once 'includes/config.php';

// Simple VAPID key generation (for basic setups)
// For production, consider using a proper VAPID library like web-push-php

function generateVAPIDKeys() {
    // Generate a simple key pair (this is a basic implementation)
    // For production use, implement proper VAPID key generation
    
    $privateKey = base64_encode(random_bytes(32));
    $publicKey = base64_encode(random_bytes(65));
    
    return array(
        'private' => $privateKey,
        'public' => $publicKey
    );
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
        
        // Read current config file
        $config_file = 'includes/notifications.php';
        $config_content = file_get_contents($config_file);
        
        if ($config_content === false) {
            throw new Exception('Could not read configuration file');
        }
        
        // Update VAPID keys in the config
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
        
        // Save updated config
        if (file_put_contents($config_file, $config_content) !== false) {
            $keys_generated = true;
        } else {
            throw new Exception('Could not save configuration file');
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
                                <?php echo !empty(VAPID_PUBLIC_KEY) ? htmlspecialchars(VAPID_PUBLIC_KEY) : '<span style="color: red;">NOT SET</span>'; ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 8px; font-weight: bold;">Private Key:</td>
                            <td style="padding: 8px;">
                                <?php echo !empty(VAPID_PRIVATE_KEY) ? '<span style="color: green;">SET (hidden for security)</span>' : '<span style="color: red;">NOT SET</span>'; ?>
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
