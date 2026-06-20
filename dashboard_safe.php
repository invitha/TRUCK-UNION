<?php
/**
 * SAFE VERSION OF DASHBOARD - Checks everything before using it
 */

// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    if ($con->connect_error) {
        die('<h1>Database Connection Failed</h1><p>Error: ' . $con->connect_error . '</p>');
    }
    $con->set_charset('utf8mb4');
} catch (Exception $e) {
    die('<h1>Database Error</h1><p>' . 