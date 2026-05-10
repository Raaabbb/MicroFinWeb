-- Database Migration Script: Normalize Interest Types

-- Disable safe updates to allow updating without primary keys
SET SQL_SAFE_UPDATES = 0;

-- 1. Update existing legacy values to the new standards
UPDATE loan_products 
SET interest_type = 'Flat' 
WHERE interest_type = 'Fixed';

UPDATE loan_products 
SET interest_type = 'Declining Balance' 
WHERE interest_type = 'Diminishing';

-- 2. Modify the ENUM column to only allow the new standardized values
ALTER TABLE loan_products 
MODIFY COLUMN interest_type ENUM('Flat', 'Declining Balance') 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci 
DEFAULT 'Declining Balance';

-- Re-enable safe updates
SET SQL_SAFE_UPDATES = 1;

-- Migration complete
