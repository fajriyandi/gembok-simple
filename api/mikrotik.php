<?php
/**
 * API: MikroTik
 */

header('Content-Type: application/json');

require_once '../includes/functions.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    if ($method === 'GET') {
        if ($action === 'users') {
            // Get all PPPoE users
            $users = mikrotikGetPppoeUsers();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'users' => $users,
                    'total' => count($users)
                ]
            ]);
        } elseif ($action === 'active') {
            // Get active sessions
            $socket = mikrotikConnect();
            if (!$socket) {
                echo json_encode(['success' => false, 'message' => 'Cannot connect to MikroTik']);
                exit;
            }
            
            mikrotikLogin($socket);
            mikrotikWrite($socket, '/ppp/active/print');
            
            $response = mikrotikRead($socket);
            fclose($socket);
            
            $activeSessions = mikrotikParseUsers($response);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'active' => $activeSessions,
                    'total' => count($activeSessions)
                ]
            ]);
        } elseif ($action === 'profiles') {
            // Get PPPoE profiles
            $socket = mikrotikConnect();
            if (!$socket) {
                echo json_encode(['success' => false, 'message' => 'Cannot connect to MikroTik']);
                exit;
            }
            
            mikrotikLogin($socket);
            mikrotikWrite($socket, '/ppp/profile/print');
            
            $response = mikrotikRead($socket);
            fclose($socket);
            
            $profiles = [];
            $lines = explode("\n", $response);
            $currentProfile = [];
            
            foreach ($lines as $line) {
                if (strpos($line, '!done') !== false) {
                    if (!empty($currentProfile)) {
                        $profiles[] = $currentProfile;
                        $currentProfile = [];
                    }
                    break;
                }
                
                if (preg_match('/^=([a-zA-Z0-9_-+)=(.*)$/', $line, $matches)) {
                    $currentProfile[$matches[1]] = $matches[2];
                } elseif (strpos($line, '!re') !== false) {
                    if (!empty($currentProfile)) {
                        $profiles[] = $currentProfile;
                        $currentProfile = [];
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'profiles' => $profiles,
                    'total' => count($profiles)
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($action === 'add_user') {
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            $profile = $input['profile'] ?? 'default';
            $service = $input['service'] ?? 'pppoe';
            
            if (empty($username) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Username and password required']);
                exit;
            }
            
            $socket = mikrotikConnect();
            if (!$socket) {
                echo json_encode(['success' => false, 'message' => 'Cannot connect to MikroTik']);
                exit;
            }
            
            mikrotikLogin($socket);
            
            // Add PPPoE secret
            mikrotikWrite($socket, '/ppp/secret/add');
            mikrotikWrite($socket, '=name=' . $username);
            mikrotikWrite($socket, '=password=' . $password);
            mikrotikWrite($socket, '=profile=' . $profile);
            mikrotikWrite($socket, '=service=' . $service);
            
            fclose($socket);
            
            echo json_encode(['success' => true, 'message' => 'User added successfully']);
        }
    }
    
} catch (Exception $e) {
    logError("API Error (mikrotik.php): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
