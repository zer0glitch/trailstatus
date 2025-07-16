<?php
/**
 * User Management Script for LCFTF Trail Status
 * Use this script to add new admin users
 * Run from command line: php add_user.php
 */

require_once 'includes/config.php';

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line for security reasons.');
}

function prompt($message) {
    echo $message;
    return trim(fgets(STDIN));
}

function promptPassword($message) {
    echo $message;
    system('stty -echo');
    $password = trim(fgets(STDIN));
    system('stty echo');
    echo "\n";
    return $password;
}

echo "🚵‍♂️ LCFTF Trail Status - User Management\n";
echo "========================================\n\n";

// Load existing users
$users = loadJsonData(USERS_FILE);

echo "Current users:\n";
foreach ($users as $user) {
    echo "  - {$user['username']} (ID: {$user['id']})\n";
}
echo "\n";

$action = prompt("What would you like to do? (add/list/delete): ");

switch (strtolower($action)) {
    case 'add':
        echo "\nAdding new user:\n";
        $username = prompt("Enter username: ");
        
        // Check if username already exists
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                echo "Error: Username already exists!\n";
                exit(1);
            }
        }
        
        $password = promptPassword("Enter password: ");
        $confirmPassword = promptPassword("Confirm password: ");
        
        if ($password !== $confirmPassword) {
            echo "Error: Passwords do not match!\n";
            exit(1);
        }
        
        if (strlen($password) < 6) {
            echo "Error: Password must be at least 6 characters long!\n";
            exit(1);
        }
        
        // Generate new user ID
        $maxId = 0;
        foreach ($users as $user) {
            if ($user['id'] > $maxId) {
                $maxId = $user['id'];
            }
        }
        
        // Add new user
        $newUser = array(
            'id' => $maxId + 1,
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        );
        
        $users[] = $newUser;
        
        if (saveJsonData(USERS_FILE, $users)) {
            echo "✓ User '$username' added successfully!\n";
        } else {
            echo "✗ Failed to save user data.\n";
            exit(1);
        }
        break;
        
    case 'list':
        echo "\nAll users:\n";
        foreach ($users as $user) {
            echo "  ID: {$user['id']}\n";
            echo "  Username: {$user['username']}\n";
            echo "  Created: {$user['created_at']}\n";
            echo "  ---\n";
        }
        break;
        
    case 'delete':
        $username = prompt("Enter username to delete: ");
        
        $userFound = false;
        $users = array_filter($users, function($user) use ($username, &$userFound) {
            if ($user['username'] === $username) {
                $userFound = true;
                return false;
            }
            return true;
        });
        
        if (!$userFound) {
            echo "Error: User '$username' not found!\n";
            exit(1);
        }
        
        $confirm = prompt("Are you sure you want to delete user '$username'? (yes/no): ");
        if (strtolower($confirm) !== 'yes') {
            echo "Operation cancelled.\n";
            exit(0);
        }
        
        // Re-index array
        $users = array_values($users);
        
        if (saveJsonData(USERS_FILE, $users)) {
            echo "✓ User '$username' deleted successfully!\n";
        } else {
            echo "✗ Failed to save user data.\n";
            exit(1);
        }
        break;
        
    default:
        echo "Invalid action. Please choose 'add', 'list', or 'delete'.\n";
        exit(1);
}

echo "\nDone!\n";
?>
