-- Database enhancements for Swachh Waste Management System
-- Run this to add credit tracking, penalties, and extended reporting features

USE swachh;

-- Add credit tracking table
CREATE TABLE IF NOT EXISTS credit_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_type ENUM('earned', 'lost', 'penalty', 'bonus') NOT NULL,
    amount INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    report_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (report_id) REFERENCES segregation_reports(id) ON DELETE SET NULL
);

-- Add penalties table
CREATE TABLE IF NOT EXISTS penalties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('pending', 'paid', 'waived') DEFAULT 'pending',
    due_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add society management table
CREATE TABLE IF NOT EXISTS society_workers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    worker_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (society_id) REFERENCES societies(id) ON DELETE CASCADE,
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (society_id, worker_id)
);

-- Add waste categories table
CREATE TABLE IF NOT EXISTS waste_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color_code VARCHAR(7) DEFAULT '#666666',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default waste categories
INSERT IGNORE INTO waste_categories (name, description, color_code) VALUES
('Wet Waste', 'Organic waste like food scraps, vegetable peels', '#27ae60'),
('Dry Waste', 'Paper, plastic, metal, glass', '#3498db'),
('Hazardous Waste', 'Batteries, electronics, chemicals', '#e74c3c'),
('Sanitary Waste', 'Medical waste, sanitary products', '#9b59b6');

-- Add detailed segregation reports table
CREATE TABLE IF NOT EXISTS detailed_segregation_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    category_id INT NOT NULL,
    segregation_quality ENUM('excellent', 'good', 'fair', 'poor') NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES segregation_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES waste_categories(id) ON DELETE CASCADE
);

-- Add apartment assignments table for better management
CREATE TABLE IF NOT EXISTS apartment_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    apartment_id INT NOT NULL,
    resident_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Update existing apartments table to use assignments
UPDATE apartments SET resident_id = NULL WHERE resident_id IS NOT NULL;

-- Add indexes for better performance
CREATE INDEX idx_credit_transactions_user_date ON credit_transactions(user_id, created_at);
CREATE INDEX idx_penalties_user_status ON penalties(user_id, status);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);
CREATE INDEX idx_segregation_reports_date ON segregation_reports(report_date);
CREATE INDEX idx_segregation_reports_apartment_date ON segregation_reports(apartment_id, report_date);

-- Add triggers for automatic credit management
DELIMITER //

CREATE TRIGGER update_credits_after_report
AFTER INSERT ON segregation_reports
FOR EACH ROW
BEGIN
    DECLARE resident_user_id INT;
    DECLARE credit_change INT;
    DECLARE transaction_reason VARCHAR(255);
    
    -- Get resident ID for the apartment
    SELECT resident_id INTO resident_user_id 
    FROM apartments 
    WHERE id = NEW.apartment_id;
    
    -- Only process if there's a resident assigned
    IF resident_user_id IS NOT NULL THEN
        -- Calculate credit change based on status
        CASE NEW.status
            WHEN 'segregated' THEN SET credit_change = 5, transaction_reason = 'Excellent waste segregation';
            WHEN 'partial' THEN SET credit_change = 2, transaction_reason = 'Partial waste segregation';
            WHEN 'no_waste' THEN SET credit_change = 3, transaction_reason = 'No waste generated';
            WHEN 'not' THEN SET credit_change = -3, transaction_reason = 'Poor waste segregation';
            ELSE SET credit_change = 0, transaction_reason = 'Unknown status';
        END CASE;
        
        -- Insert credit transaction
        INSERT INTO credit_transactions (user_id, transaction_type, amount, reason, report_id)
        VALUES (resident_user_id, 
                CASE WHEN credit_change > 0 THEN 'earned' ELSE 'lost' END,
                ABS(credit_change), 
                transaction_reason, 
                NEW.id);
        
        -- Update user credits
        UPDATE users 
        SET credits = GREATEST(0, LEAST(100, credits + credit_change))
        WHERE id = resident_user_id;
    END IF;
END//

CREATE TRIGGER check_penalty_threshold
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    DECLARE penalty_count INT;
    
    -- Check if credits dropped below 20 and create penalty if needed
    IF NEW.credits < 20 AND OLD.credits >= 20 AND NEW.role = 'resident' THEN
        -- Check if there's already a pending penalty for this month
        SELECT COUNT(*) INTO penalty_count
        FROM penalties 
        WHERE user_id = NEW.id 
        AND status = 'pending' 
        AND MONTH(due_date) = MONTH(CURDATE()) 
        AND YEAR(due_date) = YEAR(CURDATE());
        
        -- Create penalty if none exists for this month
        IF penalty_count = 0 THEN
            INSERT INTO penalties (user_id, amount, reason, due_date)
            VALUES (NEW.id, 500.00, 'Credits below threshold', DATE_ADD(CURDATE(), INTERVAL 30 DAY));
            
            -- Add notification
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (NEW.id, 'Penalty Applied', 
                   CONCAT('Your credits have fallen below 20. A penalty of ₹500 has been applied to your municipal account. Due date: ', DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 30 DAY), '%M %d, %Y')), 
                   'error');
        END IF;
    END IF;
END//

DELIMITER ;

-- Add some sample data for testing
INSERT IGNORE INTO society_workers (society_id, worker_id) VALUES (1, 1);

-- Update existing users with better credit starting values
UPDATE users SET credits = 100 WHERE role = 'resident' AND credits = 0;

-- Add sample notifications
INSERT IGNORE INTO notifications (user_id, title, message, type) VALUES
(2, 'Welcome to Swachh!', 'Thank you for joining our waste segregation monitoring system. Start segregating your waste to earn credits!', 'info'),
(2, 'First Report', 'Your first waste segregation report has been recorded. Keep up the good work!', 'success');

COMMIT;
