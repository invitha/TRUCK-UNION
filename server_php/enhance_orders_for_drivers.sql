-- Add AL number and load type fields to customer_orders table
ALTER TABLE customer_orders 
ADD COLUMN IF NOT EXISTS al_number VARCHAR(50) NULL COMMENT 'Airway Bill / Lorry Receipt Number',
ADD COLUMN IF NOT EXISTS load_category ENUM('part_load', 'ftl', 'express') DEFAULT 'part_load' COMMENT 'Load category: Part Load, FTL (Full Truck Load), Express',
ADD COLUMN IF NOT EXISTS driver_notes TEXT NULL COMMENT 'Driver notes for this order',
ADD COLUMN IF NOT EXISTS pickup_pod_image VARCHAR(255) NULL COMMENT 'Pickup POD (Proof of Delivery) photo path',
ADD COLUMN IF NOT EXISTS delivery_pod_image VARCHAR(255) NULL COMMENT 'Delivery POD (Proof of Delivery) photo path',
ADD COLUMN IF NOT EXISTS current_location_lat DECIMAL(10, 8) NULL COMMENT 'Current shipment latitude',
ADD COLUMN IF NOT EXISTS current_location_lng DECIMAL(11, 8) NULL COMMENT 'Current shipment longitude',
ADD COLUMN IF NOT EXISTS location_updated_at TIMESTAMP NULL COMMENT 'Last location update time',
ADD COLUMN IF NOT EXISTS in_scan_at TIMESTAMP NULL COMMENT 'In Scan timestamp',
ADD COLUMN IF NOT EXISTS in_transit_at TIMESTAMP NULL COMMENT 'In Transit timestamp',
ADD COLUMN IF NOT EXISTS in_warehouse_at TIMESTAMP NULL COMMENT 'In Warehouse timestamp',
ADD COLUMN IF NOT EXISTS out_for_delivery_at TIMESTAMP NULL COMMENT 'Out for Delivery timestamp';

-- Add index for AL number for faster searches
ALTER TABLE customer_orders ADD INDEX idx_al_number (al_number);
ALTER TABLE customer_orders ADD INDEX idx_load_category (load_category);

-- Update existing orders to have AL numbers (if they don't have one)
UPDATE customer_orders 
SET al_number = CONCAT('AL', LPAD(id, 8, '0'))
WHERE al_number IS NULL OR al_number = '';
