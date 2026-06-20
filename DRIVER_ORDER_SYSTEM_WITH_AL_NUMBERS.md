# Driver Order Management System with AL Numbers

## Overview
This system allows drivers to manage orders using AL (Airway Bill/Lorry Receipt) numbers with categorization by load type (Part Load, FTL, Express).

## Features

### 1. **AL Number System**
- Every order gets a unique AL number (e.g., AL00000123)
- Drivers select orders by AL number instead of order ID
- Easy to communicate and track

### 2. **Load Type Categories**
- **Part Load**: Shared truck space, multiple shipments
- **FTL (Full Truck Load)**: Entire truck for one shipment
- **Express**: Priority/urgent deliveries

### 3. **Status Management**
Drivers can update orders through these statuses:
- **AWB Created / Assigned** → Ready for pickup
- **Picked Up** → Driver has collected the shipment
- **In Transit** → Shipment is on the way
- **Delivered** → Shipment delivered to receiver

### 4. **Driver Dashboard Features**
- View all assigned orders
- Filter by load type (Part Load, FTL, Express)
- Filter by status (Pending Pickup, In Transit)
- Real-time order count summary
- Update status with one tap
- View complete order details

## Database Setup

### Step 1: Run SQL Migration
Upload and execute this file on your server:
```
vendor_app/server_php/enhance_orders_for_drivers.sql
```

This adds:
- `al_number` - Unique AL number for each order
- `load_category` - Part Load, FTL, or Express
- `driver_notes` - Notes from driver
- `pickup_proof_image` - Photo proof of pickup
- `delivery_proof_image` - Photo proof of delivery
- Location tracking fields

### Step 2: Upload API Files
Upload these files to your server at `api1/vendor/`:

1. **get_driver_orders_enhanced.php**
   - Returns orders grouped by load type
   - Includes summary counts
   - Filters out delivered/cancelled orders

2. **update_order_status_enhanced.php**
   - Updates status using AL number
   - Records timestamps automatically
   - Supports driver notes and location

## API Endpoints

### Get Driver Orders (Enhanced)
```
GET /api1/vendor/get_driver_orders_enhanced.php?vehicle_id=123
```

**Response:**
```json
{
  "status": "success",
  "orders": [
    {
      "id": 1,
      "al_number": "AL00000001",
      "tracking_number": "TRK123456",
      "load_category": "part_load",
      "status": "Assigned",
      "sender_name": "John Doe",
      "sender_address": "123 Main St",
      "receiver_name": "Jane Smith",
      "receiver_address": "456 Oak Ave",
      "shipping_amount": "500.00",
      ...
    }
  ],
  "summary": {
    "total": 10,
    "part_load": 6,
    "ftl": 3,
    "express": 1,
    "pending_pickup": 4,
    "in_transit": 6
  }
}
```

### Update Order Status (Enhanced)
```
POST /api1/vendor/update_order_status_enhanced.php
```

**Request Body:**
```json
{
  "al_number": "AL00000001",
  "vehicle_id": 123,
  "status": "Picked Up",
  "driver_notes": "Package collected at 10:30 AM",
  "latitude": 28.6139,
  "longitude": 77.2090
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Order status updated successfully",
  "order": { ... }
}
```

## Flutter Implementation

### Using the New Driver Orders Screen

Replace the old driver dashboard with the new enhanced version:

```dart
import 'package:vendor_app/screens/driver/driver_orders_screen.dart';

// Navigate to driver orders
Navigator.push(
  context,
  MaterialPageRoute(
    builder: (_) => DriverOrdersScreen(
      driverData: driverData,
    ),
  ),
);
```

### API Service Methods

```dart
// Get enhanced orders
final result = await ApiService.getDriverOrdersEnhanced(
  vehicleId: vehicleId,
);

// Update status with AL number
final result = await ApiService.updateOrderStatusEnhanced(
  alNumber: 'AL00000001',
  vehicleId: vehicleId,
  status: 'Picked Up',
  driverNotes: 'Package in good condition',
  latitude: 28.6139,
  longitude: 77.2090,
);
```

## Admin Module Integration

### Assigning AL Numbers
When creating/assigning orders in your admin module:

```php
// Auto-generate AL number
$al_number = 'AL' . str_pad($order_id, 8, '0', STR_PAD_LEFT);

// Or use custom format
$al_number = 'AL' . date('Ymd') . str_pad($order_id, 4, '0', STR_PAD_LEFT);
// Example: AL202605230001

// Set load category
$load_category = 'part_load'; // or 'ftl' or 'express'

// Insert/Update order
$sql = "INSERT INTO customer_orders 
        (al_number, load_category, vehicle_id, status, ...)
        VALUES (?, ?, ?, 'Assigned', ...)";
```

### Viewing Driver Updates
Track driver progress in your admin panel:

```php
// Get orders by AL number
$sql = "SELECT * FROM customer_orders WHERE al_number = ?";

// Get all orders for a vehicle
$sql = "SELECT * FROM customer_orders 
        WHERE vehicle_id = ? 
        AND status NOT IN ('Delivered', 'Cancelled')
        ORDER BY load_category, status";

// Check status history
$sql = "SELECT 
          al_number,
          status,
          assigned_at,
          picked_up_at,
          delivered_at,
          driver_notes
        FROM customer_orders 
        WHERE vehicle_id = ?";
```

## Workflow Example

### 1. Admin Assigns Order
```
Admin creates order → Assigns AL number (AL00000123)
→ Sets load type (Part Load)
→ Assigns to vehicle/driver
→ Status: "Assigned"
```

### 2. Driver Receives Order
```
Driver opens app → Sees order AL00000123
→ Filters by "Part Load"
→ Views order details
```

### 3. Driver Updates Status
```
Driver picks up → Taps "Mark as Picked Up"
→ Status: "Picked Up" (timestamp recorded)
→ Admin sees update in real-time
```

### 4. Driver Delivers
```
Driver delivers → Taps "Mark as Delivered"
→ Status: "Delivered" (timestamp recorded)
→ Order removed from driver's active list
```

## Benefits

### For Drivers
✅ Easy to identify orders by AL number
✅ Clear categorization (Part Load vs FTL)
✅ Simple status updates
✅ See only active orders
✅ Filter by load type and status

### For Admin
✅ Track orders by AL number
✅ Real-time status updates from drivers
✅ Automatic timestamp recording
✅ Driver notes for each update
✅ Location tracking capability

### For Business
✅ Better order organization
✅ Improved communication
✅ Faster status updates
✅ Reduced errors
✅ Better customer service

## Testing

### Test the System

1. **Create test orders** in your admin panel with AL numbers
2. **Assign to a driver** (vehicle_id)
3. **Login as driver** in the app
4. **View orders** - should see AL numbers and load types
5. **Update status** - should update in database
6. **Check admin panel** - should see updated status

### Sample Test Data
```sql
-- Insert test order
INSERT INTO customer_orders (
  al_number, 
  load_category, 
  tracking_number,
  customer_name,
  sender_name,
  sender_mobile,
  sender_address,
  receiver_name,
  receiver_mobile,
  receiver_address,
  shipping_amount,
  vehicle_id,
  status
) VALUES (
  'AL00000TEST',
  'part_load',
  'TRK123TEST',
  'Test Customer',
  'Test Sender',
  '9999999999',
  'Test Pickup Address',
  'Test Receiver',
  '8888888888',
  'Test Delivery Address',
  500.00,
  1, -- Your test vehicle_id
  'Assigned'
);
```

## Troubleshooting

### Orders not showing in driver app?
- Check vehicle_id is correct
- Verify order status is not "Delivered" or "Cancelled"
- Check API endpoint URL is correct

### Status update not working?
- Verify AL number exists in database
- Check vehicle_id matches the order
- Ensure API file has correct database credentials

### AL numbers not generated?
- Run the SQL migration file
- Check if al_number column exists
- Verify UPDATE query ran successfully

## Next Steps

1. ✅ Run database migration
2. ✅ Upload API files
3. ✅ Test with sample orders
4. ✅ Update admin module to assign AL numbers
5. ✅ Train drivers on new system
6. 🔄 Add photo proof feature (optional)
7. 🔄 Add signature capture (optional)
8. 🔄 Add real-time notifications (optional)

## Files to Upload

```
📁 Server Files (upload to your hosting):
├── server_php/enhance_orders_for_drivers.sql
├── server_php/api1_vendor/get_driver_orders_enhanced.php
└── server_php/api1_vendor/update_order_status_enhanced.php

📁 Flutter Files (already in your project):
├── lib/screens/driver/driver_orders_screen.dart
└── lib/services/api_service.dart (updated)
```

## Support

If you need help:
1. Check database connection in db_config.php
2. Verify API endpoints are accessible
3. Check Flutter console for error messages
4. Test API endpoints directly using Postman/browser

---

**System Ready!** 🚀
Your drivers can now manage orders efficiently using AL numbers with load type categorization.
