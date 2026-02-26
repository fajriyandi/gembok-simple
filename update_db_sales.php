<?php
require 'includes/db.php';
try {
    $pdo = getDB();
    // Add columns if not exist
    $cols = $pdo->query("SHOW COLUMNS FROM sales_users")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('voucher_mode', $cols)) {
        $pdo->exec("ALTER TABLE sales_users ADD COLUMN voucher_mode VARCHAR(20) DEFAULT 'alp'"); // alp, num, mix
        echo "Added voucher_mode\n";
    }
    
    if (!in_array('voucher_length', $cols)) {
        $pdo->exec("ALTER TABLE sales_users ADD COLUMN voucher_length INT DEFAULT 6");
        echo "Added voucher_length\n";
    }
    
    if (!in_array('voucher_type', $cols)) {
        $pdo->exec("ALTER TABLE sales_users ADD COLUMN voucher_type VARCHAR(20) DEFAULT 'upp'"); // upp (user=pass), up (user & pass)
        echo "Added voucher_type\n";
    }

    if (!in_array('bill_discount', $cols)) {
        $pdo->exec("ALTER TABLE sales_users ADD COLUMN bill_discount DECIMAL(10,2) DEFAULT 0"); 
        echo "Added bill_discount\n";
    }
    
    echo "Done";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
