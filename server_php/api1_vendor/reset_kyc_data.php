<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Database connection
    $host = 'localhost';
    $dbname = 'royaldxd_abra_crm';
    $username = 'royaldxd_user';
    $password = 'meg_layout312';
    
    $con = new mysqli($host, $username, $password, $dbname);
    
    if ($con->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Wipe all data from driver_kyc and notifications
    $con->query("TRUNCATE TABLE driver_kyc");
    $con->query("DELETE FROM notifications WHERE type = 'kyc_update'");
    
    echo "<h1>SUCCESS: All test Driver KYC data has been completely wiped!</h1>";
    echo "<p>You can now go back to your app, refresh, and start fresh with real data.</p>";
    
} catch (Exception $e) {
    echo "<h1>Error: " . $e->getMessage() . "</h1>";
}
?>
