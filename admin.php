<?php
require_once 'includes/config.php';
require_once 'includes/notifications.php';

// Prevent caching to ensure fresh admin data
preventCaching();

// Require login to access admin panel
requireLogin();

$error = '';
$success = '';

// Handle trail status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'update_status') {
        $trail_id = isset($_POST['trail_id']) ? (int)$_POST['trail_id'] : 0;
        $new_status = isset($_POST['status']) ? $_POST['status'] : '';
        
        $valid_statuses = array(STATUS_OPEN, STATUS_CAUTION, STATUS_CLOSED);
        if ($trail_id > 0 && in_array($new_status, $valid_statuses)) {
            $trails = loadJsonData(TRAILS_FILE);
            $trail_found = false;
            $old_status = '';
            $trail_name = '';
            
            foreach ($trails as &$trail) {
                if ($trail['id'] === $trail_id) {
                    $old_status = $trail['status'];
                    $trail_name = $trail['name'];
                    $trail['status'] = $new_status;
                    $trail['updated_at'] = date('Y-m-d H:i:s');
                    $trail['updated_by'] = $_SESSION['username'];
                    $trail_found = true;
                    break;
                }
            }
            
            if ($trail_found && saveJsonData(TRAILS_FILE, $trails)) {
                // Send notification if status actually changed
                if ($old_status !== $new_status) {
                    notifyTrailStatusChange($trail_id, $trail_name, $old_status, $new_status, $_SESSION['username']);
                }
                $success = 'Trail status updated successfully!';
            } else {
                $error = 'Failed to update trail status.';
            }
        } else {
            $error = 'Invalid trail or status data.';
        }
    } elseif ($action === 'add_trail') {
        $trail_name = isset($_POST['trail_name']) ? trim($_POST['trail_name']) : '';
        $trail_status = isset($_POST['trail_status']) ? $_POST['trail_status'] : STATUS_OPEN;
        
        $valid_statuses = array(STATUS_OPEN, STATUS_CAUTION, STATUS_CLOSED);
        if (!empty($trail_name) && in_array($trail_status, $valid_statuses)) {
            $trails = loadJsonData(TRAILS_FILE);
            
            // Generate new ID
            $max_id = 0;
            foreach ($trails as $trail) {
                if ($trail['id'] > $max_id) {
                    $max_id = $trail['id'];
                }
            }
            
            $new_trail = array(
                'id' => $max_id + 1,
                'name' => $trail_name,
                'status' => $trail_status,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $_SESSION['username']
            );
            
            $trails[] = $new_trail;
            
            if (saveJsonData(TRAILS_FILE, $trails)) {
                $success = 'New trail added successfully!';
            } else {
                $error = 'Failed to add new trail.';
            }
        } else {
            $error = 'Please enter a valid trail name.';
        }
    } elseif ($action === 'delete_trail') {
        $trail_id = isset($_POST['trail_id']) ? (int)$_POST['trail_id'] : 0;
        
        if ($trail_id > 0) {
            $trails = loadJsonData(TRAILS_FILE);
            $trails = array_filter($trails, function($trail) use ($trail_id) {
                return $trail['id'] !== $trail_id;
            });
            
            // Re-index array
            $trails = array_values($trails);
            
            if (saveJsonData(TRAILS_FILE, $trails)) {
                $success = 'Trail deleted successfully!';
            } else {
                $error = 'Failed to delete trail.';
            }
        } else {
            $error = 'Invalid trail ID.';
        }
    } elseif ($action === 'remove_push_subscriber') {
        $subscriber_endpoint = isset($_POST['subscriber_endpoint']) ? trim($_POST['subscriber_endpoint']) : '';
        
        if (!empty($subscriber_endpoint)) {
            if (removePushSubscriber($subscriber_endpoint)) {
                $success = 'Push subscriber removed successfully!';
            } else {
                $error = 'Failed to remove push subscriber or subscriber not found.';
            }
        } else {
            $error = 'Invalid subscriber endpoint.';
        }
    }
}

// Load trails for display
$trails = loadJsonData(TRAILS_FILE);

// Load push subscribers for management
$push_subscribers = loadPushSubscribers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - LCFTF Trail Status</title>
    <link rel="stylesheet" href="css/style.css<?php echo getCacheBuster(); ?>">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-logo">
                <img src="https://www.zeroglitch.com/trailstatus/images/ftf_logo.jpg" alt="FTF Logo" />
                <div>
                    <h1>🚵‍♂️ LCFTF Trail Status</h1>
                    <p>Administrator Panel</p>
                </div>
            </div>
        </header>

        <nav class="nav">
            <ul>
                <li><a href="index.php">Trail Status</a></li>
                <li><a href="notifications.php">Notifications</a></li>
                <li><a href="admin.php" class="active">Admin Panel</a></li>
                <li><a href="logout.php" class="btn-logout">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
            </ul>
        </nav>

        <main>
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="message success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Add New Trail -->
            <div class="admin-panel">
                <h2>Add New Trail</h2>
                <form method="POST" action="admin.php" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
                    <input type="hidden" name="action" value="add_trail">
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                        <label for="trail_name">Trail Name:</label>
                        <input type="text" id="trail_name" name="trail_name" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
                        <label for="trail_status">Initial Status:</label>
                        <select id="trail_status" name="trail_status">
                            <option value="<?php echo STATUS_OPEN; ?>">Open</option>
                            <option value="<?php echo STATUS_CAUTION; ?>">Caution</option>
                            <option value="<?php echo STATUS_CLOSED; ?>">Closed</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success">Add Trail</button>
                </form>
            </div>

            <!-- Manage Existing Trails -->
            <div class="admin-panel">
                <div class="admin-header">
                    <h2>Manage Trails</h2>
                    <div style="font-size: 0.9rem; color: #666;">
                        Total Trails: <?php echo count($trails); ?>
                    </div>
                </div>

                <?php if (empty($trails)): ?>
                    <p>No trails available. Add some trails using the form above.</p>
                <?php else: ?>
                    <table class="trail-table">
                        <thead>
                            <tr>
                                <th>Trail Name</th>
                                <th>Current Status</th>
                                <th>Last Updated</th>
                                <th>Updated By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trails as $trail): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($trail['name']); ?></strong></td>
                                    <td>
                                        <span class="trail-status status-<?php echo $trail['status']; ?>" style="font-size: 0.8rem; padding: 4px 12px;">
                                            <?php echo ucfirst($trail['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($trail['updated_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($trail['updated_by']); ?></td>
                                    <td>
                                        <form method="POST" action="admin.php" style="display: inline-block; margin-right: 10px;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="trail_id" value="<?php echo $trail['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" style="padding: 5px 8px; font-size: 0.9rem;">
                                                <option value="<?php echo STATUS_OPEN; ?>" <?php echo $trail['status'] === STATUS_OPEN ? 'selected' : ''; ?>>Open</option>
                                                <option value="<?php echo STATUS_CAUTION; ?>" <?php echo $trail['status'] === STATUS_CAUTION ? 'selected' : ''; ?>>Caution</option>
                                                <option value="<?php echo STATUS_CLOSED; ?>" <?php echo $trail['status'] === STATUS_CLOSED ? 'selected' : ''; ?>>Closed</option>
                                            </select>
                                        </form>
                                        <form method="POST" action="admin.php" style="display: inline-block;" 
                                              onsubmit="return confirm('Are you sure you want to delete this trail?');">
                                            <input type="hidden" name="action" value="delete_trail">
                                            <input type="hidden" name="trail_id" value="<?php echo $trail['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8rem;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Quick Status Update -->
            <div class="admin-panel">
                <h2>Quick Status Updates</h2>
                <p style="margin-bottom: 20px; color: #666;">Quickly update multiple trails to the same status:</p>
                
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <form method="POST" action="admin.php" style="display: inline-block;">
                        <input type="hidden" name="action" value="bulk_update">
                        <button type="button" class="btn btn-success" onclick="updateAllTrails('<?php echo STATUS_OPEN; ?>')">
                            Set All Open
                        </button>
                    </form>
                    <form method="POST" action="admin.php" style="display: inline-block;">
                        <button type="button" class="btn" style="background: #ffc107; color: #333;" onclick="updateAllTrails('<?php echo STATUS_CAUTION; ?>')">
                            Set All Caution
                        </button>
                    </form>
                    <form method="POST" action="admin.php" style="display: inline-block;">
                        <button type="button" class="btn btn-danger" onclick="updateAllTrails('<?php echo STATUS_CLOSED; ?>')">
                            Set All Closed
                        </button>
                    </form>
                </div>
            </div>

            <!-- Push Notification Subscribers Management -->
            <?php if (ENABLE_PUSH_NOTIFICATIONS): ?>
            <div class="admin-panel">
                <div class="admin-header">
                    <h2>Push Notification Subscribers</h2>
                    <div style="font-size: 0.9rem; color: #666;">
                        Total Push Subscribers: <?php echo count($push_subscribers); ?>
                    </div>
                </div>

                <?php if (empty($push_subscribers)): ?>
                    <p>No push notification subscribers yet. Users can enable push notifications on the <a href="notifications.php">Notifications page</a>.</p>
                <?php else: ?>
                    <table class="trail-table">
                        <thead>
                            <tr>
                                <th>Browser/Device</th>
                                <th>Trail Preferences</th>
                                <th>Subscribed Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($push_subscribers as $subscriber): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        $ua = $subscriber['user_agent'];
                                        if (strpos($ua, 'Chrome') !== false) {
                                            echo '🟢 Chrome';
                                        } elseif (strpos($ua, 'Firefox') !== false) {
                                            echo '🟠 Firefox';
                                        } elseif (strpos($ua, 'Safari') !== false) {
                                            echo '🔵 Safari';
                                        } elseif (strpos($ua, 'Edge') !== false) {
                                            echo '🟣 Edge';
                                        } else {
                                            echo '📱 Unknown';
                                        }
                                        ?>
                                        <br>
                                        <small style="color: #666;">
                                            <?php echo htmlspecialchars(substr($subscriber['endpoint'], -20)); ?>...
                                        </small>
                                    </td>
                                    <td>
                                        <?php 
                                        if (in_array('all', $subscriber['trails'])) {
                                            echo '<span style="color: #228B22; font-weight: bold;">All Trails</span>';
                                        } else {
                                            $trail_names = array();
                                            foreach ($subscriber['trails'] as $trail_id) {
                                                foreach ($trails as $trail) {
                                                    if ($trail['id'] == $trail_id) {
                                                        $trail_names[] = $trail['name'];
                                                        break;
                                                    }
                                                }
                                            }
                                            echo htmlspecialchars(implode(', ', $trail_names));
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($subscriber['created_at'])); ?></td>
                                    <td>
                                        <span class="trail-status <?php echo $subscriber['active'] ? 'status-open' : 'status-closed'; ?>" style="font-size: 0.8rem; padding: 4px 12px;">
                                            <?php echo $subscriber['active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" action="admin.php" style="display: inline-block;" 
                                              onsubmit="return confirm('Are you sure you want to remove this push subscriber?');">
                                            <input type="hidden" name="action" value="remove_push_subscriber">
                                            <input type="hidden" name="subscriber_endpoint" value="<?php echo htmlspecialchars($subscriber['endpoint']); ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8rem;">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <h4 style="margin: 0 0 10px 0; color: #333;">Push Notification Management:</h4>
                        <ul style="margin: 0; padding-left: 20px; color: #666;">
                            <li>Push subscribers receive instant notifications when trail statuses change</li>
                            <li>Notifications work even when the browser is closed (on supported devices)</li>
                            <li>Each subscription represents a browser/device combination</li>
                            <li>Removing a subscription here will disable notifications for that device</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> LCFTF Mountain Bike Club</p>
        </footer>
    </div>

    <script>
        function updateAllTrails(status) {
            if (confirm('Are you sure you want to set all trails to ' + status.toUpperCase() + '?')) {
                // Create and submit forms for each trail
                <?php foreach ($trails as $trail): ?>
                    var form<?php echo $trail['id']; ?> = document.createElement('form');
                    form<?php echo $trail['id']; ?>.method = 'POST';
                    form<?php echo $trail['id']; ?>.action = 'admin.php';
                    form<?php echo $trail['id']; ?>.innerHTML = 
                        '<input type="hidden" name="action" value="update_status">' +
                        '<input type="hidden" name="trail_id" value="<?php echo $trail['id']; ?>">' +
                        '<input type="hidden" name="status" value="' + status + '">';
                    document.body.appendChild(form<?php echo $trail['id']; ?>);
                    form<?php echo $trail['id']; ?>.submit();
                <?php endforeach; ?>
            }
        }
    </script>
</body>
</html>
