<?php
/**
 * Database Configuration for TRUCK UNION Vendor App
 * 
 * INSTRUCTIONS:
 * 1. Update the values below with your actual database credentials
 * 2. Make sure this file is NOT accessible from the web (use .htaccess)
 * 3. Keep this file secure and never commit real credentials to version control
 */

// Database credentials
$db_host = 'localhost';           // Usually 'localhost' for local development
$db_username = 'royaldxd_abra';   // Your database username
$db_password = 'your_password';   // Your database password (UPDATE THIS!)
$db_name = 'royaldxd_abra';       // Your database name

// Create connection
$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed. Please check db_config.php settings.'
    ]));
}

// Set charset to utf8mb4 for proper emoji and special character support
$conn->set_charset("utf8mb4");

// Optional: Set timezone (adjust as needed)
// $conn->query("SET time_zone = '+05:30'"); // IST timezone
