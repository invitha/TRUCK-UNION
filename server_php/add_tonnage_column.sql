-- Add tonnage column to vehicles table
ALTER TABLE `vehicles` 
ADD COLUMN `vehicle_tonnage` VARCHAR(50) NULL AFTER `vehicle_size_feet`;
