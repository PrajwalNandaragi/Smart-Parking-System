-- Migration script to add vehicle_type column and make vehicle_number unique globally
-- Run this SQL file in phpMyAdmin if you have an existing database

USE parking_system;

-- Step 1: Add vehicle_type column (if it doesn't exist)
-- Note: If column already exists, you'll get an error - that's okay, just continue
ALTER TABLE vehicles 
ADD COLUMN vehicle_type ENUM('Bike', 'Car', 'Truck', 'SUV', 'Other') DEFAULT 'Car' AFTER vehicle_number;

-- Step 2: Update existing records to have default vehicle type (if any are NULL)
UPDATE vehicles SET vehicle_type = 'Car' WHERE vehicle_type IS NULL;

-- Step 3: Check for duplicate vehicle numbers first
-- Run this query to see if you have duplicates:
-- SELECT vehicle_number, COUNT(*) as count FROM vehicles GROUP BY vehicle_number HAVING count > 1;

-- Step 4: If you have duplicates, you need to resolve them first before adding unique constraint
-- Option A: Delete duplicates (keep only one):
-- DELETE v1 FROM vehicles v1 INNER JOIN vehicles v2 WHERE v1.vehicle_id > v2.vehicle_id AND v1.vehicle_number = v2.vehicle_number;

-- Option B: Or manually update duplicate vehicle numbers to make them unique

-- Step 5: Add unique constraint (only after resolving duplicates)
ALTER TABLE vehicles 
ADD UNIQUE KEY unique_vehicle_number (vehicle_number);
