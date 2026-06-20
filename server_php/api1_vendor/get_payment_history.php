<?php
/**
 * Get Payment History for a Fleet Assignment
 * Returns summary payment info + individual installments from vendor_payments table
 * Called by: Flutter vendor app → Payment History sheet
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

error_reporting(E_ALL);
ini_set('display_errors', 0);

$host     = 'localhost';
$dbname   = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

$con = new mysqli($host, $username, $password, $dbname);
if ($con->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed']);
    exit();
}
$con->set_charset('utf8mb4');

// ── Accept tracking via GET or POST JSON ─────────────────────────────────
$tracking  = '';
$al_number = '';

if (!empty($_GET['tracking']))  $tracking  = trim($_GET['tracking']);
if (!empty($_GET['al_number'])) $al_number = trim($_GET['al_number']);
if (empty($tracking) && !empty($al_number)) $tracking = $al_number;

if (empty($tracking)) {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $tracking  = trim($body['tracking']  ?? '');
    $al_number = trim($body['al_number'] ?? '');
    if (empty($tracking) && !empty($al_number)) $tracking = $al_number;
}

if (empty($tracking)) {
    echo json_encode(['status' => 'error', 'message' => 'tracking or al_number is required']);
    exit();
}

$s = $con->real_escape_string($tracking);

// ── 1. Find courier row by tracking number ────────────────────────────────
$courier_info    = null;
$cid             = 0;
$al_from_courier = ''; // assigned_vehicle = AL number

$ci = $con->query("
    SELECT cid, tracking, assigned_vehicle, ship_name, s_add,
           rev_name, r_add, status, book_date,
           COALESCE(vendor_amount,       0)  AS vendor_amount,
           COALESCE(vendor_paid_amount,  0)  AS vendor_paid_amount,
           COALESCE(vendor_transaction_id,'') AS vendor_transaction_id,
           reference_no
    FROM courier
    WHERE tracking = '$s'
       OR assigned_vehicle = '$s'
    LIMIT 1
");
if ($ci && $row = $ci->fetch_assoc()) {
    $courier_info    = $row;
    $cid             = intval($row['cid']);
    $al_from_courier = trim($row['assigned_vehicle'] ?? '');
}

// ── 2. Build payment summary ──────────────────────────────────────────────
$payment = [
    'agreed_amount'  => 0,
    'paid_amount'    => 0,
    'balance'        => 0,
    'transaction_id' => '',
];

// Pull from courier fields first
if ($courier_info) {
    $payment['agreed_amount']  = (float)$courier_info['vendor_amount'];
    $payment['paid_amount']    = (float)$courier_info['vendor_paid_amount'];
    $payment['transaction_id'] = $courier_info['vendor_transaction_id'];
    $payment['balance']        = $payment['agreed_amount'] - $payment['paid_amount'];
}

// Always check fleet_assignments using the AL number from courier.assigned_vehicle
// (NOT the courier tracking number — that was the original bug)
$al_to_check = !empty($al_from_courier) ? $al_from_courier : $s;
$sa = $con->real_escape_string($al_to_check);

$fa = $con->query("
    SELECT COALESCE(payment_amount, 0)       AS payment_amount,
           COALESCE(advance_amount, 0)        AS advance_amount,
           COALESCE(vendor_transaction_id,'') AS vendor_transaction_id
    FROM fleet_assignments
    WHERE al_number = '$sa'
       OR al_number = '$s'
    ORDER BY id DESC
    LIMIT 1
");
$fleet_advance = 0;
$fleet_tx      = '';
$fleet_agreed  = 0;
if ($fa && $fr = $fa->fetch_assoc()) {
    $fleet_agreed  = (float)$fr['payment_amount'];
    $fleet_advance = (float)$fr['advance_amount'];
    $fleet_tx      = $fr['vendor_transaction_id'];
    // Use fleet data if courier fields are missing
    if ($payment['agreed_amount'] == 0 && $fleet_agreed > 0) {
        $payment['agreed_amount'] = $fleet_agreed;
    }
    if ($payment['paid_amount'] == 0 && $fleet_advance > 0) {
        $payment['paid_amount'] = $fleet_advance;
    }
    if (empty($payment['transaction_id']) && !empty($fleet_tx)) {
        $payment['transaction_id'] = $fleet_tx;
    }
    $payment['balance'] = $payment['agreed_amount'] - $payment['paid_amount'];
}

// ── 3. Get all CIDs linked to this AL number for vendor_payments lookup ───
$all_cids = [];
if ($cid > 0) $all_cids[] = $cid;

if (!empty($al_from_courier)) {
    $cid_q = $con->query("
        SELECT cid FROM courier
        WHERE TRIM(assigned_vehicle) = TRIM('$sa')
    ");
    if ($cid_q) {
        while ($cr = $cid_q->fetch_assoc()) {
            $c = intval($cr['cid']);
            if ($c > 0 && !in_array($c, $all_cids)) $all_cids[] = $c;
        }
    }
}

// ── 4. Fetch vendor_payments installments ────────────────────────────────
$payments = [];

if (!empty($all_cids)) {
    $cid_placeholder = implode(',', $all_cids);

    // Check vendor_payments table exists
    $vp_exists = $con->query("SHOW TABLES LIKE 'vendor_payments'");
    if ($vp_exists && $vp_exists->num_rows > 0) {
        $vp = $con->query("
            SELECT id, amount, transaction_id, notes, paid_by, paid_at
            FROM vendor_payments
            WHERE cid IN ($cid_placeholder)
            ORDER BY paid_at ASC
        ");
        if ($vp) {
            $idx = 1;
            while ($p = $vp->fetch_assoc()) {
                $payments[] = [
                    'id'             => intval($p['id']),
                    'installment_no' => $idx++,
                    'amount'         => (float)$p['amount'],
                    'transaction_id' => $p['transaction_id'] ?? '',
                    'notes'          => $p['notes']          ?? '',
                    'paid_by'        => $p['paid_by']        ?? '',
                    'paid_at'        => $p['paid_at']        ?? '',
                ];
            }
        }
    }
}

// ── 5. If vendor_payments empty, synthesize from fleet_assignments advance ─
$advance_to_show = $fleet_advance > 0 ? $fleet_advance : $payment['paid_amount'];
$tx_to_show      = !empty($fleet_tx) ? $fleet_tx : $payment['transaction_id'];

if (empty($payments) && $advance_to_show > 0) {
    $payments[] = [
        'id'             => 0,
        'installment_no' => 1,
        'amount'         => $advance_to_show,
        'transaction_id' => $tx_to_show,
        'notes'          => 'Advance payment',
        'paid_by'        => '',
        'paid_at'        => $courier_info['book_date'] ?? '',
    ];
    // Also update payment summary to reflect this
    $payment['paid_amount'] = $advance_to_show;
    $payment['balance']     = $payment['agreed_amount'] - $advance_to_show;
}

// ── 6. Recalculate totals from installments if available ──────────────────
if (!empty($payments)) {
    $total_from_installments = array_sum(array_column($payments, 'amount'));
    $payment['paid_amount']  = $total_from_installments;
    $payment['balance']      = $payment['agreed_amount'] - $total_from_installments;
}

$con->close();

echo json_encode([
    'status'        => 'success',
    'tracking'      => $tracking,
    'courier'       => $courier_info,
    'payment'       => $payment,
    'payments'      => $payments,
    'payment_count' => count($payments),
]);
?>
