<?php
/**
 * LCFTF Trail Status Setup Script
 * Run this once to set up the initial configuration
 */

// PHP 5.4 compatibility for password functions
if (!function_exists('password_hash')) {
    define('PASSWORD_DEFAULT', 1);
    
    function password_hash($password, $algo, $options = array()) {
        // Use bcrypt-like hashing for PHP 5.4
        $cost = isset($options['cost']) ? $options['cost'] : 10;
        $salt = '';
        
        // Generate a random salt
        for ($i = 0; $i < 22; $i++) {
            $salt .= chr(rand(33, 126));
        }
        
        // Create a simple hash (for PHP 5.4 compatibility)
        return '$2y$' . sprintf('%02d', $cost) . '$' . base64_encode($salt . hash('sha256', $salt . $password));
    }
    
    function password_verify($password, $hash) {
        // Extract salt from hash and verify
        if (strlen($hash) < 60) return false;
        
        $parts = explode('$', $hash);
        if (count($parts) < 4) return false;
        
        $stored_hash = base64_decode($parts[3]);
        if (strlen($stored_hash) < 22) return false;
        
        $salt = substr($stored_hash, 0, 22);
        $stored_password_hash = substr($stored_hash, 22);
        
        return hash('sha256', $salt . $password) === $stored_password_hash;
    }
}

// Check if running from command line or web
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    echo "<!DOCTYPE html><html><head><title>LCFTF Trail Status Setup</title>";
    echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}</style></head><body>";
    echo "<h1>🚵‍♂️ LCFTF Trail Status Setup</h1>";
}

// Define paths
$base_dir = __DIR__;
$data_dir = $base_dir . '/data/';
$includes_dir = $base_dir . '/includes/';

function output($message, $is_cli = false) {
    if ($is_cli) {
        echo $message . "\n";
    } else {
        echo "<p>" . htmlspecialchars($message) . "</p>";
    }
}

function check_requirements() {
    global $is_cli;
    $requirements = array(
        'PHP Version >= 5.4' => version_compare(PHP_VERSION, '5.4.0', '>='),
        'JSON Extension' => extension_loaded('json'),
        'Session Support' => function_exists('session_start'),
        'File System Write Access' => is_writable(__DIR__)
    );
    
    $all_good = true;
    output("Checking requirements:", $is_cli);
    
    foreach ($requirements as $req => $check) {
        $status = $check ? "✓ PASS" : "✗ FAIL";
        output("  $req: $status", $is_cli);
        if (!$check) $all_good = false;
    }
    
    return $all_good;
}

function setup_directories() {
    global $data_dir, $is_cli;
    
    output("Setting up directories:", $is_cli);
    
    if (!is_dir($data_dir)) {
        if (mkdir($data_dir, 0755, true)) {
            output("  ✓ Created data directory", $is_cli);
        } else {
            output("  ✗ Failed to create data directory", $is_cli);
            return false;
        }
    } else {
        output("  ✓ Data directory exists", $is_cli);
    }
    
    return true;
}

function create_default_data() {
    global $data_dir, $is_cli;
    
    $users_file = $data_dir . 'users.json';
    $trails_file = $data_dir . 'trails.json';
    
    output("Creating default data files:", $is_cli);
    
    // Default users
    if (!file_exists($users_file)) {
        $default_users = array(
            array(
                'id' => 1,
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s')
            )
        );
        
        if (file_put_contents($users_file, json_encode($default_users, JSON_PRETTY_PRINT))) {
            output("  ✓ Created users.json", $is_cli);
        } else {
            output("  ✗ Failed to create users.json", $is_cli);
            return false;
        }
    } else {
        output("  ✓ users.json exists", $is_cli);
    }
    
    // Default trails
    if (!file_exists($trails_file)) {
        $default_trails = array(
            array(
                'id' => 1,
                'name' => 'Marrington Plantation',
                'status' => 'open',
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => 'System'
            ),
            array(
                'id' => 2,
                'name' => 'Wannamaker North Trail',
                'status' => 'caution',
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => 'System'
            ),
            array(
                'id' => 3,
                'name' => 'Biggin Creek',
                'status' => 'closed',
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => 'System'
            )
        );
        
        if (file_put_contents($trails_file, json_encode($default_trails, JSON_PRETTY_PRINT))) {
            output("  ✓ Created trails.json", $is_cli);
        } else {
            output("  ✗ Failed to create trails.json", $is_cli);
            return false;
        }
    } else {
        output("  ✓ trails.json exists", $is_cli);
    }
    
    return true;
}

function set_permissions() {
    global $data_dir, $is_cli;
    
    output("Setting file permissions:", $is_cli);
    
    if (chmod($data_dir, 0755)) {
        output("  ✓ Set data directory permissions", $is_cli);
    } else {
        output("  ⚠ Could not set data directory permissions", $is_cli);
    }
    
    $files = array('users.json', 'trails.json');
    foreach ($files as $file) {
        $filepath = $data_dir . $file;
        if (file_exists($filepath)) {
            if (chmod($filepath, 0644)) {
                output("  ✓ Set permissions for $file", $is_cli);
            } else {
                output("  ⚠ Could not set permissions for $file", $is_cli);
            }
        }
    }
    
    return true;
}

// Main setup process
output("Starting LCFTF Trail Status setup...", $is_cli);
output("", $is_cli);

if (!check_requirements()) {
    output("Setup failed: Requirements not met.", $is_cli);
    exit(1);
}

output("", $is_cli);

if (!setup_directories()) {
    output("Setup failed: Could not create directories.", $is_cli);
    exit(1);
}

output("", $is_cli);

if (!create_default_data()) {
    output("Setup failed: Could not create default data.", $is_cli);
    exit(1);
}

output("", $is_cli);

set_permissions();

output("", $is_cli);
output("🎉 Setup completed successfully!", $is_cli);
output("", $is_cli);
output("Default login credentials:", $is_cli);
output("  Username: admin", $is_cli);
output("  Password: admin123", $is_cli);
output("", $is_cli);
output("Please change the default password after your first login!", $is_cli);
output("", $is_cli);
output("You can now access your trail status website:", $is_cli);
output("  Public view: index.php", $is_cli);
output("  Admin panel: admin.php", $is_cli);

if (!$is_cli) {
    echo "<div style='background:#d4edda;border:1px solid #c3e6cb;padding:15px;border-radius:5px;margin:20px 0;'>";
    echo "<strong>Next Steps:</strong><br>";
    echo "1. <a href='index.php'>View the public trail status page</a><br>";
    echo "2. <a href='login.php'>Login to the admin panel</a><br>";
    echo "3. Change the default password<br>";
    echo "4. Add your actual trails and update their status";
    echo "</div>";
    echo "</body></html>";
}
?>
