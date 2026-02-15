<?php
/**
 * API: ONU WiFi Settings
 */

header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $pppoeUsername = $input['pppoe_username'] ?? '';
    $serial = $input['serial'] ?? '';  // Keep for backward compatibility
    $ssid = $input['ssid'] ?? '';
    $password = $input['password'] ?? '';
    
    // Use either pppoe_username or serial
    if (empty($pppoeUsername) && empty($serial)) {
        echo json_encode(['success' => false, 'message' => 'PPPoE username or serial number is required']);
        exit;
    }
    
    // If PPPoE username is provided, find the device
    if (!empty($pppoeUsername)) {
        $device = genieacsFindDeviceByPppoe($pppoeUsername);
        if (!$device) {
            echo json_encode(['success' => false, 'message' => 'Device not found for PPPoE username: ' . $pppoeUsername]);
            exit;
        }
        $serial = $device['DeviceID']['_SerialNumber'] ?? $pppoeUsername; // Fallback to username if serial not found
    }
    
    // Validate SSID
    if (!empty($ssid) && strlen($ssid) < 3) {
        echo json_encode(['success' => false, 'message' => 'SSID minimal 3 karakter']);
        exit;
    }
    
    // Validate password
    if (!empty($password) && strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password minimal 8 karakter']);
        exit;
    }
    
    // Update WiFi settings via GenieACS
    if (!empty($ssid)) {
        $result = genieacsSetParameter($serial, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', $ssid);
        
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Failed to update SSID']);
            exit;
        }
    }
    
    if (!empty($password)) {
        $result = genieacsSetParameter($serial, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase', $password);
        
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Failed to update password']);
            exit;
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'WiFi settings updated successfully']);
    
} catch (Exception $e) {
    logError("API Error (onu_wifi.php): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
