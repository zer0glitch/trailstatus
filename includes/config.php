<?php
session_start();

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

// Configuration settings
define('DATA_DIR', __DIR__ . '/../data/');
define('USERS_FILE', DATA_DIR . 'users.json');
define('TRAILS_FILE', DATA_DIR . 'trails.json');

// Ensure data directory exists and is writable
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Trail status constants
define('STATUS_OPEN', 'open');
define('STATUS_CAUTION', 'caution');
define('STATUS_CLOSED', 'closed');

// Status colors (matching FTF theme)
$status_colors = array(
    STATUS_OPEN => '#228B22',     // Forest Green
    STATUS_CAUTION => '#FF8C00',  // Dark Orange  
    STATUS_CLOSED => '#DC143C'    // Crimson Red
);

// Status labels
$status_labels = array(
    STATUS_OPEN => 'Open',
    STATUS_CAUTION => 'Caution',
    STATUS_CLOSED => 'Closed'
);

// Helper function to load JSON data
function loadJsonData($file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $decoded = json_decode($content, true);
        return $decoded ? $decoded : array();
    }
    return array();
}

// Helper function to save JSON data
function saveJsonData($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Initialize default data files if they don't exist
if (!file_exists(USERS_FILE)) {
    $default_users = array(
        array(
            'id' => 1,
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        )
    );
    saveJsonData(USERS_FILE, $default_users);
}

if (!file_exists(TRAILS_FILE)) {
    $default_trails = array(
        array(
            'id' => 1,
            'name' => 'Marrington Plantation',
            'status' => STATUS_OPEN,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => 'System'
        ),
        array(
            'id' => 2,
            'name' => 'Wannamaker North Trail',
            'status' => STATUS_CAUTION,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => 'System'
        ),
        array(
            'id' => 3,
            'name' => 'Biggin Creek',
            'status' => STATUS_CLOSED,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => 'System'
        )
    );
    saveJsonData(TRAILS_FILE, $default_trails);
}

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function authenticateUser($username, $password) {
    $users = loadJsonData(USERS_FILE);
    foreach ($users as $user) {
        if ($user['username'] === $username && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            return true;
        }
    }
    return false;
}

function logout() {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
