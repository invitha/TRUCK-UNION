<?php
// Database connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all vendor KYC submissions
    $stmt = $pdo->prepare("
        SELECT * FROM vendor_kyc
        ORDER BY 
            account_type ASC,
            CASE kyc_status
                WHEN 'submitted' THEN 1
                WHEN 'verified' THEN 2
                WHEN 'rejected' THEN 3
            END,
            created_at DESC
    ");
    
    $stmt->execute();
    $all_kyc = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate by account type
    $individual_vendors = array_filter($all_kyc, function($kyc) {
        return $kyc['account_type'] === 'individual';
    });
    
    $business_vendors = array_filter($all_kyc, function($kyc) {
        return $kyc['account_type'] === 'business';
    });
    
    // Calculate stats
    $stats = [
        'total' => count($all_kyc),
        'individual' => count($individual_vendors),
        'business' => count($business_vendors),
        'submitted' => count(array_filter($all_kyc, fn($k) => $k['kyc_status'] === 'submitted')),
        'verified' => count(array_filter($all_kyc, fn($k) => $k['kyc_status'] === 'verified')),
        'rejected' => count(array_filter($all_kyc, fn($k) => $k['kyc_status'] === 'rejected'))
    ];
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor KYC Dashboard - TRUCK UNION</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .header h1 {
            color: #0D2E6E;
            font-size: 36px;
            font-weight: 900;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header p {
            color: #64748B;
            font-size: 16px;
            font-weight: 500;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 28px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
        }

        .stat-card h3 {
            font-size: 13px;
            color: #64748B;
            margin-bottom: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card .number {
            font-size: 42px;
            font-weight: 900;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-header {
            background: white;
            padding: 24px 32px;
            border-radius: 20px;
            margin: 40px 0 24px 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .section-header.individual {
            border-left: 8px solid #3B82F6;
        }

        .section-header.business {
            border-left: 8px solid #10B981;
        }

        .section-header h2 {
            font-size: 28px;
            font-weight: 900;
            color: #0D2E6E;
            flex: 1;
        }

        .section-header .count-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 24px;
            border-radius: 25px;
            font-size: 18px;
            font-weight: 800;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .vendors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(550px, 1fr));
            gap: 28px;
            margin-bottom: 50px;
        }

        .vendor-card {
            background: white;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .vendor-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 8px;
            background: linear-gradient(90deg, #3B82F6, #8B5CF6);
        }

        .vendor-card.business::before {
            background: linear-gradient(90deg, #10B981, #059669);
        }

        .vendor-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 3px solid #F1F5F9;
        }

        .vendor-name {
            font-size: 24px;
            font-weight: 900;
            color: #0D2E6E;
            margin-bottom: 10px;
        }

        .account-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .account-badge.individual {
            background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
            color: #1E40AF;
        }

        .account-badge.business {
            background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
            color: #065F46;
        }

        .status-badge {
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-submitted {
            background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
            color: #1E40AF;
        }

        .status-verified {
            background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
            color: #065F46;
        }

        .status-rejected {
            background: linear-gradient(135deg, #FEE2E2, #FECACA);
            color: #991B1B;
        }

        .info-section {
            margin-bottom: 24px;
        }

        .info-row {
            display: flex;
            align-items: start;
            gap: 14px;
            padding: 14px;
            background: linear-gradient(135deg, #F8FAFC, #F1F5F9);
            border-radius: 12px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }

        .info-row:hover {
            background: linear-gradient(135deg, #E2E8F0, #CBD5E1);
            transform: translateX(5px);
        }

        .info-icon {
            font-size: 22px;
            min-width: 28px;
            text-align: center;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 11px;
            font-weight: 800;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 700;
            color: #0F172A;
            word-break: break-word;
        }

        .company-box {
            background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            border-left: 6px solid #10B981;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
        }

        .company-name-large {
            font-size: 22px;
            font-weight: 900;
            color: #065F46;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .documents-section {
            margin: 24px 0;
        }

        .documents-title {
            font-size: 13px;
            font-weight: 800;
            color: #0D2E6E;
            margin-bottom: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
        }

        .doc-link {
            padding: 14px;
            background: linear-gradient(135deg, #F8FAFC, #F1F5F9);
            border: 2px solid #E2E8F0;
            border-radius: 12px;
            text-decoration: none;
            color: #0D2E6E;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.3s;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .doc-link:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .doc-icon {
            font-size: 28px;
        }

        .actions-section {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 3px solid #F1F5F9;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1;
            min-width: 140px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-approve {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }

        .btn-approve:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.6);
        }

        .btn-reject {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }

        .btn-reject:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.6);
        }

        .btn-revoke {
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: white;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
        }

        .btn-revoke:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.6);
        }

        .empty-state {
            background: white;
            padding: 60px;
            border-radius: 24px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-text {
            font-size: 18px;
            color: #64748B;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .vendors-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🚛 TRUCK UNION - Vendor KYC Dashboard</h1>
            <p>Complete vendor verification management system - All KYC details at a glance</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Vendors</h3>
                <div class="number"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Individual Vendors</h3>
                <div class="number"><?php echo $stats['individual']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Business Vendors</h3>
                <div class="number"><?php echo $stats['business']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Submitted</h3>
                <div class="number"><?php echo $stats['submitted']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Verified</h3>
                <div class="number"><?php echo $stats['verified']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Rejected</h3>
                <div class="number"><?php echo $stats['rejected']; ?></div>
            </div>
        </div>

        <!-- Individual Vendors Section -->
        <div class="section-header individual">
            <span style="font-size: 32px;">👤</span>
            <h2>Individual Vendors</h2>
            <span class="count-badge"><?php echo count($individual_vendors); ?></span>
        </div>

        <div class="vendors-grid">
            <?php if (empty($individual_vendors)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <div class="empty-text">No individual vendors found</div>
                </div>
            <?php else: ?>
                <?php foreach ($individual_vendors as $vendor): ?>
                    <div class="vendor-card individual">
                        <div class="card-top">
                            <div>
                                <div class="vendor-name"><?php echo htmlspecialchars($vendor['name']); ?></div>
                                <span class="account-badge individual">👤 Individual Account</span>
                            </div>
                            <span class="status-badge status-<?php echo $vendor['kyc_status']; ?>">
                                <?php echo strtoupper($vendor['kyc_status']); ?>
                            </span>
                        </div>

                        <div class="info-section">
                            <div class="info-row">
                                <div class="info-icon">📧</div>
                                <div class="info-content">
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['email']); ?></div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon">📱</div>
                                <div class="info-content">
                                    <div class="info-label">Phone Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['phone']); ?></div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon">🆔</div>
                                <div class="info-content">
                                    <div class="info-label">Aadhaar Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['aadhaar_number']); ?></div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon">💳</div>
                                <div class="info-content">
                                    <div class="info-label">PAN Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['pan_number']); ?></div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon">🏦</div>
                                <div class="info-content">
                                    <div class="info-label">Bank Account Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['bank_account_name']); ?></div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon">💰</div>
                                <div class="info-content">
                                    <div class="info-label">Bank Account Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['bank_account_number']); ?></div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon">🔢</div>
                                <div class="info-content">
                                    <div class="info-label">IFSC Code</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['ifsc_code']); ?></div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon">📅</div>
                                <div class="info-content">
                                    <div class="info-label">Submitted On</div>
                                    <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($vendor['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="documents-section">
                            <div class="documents-title">📎 Uploaded Documents</div>
                            <div class="documents-grid">
                                <?php if ($vendor['aadhaar_doc']): ?>
                                    <a href="serve_kyc_image.php?uid=<?php echo $vendor['firebase_uid']; ?>&file=<?php echo basename($vendor['aadhaar_doc']); ?>" target="_blank" class="doc-link">
                                        <div class="doc-icon">🆔</div>
                                        <div>Aadhaar</div>
                                    </a>
                                <?php endif; ?>

                                <?php if ($vendor['pan_doc']): ?>
                                    <a href="serve_kyc_image.php?uid=<?php echo $vendor['firebase_uid']; ?>&file=<?php echo basename($vendor['pan_doc']); ?>" target="_blank" class="doc-link">
                                        <div class="doc-icon">💳</div>
                                        <div>PAN Card</div>
                                    </a>
                                <?php endif; ?>

                                <?php if ($vendor['photo_doc']): ?>
                                    <a href="serve_kyc_image.php?uid=<?php echo $vendor['firebase_uid']; ?>&file=<?php echo basename($vendor['photo_doc']); ?>" target="_blank" class="doc-link">
                                        <div class="doc-icon">📷</div>
                                        <div>Photo</div>
                                    </a>
                                <?php endif; ?>

                                <?php if ($vendor['bank_account_photo']): ?>
                                    <a href="serve_kyc_image.php?uid=<?php echo $vendor['firebase_uid']; ?>&file=<?php echo basename($vendor['bank_account_photo']); ?>" target="_blank" class="doc-link">
                                        <div class="doc-icon">🏦</div>
                                        <div>Bank Account</div>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="actions-section">
                            <?php if ($vendor['kyc_status'] === 'submitted'): ?>
                                <button class="btn btn-approve" onclick="updateStatus('<?php echo $vendor['firebase_uid']; ?>', 'verified')">✓ Approve</button>
                                <button class="btn btn-reject" onclick="rejectKYC('<?php echo $vendor['firebase_uid']; ?>')">✗ Reject</button>
                            <?php elseif ($vendor['kyc_status'] === 'verified'): ?>
                                <button class="btn btn-revoke" onclick="revokeKYC('<?php echo $vendor['firebase_uid']; ?>')">⚠️ Revoke</button>
                            <?php elseif ($vendor['kyc_status'] === 'rejected'): ?>
                                <button class="btn btn-approve" onclick="updateStatus('<?php echo $vendor['firebase_uid']; ?>', 'verified')">✓ Re-Approve</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Business Vendors Section -->
        <div class="section-header business">
            <span style="font-size: 32px;">🏢</span>
            <h2>Business Vendors</h2>
            <span class="count-badge"><?php echo count($business_vendors); ?></span>
        </div>

        <div class="vendors-grid">
            <?php if (empty($business_vendors)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <div class="empty-text">No business vendors found</div>
                </div>
            <?php else: ?>
                <?php foreach ($business_vendors as $vendor): ?>
                    <div class="vendor-card business">
                        <div class="card-top">
                            <div>
                                <div class="vendor-name"><?php echo htmlspecialchars($vendor['name']); ?></div>
                                <span class="account-badge business">🏢 Business Account</span>
                            </div>
                            <span class="status-badge status-<?php echo $vendor['kyc_status']; ?>">
                                <?php echo strtoupper($vendor['kyc_status']); ?>
                            </span>
                        </div>

                        <!-- Company Information Highlight -->
                        <div class="company-box">
                            <div class="company-name-large">
                                🏢 <?php echo htmlspecialchars($vendor['company_name'] ?: 'N/A'); ?>
                            </div>
                            <div class="info-row" style="margin-bottom: 10px;">
                                <div class="info-icon">📄</div>
                                <div class="info-content">
                                    <div class="info-label">GST Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['gst_number'] ?: 'N/A'); ?></div>
                                </div>
                            </div>
                            <div class="info-row" style="margin-bottom: 0;">
                                <div class="info-icon">📍</div>
                                <div class="info-content">
                                    <div class="info-label">Business Address</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['address'] ?: 'N/A'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="info-section">
                            <div class="info-row">
                                <div class="info-icon">📧</div>
                                <div class="info-content">
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['email']); ?></div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon">📱</div>
                                <div class="info-content">
                                    <div class="info-label">Phone Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['phone']); ?></div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon">🆔</div>
                                <div class="info-content">
                                    <div class="info-label">Aadhaar Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['aadhaar_number']); ?></div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon">💳</div>
                                <div class="info-content">
                                    <div class="info-label">PAN Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['pan_number']); ?></div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon">🏦</div>
                                <div class="info-content">
                                    <div class="info-label">Bank Account Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['bank_account_name']); ?></div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon">💰</div>
                                <div class="info-content">
                                    <div class="info-label">Bank Account Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['bank_account_number']); ?></div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon">🔢</div>
                                <div class="info-content">
                                    <div class="info-label">IFSC Code</div>
                                    <div class="info-value"><?php echo htmlspecialchars($vendor['ifsc_code']); ?></div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon">📅</div>
                                <div class="info-content">
                                    <div class="info-label">Submitted On</div>
                                    <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($vendor['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="documents-section">
                            <div class="documents-title">📎 Uploaded Documents</div>
                            <div class="documents-grid">
                                <?php if ($vendor['aadhaar_doc']): ?>
                                    <a href="serve_kyc_image.php?uid=<?php echo $vendor['firebase_uid']; ?>&file=<?php echo basename($vendor['aadhaar_doc']); ?>" target="_blank" class="doc-link">
                                        <div class="doc-icon">🆔</div>
                                        <div>Aadhaar</div>
                                    </a>
                                <?php endif; ?>

                                <?php if ($vendor['pan_doc']): ?>
                                    <a href="serve_kyc_image.php?uid=<?php echo $vendor['firebase_uid']; ?>&file=<?php echo basename($vendor['pan_doc']); ?>" target="_blank" class="doc-link">
                                        <div class="doc-icon">💳</div>
                                        <div>PAN Card</div>
                                    </a>
                                <?php endif; ?>

                                <?php if ($vendor['photo_doc']): ?>
                                    <a href="serve_kyc_image.php?uid=<?php echo $vendor['firebase_uid']; ?>&file=<?php echo basename($vendor['photo_doc']); ?>" target="_blank" class="doc-link">
                                        <div class="doc-icon">📷</div>
                                        <div>Photo</div>
                                    </a>
                                <?php endif; ?>

                                <?php if ($vendor['gst_doc']): ?>
                                    <a href="serve_kyc_image.php?uid=<?php echo $vendor['firebase_uid']; ?>&file=<?php echo basename($vendor['gst_doc']); ?>" target="_blank" class="doc-link">
                                        <div class="doc-icon">📄</div>
                                        <div>GST Certificate</div>
                                    </a>
                                <?php endif; ?>

                                <?php if ($vendor['address_doc']): ?>
                                    <a href="serve_kyc_image.php?uid=<?php echo $vendor['firebase_uid']; ?>&file=<?php echo basename($vendor['address_doc']); ?>" target="_blank" class="doc-link">
                                        <div class="doc-icon">📍</div>
                                        <div>Address Proof</div>
                                    </a>
                                <?php endif; ?>

                                <?php if ($vendor['bank_account_photo']): ?>
                                    <a href="serve_kyc_image.php?uid=<?php echo $vendor['firebase_uid']; ?>&file=<?php echo basename($vendor['bank_account_photo']); ?>" target="_blank" class="doc-link">
                                        <div class="doc-icon">🏦</div>
                                        <div>Bank Account</div>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="actions-section">
                            <?php if ($vendor['kyc_status'] === 'submitted'): ?>
                                <button class="btn btn-approve" onclick="updateStatus('<?php echo $vendor['firebase_uid']; ?>', 'verified')">✓ Approve</button>
                                <button class="btn btn-reject" onclick="rejectKYC('<?php echo $vendor['firebase_uid']; ?>')">✗ Reject</button>
                            <?php elseif ($vendor['kyc_status'] === 'verified'): ?>
                                <button class="btn btn-revoke" onclick="revokeKYC('<?php echo $vendor['firebase_uid']; ?>')">⚠️ Revoke</button>
                            <?php elseif ($vendor['kyc_status'] === 'rejected'): ?>
                                <button class="btn btn-approve" onclick="updateStatus('<?php echo $vendor['firebase_uid']; ?>', 'verified')">✓ Re-Approve</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const API_BASE = 'https://crm.abra-logistic.com/api1/vendor';

        function updateStatus(firebaseUid, newStatus) {
            if (!confirm(`Are you sure you want to ${newStatus === 'verified' ? 'APPROVE' : 'UPDATE'} this KYC?`)) {
                return;
            }

            fetch(`${API_BASE}/update_kyc_status.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    firebase_uid: firebaseUid,
                    kyc_status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(`KYC ${newStatus === 'verified' ? 'approved' : 'updated'} successfully!`);
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update KYC'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating KYC status');
            });
        }

        function rejectKYC(firebaseUid) {
            const reason = prompt('Enter rejection reason:', 'Documents not clear or incomplete');
            if (!reason || !reason.trim()) {
                return;
            }

            if (!confirm(`Are you sure you want to REJECT this KYC?\n\nReason: ${reason}`)) {
                return;
            }

            fetch(`${API_BASE}/update_kyc_status.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    firebase_uid: firebaseUid,
                    kyc_status: 'rejected',
                    rejection_reason: reason.trim()
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('KYC rejected successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to reject KYC'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error rejecting KYC');
            });
        }

        function revokeKYC(firebaseUid) {
            const reason = prompt('Enter revoke reason:', 'KYC verification revoked by admin');
            if (!reason || !reason.trim()) {
                return;
            }

            if (!confirm(`Are you sure you want to REVOKE this verified KYC?\n\nReason: ${reason}`)) {
                return;
            }

            fetch(`${API_BASE}/update_kyc_status.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    firebase_uid: firebaseUid,
                    kyc_status: 'rejected',
                    rejection_reason: reason.trim()
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('KYC revoked successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to revoke KYC'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error revoking KYC');
            });
        }
    </script>
</body>
</html>
