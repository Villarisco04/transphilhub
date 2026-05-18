<?php
// import_db.php - Run this ONCE to import your database
// WARNING: DELETE THIS FILE AFTER IMPORTING!

require_once 'includes/db.php';

echo "<h1>Database Import Tool</h1>";
echo "<pre>";

// Check if tables already exist
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'");
$tableCount = $stmt->fetchColumn();

if ($tableCount > 0) {
    echo "WARNING: Tables already exist! Database may already be imported.\n";
    echo "Current tables: " . $tableCount . "\n";
    echo "\n";
    echo "If you want to re-import, run this SQL first:\n";
    echo "DROP SCHEMA public CASCADE; CREATE SCHEMA public;\n";
    exit;
}

echo "Starting import...\n\n";

$sql = "
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

CREATE TABLE appointments (
    id SERIAL PRIMARY KEY,
    client_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    agent_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    property_id INTEGER REFERENCES properties(id) ON DELETE SET NULL,
    scheduled_date TIMESTAMP,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'cancelled'))
);

CREATE TABLE favorites (
    id SERIAL PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    property_id INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(client_id, property_id)
);

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

CREATE TABLE login_attempts (
    id SERIAL PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    success INTEGER DEFAULT 0,
    ip_address VARCHAR(45),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_email_time ON login_attempts(email, attempted_at);

CREATE TABLE mfa_codes (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    otp VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    message TEXT,
    is_read INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    link VARCHAR(255)
);

CREATE TABLE notification_settings (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    email_notifications INTEGER DEFAULT 1,
    inquiry_alerts INTEGER DEFAULT 1,
    lead_alerts INTEGER DEFAULT 1,
    appointment_alerts INTEGER DEFAULT 1
);

CREATE TABLE password_resets (
    id SERIAL PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_password_resets_email ON password_resets(email);

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

CREATE TABLE trusted_emails (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, email)
);

INSERT INTO users (id, full_name, email, password, role, status, created_at, phone, address, avg_rating, total_reviews) VALUES
(1, 'Admin', 'admin.transphilhub@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', '2026-05-12 13:03:06', NULL, NULL, 0.00, 0),
(2, 'Test Client', 'client@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active', '2026-05-12 15:23:24', NULL, NULL, 0.00, 0),
(3, 'LYKA F VILLARISCO', 'iamfaye011@gmail.com', '$2y$10$T3Tie9CiFJUhZluVnHUynuc7BNch4fyEYa57cbArznp442amWfkdC', 'client', 'active', '2026-05-12 16:57:22', '09662834639', 'bonifacio st., bangkal', 0.00, 0),
(4, 'faye faye', 'iamfaye@gmail.com', '$2y$10$0BzZgc9foIm5ed8op1nIcuJ/ZzYVziiSMDZyri2Moxb4dP3A1Q7G2', 'agent', 'active', '2026-05-12 19:17:21', '09662834639', 'bonifacio st., bangkal', 0.00, 0),
(5, 'Lyka Peralta', 'villariscolykafaye@gmail.com', '$2y$10$pLwe0hi3EBkU2CjVoBJjlev..8/bKSeMs4ybW76Kv4wNJhAikemQK', 'client', 'active', '2026-05-18 03:35:25', '09662834639', '2653 ttttt', 0.00, 0),
(6, 'kiki', 'lvillarisco.a12345296@umak.edu.ph', '$2y$10$jA85MINvbjd1H8zNDsSPOOkNxaveZfMs9ErJm7dSIf3akLPjon0K2', 'client', 'active', '2026-05-18 04:18:35', '09662834639', 'hhhhj', 0.00, 0);

SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));

INSERT INTO properties (id, title, description, type, price, location, status, is_featured, image, created_at, bedrooms, bathrooms, area) VALUES
(1, 'Modern Townhouse San Antonio', '3-bedroom townhouse with parking', 'sale', 4500000.00, 'Makati City', 'available', 1, 'property1.png', '2026-05-12 15:23:24', 3, 2, 85);

SELECT setval('properties_id_seq', (SELECT MAX(id) FROM properties));

INSERT INTO leads (id, client_id, agent_id, property_id, stage, notes, created_at, priority) VALUES
(1, 1, NULL, 1, 'new', 'Interested in schedule viewing', '2026-05-12 15:23:24', 'medium');

SELECT setval('leads_id_seq', (SELECT MAX(id) FROM leads));
";

try {
    $queries = explode(';', $sql);
    $executed = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
            $executed++;
            echo "[OK] Executed: " . substr($query, 0, 80) . "...\n";
        }
    }
    
    echo "\n";
    echo "========================================\n";
    echo "IMPORT COMPLETE!\n";
    echo "Executed: $executed statements.\n";
    echo "========================================\n";
    
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "Users imported: " . $userCount . "\n";
    
    echo "\n";
    echo "REMEMBER TO DELETE THIS FILE FOR SECURITY!\n";
    
} catch(PDOException $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
}
?>