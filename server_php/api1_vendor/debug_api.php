<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$data = json_decode(file_get_contents('php://input'), true);
$firebase_uid = isset($data['firebase_uid']) ? trim($data['firebase_uid']) : '';

echo json_encode(['received_firebase_uid' => $firebase_uid, 'status' => 'success', 'message' => 'Test API is working']);
