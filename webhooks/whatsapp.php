<?php
/**
 * Webhook Handler - WhatsApp
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    // Get raw POST data
    $json = file_get_contents('php://input');
    
    logActivity('WHATSAPP_WEBHOOK', "Received webhook");
    
    // Validate signature if configured
    if (!empty(WHATSAPP_TOKEN)) {
        $webhookToken = $_SERVER['HTTP_X_WHATSAPP_TOKEN'] ?? '';
        
        if (!hash_equals(WHATSAPP_TOKEN, $webhookToken)) {
            logError('WhatsApp webhook: Invalid token');
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            exit;
        }
    }
    
    // Parse JSON data
    $data = json_decode($json, true);
    
    if (!$data) {
        logError('WhatsApp webhook: Invalid JSON');
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }
    
    // Log webhook
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO webhook_logs (source, payload, status_code, response, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute(['whatsapp', $json, 200, 'Received']);
    
    // Handle webhook based on type
    $webhookType = $data['type'] ?? '';
    
    switch ($webhookType) {
        case 'message_status':
            handleMessageStatus($data);
            break;
            
        case 'message_sent':
            handleMessageSent($data);
            break;
            
        default:
            logActivity('WHATSAPP_WEBHOOK', "Unknown type: {$webhookType}");
    }
    
    echo json_encode(['success' => true, 'message' => 'Webhook processed']);
    
} catch (Exception $e) {
    logError("WhatsApp webhook error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function handleMessageStatus($data) {
    $status = $data['status'] ?? '';
    $messageId = $data['message_id'] ?? '';
    $recipient = $data['recipient'] ?? '';
    
    logActivity('WHATSAPP_MESSAGE_STATUS', "Status: {$status}, Message ID: {$messageId}, Recipient: {$recipient}");
    
    // Update message status in database if needed
    // This depends on your application's requirements
}

function handleMessageSent($data) {
    $recipient = $data['recipient'] ?? '';
    $message = $data['message'] ?? '';
    
    logActivity('WHATSAPP_MESSAGE_SENT', "To: {$recipient}, Message: " . substr($message, 0, 50));
}
