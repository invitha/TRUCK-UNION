-- POD (Proof of Delivery) System Database Schema - Based on Abra Logistics
-- This creates the exact same POD structure as used in abra_logistics

-- Add POD columns to courier table (matching abra_logistics structure)
ALTER TABLE courier 
ADD COLUMN pickup_pod_image VARCHAR(255) DEFAULT NULL AFTER status,
ADD COLUMN pickup_pod_timestamp DATETIME DEFAULT NULL AFTER pickup_pod_image,
ADD COLUMN pickup_latitude DECIMAL(10, 8) DEFAULT NULL AFTER pickup_pod_timestamp,
ADD COLUMN pickup_longitude DECIMAL(11, 8) DEFAULT NULL AFTER pickup_latitude,
ADD COLUMN pickup_driver_id VARCHAR(100) DEFAULT NULL AFTER pickup_longitude,

ADD COLUMN delivery_pod_image VARCHAR(255) DEFAULT NULL AFTER pickup_driver_id,
ADD COLUMN delivery_pod_timestamp DATETIME DEFAULT NULL AFTER delivery_pod_image,
ADD COLUMN delivery_latitude DECIMAL(10, 8) DEFAULT NULL AFTER delivery_pod_timestamp,
ADD COLUMN delivery_longitude DECIMAL(11, 8) DEFAULT NULL AFTER delivery_latitude,
ADD COLUMN delivery_driver_id VARCHAR(100) DEFAULT NULL AFTER delivery_longitude,

ADD COLUMN receiver_name_pod VARCHAR(100) DEFAULT NULL AFTER delivery_driver_id,
ADD COLUMN receiver_phone_pod VARCHAR(20) DEFAULT NULL AFTER receiver_name_pod,
ADD COLUMN scanned_barcode VARCHAR(255) DEFAULT NULL AFTER receiver_phone_pod;

-- Create milestones table (exactly like abra_logistics tsp_milestones)
CREATE TABLE IF NOT EXISTS tsp_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id VARCHAR(100) DEFAULT NULL,
    tracking VARCHAR(100) NOT NULL,
    trStatus VARCHAR(50) DEFAULT NULL,
    trLatitude DECIMAL(10, 8) DEFAULT NULL,
    trLongitude DECIMAL(11, 8) DEFAULT NULL,
    trReason TEXT DEFAULT NULL,
    trPODimage LONGTEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tracking (tracking),
    INDEX idx_delivery_id (delivery_id),
    INDEX idx_status (trStatus)
);

-- Status flow with POD (same as abra_logistics):
-- 1. AWB Created → Admin assigns → Pickup Assigned (pending vendor acceptance)
-- 2. Vendor accepts → Active → Driver sees order
-- 3. Driver goes to pickup → Takes pickup photo → "Picked Up"
-- 4. Driver in transit → "In Transit"  
-- 5. Driver at delivery → Takes delivery photo → "Delivered" + POD data
-- 6. Assignment marked as "Completed"

-- POD images stored in: server_php/uploads/pickup-photos/ (same structure as abra_logistics)
-- Base64 images also stored in tsp_milestones.trPODimage