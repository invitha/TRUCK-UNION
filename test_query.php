<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';
$con = new mysqli($host, $username, $password, $dbname);
if ($con->connect_error) { die('DB error'); }
$res = $con->query("SELECT * FROM vendors LIMIT 1");
if (!$res) { echo "vendors error: " . $con->error . "\n"; } else { print_r($res->fetch_assoc()); }

$res2 = $con->query("SELECT c.cid FROM courier c LEFT JOIN vendors v ON c.assigned_vendor_id = v.vendor_id LIMIT 1");
if (!$res2) { echo "query error: " . $con->error . "\n"; } else { print_r($res2->fetch_assoc()); }
?>
