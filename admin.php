<?php
require_once 'includes/config.php';

// Require login to access admin panel
requireLogin();

$error = '';
$success = '';

// Handle trail status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $trail_id = (int)($_POST['trail_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';
        
        if ($trail_id > 0 && in_array($new_status, [STATUS_OPEN, STATUS_CAUTION, STATUS_CLOSED])) {
            $trails = loadJsonData(TRAILS_FILE);
            $trail_found = false;
            
            foreach ($trails as &$trail) {
                if ($trail['id'] === $trail_id) {
                    $trail['status'] = $new_status;
                    $trail['updated_at'] = date('Y-m-d H:i:s');
                    $trail['updated_by'] = $_SESSION['username'];
                    $trail_found = true;
                    break;
                }
            }
            
            if ($trail_found && saveJsonData(TRAILS_FILE, $trails)) {
                $success = 'Trail status updated successfully!';
            } else {
                $error = 'Failed to update trail status.';
            }
        } else {
            $error = 'Invalid trail or status data.';
        }
    } elseif ($action === 'add_trail') {
        $trail_name = trim($_POST['trail_name'] ?? '');
        $trail_status = $_POST['trail_status'] ?? STATUS_OPEN;
        
        if (!empty($trail_name) && in_array($trail_status, [STATUS_OPEN, STATUS_CAUTION, STATUS_CLOSED])) {
            $trails = loadJsonData(TRAILS_FILE);
            
            // Generate new ID
            $max_id = 0;
            foreach ($trails as $trail) {
                if ($trail['id'] > $max_id) {
                    $max_id = $trail['id'];
                }
            }
            
            $new_trail = [
                'id' => $max_id + 1,
                'name' => $trail_name,
                'status' => $trail_status,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $_SESSION['username']
            ];
            
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
        $trail_id = (int)($_POST['trail_id'] ?? 0);
        
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
        }
    }
}

$trails = loadJsonData(TRAILS_FILE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - LCFTF Trail Status</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>🚵‍♂️ LCFTF Trail Status</h1>
            <p>Administrator Panel</p>
        </header>

        <nav class="nav">
            <ul>
                <li><a href="index.php">Trail Status</a></li>
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
