<?php
/**
 * Webhook Handler - Telegram Bot
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    // Get raw POST data
    $json = file_get_contents('php://input');
    
    logActivity('TELEGRAM_WEBHOOK', "Received webhook");
    
    // Validate token if configured
    if (!empty(TELEGRAM_BOT_TOKEN)) {
        // Telegram doesn't use signature validation
        // Just log the webhook
    }
    
    // Parse JSON data
    $data = json_decode($json, true);
    
    if (!$data) {
        logError('Telegram webhook: Invalid JSON');
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }
    
    // Log webhook
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO webhook_logs (source, payload, status_code, response, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute(['telegram', $json, 200, 'Received']);
    
    // Handle webhook based on type
    $message = $data['message'] ?? '';
    $chatId = $data['chat']['id'] ?? '';
    $callbackQuery = $data['callback_query'] ?? '';
    
    // Parse callback query data
    parse_str($callbackQuery, $callbackData);
    
    $action = $callbackData['action'] ?? '';
    
    switch ($action) {
        case 'pay_invoice':
            handlePayInvoice($chatId, $callbackData);
            break;
            
        case 'check_status':
            handleCheckStatus($chatId, $callbackData);
            break;
            
        case 'help':
            handleHelp($chatId);
            break;
            
        default:
            // Handle regular messages
            handleRegularMessage($chatId, $message);
    }
    
    echo json_encode(['success' => true, 'message' => 'Webhook processed']);
    
} catch (Exception $e) {
    logError("Telegram webhook error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function handlePayInvoice($chatId, $data) {
    $invoiceId = $data['invoice_id'] ?? '';
    
    // Get invoice details
    $invoice = fetchOne("SELECT i.*, c.name as customer_name FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id WHERE i.id = ?", [$invoiceId]);
    
    if (!$invoice) {
        sendMessage($chatId, "❌ Invoice tidak ditemukan.");
        return;
    }
    
    // Send payment link
    $paymentLink = generatePaymentLink($invoice);
    
    $message = "💳 *Invoice #{$invoice['invoice_number']}*\n\n";
    $message .= "Pelanggan: {$invoice['customer_name']}\n";
    $message .= "Jumlah: " . formatCurrency($invoice['amount']) . "\n";
    $message .= "Jatuh Tempo: " . formatDate($invoice['due_date']) . "\n\n";
    $message .= "Silakan bayar melalui link berikut:\n";
    $message .= $paymentLink;
    
    sendMessage($chatId, $message);
}

function handleCheckStatus($chatId, $data) {
    $phone = $data['phone'] ?? '';
    
    // Get customer by phone
    $customer = fetchOne("SELECT * FROM customers WHERE phone = ?", [$phone]);
    
    if (!$customer) {
        sendMessage($chatId, "❌ Pelanggan tidak ditemukan dengan nomor HP tersebut.");
        return;
    }
    
    // Get customer status
    $status = $customer['status'] === 'active' ? 'Aktif' : 'Isolir';
    
    $message = "📊 *Status Pelanggan*\n\n";
    $message .= "Nama: {$customer['name']}\n";
    $message .= "No HP: {$customer['phone']}\n";
    $message .= "PPPoE Username: {$customer['pppoe_username']}\n";
    $message .= "Status: {$status}\n";
    
    if ($customer['status'] === 'isolated') {
        $message .= "\n⚠️ Koneksi sedang diisolir karena belum bayar.";
    }
    
    sendMessage($chatId, $message);
}

function handleHelp($chatId) {
    $message = "🤖 *GEMBOK Bot Commands*\n\n";
    $message .= "/pay_invoice - Cek dan bayar invoice\n";
    $message .= "/check_status - Cek status pelanggan\n";
    $message .= "/help - Tampilkan bantuan ini\n\n";
    $message .= "Silakan pilih command yang ingin Anda jalankan.";
    
    sendMessage($chatId, $message);
}

function handleRegularMessage($chatId, $message) {
    // Handle regular messages from users
    // This can be extended based on application needs
    
    $message = "Terima kasih atas pesan Anda.\n\n";
    $message .= "Untuk menggunakan bot ini, silakan gunakan command yang tersedia.\n";
    $message .= "Ketik /help untuk melihat daftar command.";
    
    sendMessage($chatId, $message);
}

function sendMessage($chatId, $text) {
    if (empty(TELEGRAM_BOT_TOKEN)) {
        logError('Telegram bot token not configured');
        return false;
    }
    
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    logActivity('TELEGRAM_SEND', "To: {$chatId}, Status code: {$httpCode}");
    
    return $httpCode === 200;
}

function generatePaymentLink($invoice) {
    // Generate Tripay payment link
    if (empty(TRIPAY_API_KEY) || empty(TRIPAY_MERCHANT_CODE)) {
        return 'Payment gateway not configured';
    }
    
    // This is a placeholder - implement actual Tripay payment link generation
    $amount = $invoice['amount'];
    $merchantRef = $invoice['invoice_number'];
    
    $paymentLink = "https://tripay.co.id/checkout?merchant_code=" . TRIPAY_MERCHANT_CODE . "&amount={$amount}&merchant_ref={$merchantRef}";
    
    return $paymentLink;
}
