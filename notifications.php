<?php
require_once 'includes/config.php';
require_once 'includes/notifications.php';

$message = '';
$error = '';

// Handle subscription form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'subscribe') {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $selected_trails = isset($_POST['trails']) ? $_POST['trails'] : array('all');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            if (addSubscriber($email, $name, $selected_trails)) {
                $message = 'Successfully subscribed to trail status notifications!';
            } else {
                $error = 'This email address is already subscribed.';
            }
        }
    } elseif ($action === 'unsubscribe') {
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
}

// Load trails for selection
$trails = loadJsonData(TRAILS_FILE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trail Status Notifications - LCFTF</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-logo">
                <img src="https://www.zeroglitch.com/trailstatus/images/ftf_logo.jpg" alt="FTF Logo" />
                <div>
                    <h1>🚵‍♂️ LCFTF Trail Status</h1>
                    <p>Notification Subscriptions</p>
                </div>
            </div>
        </header>

        <nav class="nav">
            <ul>
                <li><a href="index.php">Trail Status</a></li>
                <li><a href="notifications.php" class="active">Notifications</a></li>
                <?php if (isLoggedIn()): ?>
                    <li><a href="admin.php">Admin Panel</a></li>
                    <li><a href="logout.php" class="btn-logout">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                <?php else: ?>
                    <li><a href="login.php">Admin Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <main>
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px;">
                <!-- Subscribe Section -->
                <div class="form-container">
                    <h2 style="color: var(--ftf-primary);">📧 Subscribe to Notifications</h2>
                    <p style="margin-bottom: 20px; color: var(--ftf-secondary);">Get email notifications when trail status changes.</p>
                    
                    <form method="POST" action="notifications.php">
                        <input type="hidden" name="action" value="subscribe">
                        
                        <div class="form-group">
                            <label for="email">Email Address:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="name">Name (optional):</label>
                            <input type="text" id="name" name="name">
                        </div>
                        
                        <div class="form-group">
                            <label>Which trails to monitor:</label>
                            <div style="margin-top: 10px;">
                                <label style="display: block; margin-bottom: 8px; font-weight: normal;">
                                    <input type="checkbox" name="trails[]" value="all" checked style="margin-right: 8px;">
                                    All Trails
                                </label>
                                <?php foreach ($trails as $trail): ?>
                                    <label style="display: block; margin-bottom: 8px; font-weight: normal;">
                                        <input type="checkbox" name="trails[]" value="<?php echo $trail['id']; ?>" style="margin-right: 8px;">
                                        <?php echo htmlspecialchars($trail['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Subscribe</button>
                    </form>
                </div>

                <!-- Unsubscribe Section -->
                <div class="form-container">
                    <h2 style="color: var(--ftf-primary);">🚫 Unsubscribe</h2>
                    <p style="margin-bottom: 20px; color: var(--ftf-secondary);">No longer want to receive notifications?</p>
                    
                    <form method="POST" action="notifications.php">
                        <input type="hidden" name="action" value="unsubscribe">
                        
                        <div class="form-group">
                            <label for="unsubscribe_email">Email Address:</label>
                            <input type="email" id="unsubscribe_email" name="email" required>
                        </div>
                        
                        <button type="submit" class="btn btn-danger" style="width: 100%;">Unsubscribe</button>
                    </form>
                </div>
            </div>

            <!-- Information Section -->
            <div class="form-container" style="margin-top: 30px;">
                <h3 style="color: var(--ftf-primary);">📋 About Notifications</h3>
                <div style="color: var(--ftf-secondary); line-height: 1.6;">
                    <ul style="margin-left: 20px;">
                        <li><strong>Real-time Updates:</strong> Get notified immediately when trail status changes</li>
                        <li><strong>Choose Your Trails:</strong> Subscribe to all trails or select specific ones</li>
                        <li><strong>Email Format:</strong> Clean, mobile-friendly email notifications</li>
                        <li><strong>Easy Unsubscribe:</strong> One-click unsubscribe from any notification email</li>
                        <li><strong>Privacy:</strong> Your email is only used for trail status notifications</li>
                    </ul>
                    
                    <div style="background: var(--ftf-light); padding: 15px; border-radius: 8px; margin-top: 20px; border-left: 4px solid var(--ftf-accent);">
                        <strong>Sample Notification:</strong><br>
                        <em>"Trail Status Change: Blue Trail is now Closed"</em><br>
                        <small>Includes trail name, old status, new status, who updated it, and when.</small>
                    </div>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> LCFTF Mountain Bike Club</p>
        </footer>
    </div>

    <script>
        // Handle "All Trails" checkbox
        document.querySelector('input[value="all"]').addEventListener('change', function() {
            const otherCheckboxes = document.querySelectorAll('input[name="trails[]"]:not([value="all"])');
            if (this.checked) {
                otherCheckboxes.forEach(cb => cb.checked = false);
            }
        });

        // Handle individual trail checkboxes
        document.querySelectorAll('input[name="trails[]"]:not([value="all"])').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    document.querySelector('input[value="all"]').checked = false;
                }
            });
        });
    </script>
</body>
</html>
