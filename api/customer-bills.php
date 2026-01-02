<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require_once __DIR__ . '/../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $account_id = isset($data['account_id']) ? intval($data['account_id']) : 0;
    
    if (empty($account_id)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Account ID required'
        ]);
        exit;
    }
    
    try {
        $db = new Database();
        $conn = $db->connect();
        
        // Get ALL bills for this account with their segments
        // Only show bills that are approved/finalized (exclude draft, pending_review, and rejected)
        $billsSql = "SELECT b.id, b.bill_date, b.due_date, b.total_amount, b.status, b.created_at
                     FROM bills b
                     WHERE b.account_id = ?
                     AND b.status NOT IN ('draft', 'pending_review', 'rejected')
                     ORDER BY b.created_at DESC";
        
        $stmt = $conn->prepare($billsSql);
        $stmt->bind_param('i', $account_id);
        $stmt->execute();
        $billsResult = $stmt->get_result();
        
        $bills = [];
        
        if ($billsResult && $billsResult->num_rows > 0) {
            while ($row = $billsResult->fetch_assoc()) {
                // Get segments for this bill
                $segmentsSql = "SELECT id, name, consumption, amount, status, remarks FROM bill_segments 
                                WHERE bill_id = ? 
                                ORDER BY id ASC";
                $segStmt = $conn->prepare($segmentsSql);
                $segStmt->bind_param('i', $row['id']);
                $segStmt->execute();
                $segmentsResult = $segStmt->get_result();
                
                $segments = [];
                if ($segmentsResult && $segmentsResult->num_rows > 0) {
                    while ($seg = $segmentsResult->fetch_assoc()) {
                        $segments[] = $seg;
                    }
                }
                
                $row['segments'] = $segments;
                $bills[] = $row;
            }
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'bills' => $bills
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Server error: ' . $e->getMessage()
        ]);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;
    
    if (empty($account_id)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Account ID required'
        ]);
        exit;
    }
    
    try {
        $db = new Database();
        $conn = $db->connect();
        
        // Get ALL bills for this account with their segments
        // Only show bills that are approved/finalized (exclude draft, pending_review, and rejected)
        $billsSql = "SELECT b.id, b.bill_date, b.due_date, b.total_amount, b.status, b.created_at
                     FROM bills b
                     WHERE b.account_id = ?
                     AND b.status NOT IN ('draft', 'pending_review', 'rejected')
                     ORDER BY b.created_at DESC";
        
        $stmt = $conn->prepare($billsSql);
        $stmt->bind_param('i', $account_id);
        $stmt->execute();
        $billsResult = $stmt->get_result();
        
        $bills = [];
        
        if ($billsResult && $billsResult->num_rows > 0) {
            while ($row = $billsResult->fetch_assoc()) {
                // Get segments for this bill
                $segmentsSql = "SELECT id, name, consumption, amount, status, remarks FROM bill_segments 
                                WHERE bill_id = ? 
                                ORDER BY id ASC";
                $segStmt = $conn->prepare($segmentsSql);
                $segStmt->bind_param('i', $row['id']);
                $segStmt->execute();
                $segmentsResult = $segStmt->get_result();
                
                $segments = [];
                if ($segmentsResult && $segmentsResult->num_rows > 0) {
                    while ($seg = $segmentsResult->fetch_assoc()) {
                        $segments[] = $seg;
                    }
                }
                
                $row['segments'] = $segments;
                $bills[] = $row;
            }
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'bills' => $bills
        ]);
        
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
