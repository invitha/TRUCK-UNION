# 🚚 DRIVER DASHBOARD - COMPLETE IMPLEMENTATION PLAN

## Overview
Create a driver dashboard similar to Abra Logistics app with:
- Pickup Orders (assigned to driver)
- Delivery Orders (in progress)
- Order details and status updates
- Navigation and tracking

---

## 📊 DATABASE REQUIREMENTS

### 1. Orders Table
```sql
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) UNIQUE NOT NULL,
  `customer_firebase_uid` VARCHAR(255) NOT NULL,
  `customer_name` VARCHAR(255) NOT NULL,
  `customer_phone` VARCHAR(20) NOT NULL,
  
  -- Pickup Details
  `pickup_address` TEXT NOT NULL,
  `pickup_lat` DECIMAL(10, 8),
  `pickup_lng` DECIMAL(11, 8),
  `pickup_date` DATE NOT NULL,
  `pickup_time` TIME NOT NULL,
  
  -- Delivery Details
  `delivery_address` TEXT NOT NULL,
  `delivery_lat` DECIMAL(10, 8),
  `delivery_lng` DECIMAL(11, 8),
  `delivery_date` DATE,
  `delivery_time` TIME,
  
  -- Vehicle & Driver Assignment
  `vehicle_id` INT,
  `driver_name` VARCHAR(255),
  `assigned_at` TIMESTAMP NULL,
  
  -- Order Details
  `vehicle_type` VARCHAR(100) NOT NULL,
  `goods_type` VARCHAR(255),
  `weight` VARCHAR(50),
  `distance_km` DECIMAL(10, 2),
  `estimated_price` DECIMAL(10, 2),
  `final_price` DECIMAL(10, 2),
  
  -- Status
  `status` ENUM('pending', 'assigned', 'picked_up', 'in_transit', 'delivered', 'cancelled') DEFAULT 'pending',
  `payment_status` ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
  
  -- Timestamps
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `picked_up_at` TIMESTAMP NULL,
  `delivered_at` TIMESTAMP NULL,
  
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE SET NULL,
  INDEX `idx_vehicle_id` (`vehicle_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_customer` (`customer_firebase_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 🎯 DRIVER DASHBOARD FEATURES

### 1. **Pickup Orders Tab**
- Shows orders assigned to driver with status 'assigned'
- Driver can see:
  - Order number
  - Customer name & phone
  - Pickup address & time
  - Delivery address
  - Goods type & weight
  - Distance
- Actions:
  - View details
  - Start pickup (changes status to 'picked_up')
  - Call customer
  - Navigate to pickup location

### 2. **Delivery Orders Tab**
- Shows orders with status 'picked_up' or 'in_transit'
- Driver can see:
  - Same info as pickup
  - Pickup completion time
  - Estimated delivery time
- Actions:
  - View details
  - Mark as delivered
  - Call customer
  - Navigate to delivery location

### 3. **Order Details Screen**
- Full order information
- Customer details
- Pickup & delivery locations
- Timeline of status changes
- Actions based on current status

---

## 📱 FLUTTER SCREENS TO CREATE

### 1. Driver Dashboard (Main Screen)
```
lib/screens/driver/driver_dashboard.dart
```
- Tab view with Pickup Orders and Delivery Orders
- Pull to refresh
- Order count badges

### 2. Order Detail Screen
```
lib/screens/driver/order_detail_screen.dart
```
- Complete order information
- Status update buttons
- Call & navigate buttons

### 3. Driver Profile
```
lib/screens/driver/driver_profile_screen.dart
```
- Driver info
- Vehicle info
- Logout option

---

## 🔌 API ENDPOINTS TO CREATE

### 1. Get Driver Orders
```
server_php/api1_vendor/get_driver_orders.php
```
- Input: vehicle_id, status (optional)
- Output: List of orders

### 2. Update Order Status
```
server_php/api1_vendor/update_order_status.php
```
- Input: order_id, status, vehicle_id
- Output: Success/error

### 3. Get Order Details
```
server_php/api1_vendor/get_order_details.php
```
- Input: order_id, vehicle_id
- Output: Complete order info

---

## 🚀 IMPLEMENTATION STEPS

1. ✅ Create orders table in database
2. ✅ Create API endpoints
3. ✅ Create driver dashboard screen
4. ✅ Create order detail screen
5. ✅ Update driver login to navigate to dashboard
6. ✅ Add order status update functionality
7. ✅ Test complete flow

---

## 💡 NOTES

- This matches Abra Logistics app structure
- Orders are assigned by admin/vendor
- Driver only sees orders assigned to their vehicle
- Status flow: pending → assigned → picked_up → in_transit → delivered
- Real-time updates can be added later with Firebase

---

Would you like me to proceed with implementing this complete system?
