<?php
/**
 * Vendor Details Admin Panel
 * Shows all vendor information including personal details and KYC data
 * Separate views for Individual and Business accounts
 */

// Database connection
$host = 'localhost';
$dbname = 'royaldxd_abra_crm';
$username = 'royaldxd_user';
$password = 'meg_layout312';

try {
    $con = new mysqli($host, $username, $password, $dbname);
    if ($con->connect_error) {
        die("Connection failed: " . $con->connect_error);
    }
    $con->set_charset('utf8mb4');
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Get filter type (individual or business)
$filter = isset($_GET['type']) ? $_GET['type'] : 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Details - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 16px;
        }
        
        .tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .tab {
            background: white;
            padding: 15px 30px;
            border-radius: 12px;
            text-decoration: none;
            color: #667eea;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .tab:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .vendor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
        }
        
        .vendor-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .vendor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .vendor-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .vendor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        
        .vendor-name {
            flex: 1;
        }
        
        .vendor-name h3 {
            color: #333;
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .account-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-individual {
            background: #e0f2fe;
            color: #0369a1;
        }
        
        .badge-business {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .status-verified {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-submitted {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .detail-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
            width: 140px;
            flex-shrink: 0;
        }
        
        .detail-value {
            color: #333;
            flex: 1;
            word-break: break-word;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .no-data h2 {
            color: #667eea;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .no-data p {
            color: #666;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Vendor Details</h1>
            <p>View all vendor information including personal details and KYC status</p>
        </div>
        
        <?php
        // Get statistics
        $total_query = "SELECT COUNT(*) as total FROM vendor_kyc";
        $individual_query = "SELECT COUNT(*) as count FROM vendor_kyc WHERE account_type='individual'";
        $business_query = "SELECT COUNT(*) as count FROM vendor_kyc WHERE account_type='business'";
        
        $total_result = $con->query($total_query);
        $individual_result = $con->query($individual_query);
        $business_result = $con->query($business_query);
        
        $total_count = $total_result->fetch_assoc()['total'];
        $individual_count = $individual_result->fetch_assoc()['count'];
        $business_count = $business_result->fetch_assoc()['count'];
        ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_count; ?></div>
                <div class="stat-label">Total Vendors</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $individual_count; ?></div>
                <div class="stat-label">Individual Accounts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $business_count; ?></div>
                <div class="stat-label">Business Accounts</div>
            </div>
        </div>
        
        <div class="tabs">
            <a href="?type=all" class="tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                All Vendors (<?php echo $total_count; ?>)
            </a>
            <a href="?type=individual" class="tab <?php echo $filter === 'individual' ? 'active' : ''; ?>">
                Individual (<?php echo $individual_count; ?>)
            </a>
            <a href="?type=business" class="tab <?php echo $filter === 'business' ? 'active' : ''; ?>">
                Business (<?php echo $business_count; ?>)
            </a>
        </div>
        
        <?php
        // Build query based on filter
        $query = "SELECT * FROM vendor_kyc";
        if ($filter === 'individual') {
            $query .= " WHERE account_type='individual'";
        } elseif ($filter === 'business') {
            $query .= " WHERE account_type='business'";
        }
        $query .= " ORDER BY created_at DESC";
        
        $result = $con->query($query);
        
        if ($result && $result->num_rows > 0) {
            echo '<div class="vendor-grid">';
            
            while ($vendor = $result->fetch_assoc()) {
                $initial = strtoupper(substr($vendor['vendor_name'] ?? 'V', 0, 1));
                $account_type = $vendor['account_type'] ?? 'individual';
                $status = $vendor['kyc_status'] ?? 'submitted';
                
                // Status badge class
                $status_class = 'status-submitted';
                if ($status === 'verified') $status_class = 'status-verified';
                elseif ($status === 'rejected') $status_class = 'status-rejected';
                
                echo '<div class="vendor-card">';
                echo '<div class="vendor-header">';
                echo '<div class="vendor-avatar">' . $initial . '</div>';
                echo '<div class="vendor-name">';
                echo '<h3>' . htmlspecialchars($vendor['vendor_name'] ?? 'N/A') . '</h3>';
                echo '<span class="account-badge badge-' . $account_type . '">';
                echo $account_type === 'business' ? '🏢 Business' : '👤 Individual';
                echo '</span> ';
                echo '<span class="status-badge ' . $status_class . '">' . $status . '</span>';
                echo '</div>';
                echo '</div>';
                
                // Personal Details
                echo '<div class="detail-row">';
                echo '<div class="detail-label">📧 Email:</div>';
                echo '<div class="detail-value">' . htmlspecialchars($vendor['vendor_email'] ?? 'N/A') . '</div>';
                echo '</div>';
                
                echo '<div class="detail-row">';
                echo '<div class="detail-label">📱 Phone:</div>';
                echo '<div class="detail-value">' . htmlspecialchars($vendor['vendor_phone'] ?? 'N/A') . '</div>';
                echo '</div>';
                
                echo '<div class="detail-row">';
                echo '<div class="detail-label">📍 Address:</div>';
                echo '<div class="detail-value">' . htmlspecialchars($vendor['vendor_address'] ?? 'N/A') . '</div>';
                echo '</div>';
                
                // Business-specific fields
                if ($account_type === 'business') {
                    echo '<div class="detail-row">';
                    echo '<div class="detail-label">🏢 Company:</div>';
                    echo '<div class="detail-value">' . htmlspecialchars($vendor['company_name'] ?? 'N/A') . '</div>';
                    echo '</div>';
                    
                    echo '<div class="detail-row">';
                    echo '<div class="detail-label">📄 GST Number:</div>';
                    echo '<div class="detail-value">' . htmlspecialchars($vendor['gst_number'] ?? 'N/A') . '</div>';
                    echo '</div>';
                }
                
                echo '<div class="detail-row">';
                echo '<div class="detail-label">🆔 PAN Number:</div>';
                echo '<div class="detail-value">' . htmlspecialchars($vendor['pan_number'] ?? 'N/A') . '</div>';
                echo '</div>';
                
                echo '<div class="detail-row">';
                echo '<div class="detail-label">🆔 Aadhar Number:</div>';
                echo '<div class="detail-value">' . htmlspecialchars($vendor['aadhar_number'] ?? 'N/A') . '</div>';
                echo '</div>';
                
                echo '<div class="detail-row">';
                echo '<div class="detail-label">📅 Submitted:</div>';
                echo '<div class="detail-value">' . date('d M Y, h:i A', strtotime($vendor['created_at'])) . '</div>';
                echo '</div>';
                
                if ($status === 'verified' && !empty($vendor['verified_at'])) {
                    echo '<div class="detail-row">';
                    echo '<div class="detail-label">✅ Verified:</div>';
                    echo '<div class="detail-value">' . date('d M Y, h:i A', strtotime($vendor['verified_at'])) . '</div>';
                    echo '</div>';
                }
                
                if ($status === 'rejected' && !empty($vendor['rejection_reason'])) {
                    echo '<div class="detail-row">';
                    echo '<div class="detail-label">❌ Reason:</div>';
                    echo '<div class="detail-value">' . htmlspecialchars($vendor['rejection_reason']) . '</div>';
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
            echo '</div>';
        } else {
            echo '<div class="no-data">';
            echo '<h2>No Vendors Found</h2>';
            echo '<p>There are no ' . ($filter === 'all' ? '' : $filter) . ' vendors in the system yet.</p>';
            echo '</div>';
        }
        
        $con->close();
        ?>
    </div>
</body>
</html>
