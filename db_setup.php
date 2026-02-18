<?php
/**
 * Database Initializer for Gembok Simple on Laragon
 * This script creates the database and tables manually.
 */

// Configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = ''; // Default Laragon password is empty
$db_name = 'gembok_simple';

try {
    // 1. Connect to MySQL without database
    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // 2. Create Database
    echo "Creating database $db_name...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $db_name");

    // 3. Create Tables (Extracted from install.php)
    echo "Creating tables...\n";
    $sql = "
    CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        name VARCHAR(100),
        reset_token VARCHAR(64),
        reset_expiry DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS packages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        profile_normal VARCHAR(50) NOT NULL,
        profile_isolir VARCHAR(50) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        pppoe_username VARCHAR(50) UNIQUE NOT NULL,
        package_id INT,
        router_id INT DEFAULT 0,
        status ENUM('active', 'isolated') DEFAULT 'active',
        isolation_date INT DEFAULT 20,
        address TEXT,
        lat DECIMAL(10,8),
        lng DECIMAL(10,8),
        portal_password VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(50) UNIQUE NOT NULL,
        customer_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('unpaid', 'paid', 'cancelled') DEFAULT 'unpaid',
        due_date DATE NOT NULL,
        paid_at DATETIME,
        payment_method VARCHAR(50),
        payment_ref VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS odps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        code VARCHAR(50) UNIQUE,
        lat DECIMAL(10,8),
        lng DECIMAL(10,8),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS onu_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        serial_number VARCHAR(100) UNIQUE,
        lat DECIMAL(10,8),
        lng DECIMAL(10,8),
        odp_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (odp_id) REFERENCES odps(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS odp_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_odp_id INT NOT NULL,
        to_odp_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (from_odp_id) REFERENCES odps(id) ON DELETE CASCADE,
        FOREIGN KEY (to_odp_id) REFERENCES odps(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS trouble_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT,
        description TEXT,
        status ENUM('pending', 'in_progress', 'resolved') DEFAULT 'pending',
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS cron_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        task_type VARCHAR(50),
        schedule_time TIME,
        schedule_days VARCHAR(20),
        is_active BOOLEAN DEFAULT 1,
        last_run DATETIME,
        next_run DATETIME,
        last_status VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS cron_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        schedule_id INT,
        status ENUM('success', 'failed', 'started'),
        output TEXT,
        error_message TEXT,
        execution_time FLOAT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (schedule_id) REFERENCES cron_schedules(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS webhook_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source VARCHAR(50),
        payload JSON,
        status_code INT,
        response TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS hotspot_sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        profile VARCHAR(100) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        selling_price DECIMAL(10,2) NOT NULL,
        prefix VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS routers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        host VARCHAR(100) NOT NULL,
        username VARCHAR(100) NOT NULL,
        password VARCHAR(100) NOT NULL,
        port INT DEFAULT 8728,
        is_active BOOLEAN DEFAULT 0,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($sql);

    // 4. Insert Default Admin (username: admin, pass: admin123)
    echo "Inserting default admin...\n";
    $pass = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO admin_users (username, password, email, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute(['admin', $pass, 'admin@example.com']);

    // 5. Insert Default Data
    echo "Inserting default packages and settings...\n";
    $packages = [
        ['Paket 10 Mbps', 150000, '10Mbps', 'isolir-10Mbps'],
        ['Paket 20 Mbps', 250000, '20Mbps', 'isolir-20Mbps'],
        ['Paket 50 Mbps', 350000, '50Mbps', 'isolir-50Mbps']
    ];
    foreach ($packages as $pkg) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO packages (name, price, profile_normal, profile_isolir) VALUES (?, ?, ?, ?)");
        $stmt->execute($pkg);
    }

    $settings = [
        ['app_name', 'GEMBOK'],
        ['app_version', '2.0.0'],
        ['currency', 'IDR'],
        ['timezone', 'Asia/Jakarta'],
        ['invoice_prefix', 'INV'],
        ['invoice_start', '1']
    ];
    foreach ($settings as $setting) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute($setting);
    }

    echo "Initial data setup complete!\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
