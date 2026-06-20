<?php
// Pickup Assignment Module - Assign AWB Created orders to drivers
require_once 'server_php/db_config.php';

$con = new mysqli($host, $username, $password, $dbname);
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}
$con->set_charset('utf8mb4');

// Get all vehicles with drivers
$vehicles_query = "SELECT id, vehicle_number, vehicle_type, driver_name, driver_mobile FROM vehicles WHERE status = 'active' ORDER BY vehicle_number";
$vehicles_result = mysqli_query($con, $vehicles_query);
$vehicles = [];
while ($row = mysqli_fetch_assoc($vehicles_result)) {
    $vehicles[] = $row;
}

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_order'])) {
    $order_id = intval($_POST['order_id']);
    $vehicle_id = intval($_POST['vehicle_id']);
    
    // Get vehicle details
    $vehicle_query = "SELECT driver_name FROM vehicles WHERE id = ?";
    $stmt = mysqli_prepare($con, $vehicle_query);
    mysqli_stmt_bind_param($stmt, 'i', $vehicle_id);
    mysqli_stmt_execute($stmt);
    $vehicle_result = mysqli_stmt_get_result($stmt);
    $vehicle = mysqli_fetch_assoc($vehicle_result);
    
    // Update order
    $update_query = "UPDATE customer_orders SET 
                     vehicle_id = ?, 
                     driver_name = ?,
                     status = 'Assigned',
                     assigned_at = NOW(),
                     updated_at = NOW()
                     WHERE id = ?";
    $update_stmt = mysqli_prepare($con, $update_query);
    mysqli_stmt_bind_param($update_stmt, 'isi', $vehicle_id, $vehicle['driver_name'], $order_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        $success_message = "Order assigned successfully!";
    } else {
        $error_message = "Failed to assign order: " . mysqli_error($con);
    }
}

// Get Part Load orders (AWB Created only - not assigned yet)
$part_load_query = "SELECT * FROM customer_orders 
                    WHERE status = 'AWB Created' 
                    AND load_category = 'part_load'
                    AND (vehicle_id IS NULL OR vehicle_id = 0)
                    ORDER BY book_date DESC";
$part_load_result = mysqli_query($con, $part_load_query);

// Get Full Load orders (AWB Created only - not assigned yet)
$full_load_query = "SELECT * FROM customer_orders 
                    WHERE status = 'AWB Created' 
                    AND load_category = 'ftl'
                    AND (vehicle_id IS NULL OR vehicle_id = 0)
                    ORDER BY book_date DESC";
$full_load_result = mysqli_query($con, $full_load_query);

// Count orders
$part_load_count = mysqli_num_rows($part_load_result);
$full_load_count = mysqli_num_rows($full_load_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickup Assignment - Abra Logistics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #2563eb;
            --primary-purple: #7c3aed;
            --accent-orange: #f97316;
            --accent-green: #10b981;
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
        
        .header-card h1 {
            color: var(--primary-blue);
            font-weight: 700;
            margin: 0;
        }
        
        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            flex: 1;
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card.part-load {
            border-left: 5px solid var(--primary-blue);
        }
        
        .stat-card.full-load {
            border-left: 5px solid var(--primary-purple);
        }
        
        .stat-number {
            font-size: 48px;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stat-card.part-load .stat-number {
            color: var(--primary-blue);
        }
        
        .stat-card.full-load .stat-number {
            color: var(--primary-purple);
        }
        
        .tabs-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .nav-tabs {
            border-bottom: 3px solid #e5e7eb;
            padding: 0 20px;
            background: #f9fafb;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6b7280;
            font-weight: 600;
            padding: 15px 30px;
            margin-right: 10px;
            border-radius: 0;
            transition: all 0.3s;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary-blue);
            background: rgba(37, 99, 235, 0.05);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-blue);
            background: white;
            border-bottom: 3px solid var(--primary-blue);
            margin-bottom: -3px;
        }
        
        .tab-content {
            padding: 30px;
        }
        
        .order-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .order-card:hover {
            border-color: var(--primary-blue);
            box-shadow: 0 5px 20px rgba(37, 99, 235, 0.1);
            transform: translateY(-2px);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .al-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-blue);
        }
        
        .badge-part-load {
            background: var(--primary-blue);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .badge-full-load {
            background: var(--primary-purple);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            align-items: start;
            gap: 10px;
        }
        
        .detail-item i {
            color: var(--primary-blue);
            margin-top: 3px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #6b7280;
            font-size: 13px;
        }
        
        .detail-value {
            color: #111827;
            font-weight: 500;
        }
        
        .route-section {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .route-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .route-item:last-child {
            margin-bottom: 0;
        }
        
        .route-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        .route-icon.pickup {
            background: #d1fae5;
            color: var(--accent-green);
        }
        
        .route-icon.delivery {
            background: #fee2e2;
            color: #ef4444;
        }
        
        .assign-section {
            display: flex;
            gap: 15px;
            align-items: center;
            padding-top: 15px;
            border-top: 2px solid #f3f4f6;
        }
        
        .assign-section select {
            flex: 1;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .assign-section select:focus {
            border-color: var(--primary-blue);
            outline: none;
        }
        
        .btn-assign {
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-purple));
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-assign:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 80px;
            color: #d1d5db;
            margin-bottom: 20px;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="container-fluid">
            <!-- Header -->
            <div class="header-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="fas fa-clipboard-list"></i> Pickup Assignment Module</h1>
                        <p class="text-muted mb-0">Assign AWB Created orders to drivers for pickup</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card part-load">
                    <i class="fas fa-boxes fa-2x" style="color: var(--primary-blue);"></i>
                    <div class="stat-number"><?php echo $part_load_count; ?></div>
                    <div class="stat-label">Part Load Orders</div>
                    <small class="text-muted">Ready for Pickup</small>
                </div>
                <div class="stat-card full-load">
                    <i class="fas fa-truck-loading fa-2x" style="color: var(--primary-purple);"></i>
                    <div class="stat-number"><?php echo $full_load_count; ?></div>
                    <div class="stat-label">Full Load Orders</div>
                    <small class="text-muted">Ready for Pickup</small>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs-container">
                <ul class="nav nav-tabs" id="orderTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="part-load-tab" data-bs-toggle="tab" 
                                data-bs-target="#part-load" type="button" role="tab">
                            <i class="fas fa-boxes"></i> Part Load (<?php echo $part_load_count; ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="full-load-tab" data-bs-toggle="tab" 
                                data-bs-target="#full-load" type="button" role="tab">
                            <i class="fas fa-truck-loading"></i> Full Load (<?php echo $full_load_count; ?>)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="orderTabsContent">
                    <!-- Part Load Tab -->
                    <div class="tab-pane fade show active" id="part-load" role="tabpanel">
                        <?php if ($part_load_count > 0): ?>
                            <?php mysqli_data_seek($part_load_result, 0); ?>
                            <?php while ($order = mysqli_fetch_assoc($part_load_result)): ?>
                                <div class="order-card">
                                    <div class="order-header">
                                        <div>
                                            <div class="al-number">
                                                <i class="fas fa-receipt"></i> <?php echo $order['al_number']; ?>
                                            </div>
                                            <small class="text-muted">Tracking: <?php echo $order['tracking_number']; ?></small>
                                        </div>
                                        <span class="badge-part-load">
                                            <i class="fas fa-boxes"></i> Part Load
                                        </span>
                                    </div>

                                    <div class="route-section">
                                        <div class="route-item">
                                            <div class="route-icon pickup">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </div>
                                            <div>
                                                <div class="detail-label">PICKUP</div>
                                                <div class="detail-value"><?php echo $order['sender_address']; ?></div>
                                                <small class="text-muted"><?php echo $order['sender_name']; ?> - <?php echo $order['sender_mobile']; ?></small>
                                            </div>
                                        </div>
                                        <div class="route-item">
                                            <div class="route-icon delivery">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </div>
                                            <div>
                                                <div class="detail-label">DELIVERY</div>
                                                <div class="detail-value"><?php echo $order['receiver_address']; ?></div>
                                                <small class="text-muted"><?php echo $order['receiver_name']; ?> - <?php echo $order['receiver_mobile']; ?></small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="order-details">
                                        <div class="detail-item">
                                            <i class="fas fa-user"></i>
                                            <div>
                                                <div class="detail-label">Customer</div>
                                                <div class="detail-value"><?php echo $order['customer_name']; ?></div>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-rupee-sign"></i>
                                            <div>
                                                <div class="detail-label">Amount</div>
                                                <div class="detail-value">₹<?php echo number_format($order['shipping_amount'], 2); ?></div>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-credit-card"></i>
                                            <div>
                                                <div class="detail-label">Payment</div>
                                                <div class="detail-value"><?php echo strtoupper($order['payment_mode']); ?></div>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-calendar"></i>
                                            <div>
                                                <div class="detail-label">Booked On</div>
                                                <div class="detail-value"><?php echo date('d M Y, h:i A', strtotime($order['book_date'])); ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" class="assign-section">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <select name="vehicle_id" required class="form-select">
                                            <option value="">Select Driver/Vehicle</option>
                                            <?php foreach ($vehicles as $vehicle): ?>
                                                <option value="<?php echo $vehicle['id']; ?>">
                                                    <?php echo $vehicle['vehicle_number']; ?> - 
                                                    <?php echo $vehicle['driver_name']; ?> 
                                                    (<?php echo $vehicle['vehicle_type']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign_order" class="btn-assign">
                                            <i class="fas fa-check"></i> Assign for Pickup
                                        </button>
                                    </form>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h3>No Part Load Orders</h3>
                                <p>All part load orders have been assigned</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Full Load Tab -->
                    <div class="tab-pane fade" id="full-load" role="tabpanel">
                        <?php if ($full_load_count > 0): ?>
                            <?php mysqli_data_seek($full_load_result, 0); ?>
                            <?php while ($order = mysqli_fetch_assoc($full_load_result)): ?>
                                <div class="order-card">
                                    <div class="order-header">
                                        <div>
                                            <div class="al-number">
                                                <i class="fas fa-receipt"></i> <?php echo $order['al_number']; ?>
                                            </div>
                                            <small class="text-muted">Tracking: <?php echo $order['tracking_number']; ?></small>
                                        </div>
                                        <span class="badge-full-load">
                                            <i class="fas fa-truck-loading"></i> Full Load (FTL)
                                        </span>
                                    </div>

                                    <div class="route-section">
                                        <div class="route-item">
                                            <div class="route-icon pickup">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </div>
                                            <div>
                                                <div class="detail-label">PICKUP</div>
                                                <div class="detail-value"><?php echo $order['sender_address']; ?></div>
                                                <small class="text-muted"><?php echo $order['sender_name']; ?> - <?php echo $order['sender_mobile']; ?></small>
                                            </div>
                                        </div>
                                        <div class="route-item">
                                            <div class="route-icon delivery">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </div>
                                            <div>
                                                <div class="detail-label">DELIVERY</div>
                                                <div class="detail-value"><?php echo $order['receiver_address']; ?></div>
                                                <small class="text-muted"><?php echo $order['receiver_name']; ?> - <?php echo $order['receiver_mobile']; ?></small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="order-details">
                                        <div class="detail-item">
                                            <i class="fas fa-user"></i>
                                            <div>
                                                <div class="detail-label">Customer</div>
                                                <div class="detail-value"><?php echo $order['customer_name']; ?></div>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-rupee-sign"></i>
                                            <div>
                                                <div class="detail-label">Amount</div>
                                                <div class="detail-value">₹<?php echo number_format($order['shipping_amount'], 2); ?></div>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-credit-card"></i>
                                            <div>
                                                <div class="detail-label">Payment</div>
                                                <div class="detail-value"><?php echo strtoupper($order['payment_mode']); ?></div>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-calendar"></i>
                                            <div>
                                                <div class="detail-label">Booked On</div>
                                                <div class="detail-value"><?php echo date('d M Y, h:i A', strtotime($order['book_date'])); ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" class="assign-section">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <select name="vehicle_id" required class="form-select">
                                            <option value="">Select Driver/Vehicle</option>
                                            <?php foreach ($vehicles as $vehicle): ?>
                                                <option value="<?php echo $vehicle['id']; ?>">
                                                    <?php echo $vehicle['vehicle_number']; ?> - 
                                                    <?php echo $vehicle['driver_name']; ?> 
                                                    (<?php echo $vehicle['vehicle_type']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign_order" class="btn-assign">
                                            <i class="fas fa-check"></i> Assign for Pickup
                                        </button>
                                    </form>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h3>No Full Load Orders</h3>
                                <p>All full load orders have been assigned</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
<?php mysqli_close($con); ?>
