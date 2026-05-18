<?php
// install_db.php - Clean version with NO special characters
// RUN THIS ONCE, THEN DELETE IT

require_once 'includes/db.php';

echo "<h1>Database Installation</h1>";
echo "<pre>";

// Check connection
try {
    $pdo->query("SELECT 1");
    echo "Database connection: OK\n\n";
} catch(Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if users table exists
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'users'");
$tableExists = $stmt->fetchColumn();

if ($tableExists > 0) {
    echo "WARNING: Users table already exists.\n";
    echo "Database may already be installed.\n\n";
    echo "To reinstall, first run this SQL in your database console:\n";
    echo "DROP SCHEMA public CASCADE; CREATE SCHEMA public;\n";
    exit;
}

echo "Creating tables...\n\n";

// Create users table
$pdo->exec("
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    phone VARCHAR(20),
    address TEXT,
    avg_rating DECIMAL(3,2) DEFAULT 0,
    total_reviews INTEGER DEFAULT 0
)
");
echo "[OK] users table created\n";

// Create properties table
$pdo->exec("
CREATE TABLE properties (
    id SERIAL PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    type VARCHAR(20) NOT NULL,
    price DECIMAL(15,2),
    location VARCHAR(200),
    status VARCHAR(20) DEFAULT 'available',
    is_featured INTEGER DEFAULT 0,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id INTEGER,
    agent_id INTEGER,
    bedrooms INTEGER DEFAULT 0,
    bathrooms INTEGER DEFAULT 0,
    area INTEGER DEFAULT 0
)
");
echo "[OK] properties table created\n";

// Create leads table
$pdo->exec("
CREATE TABLE leads (
    id SERIAL PRIMARY KEY,
    client_id INTEGER REFERENCES users(id),
    agent_id INTEGER REFERENCES users(id),
    property_id INTEGER REFERENCES properties(id),
    stage VARCHAR(20) DEFAULT 'new',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INTEGER,
    updated_by INTEGER,
    follow_up_date DATE,
    last_contacted TIMESTAMP,
    priority VARCHAR(10) DEFAULT 'medium',
    last_contact TIMESTAMP,
    next_followup DATE
)
");
echo "[OK] leads table created\n";

// Create notifications table
$pdo->exec("
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    message TEXT,
    is_read INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    link VARCHAR(255)
)
");
echo "[OK] notifications table created\n";

// Create favorites table
$pdo->exec("
CREATE TABLE favorites (
    id SERIAL PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES users(id),
    property_id INTEGER NOT NULL REFERENCES properties(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(client_id, property_id)
)
");
echo "[OK] favorites table created\n";

// Insert admin user
$pdo->exec("
INSERT INTO users (full_name, email, password, role, status) VALUES (
    'Admin User',
    'admin@transphil.com',
    '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    'active'
)
");
echo "[OK] Admin user created (email: admin@transphil.com, password: password)\n";

// Insert sample property
$pdo->exec("
INSERT INTO properties (title, description, type, price, location, status, is_featured) VALUES (
    'Sample Property',
    'This is a sample property. Add your own properties through the admin panel.',
    'sale',
    5000000.00,
    'Metro Manila',
    'available',
    1
)
");
echo "[OK] Sample property created\n";

echo "\n";
echo "========================================\n";
echo "INSTALLATION COMPLETE!\n";
echo "========================================\n";
echo "\n";
echo "Login with:\n";
echo "Email: admin@transphil.com\n";
echo "Password: password\n";
echo "\n";
echo "IMPORTANT: Delete this file (install_db.php) now!\n";

?>