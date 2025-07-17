<?php
/**
 * Email Test Script
 * Use this to test your SMTP configuration
 */

require_once 'includes/config.php';
require_once 'includes/notifications.php';

// Only allow access to logged-in admins
requireLogin();

$test_result = '';
$test_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = isset($_POST['test_email']) ? trim($_POST['test_email']) : '';
    
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $test_error = 'Please enter a valid email address.';
    } else {
        // Test email content
        $subject = 'LCFTF Trail Status - Email Test';
        $message = "
            <h3>Email Configuration Test</h3>
            <p>This is a test email to verify that your SMTP configuration is working correctly.</p>
            <p><strong>Test Details:</strong></p>
            <ul>
                <li><strong>SMTP Enabled:</strong> " . (SMTP_ENABLED ? 'Yes' : 'No') . "</li>
                <li><strong>SMTP Host:</strong> " . SMTP_HOST . "</li>
                <li><strong>SMTP Port:</strong> " . SMTP_PORT . "</li>
                <li><strong>SMTP Security:</strong> " . SMTP_SECURE . "</li>
                <li><strong>SMTP Auth:</strong> " . (SMTP_AUTH ? 'Yes' : 'No') . "</li>
                <li><strong>SMTP Username:</strong> " . SMTP_USERNAME . "</li>
                <li><strong>Test Time:</strong> " . date('Y-m-d H:i:s T') . "</li>
            </ul>
            <p>If you received this email, your SMTP configuration is working correctly!</p>
        ";
        
        if (sendEmailNotification($test_email, '', $subject, $message)) {
            $test_result = 'Test email sent successfully! Check your inbox.';
        } else {
            $test_error = 'Failed to send test email. Check your SMTP configuration and server logs.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration Test - LCFTF Admin</title>
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
                <h1>Email Configuration Test</h1>
                
                <?php if (!empty($test_error)): ?>
                    <div class="error-message" style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <?php echo htmlspecialchars($test_error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($test_result)): ?>
                    <div class="success-message" style="background: #e8f5e8; color: #2e7d32; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <?php echo htmlspecialchars($test_result); ?>
                    </div>
                <?php endif; ?>

                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h3>Current SMTP Configuration</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 8px; font-weight: bold;">SMTP Enabled:</td>
                            <td style="padding: 8px;"><?php echo SMTP_ENABLED ? 'Yes' : 'No'; ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 8px; font-weight: bold;">SMTP Host:</td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars(SMTP_HOST); ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 8px; font-weight: bold;">SMTP Port:</td>
                            <td style="padding: 8px;"><?php echo SMTP_PORT; ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 8px; font-weight: bold;">SMTP Security:</td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars(SMTP_SECURE); ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 8px; font-weight: bold;">SMTP Auth:</td>
                            <td style="padding: 8px;"><?php echo SMTP_AUTH ? 'Yes' : 'No'; ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 8px; font-weight: bold;">SMTP Username:</td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars(SMTP_USERNAME); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; font-weight: bold;">SMTP Password:</td>
                            <td style="padding: 8px;"><?php echo !empty(SMTP_PASSWORD) ? 'Set (hidden)' : '<span style="color: red;">NOT SET</span>'; ?></td>
                        </tr>
                    </table>
                </div>

                <form method="POST" action="test-email.php" style="margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="test_email">Test Email Address:</label>
                        <input type="email" id="test_email" name="test_email" required 
                               placeholder="Enter email address to test" style="width: 300px;">
                    </div>
                    <button type="submit" class="btn btn-primary">Send Test Email</button>
                </form>

                <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px;">
                    <h4>Setup Instructions:</h4>
                    <ol>
                        <li>Get SMTP settings from your hosting provider</li>
                        <li>Update the SMTP configuration in <code>/includes/notifications.php</code></li>
                        <li>Set your SMTP password in the configuration</li>
                        <li>Use this test page to verify email delivery</li>
                    </ol>
                    <p><strong>Note:</strong> If SMTP password is not set, the system will fall back to basic mail() function.</p>
                    <p>See <code>smtp-setup-guide.md</code> for detailed configuration instructions.</p>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> LCFTF Mountain Bike Club</p>
        </footer>
    </div>
</body>
</html>
