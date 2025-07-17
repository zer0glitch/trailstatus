<?php
require_once 'includes/config.php';
require_once 'includes/notifications.php';

$message = '';
$error = '';

// Handle unsubscribe from email link
if (isset($_GET['email'])) {
    $email = trim($_GET['email']);
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (removeSubscriber($email)) {
            $message = 'You have been successfully unsubscribed from trail status notifications.';
        } else {
            $error = 'Email address not found in our subscription list.';
        }
    } else {
        $error = 'Invalid email address.';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        if (removeSubscriber($email)) {
            $message = 'Successfully unsubscribed from trail status notifications.';
        } else {
            $error = 'Email address not found in our subscription list.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe - LCFTF Trail Status</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-logo">
                <img src="https://www.zeroglitch.com/trailstatus/images/ftf_logo.jpg" alt="FTF Logo" />
                <div>
                    <h1>🚵‍♂️ LCFTF Trail Status</h1>
                    <p>Unsubscribe from Notifications</p>
                </div>
            </div>
        </header>

        <nav class="nav">
            <ul>
                <li><a href="index.php">Trail Status</a></li>
                <li><a href="notifications.php">Notifications</a></li>
            </ul>
        </nav>

        <main>
            <div class="form-container">
                <?php if ($error): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="message success"><?php echo htmlspecialchars($message); ?></div>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="index.php" class="btn btn-primary">Back to Trail Status</a>
                        <a href="notifications.php" class="btn btn-secondary" style="margin-left: 10px;">Manage Subscriptions</a>
                    </div>
                <?php else: ?>
                    <h2 style="color: var(--ftf-primary);">🚫 Unsubscribe from Notifications</h2>
                    <p style="margin-bottom: 20px; color: var(--ftf-secondary);">
                        Sorry to see you go! Enter your email address below to unsubscribe from trail status notifications.
                    </p>
                    
                    <form method="POST" action="unsubscribe.php">
                        <div class="form-group">
                            <label for="email">Email Address:</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars(isset($_GET['email']) ? $_GET['email'] : ''); ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-danger" style="width: 100%;">Unsubscribe</button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="notifications.php" style="color: var(--ftf-secondary);">Rather manage your subscription preferences?</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> LCFTF Mountain Bike Club</p>
        </footer>
    </div>
</body>
</html>
