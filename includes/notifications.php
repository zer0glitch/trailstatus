<?php
/**
 * Notification System for Trail Status Changes
 * Supports email notifications and can be extended for other methods
 */

// Notification configuration
define('ENABLE_NOTIFICATIONS', true);
define('NOTIFICATION_FROM_EMAIL', 'noreply@zeroglitch.com');
define('NOTIFICATION_FROM_NAME', 'LCFTF Trail Status');

// SMTP Configuration - Update these with your hosting provider's SMTP settings
define('SMTP_ENABLED', true);  // Set to false to use basic mail() function
define('SMTP_HOST', 'mail.zeroglitch.com');  // Your hosting provider's SMTP server
define('SMTP_PORT', 587);  // Usually 587 for TLS or 465 for SSL
define('SMTP_SECURE', 'tls');  // 'tls', 'ssl', or false for no encryption
define('SMTP_AUTH', true);  // Set to true if authentication is required
define('SMTP_USERNAME', 'noreply@zeroglitch.com');  // Your SMTP username
define('SMTP_PASSWORD', '');  // Your SMTP password - UPDATE THIS!

// Load notification subscribers
function loadSubscribers() {
    $subscribers_file = DATA_DIR . 'subscribers.json';
    return loadJsonData($subscribers_file);
}

// Save notification subscribers
function saveSubscribers($subscribers) {
    $subscribers_file = DATA_DIR . 'subscribers.json';
    return saveJsonData($subscribers_file, $subscribers);
}

// Add a new subscriber
function addSubscriber($email, $name = '', $trails = array()) {
    $subscribers = loadSubscribers();
    
    // Check if email already exists
    foreach ($subscribers as $subscriber) {
        if ($subscriber['email'] === $email) {
            return false; // Already subscribed
        }
    }
    
    // Generate new ID
    $max_id = 0;
    foreach ($subscribers as $subscriber) {
        if ($subscriber['id'] > $max_id) {
            $max_id = $subscriber['id'];
        }
    }
    
    $new_subscriber = array(
        'id' => $max_id + 1,
        'email' => $email,
        'name' => $name,
        'trails' => empty($trails) ? array('all') : $trails, // 'all' or specific trail IDs
        'created_at' => date('Y-m-d H:i:s'),
        'active' => true
    );
    
    $subscribers[] = $new_subscriber;
    return saveSubscribers($subscribers);
}

// Remove a subscriber
function removeSubscriber($email) {
    $subscribers = loadSubscribers();
    $subscribers = array_filter($subscribers, function($subscriber) use ($email) {
        return $subscriber['email'] !== $email;
    });
    
    // Re-index array
    $subscribers = array_values($subscribers);
    return saveSubscribers($subscribers);
}

// Send email notification with SMTP support
function sendEmailNotification($to_email, $to_name, $subject, $message) {
    if (SMTP_ENABLED && SMTP_AUTH && !empty(SMTP_PASSWORD)) {
        return sendEmailSMTP($to_email, $to_name, $subject, $message);
    } else {
        return sendEmailBasic($to_email, $to_name, $subject, $message);
    }
}

// Basic email sending using PHP mail() function
function sendEmailBasic($to_email, $to_name, $subject, $message) {
    $headers = array(
        'From: ' . NOTIFICATION_FROM_NAME . ' <' . NOTIFICATION_FROM_EMAIL . '>',
        'Reply-To: ' . NOTIFICATION_FROM_EMAIL,
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: LCFTF Trail Status System'
    );
    
    $full_message = buildEmailHtml($message, $to_email);
    return mail($to_email, $subject, $full_message, implode("\r\n", $headers));
}

// SMTP email sending (basic implementation without external libraries)
function sendEmailSMTP($to_email, $to_name, $subject, $message) {
    // For production use, consider using PHPMailer or SwiftMailer
    // This is a basic SMTP implementation for environments where libraries aren't available
    
    $socket = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
    if (!$socket) {
        error_log("SMTP Error: Could not connect to " . SMTP_HOST . ":" . SMTP_PORT . " ($errno: $errstr)");
        return sendEmailBasic($to_email, $to_name, $subject, $message); // Fallback
    }
    
    $response = fgets($socket, 512);
    if (substr($response, 0, 3) != '220') {
        fclose($socket);
        return sendEmailBasic($to_email, $to_name, $subject, $message); // Fallback
    }
    
    // SMTP conversation
    $commands = array(
        "EHLO " . $_SERVER['SERVER_NAME'],
        SMTP_SECURE == 'tls' ? "STARTTLS" : null,
        SMTP_AUTH ? "AUTH LOGIN" : null,
        SMTP_AUTH ? base64_encode(SMTP_USERNAME) : null,
        SMTP_AUTH ? base64_encode(SMTP_PASSWORD) : null,
        "MAIL FROM: <" . NOTIFICATION_FROM_EMAIL . ">",
        "RCPT TO: <$to_email>",
        "DATA"
    );
    
    foreach ($commands as $command) {
        if ($command === null) continue;
        
        fputs($socket, $command . "\r\n");
        $response = fgets($socket, 512);
        
        // Basic error checking
        $code = substr($response, 0, 3);
        if ($command == "STARTTLS" && $code == '220') {
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            fgets($socket, 512);
        } elseif (in_array($code, array('250', '220', '334', '235'))) {
            continue; // Success codes
        } else {
            fclose($socket);
            error_log("SMTP Error at command '$command': $response");
            return sendEmailBasic($to_email, $to_name, $subject, $message); // Fallback
        }
    }
    
    // Send email content
    $email_content = buildEmailContent($to_email, $to_name, $subject, $message);
    fputs($socket, $email_content);
    fputs($socket, "\r\n.\r\n");
    
    $response = fgets($socket, 512);
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    return substr($response, 0, 3) == '250';
}

// Build email content with headers
function buildEmailContent($to_email, $to_name, $subject, $message) {
    $headers = "From: " . NOTIFICATION_FROM_NAME . " <" . NOTIFICATION_FROM_EMAIL . ">\r\n";
    $headers .= "To: $to_email\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: LCFTF Trail Status System\r\n";
    $headers .= "\r\n";
    
    return $headers . buildEmailHtml($message, $to_email);
}

// Build HTML email template
function buildEmailHtml($message, $to_email) {
    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: #8B4513; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .trail-status { padding: 8px 16px; border-radius: 25px; color: white; font-weight: bold; text-transform: uppercase; }
            .status-open { background: #228B22; }
            .status-caution { background: #FF8C00; }
            .status-closed { background: #DC143C; }
            .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 0.9rem; color: #666; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>🚵‍♂️ LCFTF Trail Status Update</h2>
        </div>
        <div class='content'>
            " . $message . "
        </div>
        <div class='footer'>
            <p>You are receiving this because you subscribed to LCFTF trail status notifications.</p>
            <p><a href='https://zeroglitch.com/trailstatus/unsubscribe.php?email=" . urlencode($to_email) . "'>Unsubscribe</a></p>
        </div>
    </body>
    </html>";
}

// Send notifications for trail status change
function notifyTrailStatusChange($trail_id, $trail_name, $old_status, $new_status, $updated_by) {
    if (!ENABLE_NOTIFICATIONS) {
        return;
    }
    
    $subscribers = loadSubscribers();
    $status_colors = array('open' => 'status-open', 'caution' => 'status-caution', 'closed' => 'status-closed');
    
    $subject = "Trail Status Change: {$trail_name} is now " . ucfirst($new_status);
    
    $message = "
        <h3>Trail Status Update</h3>
        <p><strong>Trail:</strong> {$trail_name}</p>
        <p><strong>Previous Status:</strong> <span class='trail-status {$status_colors[$old_status]}'>" . ucfirst($old_status) . "</span></p>
        <p><strong>New Status:</strong> <span class='trail-status {$status_colors[$new_status]}'>" . ucfirst($new_status) . "</span></p>
        <p><strong>Updated by:</strong> {$updated_by}</p>
        <p><strong>Updated at:</strong> " . date('M j, Y g:i A') . "</p>
        
        <p><a href='https://zeroglitch.com/trailstatus/' style='background: #228B22; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View All Trail Status</a></p>
    ";
    
    foreach ($subscribers as $subscriber) {
        if (!$subscriber['active']) continue;
        
        // Check if subscriber wants notifications for this trail
        if (!in_array('all', $subscriber['trails']) && !in_array($trail_id, $subscriber['trails'])) {
            continue;
        }
        
        sendEmailNotification($subscriber['email'], $subscriber['name'], $subject, $message);
    }
}

// Initialize default subscribers file if it doesn't exist
$subscribers_file = DATA_DIR . 'subscribers.json';
if (!file_exists($subscribers_file)) {
    $default_subscribers = array();
    saveJsonData($subscribers_file, $default_subscribers);
}
?>
