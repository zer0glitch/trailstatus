<?php
/**
 * Environment Test and Permission Fixer
 * Run this from the web browser to diagnose issues
 */

echo "<!DOCTYPE html><html><head><title>LCFTF Trail Status - Environment Test</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}</style></head><body>";
echo "<h1>🚵‍♂️ LCFTF Trail Status - Environment Test</h1>";

// Check PHP version
echo "<h2>PHP Environment</h2>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>Required:</strong> PHP 5.4+ " . (version_compare(PHP_VERSION, '5.4.0', '>=') ? "✓ PASS" : "✗ FAIL") . "</p>";

// Check extensions
echo "<p><strong>JSON Extension:</strong> " . (extension_loaded('json') ? "✓ PASS" : "✗ FAIL") . "</p>";
echo "<p><strong>Session Support:</strong> " . (function_exists('session_start') ? "✓ PASS" : "✗ FAIL") . "</p>";

// Check directories and permissions
echo "<h2>File System</h2>";
$base_dir = __DIR__;
$data_dir = $base_dir . '/data';

echo "<p><strong>Base Directory:</strong> $base_dir</p>";
echo "<p><strong>Writable:</strong> " . (is_writable($base_dir) ? "✓ YES" : "✗ NO") . "</p>";
echo "<p><strong>Owner:</strong> " . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($base_dir))['name'] : 'Unknown') . "</p>";
echo "<p><strong>Permissions:</strong> " . substr(sprintf('%o', fileperms($base_dir)), -4) . "</p>";

// Check data directory
if (is_dir($data_dir)) {
    echo "<p><strong>Data Directory:</strong> Exists</p>";
    echo "<p><strong>Data Dir Writable:</strong> " . (is_writable($data_dir) ? "✓ YES" : "✗ NO") . "</p>";
} else {
    echo "<p><strong>Data Directory:</strong> Does not exist</p>";
    // Try to create it
    if (mkdir($data_dir, 0755, true)) {
        echo "<p><strong>Created Data Directory:</strong> ✓ SUCCESS</p>";
    } else {
        echo "<p><strong>Failed to Create Data Directory:</strong> ✗ FAILED</p>";
    }
}

// Test file creation
echo "<h2>Write Test</h2>";
$test_file = $base_dir . '/test_write.tmp';
$can_write = file_put_contents($test_file, 'test');

if ($can_write !== false) {
    echo "<p><strong>File Creation:</strong> ✓ SUCCESS</p>";
    unlink($test_file); // Clean up
} else {
    echo "<p><strong>File Creation:</strong> ✗ FAILED</p>";
}

// Show current user
echo "<h2>Process Information</h2>";
if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $process_user = posix_getpwuid(posix_geteuid());
    echo "<p><strong>PHP Process User:</strong> " . $process_user['name'] . "</p>";
} else {
    echo "<p><strong>PHP Process User:</strong> Unknown (POSIX functions not available)</p>";
}

// Web server info
echo "<p><strong>Web Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</p>";

// Suggested fixes
echo "<h2>Suggested Fixes</h2>";
echo "<div style='background:#f8f9fa;padding:15px;border-radius:5px;margin:20px 0;'>";
echo "<h3>If write access fails, run these commands on your server:</h3>";
echo "<pre style='background:#333;color:#fff;padding:10px;border-radius:3px;'>";
echo "# Make sure directory is writable\n";
echo "chmod 755 /home/jamie/www/zeroglitch.com/trailstatus\n";
echo "chmod 755 /home/jamie/www/zeroglitch.com/trailstatus/data\n\n";
echo "# Set correct ownership (replace 'apache' with your web server user)\n";
echo "sudo chown -R apache:apache /home/jamie/www/zeroglitch.com/trailstatus\n\n";
echo "# Alternative: make files writable by all (less secure)\n";
echo "chmod 777 /home/jamie/www/zeroglitch.com/trailstatus\n";
echo "chmod 777 /home/jamie/www/zeroglitch.com/trailstatus/data\n";
echo "</pre>";
echo "</div>";

// Try setup
if (is_writable($base_dir)) {
    echo "<div style='background:#d4edda;border:1px solid #c3e6cb;padding:15px;border-radius:5px;margin:20px 0;'>";
    echo "<h3>Ready to proceed!</h3>";
    echo "<p>Your environment looks good. You can now:</p>";
    echo "<ol>";
    echo "<li><a href='setup.php'>Run the setup script</a></li>";
    echo "<li><a href='index.php'>View the trail status page</a></li>";
    echo "<li><a href='login.php'>Login to admin panel</a></li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background:#f8d7da;border:1px solid #f5c6cb;padding:15px;border-radius:5px;margin:20px 0;'>";
    echo "<h3>Action Required</h3>";
    echo "<p>Please fix the file permissions using the commands above, then reload this page.</p>";
    echo "</div>";
}

echo "</body></html>";
?>
