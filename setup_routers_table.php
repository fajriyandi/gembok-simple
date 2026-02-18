<?php
require 'includes/db.php';
$pdo = getDB();

$sql = "CREATE TABLE IF NOT EXISTS routers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    host VARCHAR(100) NOT NULL,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(100) NOT NULL,
    port INT DEFAULT 8728,
    description TEXT,
    is_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

try {
    $pdo->exec($sql);
    echo "SUCCESS: routers table checked/created\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Ensure first router is default if none is active
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM routers WHERE is_active = 1");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("UPDATE routers SET is_active = 1 LIMIT 1");
    }
} catch (Exception $e) {}
