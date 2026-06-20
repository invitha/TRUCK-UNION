<?php
/**
 * Vendor KYC Admin Panel - Matches Customer KYC Design
 * Based on abra_app/customer-verification.php
 */

// ─── HANDLE AJAX POST REQUESTS FIRST (before any output) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    // Database connection
    $host = 'localhost';
    $dbname = 'royaldxd_abra_crm';
    $username = 'royaldxd_user';
    $password = 'meg_layout312';
    
    try {
        $con = new mysqli($host, $username, $password, $dbname);
        if ($con->connect_error) {
            echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
            exit();
        }
        $con->set_charset('utf8mb4');
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
    
    $action = trim($_POST['action']);
    $kyc_id = isset($_POST['kyc_id']) ? intval($_POST['kyc_id']) : 0;
    
    // Helper functions
    function sendNotif($con, $uid, $type, $title, $msg) {
        $stmt = mysqli_prepare($con, "INSERT INTO notifications (firebase_uid, type, title, message) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssss', $uid, $type, $title, $msg);
            $result = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $result;
        }
        return false;
    }

    function getUID($con, $id) {
        $s = mysqli_prepare($con, "SELECT firebase_uid FROM vendor_kyc WHERE id=?");
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
        $uid = getUID($con, $kyc_id);
        $stmt = mysqli_prepare($con, "UPDATE vendor_kyc SET kyc_status='verified', verified_at=NOW() WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $kyc_id);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if ($ok && $uid) {
                sendNotif($con, $uid, 'kyc_approved', '✅ KYC Verified Successfully!', 'Congratulations! Your KYC has been verified. You can now add vehicles and start accepting orders.');
            }
            echo json_encode(['status' => ($ok ? 'success' : 'error'), 'message' => ($ok ? 'KYC Approved successfully' : mysqli_error($con))]);
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
        
        $uid = getUID($con, $kyc_id);
        if (!$uid) {
            echo json_encode(['status' => 'error', 'message' => 'KYC record not found']);
            exit();
        }
        
        $stmt = mysqli_prepare($con, "UPDATE vendor_kyc SET kyc_status='rejected', rejection_reason=? WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $reason, $kyc_id);
            $ok = mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            
            if ($ok && $affected > 0) {
                sendNotif($con, $uid, 'kyc_rejected', '❌ KYC Rejected', 'Your KYC was rejected. Reason: ' . $reason . '. Please resubmit with correct documents.');
                echo json_encode(['status' => 'success', 'message' => 'KYC Rejected successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No rows updated']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database prepare failed']);
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
        
        $uid = getUID($con, $kyc_id);
        if (!$uid) {
            echo json_encode(['status' => 'error', 'message' => 'KYC record not found']);
            exit();
        }
        
        $stmt = mysqli_prepare($con, "UPDATE vendor_kyc SET kyc_status='rejected', rejection_reason=?, verified_at=NULL WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $reason, $kyc_id);
            $ok = mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            
            if ($ok && $affected > 0) {
                sendNotif($con, $uid, 'kyc_rejected', '⚠️ KYC Revoked', 'Your KYC verification has been revoked. Reason: ' . $reason . '. Please resubmit your documents.');
                echo json_encode(['status' => 'success', 'message' => 'KYC Revoked successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No rows updated']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database prepare failed']);
        }
        exit();
    }

    // DELETE ACTION
    if ($action === 'delete' && $kyc_id > 0) {
        $stmt = mysqli_prepare($con, "DELETE FROM vendor_kyc WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $kyc_id);
            $ok = mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            
            if ($ok && $affected > 0) {
                echo json_encode(['status' => 'success', 'message' => 'KYC Deleted successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No rows deleted']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database prepare failed']);
        }
        exit();
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown action: ' . $action]);
    exit();
}

// ─── CONTINUE WITH NORMAL PAGE RENDERING ─────────────────────────────────────
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    if ($con->connect_error) {
        die("Database connection failed");
    }
    $con->set_charset('utf8mb4');
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Get filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'submitted';
$allowed = array('submitted', 'verified', 'rejected', 'pending');
if (!in_array($status_filter, $allowed)) $status_filter = 'submitted';

// Get KYC records
$query = "SELECT * FROM vendor_kyc WHERE kyc_status = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 's', $status_filter);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$kyc_records = [];
while ($row = mysqli_fetch_assoc($result)) {
    $kyc_records[] = $row;
}
mysqli_stmt_close($stmt);

// Get counts
$counts = ['submitted' => 0, 'verified' => 0, 'rejected' => 0, 'pending' => 0];
foreach (['submitted', 'verified', 'rejected', 'pending'] as $status) {
    $stmt = mysqli_prepare($con, "SELECT COUNT(*) as count FROM vendor_kyc WHERE kyc_status = ?");
    mysqli_stmt_bind_param($stmt, 's', $status);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($r);
    $counts[$status] = $row['count'];
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor KYC Verification - TRUCK UNION</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
        
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; }
        .header h1 { font-size: 28px; font-weight: 800; margin-bottom: 8px; }
        .header p { opacity: 0.9; font-size: 14px; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; background: white; padding: 10px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .tab { padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
        .tab:hover { background: #f1f5f9; }
        .tab.active { background: #667eea; color: white; }
        .tab .count { background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 12px; margin-left: 8px; font-size: 12px; }
        .tab.active .count { background: rgba(255,255,255,0.3); }
        
        .kyc-card { background: white; border-radius: 16px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .kyc-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px; }
        .kyc-title { font-size: 20px; font-weight: 700; color: #1e293b; }
        .kyc-subtitle { color: #64748b; font-size: 14px; margin-top: 4px; }
        
        .status-badge { padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .status-submitted { background: #dbeafe; color: #1e40af; }
        .status-verified { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        
        .kyc-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .detail-item { font-size: 13px; }
        .detail-item strong { display: block; color: #1e293b; margin-bottom: 4px; }
        .detail-item span { color: #64748b; }
        
        .documents { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; padding: 16px; background: #f8fafc; border-radius: 12px; }
        .doc-link { padding: 10px 16px; background: white; border-radius: 8px; text-decoration: none; color: #667eea; font-size: 13px; font-weight: 600; border: 2px solid #e2e8f0; transition: all 0.2s; }
        .doc-link:hover { border-color: #667eea; transform: translateY(-2px); }
        
        .actions { display: flex; gap: 12px; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .btn-approve { background: #10b981; color: white; }
        .btn-reject { background: #ef4444; color: white; }
        .btn-revoke { background: #f59e0b; color: white; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state svg { width: 64px; height: 64px; margin-bottom: 16px; opacity: 0.5; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 16px; padding: 32px; max-width: 500px; width: 90%; }
        .modal-title { font-size: 20px; font-weight: 700; margin-bottom: 16px; }
        .modal-input { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .modal-actions { display: flex; gap: 12px; justify-content: flex-end; }
        .btn-cancel { background: #e2e8f0; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>🚛 Vendor KYC Verification</h1>
            <p>Review and approve vendor KYC submissions</p>
        </div>
    </div>

    <div class="container">
        <div class="tabs">
            <div class="tab <?php echo $status_filter === 'submitted' ? 'active' : ''; ?>" onclick="location.href='?status=submitted'">
                SUBMITTED <span class="count"><?php echo $counts['submitted']; ?></span>
            </div>
            <div class="tab <?php echo $status_filter === 'verified' ? 'active' : ''; ?>" onclick="location.href='?status=verified'">
                VERIFIED <span class="count"><?php echo $counts['verified']; ?></span>
            </div>
            <div class="tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" onclick="location.href='?status=rejected'">
                REJECTED <span class="count"><?php echo $counts['rejected']; ?></span>
            </div>
        </div>

        <?php if (empty($kyc_records)): ?>
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <h3>No <?php echo ucfirst($status_filter); ?> KYC Records</h3>
                <p>There are no vendor KYC submissions with this status.</p>
            </div>
        <?php else: ?>
            <?php foreach ($kyc_records as $kyc): ?>
                <div class="kyc-card">
                    <div class="kyc-header">
                        <div>
                            <div class="kyc-title"><?php echo htmlspecialchars($kyc['name']); ?></div>
                            <div class="kyc-subtitle">
                                <?php echo htmlspecialchars($kyc['email']); ?> • <?php echo htmlspecialchars($kyc['phone']); ?>
                            </div>
                        </div>
                        <span class="status-badge status-<?php echo $kyc['kyc_status']; ?>">
                            <?php echo strtoupper($kyc['kyc_status']); ?>
                        </span>
                    </div>

                    <div class="kyc-details">
                        <div class="detail-item">
                            <strong>Account Type</strong>
                            <span><?php echo $kyc['account_type'] === 'business' ? '🏢 Business' : '👤 Individual'; ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Aadhaar Number</strong>
                            <span><?php echo htmlspecialchars($kyc['aadhaar_number']); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>PAN Number</strong>
                            <span><?php echo htmlspecialchars($kyc['pan_number']); ?></span>
                        </div>
                        <?php if ($kyc['account_type'] === 'business'): ?>
                        <div class="detail-item">
                            <strong>Company Name</strong>
                            <span><?php echo htmlspecialchars($kyc['company_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>GST Number</strong>
                            <span><?php echo htmlspecialchars($kyc['gst_number']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="detail-item">
                            <strong>Submitted On</strong>
                            <span><?php echo date('d M Y, h:i A', strtotime($kyc['created_at'])); ?></span>
                        </div>
                    </div>

                    <div class="documents">
                        <?php if ($kyc['aadhaar_doc']): ?>
                            <a href="https://crm.abra-logistic.com/serve_kyc_image.php?uid=<?php echo $kyc['firebase_uid']; ?>&file=<?php echo basename($kyc['aadhaar_doc']); ?>" target="_blank" class="doc-link">📄 Aadhaar</a>
                        <?php endif; ?>
                        <?php if ($kyc['pan_doc']): ?>
                            <a href="https://crm.abra-logistic.com/serve_kyc_image.php?uid=<?php echo $kyc['firebase_uid']; ?>&file=<?php echo basename($kyc['pan_doc']); ?>" target="_blank" class="doc-link">📄 PAN</a>
                        <?php endif; ?>
                        <?php if ($kyc['photo_doc']): ?>
                            <a href="https://crm.abra-logistic.com/serve_kyc_image.php?uid=<?php echo $kyc['firebase_uid']; ?>&file=<?php echo basename($kyc['photo_doc']); ?>" target="_blank" class="doc-link">📷 Photo</a>
                        <?php endif; ?>
                        <?php if ($kyc['gst_doc']): ?>
                            <a href="https://crm.abra-logistic.com/serve_kyc_image.php?uid=<?php echo $kyc['firebase_uid']; ?>&file=<?php echo basename($kyc['gst_doc']); ?>" target="_blank" class="doc-link">📄 GST</a>
                        <?php endif; ?>
                        <?php if ($kyc['address_doc']): ?>
                            <a href="https://crm.abra-logistic.com/serve_kyc_image.php?uid=<?php echo $kyc['firebase_uid']; ?>&file=<?php echo basename($kyc['address_doc']); ?>" target="_blank" class="doc-link">📄 Address</a>
                        <?php endif; ?>
                        <?php if ($kyc['bank_account_photo']): ?>
                            <a href="https://crm.abra-logistic.com/serve_kyc_image.php?uid=<?php echo $kyc['firebase_uid']; ?>&file=<?php echo basename($kyc['bank_account_photo']); ?>" target="_blank" class="doc-link">🏦 Bank</a>
                        <?php endif; ?>
                    </div>

                    <div class="actions">
                        <?php if ($kyc['kyc_status'] === 'submitted'): ?>
                            <button class="btn btn-approve" onclick="doApprove(<?php echo $kyc['id']; ?>)">
                                ✓ Approve KYC
                            </button>
                            <button class="btn btn-reject" onclick="showRejectModal(<?php echo $kyc['id']; ?>)">
                                ✗ Reject KYC
                            </button>
                        <?php elseif ($kyc['kyc_status'] === 'verified'): ?>
                            <button class="btn btn-revoke" onclick="showRevokeModal(<?php echo $kyc['id']; ?>)">
                                ⚠️ Revoke KYC
                            </button>
                        <?php elseif ($kyc['kyc_status'] === 'rejected'): ?>
                            <button class="btn btn-approve" onclick="doApprove(<?php echo $kyc['id']; ?>)">
                                ✓ Re-approve KYC
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-reject" onclick="doDelete(<?php echo $kyc['id']; ?>)">
                            🗑️ Delete KYC
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Reject Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-content">
            <div class="modal-title">Reject KYC</div>
            <input type="text" id="rejectReason" class="modal-input" placeholder="Enter rejection reason..." value="Documents not clear">
            <div class="modal-actions">
                <button class="btn btn-cancel" onclick="closeModal()">Cancel</button>
                <button class="btn btn-reject" onclick="confirmReject()">Reject</button>
            </div>
        </div>
    </div>

    <!-- Revoke Modal -->
    <div class="modal" id="revokeModal">
        <div class="modal-content">
            <div class="modal-title">Revoke KYC</div>
            <input type="text" id="revokeReason" class="modal-input" placeholder="Enter revoke reason..." value="KYC verification revoked by admin">
            <div class="modal-actions">
                <button class="btn btn-cancel" onclick="closeModal()">Cancel</button>
                <button class="btn btn-revoke" onclick="confirmRevoke()">Revoke</button>
            </div>
        </div>
    </div>

    <script>
        let currentKycId = null;

        function doApprove(kycId) {
            if (!confirm('Are you sure you want to APPROVE this KYC?')) return;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=approve&kyc_id=' + kycId
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('KYC approved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Error: ' + err));
        }

        function showRejectModal(kycId) {
            currentKycId = kycId;
            document.getElementById('rejectModal').classList.add('active');
        }

        function showRevokeModal(kycId) {
            currentKycId = kycId;
            document.getElementById('revokeModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('rejectModal').classList.remove('active');
            document.getElementById('revokeModal').classList.remove('active');
            currentKycId = null;
        }

        function confirmReject() {
            const reason = document.getElementById('rejectReason').value.trim();
            if (!reason) {
                alert('Please enter a rejection reason');
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=reject&kyc_id=' + currentKycId + '&reason=' + encodeURIComponent(reason)
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('KYC rejected successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Error: ' + err));
        }

        function confirmRevoke() {
            const reason = document.getElementById('revokeReason').value.trim();
            if (!reason) {
                alert('Please enter a revoke reason');
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=revoke&kyc_id=' + currentKycId + '&reason=' + encodeURIComponent(reason)
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('KYC revoked successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Error: ' + err));
        }

        function doDelete(kycId) {
            if (!confirm('Are you absolutely sure you want to permanently delete this Vendor KYC record? This cannot be undone.')) return;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete&kyc_id=' + kycId
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('KYC deleted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Error: ' + err));
        }
    </script>
</body>
</html>
