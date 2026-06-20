<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ── FCM helper functions ────────────────────────────────────────────────────

function getFirebaseAccessToken($service_account) {
    try {
        $now    = time();
        $header = str_replace(['+', '/', '='], ['-', '_', ''],
            base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'RS256'])));
        $payload = str_replace(['+', '/', '='], ['-', '_', ''],
            base64_encode(json_encode([
                'iss'   => $service_account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ])));
        $sig_input = "$header.$payload";
        openssl_sign($sig_input, $signature, $service_account['private_key'], OPENSSL_ALGO_SHA256);
        $jwt = "$sig_input." . str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return $response['access_token'] ?? null;
    } catch (Exception $e) {
        error_log("FCM token error: " . $e->getMessage());
        return null;
    }
}

function sendFcmPush($conn, $firebase_uid, $title, $body, $access_token) {
    if (!$access_token) return;

    // Get FCM token for this UID
    $stmt = $conn->prepare("SELECT fcm_token FROM vendor_fcm_tokens WHERE firebase_uid = ? LIMIT 1");
    $stmt->bind_param('s', $firebase_uid);
    $stmt->execute();
    $stmt->bind_result($fcm_token);
    $stmt->fetch();
    $stmt->close();

    if (empty($fcm_token)) return;

    $message = [
        'message' => [
            'token'        => $fcm_token,
            'notification' => ['title' => $title, 'body' => $body],
            'android'      => [
                'priority'     => 'high',
                'notification' => [
                    'channel_id'              => 'order_updates',
                    'default_sound'           => true,
                    'default_vibrate_timings' => true,
                ],
            ],
            'apns' => [
                'payload' => ['aps' => ['sound' => 'default', 'badge' => 1]],
            ],
            'data' => ['type' => 'order_update'],
        ],
    ];

    $ch = curl_init('https://fcm.googleapis.com/v1/projects/truck-union-vendor/messages:send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
    ]);
    $resp      = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("FCM push failed for $firebase_uid: HTTP $http_code - $resp");
    }
}

// ── Database connection ─────────────────────────────────────────────────────

$conn = new mysqli('localhost', 'royaldxd_user', 'meg_layout312', 'royaldxd_abra_crm');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// ── Load Firebase service account (for push notifications) ─────────────────
$service_account_path = __DIR__ . '/truck-union-vendor-service-account.json';
$service_account      = file_exists($service_account_path)
    ? json_decode(file_get_contents($service_account_path), true)
    : null;
$access_token = $service_account ? getFirebaseAccessToken($service_account) : null;

// ── Parse request ───────────────────────────────────────────────────────────

$data           = json_decode(file_get_contents('php://input'), true);
$vehicle_id     = $data['vehicle_id']     ?? '';
$payment_status = $data['payment_status'] ?? 'unpaid';
$payment_amount = floatval($data['payment_amount'] ?? 0);
$advance_amount = floatval($data['advance_amount']  ?? 0);
$notes          = $data['notes']          ?? '';

$al_number                = 'AL' . date('Ymd') . str_pad($vehicle_id, 4, '0', STR_PAD_LEFT);
$pickup_location          = '';
$delivery_location        = '';
$expected_completion_date = date('Y-m-d');
$assigned_by              = 'Internal Team';

if (empty($vehicle_id) || $payment_amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Vehicle ID and payment amount are required']);
    exit();
}

// ── Get vehicle details ─────────────────────────────────────────────────────
// NOTE: vehicles table stores vendor UID in 'firebase_uid', not 'vendor_firebase_uid'
$stmt = $conn->prepare("SELECT firebase_uid AS vendor_firebase_uid, vehicle_number, vehicle_name, driver_name FROM vehicles WHERE id = ?");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$vehicle) {
    echo json_encode(['status' => 'error', 'message' => 'Vehicle not found']);
    exit();
}

$remaining_amount = $payment_amount - $advance_amount;

// ── Insert fleet assignment ─────────────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO fleet_assignments
        (al_number, vehicle_id, vendor_firebase_uid, vehicle_number, vehicle_name,
         driver_name, assigned_by, pickup_location, delivery_location,
         expected_completion_date, status, notes, payment_status, payment_amount,
         advance_amount, remaining_amount)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?)
");
$stmt->bind_param("sisssssssssddd",
    $al_number, $vehicle_id, $vehicle['vendor_firebase_uid'],
    $vehicle['vehicle_number'], $vehicle['vehicle_name'], $vehicle['driver_name'],
    $assigned_by, $pickup_location, $delivery_location,
    $expected_completion_date, $notes, $payment_status, $payment_amount,
    $advance_amount, $remaining_amount
);

if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create assignment: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit();
}
$assignment_id = $stmt->insert_id;
$stmt->close();

// ── Prepare notification texts ──────────────────────────────────────────────
$vendor_uid     = $vehicle['vendor_firebase_uid'];
$driver_uid     = 'driver_' . $vehicle_id;
$driver_display = !empty($vehicle['driver_name']) ? $vehicle['driver_name'] : 'your driver';

$vendor_title   = "New Order Assigned - AL: $al_number";
$vendor_message = "Order $al_number assigned to $driver_display (Vehicle: {$vehicle['vehicle_number']}). Payment: Rs.$payment_amount.";

$driver_title   = "[Driver] New Order Assigned - AL: $al_number";
$driver_message = "You have a new order (AL: $al_number) for vehicle {$vehicle['vehicle_number']}. Check your orders tab.";

// ── Save in-app notifications ───────────────────────────────────────────────

// Vendor
$ns = $conn->prepare("INSERT INTO notifications (firebase_uid, type, title, message, is_read, created_at) VALUES (?, 'order_update', ?, ?, 0, NOW())");
$ns->bind_param('sss', $vendor_uid, $vendor_title, $vendor_message);
$ns->execute();
$ns->close();

// Driver
$ns2 = $conn->prepare("INSERT INTO notifications (firebase_uid, type, title, message, is_read, created_at) VALUES (?, 'order_update', ?, ?, 0, NOW())");
$ns2->bind_param('sss', $driver_uid, $driver_title, $driver_message);
$ns2->execute();
$ns2->close();

// ── Send FCM push to phone ──────────────────────────────────────────────────
sendFcmPush($conn, $vendor_uid, $vendor_title, $vendor_message, $access_token);
sendFcmPush($conn, $driver_uid, $driver_title, $driver_message, $access_token);

$conn->close();

echo json_encode([
    'status'        => 'success',
    'message'       => 'Fleet assigned successfully',
    'assignment_id' => $assignment_id,
    'al_number'     => $al_number,
    'push_sent'     => $access_token !== null,
]);
?>
