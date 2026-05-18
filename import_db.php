<?php
// import_db.php - Run this ONCE to import your database
// ⚠️ DELETE THIS FILE AFTER IMPORTING! ⚠️

require_once 'includes/db.php';

echo "<h1>📦 Database Import Tool</h1>";
echo "<pre>";

// Check if tables already exist
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'");
$tableCount = $stmt->fetchColumn();

if ($tableCount > 0) {
    echo "⚠️ Tables already exist! Database may already be imported.\n";
    echo "Current tables: " . $tableCount . "\n";
    echo "\n";
    echo "If you want to re-import, run this SQL first:\n";
    echo "DROP SCHEMA public CASCADE; CREATE SCHEMA public;\n";
    exit;
}

echo "🟡 Starting import...\n\n";

// ============================================
// POSTGRESQL SCHEMA - Copy from below
// ============================================

$sql = "
-- ============================================
-- PostgreSQL Version of transphilhub Database
-- Compatible with Render PostgreSQL
-- ============================================

-- Drop existing tables (if they exist)
DROP TABLE IF EXISTS trusted_emails CASCADE;
DROP TABLE IF EXISTS reviews CASCADE;
DROP TABLE IF EXISTS properties CASCADE;
DROP TABLE IF EXISTS password_resets CASCADE;
DROP TABLE IF EXISTS notification_settings CASCADE;
DROP TABLE IF EXISTS notifications CASCADE;
DROP TABLE IF EXISTS mfa_codes CASCADE;
DROP TABLE IF EXISTS login_attempts CASCADE;
DROP TABLE IF EXISTS lead_history CASCADE;
DROP TABLE IF EXISTS leads CASCADE;
DROP TABLE IF EXISTS favorites CASCADE;
DROP TABLE IF EXISTS appointments CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- ============================================
-- TABLE: users
-- ============================================
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin', 'agent', 'client')),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    phone VARCHAR(20),
    address TEXT,
    avg_rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INTEGER DEFAULT 0
);

-- ============================================
-- TABLE: properties
-- ============================================
CREATE TABLE properties (
    id SERIAL PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    type VARCHAR(20) NOT NULL CHECK (type IN ('sale', 'rent', 'project')),
    price DECIMAL(15,2),
    location VARCHAR(200),
    status VARCHAR(20) DEFAULT 'available' CHECK (status IN ('available', 'sold', 'rented')),
    is_featured INTEGER DEFAULT 0,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id INTEGER,
    agent_id INTEGER,
    bedrooms INTEGER DEFAULT 0,
    bathrooms INTEGER DEFAULT 0,
    area INTEGER DEFAULT 0
);

-- ============================================
-- TABLE: appointments
-- ============================================
CREATE TABLE appointments (
    id SERIAL PRIMARY KEY,
    client_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    agent_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    property_id INTEGER REFERENCES properties(id) ON DELETE SET NULL,
    scheduled_date TIMESTAMP,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'cancelled'))
);

-- ============================================
-- TABLE: favorites
-- ============================================
CREATE TABLE favorites (
    id SERIAL PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    property_id INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(client_id, property_id)
);

-- ============================================
-- TABLE: leads
-- ============================================
CREATE TABLE leads (
    id SERIAL PRIMARY KEY,
    client_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    agent_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    property_id INTEGER REFERENCES properties(id) ON DELETE SET NULL,
    stage VARCHAR(20) DEFAULT 'new' CHECK (stage IN ('new', 'contacted', 'viewing', 'negotiation', 'closed')),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    follow_up_date DATE,
    last_contacted TIMESTAMP,
    priority VARCHAR(10) DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high')),
    last_contact TIMESTAMP,
    next_followup DATE
);

-- ============================================
-- TABLE: lead_history
-- ============================================
CREATE TABLE lead_history (
    id SERIAL PRIMARY KEY,
    lead_id INTEGER REFERENCES leads(id) ON DELETE CASCADE,
    action VARCHAR(100),
    old_stage VARCHAR(50),
    new_stage VARCHAR(50),
    notes TEXT,
    performed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE: login_attempts
-- ============================================
CREATE TABLE login_attempts (
    id SERIAL PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    success INTEGER DEFAULT 0,
    ip_address VARCHAR(45),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_email_time ON login_attempts(email, attempted_at);

-- ============================================
-- TABLE: mfa_codes
-- ============================================
CREATE TABLE mfa_codes (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    otp VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE: notifications
-- ============================================
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    message TEXT,
    is_read INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    link VARCHAR(255)
);

-- ============================================
-- TABLE: notification_settings
-- ============================================
CREATE TABLE notification_settings (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    email_notifications INTEGER DEFAULT 1,
    inquiry_alerts INTEGER DEFAULT 1,
    lead_alerts INTEGER DEFAULT 1,
    appointment_alerts INTEGER DEFAULT 1
);

-- ============================================
-- TABLE: password_resets
-- ============================================
CREATE TABLE password_resets (
    id SERIAL PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_password_resets_email ON password_resets(email);

-- ============================================
-- TABLE: reviews
-- ============================================
CREATE TABLE reviews (
    id SERIAL PRIMARY KEY,
    client_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    agent_id INTEGER,
    property_id INTEGER,
    rating INTEGER CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    is_approved INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_featured INTEGER DEFAULT 0,
    approved_at TIMESTAMP
);

-- ============================================
-- TABLE: trusted_emails
-- ============================================
CREATE TABLE trusted_emails (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, email)
);

-- ============================================
-- INSERT DATA
-- ============================================

-- Users table data
INSERT INTO users (id, full_name, email, password, role, status, created_at, phone, address, avg_rating, total_reviews) VALUES
(1, 'Admin', 'admin.transphilhub@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', '2026-05-12 13:03:06', NULL, NULL, 0.00, 0),
(2, 'Test Client', 'client@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2026-05-12 15:23:24', NULL, NULL, 0.00, 0),
(3, 'LYKA F VILLARISCO', 'iamfaye011@gmail.com', '$2y$10$T3Tie9CiFJUhZluVnHUynuc7BNch4fyEYa57cbArznp442amWfkdC', 'client', 'active', '2026-05-12 16:57:22', '09662834639', 'bonifacio st., bangkal', 0.00, 0),
(4, 'faye faye', 'iamfaye@gmail.com', '$2y$10$0BzZgc9foIm5ed8op1nIcuJ/ZzYVziiSMDZyri2Moxb4dP3A1Q7G2', 'agent', 'active', '2026-05-12 19:17:21', '09662834639', 'bonifacio st., bangkal', 0.00, 0),
(5, 'Lyka Peralta', 'villariscolykafaye@gmail.com', '$2y$10$pLwe0hi3EBkU2CjVoBJjlev..8/bKSeMs4ybW76Kv4wNJhAikemQK', 'client', 'active', '2026-05-18 03:35:25', '09662834639', '2653 ttttt', 0.00, 0),
(6, 'kiki', 'lvillarisco.a12345296@umak.edu.ph', '$2y$10$jA85MINvbjd1H8zNDsSPOOkNxaveZfMs9ErJm7dSIf3akLPjon0K2', 'client', 'active', '2026-05-18 04:18:35', '09662834639', 'hhhhj', 0.00, 0),
(7, 'Admin User', 'admin@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', '2024-01-15 01:00:00', '09171234567', 'Trans-Phil Head Office, Makati City', 0.00, 0),
(8, 'Maria Santos', 'maria.santos@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', '2024-02-20 06:30:00', '09172345678', 'BGC, Taguig City', 0.00, 0),
(9, 'John Reyes', 'deleted_9_john.reyes@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'inactive', '2024-03-10 03:15:00', '09173456789', 'Ortigas, Pasig City', 0.00, 0),
(10, 'Anna Rivera', 'anna.rivera@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', '2024-01-20 02:00:00', '09174567890', 'Makati City', 0.00, 0),
(11, 'Michael Tan', 'michael.tan@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', '2024-01-25 05:45:00', '09175678901', 'BGC, Taguig', 0.00, 0),
(12, 'Sarah Lopez', 'sarah.lopez@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', '2024-02-05 01:30:00', '09176789012', 'Quezon City', 0.00, 0),
(13, 'David Garcia', 'david.garcia@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', '2024-02-12 08:20:00', '09177890123', 'Pasig City', 0.00, 0),
(14, 'Cristina Mendoza', 'cristina.mendoza@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', '2024-02-18 03:00:00', '09178901234', 'Alabang, Muntinlupa', 0.00, 0),
(15, 'Patrick Cruz', 'patrick.cruz@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', '2024-03-01 06:30:00', '09179012345', 'Las Piñas', 0.00, 0),
(16, 'Jenny Castillo', 'jenny.castillo@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', '2024-03-08 01:45:00', '09180123456', 'Parañaque', 0.00, 0),
(17, 'Ramon Bautista', 'ramon.bautista@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', '2024-03-15 05:00:00', '09181234567', 'Mandaluyong', 0.00, 0),
(18, 'Kim Domingo', 'kim.domingo@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', '2024-03-22 02:30:00', '09182345678', 'San Juan', 0.00, 0),
(19, 'Vincent Lim', 'vincent.lim@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', '2024-04-01 07:45:00', '09183456789', 'Manila', 0.00, 0),
(20, 'Grace Villanueva', 'grace.villanueva@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', '2024-04-10 04:15:00', '09184567890', 'Caloocan', 0.00, 0),
(21, 'Mark Santiago', 'mark.santiago@transphil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', '2024-04-18 00:00:00', '09185678901', 'Valenzuela', 0.00, 0),
(22, 'Juan Dela Cruz', 'juan.delacruz@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-01-10 02:00:00', '09123456789', 'Makati City', 0.00, 0),
(23, 'Maria Santos', 'maria.santos@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-01-18 06:30:00', '09134567890', 'BGC, Taguig', 0.00, 0),
(24, 'Jose Rizal', 'jose.rizal@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-01-25 01:15:00', '09145678901', 'Quezon City', 0.00, 0),
(25, 'Andres Bonifacio', 'andres.bonifacio@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-02-05 08:45:00', '09156789012', 'Pasig City', 0.00, 0),
(26, 'Emilio Aguinaldo', 'emilio.aguinaldo@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-02-14 03:20:00', '09167890123', 'Parañaque', 0.00, 0),
(27, 'Gabriela Silang', 'gabriela.silang@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-02-22 05:50:00', '09178901234', 'Las Piñas', 0.00, 0),
(28, 'Melchora Aquino', 'melchora.aquino@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-03-03 00:30:00', '09189012345', 'Mandaluyong', 0.00, 0),
(29, 'Lapu-Lapu', 'lapulapu@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-03-12 07:10:00', '09190123456', 'Cebu City', 0.00, 0),
(30, 'Francisco Balagtas', 'francisco.balagtas@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-03-18 02:45:00', '09201234567', 'Marikina', 0.00, 0),
(31, 'Noli Me Tangere', 'noli.metangere@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-03-25 06:00:00', '09212345678', 'Pasay', 0.00, 0),
(32, 'El Filibusterismo', 'el.filibusterismo@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-04-02 01:20:00', '09223456789', 'Caloocan', 0.00, 0),
(33, 'Gregorio Del Pilar', 'gregorio.delpilar@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-04-09 08:30:00', '09234567890', 'Malabon', 0.00, 0),
(34, 'Teresa Magbanua', 'teresa.magbanua@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-04-16 03:55:00', '09245678901', 'Navotas', 0.00, 0),
(35, 'Antonio Luna', 'antonio.luna@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-04-22 05:40:00', '09256789012', 'Muntinlupa', 0.00, 0),
(36, 'Josefa Llanes Escoda', 'josefa.escoda@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2024-04-28 00:15:00', '09267890123', 'Taguig', 0.00, 0),
(37, 'filomena', 'maeow000@gmail.com', '$2y$10$caSfZ/JiqzKzq3VSm6hVcu5r/Amp9SqUeh02OhGnuGUkr2VG7RZYG', 'agent', 'active', '2026-05-18 19:44:57', '09662834639', 'bonifacio st., bangkal', 0.00, 0);

-- Reset sequence for users (since we inserted with IDs)
SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));

-- Properties table data
INSERT INTO properties (id, title, description, type, price, location, status, is_featured, image, created_at, user_id, agent_id, bedrooms, bathrooms, area) VALUES
(1, 'Modern Townhouse San Antonio', '3-bedroom townhouse with parking, fully furnished', 'sale', 4500000.00, 'Makati City', 'available', 1, 'property1.png', '2026-05-12 15:23:24', NULL, NULL, 0, 0, 0),
(2, 'Luxury Condo BGC', '2-bedroom condo with pool and gym access', 'rent', 18000.00, 'BGC Taguig', 'available', 1, 'property2.png', '2026-05-12 15:23:24', NULL, NULL, 0, 0, 0),
(3, 'Family Home Quezon City', '4-bedroom house with garden and garage', 'sale', 8900000.00, 'Quezon City', 'available', 0, 'property3.png', '2026-05-12 15:23:24', NULL, NULL, 0, 0, 0),
(4, 'Studio Unit Makati', 'Cozy studio unit near CBD', 'rent', 12000.00, 'Makati City', 'available', 0, 'property4.png', '2026-05-12 15:23:24', NULL, NULL, 0, 0, 0),
(5, 'Townhouse Project Pasig', 'Pre-selling townhouse, 3 bedrooms', 'project', 5200000.00, 'Pasig City', 'available', 1, 'property5.png', '2026-05-12 15:23:24', NULL, NULL, 0, 0, 0),
(6, 'Bahay ni Erick', 'mainit', 'rent', 1.00, 'sa impyerno', 'available', 1, '1778677831_2df51b18-9a46-4c66-90a2-0dfa8493db25.jpg', '2026-05-13 13:10:31', NULL, NULL, 11, 1, 55),
(7, 'Modern Townhouse Makati', 'Beautiful 3-bedroom townhouse in the heart of Makati. Modern design, fully furnished, with parking.', 'sale', 4500000.00, 'Makati City', 'available', 1, 'property1.png', '2024-01-05 02:00:00', NULL, NULL, 3, 2, 85),
(8, 'Luxury Condo BGC', 'Premium 2-bedroom condo unit in BGC. Access to pool, gym, and 24/7 security.', 'rent', 25000.00, 'BGC Taguig', 'available', 1, 'property2.png', '2024-01-10 06:30:00', NULL, NULL, 2, 2, 55),
(9, 'Family Home Quezon City', 'Spacious 4-bedroom house with garden and garage. Perfect for growing families.', 'sale', 8900000.00, 'Quezon City', 'available', 1, 'property3.png', '2024-01-15 01:45:00', NULL, NULL, 4, 3, 120),
(10, 'Studio Unit Pasig', 'Cozy studio unit near CBD. Walking distance to restaurants and malls.', 'rent', 15000.00, 'Pasig City', 'available', 0, 'property4.png', '2024-01-20 08:20:00', NULL, NULL, 1, 1, 32),
(11, 'Townhouse Project Pasig', 'Pre-selling townhouse development. Expected completion 2026.', 'project', 5200000.00, 'Pasig City', 'available', 1, 'property5.png', '2024-01-25 03:10:00', NULL, NULL, 3, 2, 95),
(12, 'Beachfront Villa Batangas', 'Luxury beachfront property with private pool. 2-hour drive from Manila.', 'sale', 15000000.00, 'Batangas', 'available', 0, 'property1.png', '2024-02-01 05:00:00', NULL, NULL, 5, 4, 250),
(13, 'Downtown Condo Manila', 'Studio condo near universities and hospitals. Perfect for students.', 'rent', 12000.00, 'Manila', 'available', 0, 'property2.png', '2024-02-05 02:30:00', NULL, NULL, 1, 1, 28),
(14, 'Executive House Alabang', 'Executive 5-bedroom house in exclusive subdivision. Golf course view.', 'sale', 12500000.00, 'Alabang', 'available', 1, 'property3.png', '2024-02-10 07:45:00', NULL, NULL, 5, 5, 180),
(15, 'Commercial Space Ortigas', 'Ground floor commercial space. High foot traffic area.', 'rent', 80000.00, 'Ortigas Pasig', 'available', 0, 'property4.png', '2024-02-15 01:00:00', NULL, NULL, 0, 2, 150),
(16, 'Loft Style Condo BGC', 'Industrial-style loft with high ceilings and city views.', 'sale', 7500000.00, 'BGC Taguig', 'available', 1, 'property5.png', '2024-02-20 06:15:00', NULL, NULL, 2, 2, 78),
(17, 'Affordable House Bulacan', 'Budget-friendly house for first-time home buyers.', 'sale', 2500000.00, 'Bulacan', 'available', 0, 'property1.png', '2024-02-25 03:30:00', NULL, NULL, 2, 1, 60),
(18, 'Condo Near Airport', 'Convenient condo unit near NAIA. Perfect for frequent travelers.', 'rent', 18000.00, 'Parañaque', 'available', 0, 'property2.png', '2024-03-01 08:00:00', NULL, NULL, 2, 1, 45),
(19, 'House and Lot Cavite', 'Affordable house and lot in developing area.', 'sale', 3500000.00, 'Cavite', 'available', 0, 'property3.png', '2024-03-05 02:20:00', NULL, NULL, 3, 2, 75),
(20, 'Penthouse Makati', 'Luxury penthouse with panoramic city views.', 'rent', 120000.00, 'Makati City', 'available', 1, 'property4.png', '2024-03-10 05:45:00', NULL, NULL, 4, 4, 200),
(21, 'Townhouse Antipolo', 'Mountain-view townhouse with cool climate.', 'sale', 4800000.00, 'Antipolo', 'available', 0, 'property5.png', '2024-03-15 01:15:00', NULL, NULL, 3, 2, 90),
(22, 'Farm Lot Laguna', 'Agricultural land perfect for farming or weekend getaway.', 'sale', 2200000.00, 'Laguna', 'available', 0, 'property1.png', '2024-03-20 06:30:00', NULL, NULL, 0, 0, 500),
(23, 'Apartment Unit Quezon City', '2-bedroom apartment near schools and churches.', 'rent', 10000.00, 'Quezon City', 'available', 0, 'property2.png', '2024-03-25 03:00:00', NULL, NULL, 2, 1, 40),
(24, 'Warehouse Valenzuela', 'Spacious warehouse for storage or light manufacturing.', 'rent', 60000.00, 'Valenzuela', 'available', 0, 'property3.png', '2024-04-01 07:30:00', NULL, NULL, 0, 2, 300),
(25, 'Residential Lot Pasay', 'Prime residential lot near reclamation area.', 'sale', 4200000.00, 'Pasay', 'available', 0, 'property4.png', '2024-04-05 02:45:00', NULL, NULL, 0, 0, 120),
(26, 'Duplex Mandaluyong', 'Two-story duplex unit with separate entrance.', 'rent', 22000.00, 'Mandaluyong', 'available', 0, 'property5.png', '2024-04-10 05:15:00', NULL, NULL, 3, 2, 70),
(27, 'Condotel Manila', 'Condotel unit with hotel amenities. Good for Airbnb.', 'sale', 6800000.00, 'Manila', 'available', 0, 'property1.png', '2024-04-15 01:30:00', NULL, NULL, 2, 2, 50),
(28, 'Executive Townhouse', 'High-end townhouse with modern amenities.', 'sale', 9800000.00, 'BGC Taguig', 'available', 1, 'property2.png', '2024-04-18 06:00:00', NULL, NULL, 4, 3, 110),
(29, 'Budget Studio Pasig', 'Affordable studio for young professionals.', 'rent', 8500.00, 'Pasig City', 'available', 0, 'property3.png', '2024-04-20 03:45:00', NULL, NULL, 1, 1, 25),
(30, 'Mountain Resort Rizal', 'Resort property with natural spring and mountain view.', 'sale', 18500000.00, 'Rizal', 'available', 0, 'property4.png', '2024-04-22 08:15:00', NULL, NULL, 4, 3, 400),
(31, 'Office Space Ortigas', 'Fully-furnished office space for startups.', 'rent', 45000.00, 'Ortigas Pasig', 'available', 0, 'property5.png', '2024-04-25 02:00:00', NULL, NULL, 0, 2, 85);

SELECT setval('properties_id_seq', (SELECT MAX(id) FROM properties));

-- Favorites data
INSERT INTO favorites (id, client_id, property_id, created_at) VALUES
(1, 16, 2, '2024-05-01 01:00:00'),
(2, 16, 5, '2024-05-02 02:30:00'),
(3, 17, 3, '2024-05-03 06:15:00'),
(4, 17, 7, '2024-05-04 03:45:00'),
(5, 18, 1, '2024-05-05 01:30:00'),
(6, 18, 4, '2024-05-06 05:20:00'),
(7, 19, 6, '2024-05-07 07:00:00'),
(8, 19, 8, '2024-05-08 02:00:00'),
(9, 20, 2, '2024-05-09 04:30:00'),
(10, 20, 9, '2024-05-10 08:45:00'),
(11, 21, 10, '2024-05-01 00:15:00'),
(12, 21, 11, '2024-04-30 06:00:00'),
(13, 22, 12, '2024-04-29 03:30:00'),
(14, 22, 13, '2024-04-28 01:45:00'),
(15, 23, 14, '2024-04-27 05:15:00'),
(16, 23, 15, '2024-04-26 08:30:00'),
(17, 24, 16, '2024-04-25 02:00:00'),
(18, 24, 17, '2024-04-24 07:45:00'),
(19, 25, 18, '2024-04-23 04:00:00'),
(20, 25, 19, '2024-04-22 01:30:00'),
(21, 26, 20, '2024-04-21 06:15:00'),
(22, 27, 21, '2024-04-20 03:00:00'),
(23, 28, 22, '2024-04-19 08:15:00'),
(24, 29, 23, '2024-04-18 02:30:00'),
(25, 30, 24, '2024-04-17 05:45:00');

SELECT setval('favorites_id_seq', (SELECT MAX(id) FROM favorites));

-- Leads data
INSERT INTO leads (id, client_id, agent_id, property_id, stage, notes, created_at, assigned_by, updated_by, follow_up_date, last_contacted, priority, last_contact, next_followup) VALUES
(1, 1, NULL, 1, 'new', 'Interested in schedule viewing', '2026-05-12 15:23:24', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(2, 3, NULL, 2, 'new', '', '2026-05-12 18:10:34', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(3, 3, 4, 6, 'new', '', '2026-05-13 13:11:43', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(4, 3, 4, 6, 'closed', '\n---\n2026-05-13 22:42 - Agent Update: ', '2026-05-13 14:33:58', NULL, NULL, NULL, NULL, 'high', NULL, NULL),
(5, 1, 1, 1, 'new', 'Interested in schedule viewing', '2024-05-01 01:00:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(6, 2, 2, 2, 'new', 'Request more information about parking', '2024-05-02 02:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(7, 3, 3, 3, 'new', 'Want to know about financing options', '2024-05-03 06:15:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(8, 4, 4, 4, 'new', 'Schedule weekend viewing', '2024-05-04 03:45:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(9, 5, 5, 5, 'new', 'Interested in pre-selling promo', '2024-05-05 01:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(10, 6, 6, 6, 'new', 'Request property brochure', '2024-05-06 05:20:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(11, 7, 7, 7, 'new', 'Can we negotiate the price?', '2024-05-07 07:00:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(12, 8, 8, 8, 'new', 'Schedule viewing this weekend', '2024-05-08 02:00:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(13, 9, 9, 9, 'new', 'Interested in commercial lease terms', '2024-05-09 04:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(14, 10, 10, 10, 'new', 'Request for virtual tour', '2024-05-10 08:45:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(15, 11, 1, 11, 'contacted', 'Agent called, scheduled viewing for next week', '2024-04-25 01:00:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(16, 12, 2, 12, 'contacted', 'Sent property details via email', '2024-04-24 06:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(17, 13, 3, 13, 'contacted', 'Client wants bank financing assistance', '2024-04-23 03:15:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(18, 14, 4, 14, 'contacted', 'Follow-up call done, client considering', '2024-04-22 02:45:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(19, 15, 5, 15, 'contacted', 'Sent contract for review', '2024-04-21 05:00:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(20, 1, 6, 16, 'contacted', 'Client requested additional photos', '2024-04-20 07:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(21, 2, 7, 17, 'contacted', 'Discussed payment terms', '2024-04-19 01:45:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(22, 3, 8, 18, 'contacted', 'Client wants to bring family for viewing', '2024-04-18 06:15:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(23, 4, 9, 19, 'contacted', 'Sent comparable properties for reference', '2024-04-17 03:00:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(24, 5, 10, 20, 'contacted', 'Client requested for discount', '2024-04-16 08:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(25, 6, 1, 1, 'viewing', 'Viewing scheduled for Saturday 10 AM', '2024-04-15 01:00:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(26, 7, 2, 2, 'viewing', 'Client viewing scheduled with agent', '2024-04-14 02:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(27, 8, 3, 3, 'viewing', 'Group viewing with family', '2024-04-13 06:45:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(28, 9, 4, 4, 'viewing', 'Afternoon viewing scheduled', '2024-04-12 05:15:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(29, 10, 5, 5, 'viewing', 'Virtual tour scheduled', '2024-04-11 03:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(30, 11, 6, 6, 'viewing', 'Weekend viewing with parents', '2024-04-10 07:00:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(31, 12, 7, 7, 'viewing', 'Client coming from province', '2024-04-09 01:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(32, 13, 8, 8, 'viewing', 'Second viewing requested', '2024-04-08 06:00:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(33, 14, 9, 9, 'viewing', 'Viewing with architect', '2024-04-07 02:15:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(34, 15, 10, 10, 'viewing', 'Evening viewing after work', '2024-04-06 09:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(35, 1, 1, 11, 'negotiation', 'Price negotiation in progress, client offered 10% lower', '2024-03-30 01:00:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(36, 2, 2, 12, 'negotiation', 'Negotiating payment terms, client wants 6 months to pay', '2024-03-28 06:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(37, 3, 3, 13, 'negotiation', 'Counter-offer submitted, waiting for client response', '2024-03-25 03:45:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(38, 4, 4, 14, 'negotiation', 'Client wants inclusion of furniture in price', '2024-03-22 02:15:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(39, 5, 5, 15, 'negotiation', 'Agent submitted revised proposal', '2024-03-20 05:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(40, 6, 6, 16, 'negotiation', 'Client requested for inspection first', '2024-03-18 07:45:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(41, 7, 7, 17, 'negotiation', 'Negotiating move-in date', '2024-03-15 01:20:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(42, 8, 8, 18, 'negotiation', 'Price negotiation, close to agreement', '2024-03-12 06:00:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(43, 9, 1, 1, 'closed', 'Deal closed! Client bought the property.', '2024-03-01 01:00:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(44, 10, 2, 2, 'closed', 'Sold! Payment completed.', '2024-02-25 06:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(45, 11, 3, 3, 'closed', 'Successfully closed transaction.', '2024-02-20 03:15:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(46, 12, 4, 4, 'closed', 'Client very happy with the property.', '2024-02-15 02:45:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(47, 13, 5, 5, 'closed', 'Deal completed with financing.', '2024-02-10 05:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(48, 14, 6, 6, 'closed', 'Property sold, client referred friend.', '2024-02-05 08:00:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(49, 15, 7, 7, 'closed', 'Closed! Client already moved in.', '2024-01-28 01:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(50, 1, 8, 8, 'closed', 'Successful transaction, good feedback.', '2024-01-20 06:15:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(51, 2, 9, 9, 'closed', 'Deal closed via bank loan.', '2024-01-15 03:00:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(52, 3, 10, 10, 'closed', 'Property sold to happy family.', '2024-01-10 07:30:00', NULL, NULL, NULL, NULL, 'medium', NULL, NULL),
(53, 3, 37, 14, 'closed', '=== INQUIRY DETAILS ===\nInquiry Type: Viewing\nPreferred Date: 2026-05-21\nPreferred Time: 9:00 AM - 10:00 AM\nBudget Range: Below 1M\nPreferred Contact: Email\n\n=== MESSAGE ===\nttttt\n\n=== CONTACT INFO ===\nEmail: iamfaye011@gmail.com\nPhone: 09054421709\n---\n2026-05-19 03:54 - Agent Update: ', '2026-05-18 19:05:10', 1, NULL, NULL, NULL, 'medium', NULL, NULL);

SELECT setval('leads_id_seq', (SELECT MAX(id) FROM leads));

-- Login attempts data
INSERT INTO login_attempts (id, email, success, ip_address, attempted_at) VALUES
(1, 'iamfaye011@gmail.com', 0, '::1', '2026-05-18 03:34:24'),
(2, 'villariscolykafaye@gmail.com', 1, '::1', '2026-05-18 03:35:50'),
(3, 'villariscolykafaye@gmail.com', 1, '::1', '2026-05-18 03:52:12'),
(4, 'villariscolykafaye@gmail.com', 1, '::1', '2026-05-18 03:53:38'),
(5, 'villariscolykafaye@gmail.com', 1, '::1', '2026-05-18 03:56:57'),
(6, 'villariscolykafaye@gmail.com', 1, '::1', '2026-05-18 03:58:05'),
(7, 'villariscolykafaye@gmail.com', 1, '::1', '2026-05-18 04:10:51'),
(8, 'villariscolykafaye@gmail.com', 1, '::1', '2026-05-18 04:16:40'),
(9, 'lvillarisco.a12345296@umak.edu.ph', 1, '::1', '2026-05-18 04:18:51'),
(10, 'lvillarisco.a12345296@umak.edu.ph', 1, '::1', '2026-05-18 04:19:58'),
(11, 'admin@transphil.com', 1, '::1', '2026-05-18 04:32:42'),
(12, 'admin.transphilhub@gmail.com', 0, '::1', '2026-05-18 04:46:16'),
(13, 'admin.transphilhub@gmail.com', 0, '::1', '2026-05-18 04:46:32'),
(14, 'admin.transphilhub@gmail.com', 0, '::1', '2026-05-18 04:48:40'),
(15, 'admin.transphilhub@gmail.com', 1, '::1', '2026-05-18 04:49:18'),
(16, 'admin.transphilhub@gmail.com', 1, '::1', '2026-05-18 11:05:57'),
(17, 'admin.transphilhub@gmail.com', 1, '::1', '2026-05-18 13:42:58'),
(18, 'admin.transphilhub@gmail.com', 1, '::1', '2026-05-18 16:33:19'),
(19, 'iamfaye011@gmail.com', 0, '::1', '2026-05-18 18:44:16'),
(20, 'iamfaye011@gmail.com', 1, '::1', '2026-05-18 18:45:42'),
(21, 'admin.transphilhub@gmail.com', 0, '::1', '2026-05-18 19:06:32'),
(22, 'admin.transphilhub@gmail.com', 0, '::1', '2026-05-18 19:06:46'),
(23, 'admin.transphilhub@gmail.com', 1, '::1', '2026-05-18 19:06:55'),
(24, 'iamfaye@gmail.com', 1, '::1', '2026-05-18 19:24:27'),
(25, 'admin.transphilhub@gmail.com', 1, '::1', '2026-05-18 19:26:11'),
(26, 'maeow000@gmail.com', 1, '::1', '2026-05-18 19:51:56'),
(27, 'admin.transphilhub@gmail.com', 1, '::1', '2026-05-18 19:53:02'),
(28, 'maeow000@gmail.com', 1, '::1', '2026-05-18 19:53:51'),
(29, 'admin.transphilhub@gmail.com', 1, '::1', '2026-05-18 19:58:42'),
(30, 'admin.transphilhub@gmail.com', 1, '::1', '2026-05-18 19:58:48');

SELECT setval('login_attempts_id_seq', (SELECT MAX(id) FROM login_attempts));

-- MFA codes data
INSERT INTO mfa_codes (id, user_id, otp, expires_at, used, created_at) VALUES
(5, 5, '221490', '2026-05-18 06:08:05', 0, '2026-05-18 03:58:05'),
(6, 6, '845561', '2026-05-18 12:28:51', 1, '2026-05-18 04:18:51'),
(8, 1, '747182', '2026-05-18 12:59:18', 1, '2026-05-18 04:49:18'),
(9, 3, '974353', '2026-05-19 02:55:42', 1, '2026-05-18 18:45:42'),
(10, 4, '037553', '2026-05-19 03:34:27', 0, '2026-05-18 19:24:27'),
(11, 37, '709143', '2026-05-19 04:01:56', 1, '2026-05-18 19:51:56');

SELECT setval('mfa_codes_id_seq', (SELECT MAX(id) FROM mfa_codes));

-- Notifications data
INSERT INTO notifications (id, user_id, message, is_read, created_at, link) VALUES
(1, 3, 'Your inquiry status has been updated to: Closed', 0, '2026-05-13 14:42:53', NULL),
(2, 1, 'Welcome to your admin dashboard! This is a sample notification.', 1, '2026-05-18 15:35:37', '#'),
(3, 1, 'New inquiry from LYKA F VILLARISCO for property: Executive House Alabang', 1, '2026-05-18 19:05:14', 'admin/leads.php?view=53'),
(4, 4, 'New inquiry received for Executive House Alabang from LYKA F VILLARISCO', 0, '2026-05-18 19:05:15', 'agent/dashboard.php?lead=53'),
(5, 10, 'New inquiry received for Executive House Alabang from LYKA F VILLARISCO', 0, '2026-05-18 19:05:15', 'agent/dashboard.php?lead=53'),
(6, 11, 'New inquiry received for Executive House Alabang from LYKA F VILLARISCO', 0, '2026-05-18 19:05:15', 'agent/dashboard.php?lead=53'),
(7, 12, 'New inquiry received for Executive House Alabang from LYKA F VILLARISCO', 0, '2026-05-18 19:05:15', 'agent/dashboard.php?lead=53'),
(8, 13, 'New inquiry received for Executive House Alabang from LYKA F VILLARISCO', 0, '2026-05-18 19:05:15', 'agent/dashboard.php?lead=53'),
(9, 14, 'New inquiry received for Executive House Alabang from LYKA F VILLARISCO', 0, '2026-05-18 19:05:15', 'agent/dashboard.php?lead=53'),
(10, 15, 'New inquiry received for Executive House Alabang from LYKA F VILLARISCO', 0, '2026-05-18 19:05:15', 'agent/dashboard.php?lead=53'),
(11, 16, 'New inquiry received for Executive House Alabang from LYKA F VILLARISCO', 0, '2026-05-18 19:05:15', 'agent/dashboard.php?lead=53'),
(12, 17, 'New inquiry received for Executive House Alabang from LYKA F VILLARISCO', 0, '2026-05-18 19:05:15', 'agent/dashboard.php?lead=53'),
(13, 18, 'New inquiry received for Executive House Alabang from LYKA F VILLARISCO', 0, '2026-05-18 19:05:15', 'agent/dashboard.php?lead=53'),
(14, 19, 'New inquiry received for Executive House Alabang from LYKA F VILLARISCO', 0, '2026-05-18 19:05:15', 'agent/dashboard.php?lead=53'),
(15, 20, 'New inquiry received for Executive House Alabang from LYKA F VILLARISCO', 0, '2026-05-18 19:05:15', 'agent/dashboard.php?lead=53'),
(16, 21, 'New inquiry received for Executive House Alabang from LYKA F VILLARISCO', 0, '2026-05-18 19:05:15', 'agent/dashboard.php?lead=53'),
(17, 18, 'New lead assigned: LYKA F VILLARISCO - Executive House Alabang', 0, '2026-05-18 19:22:52', 'agent/dashboard.php?lead=53'),
(18, 4, 'New lead assigned: LYKA F VILLARISCO - Executive House Alabang', 0, '2026-05-18 19:37:18', 'agent/dashboard.php?lead=53'),
(19, 4, 'New lead assigned: LYKA F VILLARISCO - Executive House Alabang', 0, '2026-05-18 19:37:30', 'agent/dashboard.php?lead=53'),
(20, 4, 'New lead assigned: LYKA F VILLARISCO - Executive House Alabang', 0, '2026-05-18 19:42:31', 'agent/dashboard.php?lead=53'),
(21, 4, 'New lead assigned: LYKA F VILLARISCO - Executive House Alabang', 0, '2026-05-18 19:42:37', 'agent/dashboard.php?lead=53'),
(22, 4, 'New lead assigned: LYKA F VILLARISCO - Executive House Alabang', 0, '2026-05-18 19:42:56', 'agent/dashboard.php?lead=53'),
(23, 37, 'New lead assigned: LYKA F VILLARISCO - Executive House Alabang', 0, '2026-05-18 19:53:25', 'agent/dashboard.php?lead=53'),
(24, 3, 'Your inquiry for ''Executive House Alabang'' has been completed successfully.', 0, '2026-05-18 19:54:02', 'client/dashboard.php?lead=53');

SELECT setval('notifications_id_seq', (SELECT MAX(id) FROM notifications));

-- Notification settings data
INSERT INTO notification_settings (id, user_id, email_notifications, inquiry_alerts, lead_alerts, appointment_alerts) VALUES
(1, 1, 1, 1, 1, 1),
(2, 2, 1, 1, 1, 1),
(3, 3, 1, 1, 1, 1),
(4, 4, 1, 1, 1, 1),
(5, 6, 1, 1, 1, 1),
(6, 5, 1, 1, 1, 1);

SELECT setval('notification_settings_id_seq', (SELECT MAX(id) FROM notification_settings));

-- Reviews data
INSERT INTO reviews (id, client_id, agent_id, property_id, rating, comment, is_approved, created_at, is_featured, approved_at) VALUES
(1, 24, 4, 1, 5, 'Excellent service from Agent Rivera! Highly recommended!', 1, '2024-03-05 02:00:00', 0, NULL),
(2, 25, 5, 2, 5, 'Michael Tan was very professional and responsive.', 1, '2024-03-01 06:30:00', 0, NULL),
(3, 26, 6, 3, 4, 'Good experience overall. Sarah was knowledgeable.', 1, '2024-02-25 01:15:00', 0, NULL),
(4, 27, 7, 4, 5, 'David Garcia is the best agent! Very patient.', 1, '2024-02-20 03:45:00', 0, NULL),
(5, 28, 8, 5, 5, 'Cristina went above and beyond to help us.', 1, '2024-02-15 05:30:00', 0, NULL),
(6, 29, 9, 6, 4, 'Patrick was helpful and professional.', 1, '2024-02-10 08:00:00', 0, NULL),
(7, 30, 10, 7, 5, 'Jenny Castillo is amazing! Highly recommend!', 1, '2024-02-05 02:45:00', 1, NULL),
(8, 16, 11, 8, 5, 'Ramon is very knowledgeable about the market.', 1, '2024-01-28 06:15:00', 0, NULL),
(9, 17, 12, 9, 4, 'Good service. Kim was responsive.', 1, '2024-01-22 01:30:00', 0, NULL),
(10, 18, 13, 10, 5, 'Vincent Lim provided excellent assistance.', 1, '2024-01-18 03:20:00', 0, NULL),
(11, 19, 4, 11, 5, 'Anna Rivera is very professional.', 1, '2024-04-01 05:45:00', 0, NULL),
(12, 20, 5, 12, 4, 'Michael helped us find a great rental property.', 1, '2024-03-28 07:30:00', 0, NULL),
(13, 21, 6, 13, 5, 'Sarah Lopez is very friendly and knowledgeable.', 1, '2024-03-22 02:00:00', 0, NULL),
(14, 22, 7, 14, 5, 'David Garcia made buying our first home easy.', 1, '2024-03-15 06:45:00', 0, NULL),
(15, 23, 8, 15, 4, 'Cristina helped us find an investment property.', 1, '2024-03-10 03:15:00', 0, NULL);

SELECT setval('reviews_id_seq', (SELECT MAX(id) FROM reviews));

-- Trusted emails data
INSERT INTO trusted_emails (id, user_id, email, token, expires_at, created_at) VALUES
(1, 5, 'villariscolykafaye@gmail.com', 'd5bfc1327572d2fa63d815797688570b4a51fbfff13e4761977fe25e0a0687c7', '2026-06-17 12:10:51', '2026-05-18 04:10:51'),
(2, 6, 'lvillarisco.a12345296@umak.edu.ph', 'f84f9e4c38b94d16791071d82394789f3da6144ac9e0fc3484bed22eef458e0d', '2026-06-17 12:19:30', '2026-05-18 04:19:30'),
(3, 1, 'admin.transphilhub@gmail.com', 'aa271d955241deed5ba52441d3ccbcf0c65e73c097db36d95239fb79e676bf51', '2026-06-17 12:49:47', '2026-05-18 04:49:47'),
(4, 3, 'iamfaye011@gmail.com', 'c4fdd3e06a3e235ca330fea067764f3dc2a0f7b251649b73000bbe0fb4dab669', '2026-06-18 02:46:06', '2026-05-18 18:46:06'),
(5, 37, 'maeow000@gmail.com', 'ea6d15f785f23f951a5fba4308e2cb0ee0742c67636202fbd35ee75dfea14d4c', '2026-06-18 03:52:27', '2026-05-18 19:52:27');

SELECT setval('trusted_emails_id_seq', (SELECT MAX(id) FROM trusted_emails));

-- ============================================
-- CREATE INDEXES FOR BETTER PERFORMANCE
-- ============================================

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_properties_type ON properties(type);
CREATE INDEX idx_properties_status ON properties(status);
CREATE INDEX idx_leads_stage ON leads(stage);
CREATE INDEX idx_leads_priority ON leads(priority);
CREATE INDEX idx_leads_client ON leads(client_id);
CREATE INDEX idx_leads_agent ON leads(agent_id);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(is_read);
CREATE INDEX idx_reviews_agent ON reviews(agent_id);
CREATE INDEX idx_reviews_rating ON reviews(rating);
CREATE INDEX idx_favorites_client ON favorites(client_id);
CREATE INDEX idx_appointments_date ON appointments(scheduled_date);

-- ============================================
-- VERIFY DATA COUNTS
-- ============================================
SELECT 'Users: ' || COUNT(*) || ' rows' FROM users;
SELECT 'Properties: ' || COUNT(*) || ' rows' FROM properties;
SELECT 'Leads: ' || COUNT(*) || ' rows' FROM leads;
SELECT 'Notifications: ' || COUNT(*) || ' rows' FROM notifications;
SELECT 'Reviews: ' || COUNT(*) || ' rows' FROM reviews;
;

try {
    // Split SQL by semicolon and execute each statement
    $queries = explode(';', $sql);
    $executed = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query) && !preg_match('/^--/', $query)) {
            $pdo->exec($query);
            $executed++;
            echo "✅ Executed statement: " . substr($query, 0, 100) . "...\n";
        }
    }
    
    echo "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🎉 IMPORT COMPLETE!\n";
    echo "📊 $executed statements executed successfully.\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    // Verify import
    echo "\n📋 Verification:\n";
    $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    echo "Tables created:\n";
    while ($row = $tables->fetch()) {
        echo "  - " . $row['table_name'] . "\n";
    }
    
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "\n👥 Users imported: " . $userCount . "\n";
    
    $leadCount = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
    echo "📌 Leads imported: " . $leadCount . "\n";
    
    echo "\n";
    echo "🔒 REMEMBER TO DELETE THIS FILE (import_db.php) FOR SECURITY!\n";
    
} catch(PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nIf you see 'relation already exists', the database may already be imported.\n";
}
?>