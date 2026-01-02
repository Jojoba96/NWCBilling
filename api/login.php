<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require_once __DIR__ . '/../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $national_id = isset($data['national_id']) ? trim($data['national_id']) : '';
    $password = isset($data['password']) ? trim($data['password']) : '';
    
    if (empty($national_id) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Please enter national ID and password'
        ]);
        exit;
    }
    
    try {
        $db = new Database();
        $conn = $db->connect();
        
        // Query users by national_id (customers use national ID to login)
        // Also fetch account info
        $query = "SELECT u.id, u.username, u.full_name, u.national_id, u.email, u.phone_number, u.password, u.role, 
                         a.id as account_id, a.account_number, a.account_type
                  FROM users u
                  LEFT JOIN accounts a ON u.id = a.user_id
                  WHERE u.national_id = ? AND u.role = 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $national_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Check password - handle NULL passwords for test accounts
            $storedPassword = $user['password'];
            
            // If password is NULL or empty, allow test123 for testing
            // Otherwise verify the password
            $passwordValid = false;
            if (empty($storedPassword)) {
                $passwordValid = ($password === 'test123');
            } else if ($password === $storedPassword) {
                $passwordValid = true;
            } else if (function_exists('password_verify') && password_verify($password, $storedPassword)) {
                $passwordValid = true;
            }
            
            if ($passwordValid) {
                // Create session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['national_id'] = $user['national_id'];
                $_SESSION['account_id'] = $user['account_id'];
                $_SESSION['role'] = $user['role'];
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'user_id' => $user['id'],
                    'account_id' => $user['account_id'],
                    'account_number' => $user['account_number'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'phone_number' => $user['phone_number'],
                    'account_type' => $user['account_type']
                ]);
            } else {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid password'
                ]);
            }
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'User not found'
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Server error: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}
?>
