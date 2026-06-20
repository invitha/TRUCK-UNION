<?php
// Driver KYC Admin Panel - View and manage driver KYC submissions
require_once 'server_php/db_config.php';

$con = new mysqli($host, $username, $password, $dbname);
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}
$con->set_charset('utf8mb4');

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $firebase_uid = $_POST['firebase_uid'];
    $new_status = $_POST['status'];
    $rejection_reason = isset($_POST['rejection_reason']) ? $_POST['rejection_reason'] : null;
    $admin_notes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : null;
    
    $timestamp_field = '';
    if ($new_status === 'verified') {
        $timestamp_field = ', verified_at = NOW()';
    } elseif ($new_status === 'rejected') {
        $timestamp_field = ', rejected_at = NOW()';
    }
    
    $update_query = "UPDATE driver_kyc SET 
                     kyc_status = ?,
                     rejection_reason = ?,
                     admin_notes = ?,
                     updated_at = NOW()
                     $timestamp_field
                     WHERE firebase_uid = ?";
    $stmt = mysqli_prepare($con, $update_query);
    mysqli_stmt_bind_param($stmt, 'ssss', $new_status, $rejection_reason, $admin_notes, $firebase_uid);
    
    if (mysqli_stmt_execute($stmt)) {
        // Create notification
        $notification_title = '';
        $notification_message = '';
        
        if ($new_status === 'verified') {
            $notification_title = 'KYC Verified ✓';
            $notification_message = 'Congratulations! Your KYC has been verified. You can now start accepting orders.';
        } elseif ($new_status === 'rejected') {
            $notification_title = 'KYC Rejected';
            $notification_message = 'Your KYC has been rejected. ' . ($rejection_reason ? 'Reason: ' . $rejection_reason : 'Please resubmit with correct documents.');
        } elseif ($new_status === 'under_review') {
            $notification_title = 'KYC Under Review';
            $notification_message = 'Your KYC documents are being reviewed. You will be notified once verified.';
        }
        
        if (!empty($notification_title)) {
            $notif_query = "INSERT INTO notifications (firebase_uid, title, message, type, created_at) 
                            VALUES (?, ?, ?, 'kyc_update', NOW())";
            $notif_stmt = mysqli_prepare($con, $notif_query);
            mysqli_stmt_bind_param($notif_stmt, 'sss', $firebase_uid, $notification_title, $notification_message);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);
        }
        
        $success_message = "Driver KYC status updated successfully!";
    }
    mysqli_stmt_close($stmt);
}

// Get all driver KYC submissions
$query = "SELECT * FROM driver_kyc ORDER BY 
          CASE kyc_status 
            WHEN 'submitted' THEN 1
            WHEN 'under_review' THEN 2
            WHEN 'verified' THEN 3
            WHEN 'rejected' THEN 4
            ELSE 5
          END,
          created_at DESC";
$result = mysqli_query($con, $query);

// Count by status
$count_query = "SELECT 
                SUM(CASE WHEN kyc_status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN kyc_status = 'under_review' THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN kyc_status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN kyc_status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM driver_kyc";
$count_result = mysqli_query($con, $count_query);
$counts = mysqli_fetch_assoc($count_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver KYC Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #2563eb;
            --accent-green: #10b981;
            --accent-orange: #f97316;
            --accent-red: #ef4444;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            padding: 30px 15px;
        }
        
        .header-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 48px;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stat-card.submitted { border-left: 5px solid var(--accent-orange); }
        .stat-card.submitted .stat-number { color: var(--accent-orange); }
        
        .stat-card.under-review { border-left: 5px solid var(--primary-blue); }
        .stat-card.under-review .stat-number { color: var(--primary-blue); }
        
        .stat-card.verified { border-left: 5px solid var(--accent-green); }
        .stat-card.verified .stat-number { color: var(--accent-green); }
        
        .stat-card.rejected { border-left: 5px solid var(--accent-red); }
        .stat-card.rejected .stat-number { color: var(--accent-red); }
        
        .kyc-table-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .badge-submitted { background: var(--accent-orange); }
        .badge-under-review { background: var(--primary-blue); }
        .badge-verified { background: var(--accent-green); }
        .badge-rejected { background: var(--accent-red); }
        
        .btn-view { background: var(--primary-blue); color: white; }
        .btn-approve { background: var(--accent-green); color: white; }
        .btn-reject { background: var(--accent-red); color: white; }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="container-fluid">
            <div class="header-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="fas fa-id-card"></i> Driver KYC Admin Panel</h1>
                        <p class="text-muted mb-0">Review and manage driver KYC submissions</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="stats-row">
                <div class="stat-card submitted">
                    <i class="fas fa-clock fa-2x" style="color: var(--accent-orange);"></i>
                    <div class="stat-number"><?php echo $counts['submitted'] ?? 0; ?></div>
                    <div class="stat-label">Submitted</div>
                </div>
                <div class="stat-card under-review">
                    <i class="fas fa-search fa-2x" style="color: var(--primary-blue);"></i>
                    <div class="stat-number"><?php echo $counts['under_review'] ?? 0; ?></div>
                    <div class="stat-label">Under Review</div>
                </div>
                <div class="stat-card verified">
                    <i class="fas fa-check-circle fa-2x" style="color: var(--accent-green);"></i>
                    <div class="stat-number"><?php echo $counts['verified'] ?? 0; ?></div>
                    <div class="stat-label">Verified</div>
                </div>
                <div class="stat-card rejected">
                    <i class="fas fa-times-circle fa-2x" style="color: var(--accent-red);"></i>
                    <div class="stat-number"><?php echo $counts['rejected'] ?? 0; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <div class="kyc-table-container">
                <h3 class="mb-4">Driver KYC Submissions</h3>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Driver Name</th>
                                <th>Mobile</th>
                                <th>Aadhar</th>
                                <th>PAN</th>
                                <th>License</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($kyc = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($kyc['driver_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($kyc['driver_mobile']); ?></td>
                                    <td><?php echo htmlspecialchars($kyc['aadhar_number']); ?></td>
                                    <td><?php echo htmlspecialchars($kyc['pan_number']); ?></td>
                                    <td><?php echo htmlspecialchars($kyc['license_number']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $kyc['kyc_status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $kyc['kyc_status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($kyc['submitted_at'] ?? $kyc['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-view" onclick="viewKYC('<?php echo $kyc['firebase_uid']; ?>')">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View KYC Modal -->
    <div class="modal fade" id="kycModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Driver KYC Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="kycModalBody">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewKYC(firebaseUid) {
            const modal = new bootstrap.Modal(document.getElementById('kycModal'));
            modal.show();
            
            // Load KYC details via AJAX
            fetch(`server_php/api1_vendor/get_driver_kyc_status.php?firebase_uid=${firebaseUid}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.kyc_exists) {
                        displayKYCDetails(data.kyc_data);
                    }
                });
        }
        
        function displayKYCDetails(kyc) {
            const modalBody = document.getElementById('kycModalBody');
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Personal Information</h6>
                        <p><strong>Name:</strong> ${kyc.driver_name}</p>
                        <p><strong>Mobile:</strong> ${kyc.driver_mobile}</p>
                        <p><strong>Email:</strong> ${kyc.driver_email || 'N/A'}</p>
                        <p><strong>Address:</strong> ${kyc.address || 'N/A'}</p>
                        <p><strong>City:</strong> ${kyc.city || 'N/A'}</p>
                        <p><strong>State:</strong> ${kyc.state || 'N/A'}</p>
                        <p><strong>Pincode:</strong> ${kyc.pincode || 'N/A'}</p>
                        <p><strong>Vehicle Number:</strong> ${kyc.vehicle_number || '<span class="text-danger">Not Provided</span>'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Documents</h6>
                        <p><strong>Aadhar:</strong> ${kyc.aadhar_number}</p>
                        <p><strong>PAN:</strong> ${kyc.pan_number}</p>
                        <p><strong>License:</strong> ${kyc.license_number}</p>
                        <p><strong>Status:</strong> <span class="badge badge-${kyc.kyc_status}">${kyc.kyc_status}</span></p>
                    </div>
                </div>
                <hr>
                <h6>Document Images</h6>
                <div class="row">
                    ${kyc.aadhar_front_image ? `<div class="col-md-3 mb-3 text-center"><img src="https://crm.abra-logistic.com/api1/serve_kyc_document.php?file=${kyc.aadhar_front_image}" class="img-fluid rounded border" alt="Aadhar Front" style="max-height: 150px; object-fit: contain;"><div class="mt-1 small fw-bold">Aadhar Front</div></div>` : ''}
                    ${kyc.aadhar_back_image ? `<div class="col-md-3 mb-3 text-center"><img src="https://crm.abra-logistic.com/api1/serve_kyc_document.php?file=${kyc.aadhar_back_image}" class="img-fluid rounded border" alt="Aadhar Back" style="max-height: 150px; object-fit: contain;"><div class="mt-1 small fw-bold">Aadhar Back</div></div>` : ''}
                    ${kyc.pan_image ? `<div class="col-md-3 mb-3 text-center"><img src="https://crm.abra-logistic.com/api1/serve_kyc_document.php?file=${kyc.pan_image}" class="img-fluid rounded border" alt="PAN" style="max-height: 150px; object-fit: contain;"><div class="mt-1 small fw-bold">PAN Card</div></div>` : ''}
                    ${kyc.license_front_image ? `<div class="col-md-3 mb-3 text-center"><img src="https://crm.abra-logistic.com/api1/serve_kyc_document.php?file=${kyc.license_front_image}" class="img-fluid rounded border" alt="License Front" style="max-height: 150px; object-fit: contain;"><div class="mt-1 small fw-bold">License Front</div></div>` : ''}
                    ${kyc.license_back_image ? `<div class="col-md-3 mb-3 text-center"><img src="https://crm.abra-logistic.com/api1/serve_kyc_document.php?file=${kyc.license_back_image}" class="img-fluid rounded border" alt="License Back" style="max-height: 150px; object-fit: contain;"><div class="mt-1 small fw-bold">License Back</div></div>` : ''}
                </div>
                <hr>
                <h6>Vehicle Documents</h6>
                <div class="row">
                    ${kyc.rc_front_image ? `<div class="col-md-3 mb-3 text-center"><img src="https://crm.abra-logistic.com/api1/serve_kyc_document.php?file=${kyc.rc_front_image}" class="img-fluid rounded border" alt="RC Front" style="max-height: 150px; object-fit: contain;"><div class="mt-1 small fw-bold">RC Front</div></div>` : ''}
                    ${kyc.rc_back_image ? `<div class="col-md-3 mb-3 text-center"><img src="https://crm.abra-logistic.com/api1/serve_kyc_document.php?file=${kyc.rc_back_image}" class="img-fluid rounded border" alt="RC Back" style="max-height: 150px; object-fit: contain;"><div class="mt-1 small fw-bold">RC Back</div></div>` : ''}
                    ${kyc.insurance_image ? `<div class="col-md-3 mb-3 text-center"><a href="https://crm.abra-logistic.com/api1/serve_kyc_document.php?file=${kyc.insurance_image}" target="_blank" class="btn btn-sm btn-outline-primary mt-3"><i class="fas fa-file-pdf"></i> View Insurance</a></div>` : ''}
                    ${kyc.fitness_image ? `<div class="col-md-3 mb-3 text-center"><a href="https://crm.abra-logistic.com/api1/serve_kyc_document.php?file=${kyc.fitness_image}" target="_blank" class="btn btn-sm btn-outline-primary mt-3"><i class="fas fa-file-pdf"></i> View Fitness (FC)</a></div>` : ''}
                    ${kyc.puc_image ? `<div class="col-md-3 mb-3 text-center"><a href="https://crm.abra-logistic.com/api1/serve_kyc_document.php?file=${kyc.puc_image}" target="_blank" class="btn btn-sm btn-outline-primary mt-3"><i class="fas fa-file-pdf"></i> View PUC</a></div>` : ''}
                    ${kyc.vehicle_photo_front ? `<div class="col-md-3 mb-3 text-center"><img src="https://crm.abra-logistic.com/api1/serve_kyc_document.php?file=${kyc.vehicle_photo_front}" class="img-fluid rounded border" alt="Vehicle Front" style="max-height: 150px; object-fit: contain;"><div class="mt-1 small fw-bold">Vehicle Front</div></div>` : ''}
                    ${kyc.vehicle_photo_side ? `<div class="col-md-3 mb-3 text-center"><img src="https://crm.abra-logistic.com/api1/serve_kyc_document.php?file=${kyc.vehicle_photo_side}" class="img-fluid rounded border" alt="Vehicle Side" style="max-height: 150px; object-fit: contain;"><div class="mt-1 small fw-bold">Vehicle Side</div></div>` : ''}
                </div>
                <hr>
                <form method="POST">
                    <input type="hidden" name="firebase_uid" value="${kyc.firebase_uid}">
                    <div class="row">
                        <div class="col-md-6">
                            <label>Update Status</label>
                            <select name="status" class="form-select" required>
                                <option value="under_review" ${kyc.kyc_status === 'under_review' ? 'selected' : ''}>Under Review</option>
                                <option value="verified" ${kyc.kyc_status === 'verified' ? 'selected' : ''}>Verified</option>
                                <option value="rejected" ${kyc.kyc_status === 'rejected' ? 'selected' : ''}>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Rejection Reason (if rejected)</label>
                            <input type="text" name="rejection_reason" class="form-control" value="${kyc.rejection_reason || ''}">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label>Admin Notes</label>
                        <textarea name="admin_notes" class="form-control" rows="3">${kyc.admin_notes || ''}</textarea>
                    </div>
                    <div class="mt-3">
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            `;
        }
    </script>
</body>
</html>
<?php mysqli_close($con); ?>
