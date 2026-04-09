<?php
/**
 * Webhook Handler - bKash Payment Gateway
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/payment.php';

$paymentID = $_GET['paymentID'] ?? '';
$status = $_GET['status'] ?? '';

if (empty($paymentID)) {
    logError("bKash callback: No paymentID received");
    redirect(APP_URL . '/portal/dashboard.php');
}

logActivity('BKASH_CALLBACK', "Status: $status, PaymentID: $paymentID");

if ($status === 'success') {
    // Execute payment
    $tokenRes = paymentBkashGetToken();
    if (!$tokenRes['success']) {
        logError("bKash execute: Failed to get token");
        setFlash('error', 'Gagal memverifikasi pembayaran bKash');
        redirect(APP_URL . '/portal/dashboard.php');
    }
    
    $idToken = $tokenRes['token'];
    $appKey = trim((string) paymentGetConfig('BKASH_APP_KEY', ''));
    
    $res = paymentBkashRequest('/tokenized/checkout/payment/execute', 'POST', [
        'Content-Type: application/json',
        'Authorization: ' . $idToken,
        'X-APP-Key: ' . $appKey
    ], ['paymentID' => $paymentID]);
    
    $json = $res['json'];
    
    // Log webhook
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO webhook_logs (source, payload, status_code, response, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute(['bkash', json_encode($json), (int) $res['http_code'], ($json['statusMessage'] ?? 'Received')]);

    if ((int) $res['http_code'] === 200 && is_array($json) && ($json['transactionStatus'] ?? '') === 'Completed') {
        $invoiceNumber = $json['merchantInvoiceNumber'] ?? '';
        handlePaidInvoice($invoiceNumber, $json);
        setFlash('success', 'Pembayaran bKash berhasil');
        
        $usePretty = (string) paymentGetConfig('USE_PRETTY_URLS', '1') === '1';
        if (preg_match('/^VCR/i', (string) $invoiceNumber)) {
            $returnUrl = rtrim(APP_URL, '/') . ($usePretty ? ('/voucher/status/' . rawurlencode((string)$invoiceNumber)) : ('/voucher-status.php?order=' . rawurlencode((string)$invoiceNumber)));
            redirect($returnUrl);
        } else {
            redirect(APP_URL . '/portal/dashboard.php');
        }
    } else {
        paymentLog('BKASH_EXECUTE_FAILED', $res);
        setFlash('error', 'Gagal mengeksekusi pembayaran bKash: ' . ($json['statusMessage'] ?? 'Unknown error'));
        redirect(APP_URL . '/portal/dashboard.php');
    }
} else {
    handleFailedInvoice($paymentID, $status);
    setFlash('error', "Pembayaran bKash $status");
    redirect(APP_URL . '/portal/dashboard.php');
}

function handlePaidInvoice($invoiceNumber, $paymentData) {
    if (empty($invoiceNumber)) {
        logError("bKash: Invoice number empty in handlePaidInvoice");
        return;
    }
    
    $invoice = fetchOne("SELECT * FROM invoices WHERE invoice_number = ?", [$invoiceNumber]);
    if (!$invoice) {
        $invoice = fetchOne("SELECT * FROM invoices WHERE payment_order_id = ? LIMIT 1", [$invoiceNumber]);
    }
    
    if (!$invoice) {
        if (markPublicVoucherOrderPaid($invoiceNumber, 'bkash', $paymentData)) {
            logActivity('PUBLIC_VOUCHER_PAID', "Order: {$invoiceNumber}");
            return;
        }
        logError("Invoice/order not found: {$invoiceNumber}");
        return;
    }
    
    // Update invoice status
    update('invoices', [
        'status' => 'paid',
        'paid_at' => date('Y-m-d H:i:s'),
        'payment_method' => 'bKash',
        'payment_ref' => $paymentData['trxID'] ?? $paymentData['paymentID'] ?? ''
    ], 'id = ?', [(int) $invoice['id']]);
    
    logActivity('INVOICE_PAID', "Invoice: {$invoice['invoice_number']}");

    if (function_exists('sendInvoicePaidWhatsapp')) {
        sendInvoicePaidWhatsapp((string) $invoice['invoice_number'], 'bkash', $paymentData);
    }
    
    // Check if customer should be unisolated
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$invoice['customer_id']]);
    
    if ($customer && $customer['status'] === 'isolated') {
        // Check if all invoices are paid
        $unpaidCount = fetchOne("
            SELECT COUNT(*) as total 
            FROM invoices 
            WHERE customer_id = ? 
            AND status = 'unpaid' 
            AND due_date < CURDATE()
        ", [$customer['id']])['total'] ?? 0;
        
        if ($unpaidCount === 0) {
            // Unisolate customer
            if (unisolateCustomer($invoice['customer_id'])) {
                logActivity('AUTO_UNISOLATE', "Customer ID: {$invoice['customer_id']}");
            }
        }
    }
}

function handleFailedInvoice($paymentID, $status) {
    logActivity('BKASH_PAYMENT_FAILED', "PaymentID: {$paymentID}, Status: {$status}");
}
