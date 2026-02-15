<?php
/**
 * API: ONU Locations with GenieACS Integration
 */

header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get all ONU locations
    $onuLocations = fetchAll("SELECT * FROM onu_locations ORDER BY name");
    
    // Add status and device info from GenieACS
    foreach ($onuLocations as &$onu) {
        $deviceInfo = genieacsGetDeviceInfo($onu['serial_number']);
        
        if ($deviceInfo) {
            $onu['status'] = $deviceInfo['status'];
            $onu['device_info'] = $deviceInfo;
            $onu['ssid'] = $deviceInfo['ssid'] ?? '';
            $onu['password'] = $deviceInfo['wifi_password'] ?? '';
        } else {
            $onu['status'] = 'unknown';
            $onu['device_info'] = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $onuLocations
    ]);
    
} elseif ($method === 'POST') {
    // Add or update ONU location
    $input = json_decode(file_get_contents('php://input'), true);
    
    $serial = $input['serial'] ?? '';
    $name = $input['name'] ?? '';
    $lat = $input['lat'] ?? null;
    $lng = $input['lng'] ?? null;
    
    if (empty($serial)) {
        echo json_encode(['success' => false, 'message' => 'Serial number is required']);
        exit;
    }
    
    // Check if ONU already exists
    $existing = fetchOne("SELECT id FROM onu_locations WHERE serial_number = ?", [$serial]);
    
    if ($existing) {
        // Update existing
        update('onu_locations', [
            'name' => $name,
            'lat' => $lat,
            'lng' => $lng,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'serial_number = ?', [$serial]);
        
        echo json_encode(['success' => true, 'message' => 'ONU location updated']);
    } else {
        // Insert new
        insert('onu_locations', [
            'name' => $name,
            'serial_number' => $serial,
            'lat' => $lat,
            'lng' => $lng,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo json_encode(['success' => true, 'message' => 'ONU location added']);
    }
}
