<?php
// =========================================================================
// Vendor KYC Admin Panel — TRUCK UNION Vendor App
// Based on customer-verification.php pattern with bank account fields
// =========================================================================

// ─── HANDLE IMAGE SERVING REQUESTS ─────────────────────────────────────────
if (isset($_GET['serve_image']) && isset($_GET['uid']) && isset($_GET['file'])) {
    $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['uid']);
    $file = basename($_GET['file']);
    
    $possible_paths = [
        '/home/royaldxd/crm.abra-logistic.com/uploads/vendor_kyc_documents/' . $uid . '/' . $file,
        '/home/royaldxd/crm.abra-logistic.com/dashboard/uploads/vendor_kyc_documents/' . $uid . '/' . $file,
        '/home/royaldxd/crm.abra-logistic.com/api1/uploads/vendor_kyc_documents/' . $uid . '/' . $file,
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $content_types = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                'gif' => 'image/gif', 'pdf' => 'application/pdf', 'webp' => 'image/webp'
            ];
            
            $content_type = isset($content_types[$extension]) ? $content_types[$extension] : 'application/octet-stream';
            
            header('Content-Type: ' . $content_type);
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: public, max-age=3600');
            readfile($path);
            exit();
        }
    }
    
    http_response_code(404);
    echo 'File not found';
    exit();
}

// ─── HANDLE AJAX POST REQUESTS FIRST (before any output) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    error_log("Vendor KYC Admin POST Request - Action: " . $_POST['action'] . ", KYC ID: " . ($_POST['kyc_id'] ?? 'none'));
    
    // Database connection for POST requests
    try {
        require_once('database.php');
        require_once('library.php');
        require_once('funciones.php');
        $con = conexion();
        if (!$con) {
            echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
            exit();
        }
        mysqli_set_charset($con, 'utf8mb4');
        $dbConn = $con;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
    
    $action = trim($_POST['action']);
    $kyc_id = isset($_POST['kyc_id']) ? intval($_POST['kyc_id']) : 0;
    
    // Helper functions
    function sendNotif($dbConn, $uid, $type, $title, $msg) {
        $stmt = mysqli_prepare($dbConn, "INSERT INTO notifications (firebase_uid, type, title, message) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssss', $uid, $type, $title, $msg);
            $result = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $result;
        }
        return false;
    }

    function getUID($dbConn, $id) {
        $s = mysqli_prepare($dbConn, "SELECT firebase_uid FROM vendor_kyc WHERE id=?");
        if ($s) {
            mysqli_stmt_bind_param($s, 'i', $id);
            mysqli_stmt_execute($s);
            $r = mysqli_stmt_get_result($s);
            $row = mysqli_fetch_assoc($r);
            mysqli_stmt_close($s);
            return $row ? $row['firebase_uid'] : null;
        }
        return null;
    }

    // APPROVE ACTION
    if ($action === 'approve' && $kyc_id > 0) {
        $uid = getUID($dbConn, $kyc_id);
        $stmt = mysqli_prepare($dbConn, "UPDATE vendor_kyc SET kyc_status='verified', verified_at=NOW() WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $kyc_id);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if ($ok && $uid) {
                sendNotif($dbConn, $uid, 'kyc_approved', 'KYC Verified ✓', 'Your vendor KYC has been approved. You can now add vehicles.');
            }
            echo json_encode(['status' => ($ok ? 'success' : 'error'), 'message' => ($ok ? 'Vendor KYC Approved successfully' : mysqli_error($dbConn))]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database prepare failed']);
        }
        exit();
    }

    // REJECT ACTION
    if ($action === 'reject' && $kyc_id > 0) {
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'Documents not clear';
        if (empty($reason)) {
            echo json_encode(['status' => 'error', 'message' => 'Rejection reason is required']);
            exit();
        }
        
        $uid = getUID($dbConn, $kyc_id);
        if (!$uid) {
            echo json_encode(['status' => 'error', 'message' => 'KYC record not found']);
            exit();
        }
        
        $stmt = mysqli_prepare($dbConn, "UPDATE vendor_kyc SET kyc_status='rejected', rejection_reason=? WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $reason, $kyc_id);
            $ok = mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            
            if ($ok && $affected > 0) {
                sendNotif($dbConn, $uid, 'kyc_rejected', 'KYC Rejected', 'Your vendor KYC was rejected. Reason: ' . $reason . '. Please resubmit.');
                echo json_encode(['status' => 'success', 'message' => 'Vendor KYC Rejected successfully', 'affected_rows' => $affected]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No rows updated. KYC ID may not exist or already rejected.', 'mysql_error' => mysqli_error($dbConn)]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database prepare failed: ' . mysqli_error($dbConn)]);
        }
        exit();
    }

    // REVOKE ACTION
    if ($action === 'revoke' && $kyc_id > 0) {
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'KYC verification revoked by admin';
        if (empty($reason)) {
            echo json_encode(['status' => 'error', 'message' => 'Revoke reason is required']);
            exit();
        }
        
        $uid = getUID($dbConn, $kyc_id);
        if (!$uid) {
            echo json_encode(['status' => 'error', 'message' => 'KYC record not found']);
            exit();
        }
        
        $stmt = mysqli_prepare($dbConn, "UPDATE vendor_kyc SET kyc_status='rejected', rejection_reason=?, verified_at=NULL WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $reason, $kyc_id);
            $ok = mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            
            if ($ok && $affected > 0) {
                sendNotif($dbConn, $uid, 'kyc_rejected', 'KYC Revoked', 'Your vendor KYC verification has been revoked. Reason: ' . $reason . '. Please resubmit your documents.');
                echo json_encode(['status' => 'success', 'message' => 'Vendor KYC Revoked successfully', 'affected_rows' => $affected]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No rows updated. KYC ID may not exist or not verified.', 'mysql_error' => mysqli_error($dbConn)]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database prepare failed: ' . mysqli_error($dbConn)]);
        }
        exit();
    }

    // DELETE ACTION
    if ($action === 'delete' && $kyc_id > 0) {
        session_start();
        $currentUserName = isset($_SESSION['user_name']) ? trim($_SESSION['user_name']) : '';
        $authorized_admins = array('Abishek Veeraswamy', 'Abishek', 'abishek');
        $isAdmin = false;
        foreach ($authorized_admins as $aname) {
            if (stripos($currentUserName, $aname) !== false || stripos($aname, $currentUserName) !== false) {
                $isAdmin = true; 
                break;
            }
        }
        
        if (!$isAdmin) {
            echo json_encode(['status' => 'error', 'message' => 'Access Denied: Only Abishek can delete KYC records']);
            exit();
        }
        
        $stmt = mysqli_prepare($dbConn, "DELETE FROM vendor_kyc WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $kyc_id);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo json_encode(['status' => ($ok ? 'success' : 'error'), 'message' => ($ok ? 'Vendor KYC record deleted' : mysqli_error($dbConn))]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database prepare failed']);
        }
        exit();
    }

    echo json_encode([
        'status' => 'error', 
        'message' => 'Unknown action: ' . $action, 
        'received_action' => $action, 
        'kyc_id' => $kyc_id
    ]);
    exit();
}

// ─── CONTINUE WITH NORMAL PAGE RENDERING ─────────────────────────────────────
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();

try {
    require_once('database.php');
    require_once('library.php');
    require_once('funciones.php');
    $con = conexion();
    if (!$con) die("Database connection failed: " . mysqli_connect_error());
    mysqli_set_charset($con, 'utf8mb4');
    $dbConn = $con;
    $table_check = mysqli_query($dbConn, "SHOW TABLES LIKE 'vendor_kyc'");
    if (mysqli_num_rows($table_check) == 0) {
        die("<h2 style='font-family:sans-serif;padding:40px;'>Setup Required: vendor_kyc table missing. Please run create_vendor_kyc_table.sql</h2>");
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// ─── Admin check ──────────────────────────────────────────────────────────────
$currentUserName = isset($_SESSION['user_name']) ? trim($_SESSION['user_name']) : '';
$authorized_admins = array('Abishek Veeraswamy', 'Abishek', 'abishek');
$isAdmin = false;
foreach ($authorized_admins as $aname) {
    if (stripos($currentUserName, $aname) !== false || stripos($aname, $currentUserName) !== false) {
        $isAdmin = true; break;
    }
}

// ─── Filters & Pagination ─────────────────────────────────────────────────────
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($dbConn, $_GET['status']) : 'submitted';
$allowed = array('submitted', 'verified', 'rejected');
if (!in_array($status_filter, $allowed)) $status_filter = 'submitted';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// ─── Fetch KYC records ────────────────────────────────────────────────────────
$where = "WHERE kyc_status = '$status_filter'";
$count_sql = "SELECT COUNT(*) as total FROM vendor_kyc $where";
$count_result = mysqli_query($dbConn, $count_sql);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

$sql = "SELECT * FROM vendor_kyc $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
$result = mysqli_query($dbConn, $sql);

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$uploads_base_url = $protocol . '://' . $host . '/uploads/vendor_kyc_documents/';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TRUCK UNION - Vendor KYC Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #001f3f 0%, #003d7a 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .header {
            background: white;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header h1 {
            color: #0D2E6E;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .header p { color: #64748B; font-size: 14px; }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .filters a {
            display: inline-block;
            padding: 10px 20px;
            margin-right: 10px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        .filters a.active {
            background: #0D2E6E;
            color: white;
        }
        .filters a:not(.active) {
            background: #F1F5F9;
            color: #64748B;
        }
        .filters a:not(.active):hover {
            background: #E2E8F0;
        }
        .kyc-list {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .kyc-item {
            padding: 24px;
            border-bottom: 1px solid #E2E8F0;
        }
        .kyc-item:hover { background: #F8FAFF; }
        .kyc-item:last-child { border-bottom: none; }
        .kyc-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 16px;
        }
        .kyc-info h3 {
            font-size: 18px;
            color: #0D2E6E;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .kyc-info p { font-size: 14px; color: #64748B; }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-submitted { background: #DBEAFE; color: #1E40AF; }
        .status-verified { background: #D1FAE5; color: #065F46; }
        .status-rejected { background: #FEE2E2; color: #991B1B; }
        .kyc-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        .detail-item { font-size: 13px; }
        .detail-item strong {
            color: #0D2E6E;
            display: block;
            margin-bottom: 4px;
        }
        .detail-item span { color: #64748B; }
        .documents {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .doc-link {
            padding: 8px 16px;
            background: #F1F5F9;
            border-radius: 8px;
            text-decoration: none;
            color: #0D2E6E;
            font-size: 13px;
            font-weight: 600;
        }
        .doc-link:hover { background: #E2E8F0; }
        .actions { display: flex; gap: 12px; }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-approve { background: #10B981; color: white; }
        .btn-approve:hover { background: #059669; }
        .btn-reject { background: #EF4444; color: white; }
        .btn-reject:hover { background: #DC2626; }
        .btn-revoke { background: #F59E0B; color: white; }
        .btn-revoke:hover { background: #D97706; }
        .btn-delete { background: #6B7280; color: white; }
        .btn-delete:hover { background: #4B5563; }
        .empty {
            text-align: center;
            padding: 60px 20px;
            color: #64748B;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        .pagination a {
            padding: 8px 16px;
            background: white;
            border-radius: 8px;
            text-decoration: none;
            color: #0D2E6E;
            font-weight: 600;
        }
        .pagination a.active {
            background: #0D2E6E;
            color: white;
        }
        .account-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            margin-left: 8px;
        }
        .badge-individual { background: #DBEAFE; color: #1E40AF; }
        .badge-business { background: #FEF3C7; color: #92400E; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚛 TRUCK UNION - Vendor KYC Admin Panel</h1>
            <p>Review and approve vendor KYC submissions</p>
        </div>

        <div class="filters">
            <a href="?status=submitted" class="<?php echo $status_filter == 'submitted' ? 'active' : ''; ?>">
                📋 Submitted (Needs Review)
            </a>
            <a href="?status=verified" class="<?php echo $status_filter == 'verified' ? 'active' : ''; ?>">
                ✅ Verified
            </a>
            <a href="?status=rejected" class="<?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">
                ❌ Rejected
            </a>
        </div>

        <div class="kyc-list">
            <?php if (mysqli_num_rows($result) == 0): ?>
                <div class="empty">
                    <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                    <p>No <?php echo $status_filter; ?> vendor KYC submissions found</p>
                </div>
            <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <div class="kyc-item">
                        <div class="kyc-header">
                            <div class="kyc-info">
                                <h3>
                                    <?php echo htmlspecialchars($row['name']); ?>
                                    <span class="account-type-badge badge-<?php echo $row['account_type']; ?>">
                                        <?php echo $row['account_type'] == 'business' ? '🏢 Business' : '👤 Individual'; ?>
                                    </span>
                                </h3>
                                <p><?php echo htmlspecialchars($row['email']); ?> • <?php echo htmlspecialchars($row['phone']); ?></p>
                            </div>
                            <span class="status-badge status-<?php echo $row['kyc_status']; ?>">
                                <?php echo $row['kyc_status']; ?>
                            </span>
                        </div>

                        <div class="kyc-details">
                            <div class="detail-item">
                                <strong>Aadhaar</strong>
                                <span><?php echo htmlspecialchars($row['aadhaar_number']); ?></span>
                            </div>
                            <div class="detail-item">
                                <strong>PAN</strong>
                                <span><?php echo htmlspecialchars($row['pan_number']); ?></span>
                            </div>
                            <div class="detail-item">
                                <strong>Bank Account</strong>
                                <span><?php echo htmlspecialchars($row['bank_account_number']); ?></span>
                            </div>
                            <div class="detail-item">
                                <strong>IFSC Code</strong>
                                <span><?php echo htmlspecialchars($row['ifsc_code']); ?></span>
                            </div>
                            <?php if ($row['account_type'] == 'business'): ?>
                                <div class="detail-item">
                                    <strong>Company</strong>
                                    <span><?php echo htmlspecialchars($row['company_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <strong>GST</strong>
                                    <span><?php echo htmlspecialchars($row['gst_number']); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <strong>Submitted</strong>
                                <span><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></span>
                            </div>
                        </div>

                        <div class="documents">
                            <?php if ($row['aadhaar_doc']): ?>
                                <a href="<?php echo $uploads_base_url . $row['firebase_uid'] . '/' . basename($row['aadhaar_doc']); ?>" target="_blank" class="doc-link">
                                    📄 Aadhaar
                                </a>
                            <?php endif; ?>
                            <?php if ($row['pan_doc']): ?>
                                <a href="<?php echo $uploads_base_url . $row['firebase_uid'] . '/' . basename($row['pan_doc']); ?>" target="_blank" class="doc-link">
                                    📄 PAN
                                </a>
                            <?php endif; ?>
                            <?php if ($row['photo_doc']): ?>
                                <a href="<?php echo $uploads_base_url . $row['firebase_uid'] . '/' . basename($row['photo_doc']); ?>" target="_blank" class="doc-link">
                                    📷 Photo
                                </a>
                            <?php endif; ?>
                            <?php if ($row['bank_account_photo']): ?>
                                <a href="<?php echo $uploads_base_url . $row['firebase_uid'] . '/' . basename($row['bank_account_photo']); ?>" target="_blank" class="doc-link">
                                    🏦 Bank Account
                                </a>
                            <?php endif; ?>
                            <?php if ($row['gst_doc']): ?>
                                <a href="<?php echo $uploads_base_url . $row['firebase_uid'] . '/' . basename($row['gst_doc']); ?>" target="_blank" class="doc-link">
                                    📄 GST
                                </a>
                            <?php endif; ?>
                            <?php if ($row['address_doc']): ?>
                                <a href="<?php echo $uploads_base_url . $row['firebase_uid'] . '/' . basename($row['address_doc']); ?>" target="_blank" class="doc-link">
                                    📄 Address
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="actions">
                            <?php if ($row['kyc_status'] == 'submitted'): ?>
                                <button class="btn btn-approve" onclick="approveKYC(<?php echo $row['id']; ?>)">
                                    ✓ Approve
                                </button>
                                <button class="btn btn-reject" onclick="rejectKYC(<?php echo $row['id']; ?>)">
                                    ✗ Reject
                                </button>
                            <?php endif; ?>
                            <?php if ($row['kyc_status'] == 'verified'): ?>
                                <button class="btn btn-revoke" onclick="revokeKYC(<?php echo $row['id']; ?>)">
                                    ⚠ Revoke
                                </button>
                            <?php endif; ?>
                            <?php if ($isAdmin): ?>
                                <button class="btn btn-delete" onclick="deleteKYC(<?php echo $row['id']; ?>)">
                                    🗑 Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?status=<?php echo $status_filter; ?>&page=<?php echo $i; ?>" 
                       class="<?php echo $page == $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function approveKYC(id) {
            if (!confirm('Approve this vendor KYC?')) return;
            sendAction('approve', id);
        }

        function rejectKYC(id) {
            const reason = prompt('Enter rejection reason:');
            if (!reason) return;
            sendAction('reject', id, reason);
        }

        function revokeKYC(id) {
            const reason = prompt('Enter revoke reason:');
            if (!reason) return;
            sendAction('revoke', id, reason);
        }

        function deleteKYC(id) {
            if (!confirm('⚠️ DELETE this vendor KYC record permanently?')) return;
            sendAction('delete', id);
        }

        function sendAction(action, kyc_id, reason = '') {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('kyc_id', kyc_id);
            if (reason) formData.append('reason', reason);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if (data.status === 'success') {
                    location.reload();
                }
            })
            .catch(err => alert('Error: ' + err));
        }
    </script>
</body>
</html>
<?php
mysqli_close($dbConn);
ob_end_flush();
?>
