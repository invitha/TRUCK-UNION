-- Create driver_kyc table for driver verification
CREATE TABLE IF NOT EXISTS `driver_kyc` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firebase_uid VARCHAR(255) NOT NULL UNIQUE,
    driver_name VARCHAR(255) NOT NULL,
    driver_mobile VARCHAR(20) NOT NULL,
    driver_email VARCHAR(255) NULL,
    
    -- Aadhar Card
    aadhar_number VARCHAR(12) NOT NULL,
    aadhar_front_image VARCHAR(255) NULL,
    aadhar_back_image VARCHAR(255) NULL,
    
    -- PAN Card
    pan_number VARCHAR(10) NOT NULL,
    pan_image VARCHAR(255) NULL,
    
    -- Driving License
    license_number VARCHAR(50) NOT NULL,
    license_front_image VARCHAR(255) NULL,
    license_back_image VARCHAR(255) NULL,
    
    -- Address
    address TEXT NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    pincode VARCHAR(10) NULL,
    
    -- KYC Status
    kyc_status ENUM('pending', 'submitted', 'under_review', 'verified', 'rejected', 'revoked') DEFAULT 'pending',
    rejection_reason TEXT NULL,
    admin_notes TEXT NULL,
    
    -- Vehicle Details
    vehicle_number VARCHAR(50) NULL,
    rc_front_image VARCHAR(255) NULL,
    rc_back_image VARCHAR(255) NULL,
    insurance_image VARCHAR(255) NULL,
    fitness_image VARCHAR(255) NULL,
    puc_image VARCHAR(255) NULL,
    vehicle_photo_front VARCHAR(255) NULL,
    vehicle_photo_side VARCHAR(255) NULL,
    
    -- Timestamps
    submitted_at TIMESTAMP NULL,
    verified_at TIMESTAMP NULL,
    rejected_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_firebase_uid (firebase_uid),
    INDEX idx_kyc_status (kyc_status),
    INDEX idx_driver_mobile (driver_mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
