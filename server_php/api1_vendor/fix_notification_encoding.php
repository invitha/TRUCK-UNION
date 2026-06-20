<?php
// fix_notification_encoding.php — run once, then delete from server
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$conn = new mysqli('localhost', 'royaldxd_user', 'meg_layout312', 'royaldxd_abra_crm');
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => $conn->connect_error]));
}

// Delete ALL order_update notifications (both garbled and clean — backfill will recreate correctly)
$conn->query("DELETE FROM notifications WHERE type = 'order_update'");
$deleted = $conn->affected_rows;

$conn->close();

echo json_encode([
    'status'  => 'success',
    'deleted' => $deleted,
    'message' => "Deleted $deleted order_update notification(s). Now run backfill_notifications.php"
]);
?>
