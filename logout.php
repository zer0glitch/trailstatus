<?php
require_once 'includes/config.php';

// Prevent caching for logout action
preventCaching();

// Logout the user
logout();
?>
