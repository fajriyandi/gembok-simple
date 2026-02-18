<?php
require 'includes/db.php';
$pdo = getDB();

// 1. Add router_id to customers
try {
    $pdo->exec("ALTER TABLE customers ADD COLUMN router_id INT DEFAULT 0 AFTER package_id");
    echo "SUCCESS: router_id added\n";
} catch (Exception $e) {
    echo "INFO: " . $e->getMessage() . "\n";
}

// 2. Check routers table
try {
    $stmt = $pdo->query("SELECT id, name FROM routers");
    $routers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "ROUTERS: " . json_encode($routers) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
