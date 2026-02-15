<?php
/**
 * API: Invoices
 */

header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 20;
    
    if ($method === 'GET') {
        // Get invoices with pagination
        $offset = ($page - 1) * $perPage;
        
        $invoices = fetchAll("
            SELECT i.*, c.name as customer_name, c.pppoe_username 
            FROM invoices i 
            LEFT JOIN customers c ON i.customer_id = c.id 
            ORDER BY i.created_at DESC 
            LIMIT {$perPage} OFFSET {$offset}
        ");
        
        $totalResult = fetchOne("SELECT COUNT(*) as total FROM invoices");
        $total = $totalResult['total'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'invoices' => $invoices,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage)
            ]
        ]);
    }
    
} catch (Exception $e) {
    logError("API Error (invoices.php): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
