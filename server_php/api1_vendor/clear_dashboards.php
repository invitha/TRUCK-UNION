<?php
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);
if ($con->connect_error) {
    die('Database connection failed: ' . $con->connect_error);
}

// Truncate the fleet_assignments table to clear all driver/vendor dashboards
$con->query("TRUNCATE TABLE fleet_assignments");

echo "<h1>All Vendor and Driver Dashboard data has been successfully cleared!</h1>";
echo "<p>You can now assign new test shipments.</p>";
echo "<p style='color:red;'><b>Please delete this file from your server after running it!</b></p>";

$con->close();
?>
