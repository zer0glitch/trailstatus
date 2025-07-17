<?php
require_once 'includes/config.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: admin.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if (authenticateUser($username, $password)) {
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - LCFTF Trail Status</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-logo">
                <img src="https://www.zeroglitch.com/trailstatus/images/ftf_logo.jpg" alt="FTF Logo" />
                <div>
                    <h1>🚵‍♂️ LCFTF Trail Status</h1>
                    <p>Administrator Login</p>
                </div>
            </div>
        </header>

        <nav class="nav">
            <ul>
                <li><a href="index.php">Trail Status</a></li>
                <li><a href="notifications.php">Notifications</a></li>
                <li><a href="login.php" class="active">Admin Login</a></li>
            </ul>
        </nav>

        <main>
            <div class="form-container">
                <h2>Admin Login</h2>
                
                <?php if ($error): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="message success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars(isset($_POST['username']) ? $_POST['username'] : ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
                    </div>
                </form>
                
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 0.9rem;">
                    <strong>Default Login:</strong><br>
                    Username: admin<br>
                    Password: admin123
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> LCFTF Mountain Bike Club</p>
        </footer>
    </div>
</body>
</html>
