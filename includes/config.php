<?php
session_start();

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

// Status colors
$status_colors = [
    STATUS_OPEN => '#28a745',
    STATUS_CAUTION => '#ffc107',
    STATUS_CLOSED => '#dc3545'
];

// Status labels
$status_labels = [
    STATUS_OPEN => 'Open',
    STATUS_CAUTION => 'Caution',
    STATUS_CLOSED => 'Closed'
];

// Helper function to load JSON data
function loadJsonData($file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        return json_decode($content, true) ?: [];
    }
    return [];
}

// Helper function to save JSON data
function saveJsonData($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
            'name' => 'Blue Trail',
            'status' => STATUS_OPEN,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => 'System'
        ],
        [
            'id' => 2,
            'name' => 'Red Trail',
            'status' => STATUS_CAUTION,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => 'System'
        ],
        [
            'id' => 3,
            'name' => 'Black Diamond',
            'status' => STATUS_CLOSED,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => 'System'
        ]
    ];
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
