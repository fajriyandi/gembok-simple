<?php
/**
 * API: GenieACS
 */

header('Content-Type: application/json');

require_once '../includes/functions.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    if ($method === 'GET') {
        if ($action === 'devices') {
            // Get all devices
            $devices = genieacsGetDevices();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'devices' => $devices,
                    'total' => count($devices)
                ]
            ]);
        } elseif ($action === 'device') {
            $serial = $_GET['serial'] ?? '';
            
            if (empty($serial)) {
                echo json_encode(['success' => false, 'message' => 'Serial number required']);
                exit;
            }
            
            $deviceInfo = genieacsGetDeviceInfo($serial);
            
            if ($deviceInfo) {
                echo json_encode([
                    'success' => true,
                    'data' => $deviceInfo
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Device not found']);
            }
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($action === 'reboot') {
            $serial = $input['serial'] ?? '';
            
            if (empty($serial)) {
                echo json_encode(['success' => false, 'message' => 'Serial number required']);
                exit;
            }
            
            // Reboot device via GenieACS
            $result = genieacsReboot($serial);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Device reboot initiated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reboot device']);
            }
        }
    }
    
} catch (Exception $e) {
    logError("API Error (genieacs.php): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
