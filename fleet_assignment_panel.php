<?php
header('Content-Type: text/html; charset=utf-8');

// Database connection
$servername = "localhost";
$username = "royaldxd_user";
$password = "meg_layout312";
$dbname = "royaldxd_abra_crm";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include notification helper — same pattern used for KYC notifications
require_once(__DIR__ . '/server_php/api1_vendor/create_notification.php');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_assignment') {
        $al_number = $_POST['al_number'];
        $vehicle_id = $_POST['vehicle_id'];
        $pickup_location = $_POST['pickup_location'];
        $delivery_location = $_POST['delivery_location'];
        $expected_completion_date = $_POST['expected_completion_date'];
        $notes = $_POST['notes'] ?? '';
        $assigned_by = $_POST['assigned_by'] ?? 'Internal Team';

        // Get vehicle details
        $vehicle_query = "SELECT vendor_firebase_uid, vehicle_number, vehicle_name, driver_name
                         FROM vehicles WHERE id = ?";
        $stmt = $conn->prepare($vehicle_query);
        $stmt->bind_param("i", $vehicle_id);
        $stmt->execute();
        $vehicle_result = $stmt->get_result();
        $vehicle = $vehicle_result->fetch_assoc();

        if ($vehicle) {
            $insert_query = "INSERT INTO fleet_assignments
                           (al_number, vehicle_id, vendor_firebase_uid, vehicle_number, vehicle_name,
                            driver_name, assigned_by, pickup_location, delivery_location,
                            expected_completion_date, status, notes)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)";

            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("sissssssss",
                $al_number, $vehicle_id, $vehicle['vendor_firebase_uid'],
                $vehicle['vehicle_number'], $vehicle['vehicle_name'], $vehicle['driver_name'],
                $assigned_by, $pickup_location, $delivery_location,
                $expected_completion_date, $notes
            );

            if ($stmt->execute()) {
                $success_message = "Fleet assigned successfully to AL Number: $al_number";

                // ── Notifications (same pattern as KYC notifications) ─────
                $vendor_uid  = $vehicle['vendor_firebase_uid'] ?? '';
                $driver_uid  = 'driver_' . intval($vehicle_id);
                $v_num       = $vehicle['vehicle_number'] ?? '';
                $driver_name = $vehicle['driver_name']    ?? 'your driver';

                // 1. Vendor notification
                if (!empty($vendor_uid)) {
                    createNotification(
                        $conn,
                        $vendor_uid,
                        'order_update',
                        "🚛 New Order Assigned — AL: $al_number",
                        "Order $al_number has been assigned to your vehicle $v_num (Driver: $driver_name)."
                    );
                }

                // 2. Driver notification
                createNotification(
                    $conn,
                    $driver_uid,
                    'order_update',
                    "📦 New Order Assigned — AL: $al_number",
                    "You have a new order (AL: $al_number) for vehicle $v_num. Check your orders tab."
                );
                // ──────────────────────────────────────────────────────────

            } else {
                $error_message = "Error: " . $stmt->error;
            }
        } else {
            $error_message = "Vehicle not found";
        }
    }
}

// Get all vehicles with vendor info
$vehicles_query = "SELECT v.*, 
                   CONCAT(v.vehicle_name, ' (', v.vehicle_number, ') - Driver: ', v.driver_name) as display_name,
                   CASE 
                       WHEN v.is_online = 1 AND TIMESTAMPDIFF(MINUTE, v.last_location_update, NOW()) <= 5 THEN 'Online'
                       ELSE 'Offline'
                   END as online_status
                   FROM vehicles v 
                   ORDER BY v.vendor_name, v.vehicle_name";
$vehicles_result = $conn->query($vehicles_query);

// Get recent assignments
$assignments_query = "SELECT fa.*, 
                      DATE_FORMAT(fa.assignment_date, '%d %b %Y %h:%i %p') as formatted_date,
                      DATE_FORMAT(fa.expected_completion_date, '%d %b %Y') as formatted_expected_date
                      FROM fleet_assignments fa 
                      ORDER BY fa.created_at DESC 
                      LIMIT 20";
$assignments_result = $conn->query($assignments_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Assignment Panel - TRUCK UNION</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #0D2E6E 0%, #1E40AF 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #0D2E6E;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
        }
        
        .header p {
            color: #64748b;
            font-size: 14px;
        }
        
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            color: #0D2E6E;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            color: #334155;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0D2E6E;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn {
            background: linear-gradient(135deg, #0D2E6E 0%, #1E40AF 100%);
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #ef4444;
        }
        
        .assignments-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .assignment-item {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
        }
        
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .al-number {
            font-size: 16px;
            font-weight: 800;
            color: #0D2E6E;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-completed {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .assignment-details {
            font-size: 13px;
            color: #64748b;
            line-height: 1.6;
        }
        
        .assignment-details strong {
            color: #334155;
        }
        
        .vehicle-option {
            padding: 8px;
        }
        
        .online-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        
        .online {
            background: #10b981;
        }
        
        .offline {
            background: #ef4444;
        }
        
        @media (max-width: 1024px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚛 Fleet Assignment Panel</h1>
            <p>Assign vehicles to AL numbers for shipment tracking</p>
        </div>
        
        <div class="grid">
            <!-- Assignment Form -->
            <div class="card">
                <h2>📋 Create New Assignment</h2>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create_assignment">
                    
                    <div class="form-group">
                        <label>AL Number *</label>
                        <input type="text" name="al_number" required placeholder="e.g., AL123456">
                    </div>
                    
                    <div class="form-group">
                        <label>Select Vehicle *</label>
                        <select name="vehicle_id" required>
                            <option value="">-- Choose Vehicle --</option>
                            <?php 
                            $vehicles_result->data_seek(0);
                            while ($vehicle = $vehicles_result->fetch_assoc()): 
                                $is_online = $vehicle['online_status'] === 'Online';
                            ?>
                                <option value="<?php echo $vehicle['id']; ?>">
                                    <?php echo $is_online ? '🟢' : '🔴'; ?> 
                                    <?php echo htmlspecialchars($vehicle['display_name']); ?> 
                                    - <?php echo htmlspecialchars($vehicle['vendor_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Pickup Location *</label>
                        <input type="text" name="pickup_location" required placeholder="Enter pickup address">
                    </div>
                    
                    <div class="form-group">
                        <label>Delivery Location *</label>
                        <input type="text" name="delivery_location" required placeholder="Enter delivery address">
                    </div>
                    
                    <div class="form-group">
                        <label>Expected Completion Date *</label>
                        <input type="date" name="expected_completion_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Assigned By</label>
                        <input type="text" name="assigned_by" placeholder="Your name (optional)">
                    </div>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" placeholder="Additional instructions or notes"></textarea>
                    </div>
                    
                    <button type="submit" class="btn">✅ Assign Fleet</button>
                </form>
            </div>
            
            <!-- Recent Assignments -->
            <div class="card">
                <h2>📦 Recent Assignments</h2>
                
                <div class="assignments-list">
                    <?php if ($assignments_result->num_rows > 0): ?>
                        <?php while ($assignment = $assignments_result->fetch_assoc()): ?>
                            <div class="assignment-item">
                                <div class="assignment-header">
                                    <span class="al-number">AL: <?php echo htmlspecialchars($assignment['al_number']); ?></span>
                                    <span class="status-badge status-<?php echo $assignment['status']; ?>">
                                        <?php echo $assignment['status']; ?>
                                    </span>
                                </div>
                                <div class="assignment-details">
                                    <strong>Vehicle:</strong> <?php echo htmlspecialchars($assignment['vehicle_name']); ?> 
                                    (<?php echo htmlspecialchars($assignment['vehicle_number']); ?>)<br>
                                    <strong>Driver:</strong> <?php echo htmlspecialchars($assignment['driver_name']); ?><br>
                                    <strong>Pickup:</strong> <?php echo htmlspecialchars($assignment['pickup_location']); ?><br>
                                    <strong>Delivery:</strong> <?php echo htmlspecialchars($assignment['delivery_location']); ?><br>
                                    <strong>Expected:</strong> <?php echo $assignment['formatted_expected_date']; ?><br>
                                    <strong>Assigned:</strong> <?php echo $assignment['formatted_date']; ?> 
                                    by <?php echo htmlspecialchars($assignment['assigned_by']); ?>
                                    <?php if ($assignment['notes']): ?>
                                        <br><strong>Notes:</strong> <?php echo htmlspecialchars($assignment['notes']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #94a3b8; padding: 40px;">
                            No assignments yet. Create your first assignment above.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
