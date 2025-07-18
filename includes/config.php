<?php
declare(strict_types=1);
session_start();

// Modern PHP 8.0+ Configuration
// No longer need PHP 5.4 compatibility shims

// Set timezone to Eastern Time
date_default_timezone_set('America/New_York');

// Configuration settings
define('DATA_DIR', __DIR__ . '/../data/');
define('USERS_FILE', DATA_DIR . 'users.json');
define('TRAILS_FILE', DATA_DIR . 'trails.json');

// Ensure data directory exists and is writable
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Trail status constants
const STATUS_OPEN = 'open';
const STATUS_CAUTION = 'caution';
const STATUS_CLOSED = 'closed';

// Status colors (matching FTF theme)
const STATUS_COLORS = [
    STATUS_OPEN => '#228B22',     // Forest Green
    STATUS_CAUTION => '#FF8C00',  // Dark Orange  
    STATUS_CLOSED => '#DC143C'    // Crimson Red
];

// Status labels
const STATUS_LABELS = [
    STATUS_OPEN => 'Open',
    STATUS_CAUTION => 'Caution',
    STATUS_CLOSED => 'Closed'
];

// Helper function to load JSON data with better error handling
function loadJsonData(string $file): array {
    if (!file_exists($file)) {
        return [];
    }
    
    $content = file_get_contents($file);
    if ($content === false) {
        error_log("Failed to read file: $file");
        return [];
    }
    
    try {
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        return $decoded ?? [];
    } catch (JsonException $e) {
        error_log("JSON decode error in $file: " . $e->getMessage());
        return [];
    }
}

// Helper function to save JSON data with better error handling
function saveJsonData(string $file, array $data): bool {
    try {
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $result = file_put_contents($file, $json, LOCK_EX);
        return $result !== false;
    } catch (JsonException $e) {
        error_log("JSON encode error for $file: " . $e->getMessage());
        return false;
    }
}

// Initialize default data files if they don't exist
if (!file_exists(USERS_FILE)) {
    $default_users = [
        [
            'id' => 1,
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
    saveJsonData(USERS_FILE, $default_users);
}

if (!file_exists(TRAILS_FILE)) {
    $default_trails = [
        [
            'id' => 1,
            'name' => 'Marrington Plantation',
            'status' => STATUS_OPEN,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => 'System'
        ],
        [
            'id' => 2,
            'name' => 'Wannamaker North Trail',
            'status' => STATUS_CAUTION,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => 'System'
        ],
        [
            'id' => 3,
            'name' => 'Biggin Creek',
            'status' => STATUS_CLOSED,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => 'System'
        ]
    ];
    saveJsonData(TRAILS_FILE, $default_trails);
}

// Push subscribers file
const PUSH_SUBSCRIBERS_FILE = DATA_DIR . 'push_subscribers.json';
if (!file_exists(PUSH_SUBSCRIBERS_FILE)) {
    saveJsonData(PUSH_SUBSCRIBERS_FILE, []);
}

// Authentication functions with type hints
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function authenticateUser(string $username, string $password): bool {
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

function logout(): void {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Load local configuration (VAPID keys, etc.)
$config_local_file = dirname(__DIR__) . '/config.local.php';
if (file_exists($config_local_file)) {
    require_once $config_local_file;
}
?>
