<?php
// Database connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) die('DB Error');
$conn->set_charset('utf8mb4');

// Get vehicle ID from URL
$vehicle_id = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;

if ($vehicle_id == 0) {
    die('Invalid vehicle ID');
}

// Get vehicle details
$vehicle_query = "SELECT * FROM vehicles WHERE id = ?";
$stmt = $conn->prepare($vehicle_query);
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle_result = $stmt->get_result();
$vehicle = $vehicle_result->fetch_assoc();

if (!$vehicle) {
    die('Vehicle not found');
}

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])