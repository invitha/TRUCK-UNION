<?php
// backfill_notifications.php — run once after fix_notification_encoding.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$conn = new mysqli('localhost', 'royaldxd_user', 'meg_layout312', 'royaldxd_abra_crm');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => $conn->connect_error]));
}

$result = $conn->query("
    SELECT
        fa.id, fa.al_number, fa.vehicle_id, fa.payment_amount,
        fa.vehicle_number, fa.vehicle_name, fa.driver_name,
        v.firebase_uid AS vendor_uid
    FROM fleet_assignments fa
    JOIN vehicles v ON fa.vehicle_id = v.id
    WHERE v.firebase_uid IS NOT NULL AND v.firebase_uid != ''
    ORDER BY fa.id ASC
");

$vendor_created = 0;
$driver_created = 0;
$skipped = 0;

while ($row = $result->fetch_assoc()) {
    $vendor_uid = $row['vendor_uid'];
    $driver_uid = 'driver_' . $row['vehicle_id'];
    $al_number  = $row['al_number'];
    $payment    = number_format((float)$row['payment_amount'], 2, '.', '');
    $driver     = $row['driver_name'] ?: 'your driver';
    $veh_num    = $row['vehicle_number'];

    // ── Vendor notification ───────────────────────────────────────────
    $check = $conn->prepare("SELECT id FROM notifications WHERE firebase_uid = ? AND title LIKE ?");
    $like  = "%AL: $al_number%";
    $check->bind_param('ss', $vendor_uid, $like);
    $check->execute();
    $check->store_result();
    $vendor_exists = $check->num_rows > 0;
    $check->close();

    if (!$vendor_exists) {
        $title   = "New Order Assigned - AL: $al_number";
        $message = "Order $al_number assigned to $driver (Vehicle: $veh_num). Payment: Rs.$payment.";
        $ins = $conn->prepare("INSERT INTO notifications (firebase_uid, type, title, message, is_read, created_at) VALUES (?, 'order_update', ?, ?, 0, NOW())");
        $ins->bind_param('sss', $vendor_uid, $title, $message);
        $ins->execute();
        $ins->close();
        $vendor_created++;
    } else {
        $skipped++;
    }

    // ── Driver notification ───────────────────────────────────────────
    $check2 = $conn->prepare("SELECT id FROM notifications WHERE firebase_uid = ? AND title LIKE ?");
    $check2->bind_param('ss', $driver_uid, $like);
    $check2->execute();
    $check2->store_result();
    $driver_exists = $check2->num_rows > 0;
    $check2->close();

    if (!$driver_exists) {
        $dtitle   = "[Driver] New Order Assigned - AL: $al_number";
        $dmessage = "You have a new order (AL: $al_number) for vehicle $veh_num. Please check your orders tab.";
        $ins2 = $conn->prepare("INSERT INTO notifications (firebase_uid, type, title, message, is_read, created_at) VALUES (?, 'order_update', ?, ?, 0, NOW())");
        $ins2->bind_param('sss', $driver_uid, $dtitle, $dmessage);
        $ins2->execute();
        $ins2->close();
        $driver_created++;
    }
}

$conn->close();

echo json_encode([
    'status'         => 'success',
    'vendor_created' => $vendor_created,
    'driver_created' => $driver_created,
    'skipped'        => $skipped,
    'message'        => "Vendor: $vendor_created created. Driver: $driver_created created. Skipped: $skipped."
]);
?>
