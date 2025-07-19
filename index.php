<?php
require_once 'includes/config.php';

// Prevent caching to ensure fresh trail status data
preventCaching();

$trails = loadJsonData(TRAILS_FILE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LCFTF Trail Status</title>
    <link rel="stylesheet" href="css/style.css<?php echo getCacheBuster(); ?>">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-logo">
                <img src="https://www.zeroglitch.com/trailstatus/images/ftf_logo.jpg" alt="FTF Logo" />
                <div>
                    <h1>🚵‍♂️ LCFTF Trail Status</h1>
                    <p>Live mountain bike trail conditions and updates</p>
                </div>
            </div>
        </header>

        <nav class="nav">
            <ul>
                <li><a href="index.php" class="active">Trail Status</a></li>
                <li><a href="notifications.php">Notifications</a></li>
                <?php if (isLoggedIn()): ?>
                    <li><a href="admin.php">Admin Panel</a></li>
                    <li><a href="logout.php" class="btn-logout">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                <?php else: ?>
                    <li><a href="login.php">Admin Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <main>
            <?php if (empty($trails)): ?>
                <div class="form-container">
                    <h2>No Trails Available</h2>
                    <p>No trail data is currently available. Please contact an administrator.</p>
                </div>
            <?php else: ?>
                <div class="trail-grid">
                    <?php foreach ($trails as $trail): ?>
                        <div class="trail-card">
                            <div class="trail-name"><?php echo htmlspecialchars($trail['name']); ?></div>
                            <div class="trail-status status-<?php echo $trail['status']; ?>">
                                <?php echo ucfirst($trail['status']); ?>
                            </div>
                            <div class="trail-meta">
                                <div>Last Updated: <?php echo date('M j, Y g:i A', strtotime($trail['updated_at'])); ?></div>
                                <div>Updated by: <?php echo htmlspecialchars($trail['updated_by']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 30px;">
                <!-- Trail Status Legend -->
                <div class="form-container">
                    <h3 style="color: var(--ftf-primary);">Trail Status Legend</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div class="trail-status status-open" style="font-size: 0.8rem; padding: 4px 12px;">Open</div>
                            <span style="color: var(--ftf-secondary);">Trail is in good condition</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div class="trail-status status-caution" style="font-size: 0.8rem; padding: 4px 12px;">Caution</div>
                            <span style="color: var(--ftf-secondary);">Use caution, conditions may vary</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div class="trail-status status-closed" style="font-size: 0.8rem; padding: 4px 12px;">Closed</div>
                            <span style="color: var(--ftf-secondary);">Trail is closed to riders</span>
                        </div>
                    </div>
                </div>
                
                <!-- Wet Trail Policy -->
                <div class="form-container">
                    <h3 style="color: var(--ftf-primary);">🌧️ Wet Trail Policy</h3>
                    <div style="margin-top: 15px; color: var(--ftf-secondary); line-height: 1.6;">
                        <p style="margin-bottom: 10px;"><strong>Do not ride any trail for a minimum 24 hours after a significant rain event.</strong></p>
                        <p style="font-size: 0.9rem;">If you encounter water on the trail, ride through it, not around it.</p>
                        <div style="background: var(--ftf-light); padding: 10px; border-radius: 5px; margin-top: 10px; border-left: 4px solid var(--ftf-accent);">
                            <small style="color: var(--ftf-primary);">Help preserve our trails for everyone by following this policy!</small>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> LCFTF Mountain Bike Club. Trail conditions updated in real-time.</p>
        </footer>
    </div>

    <script>
        // Auto-refresh page every 5 minutes to show latest trail status
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes = 300,000 milliseconds
    </script>
</body>
</html>
