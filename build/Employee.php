<?php
session_start();
require_once __DIR__ . '/../config/Database.php';

// Check if user is logged in and is an Employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header('Location: /NWCBilling/build/pages/sign-in.php');
    exit;
}

$db = new Database();

// Helper function to get tariff price based on service type, account type, and consumption
function getTariffPrice($conn, $serviceType, $accountType, $consumption) {
    $serviceType = $conn->real_escape_string($serviceType);
    $accountType = $conn->real_escape_string($accountType);
    $consumption = (float)$consumption;
    
    $sql = "SELECT unit_price, monthly_service_charge, sewage_percentage 
            FROM tariff_slabs 
            WHERE service_type = '$serviceType' 
            AND account_type = '$accountType'
            AND consumption_from <= $consumption 
            AND consumption_to > $consumption 
            LIMIT 1";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $tariff = $result->fetch_assoc();
        return [
            'unit_price' => (float)$tariff['unit_price'],
            'service_charge' => (float)$tariff['monthly_service_charge'],
            'sewage_percentage' => (float)$tariff['sewage_percentage']
        ];
    }
    
    return null;
}

// Helper function to calculate bill amount
function calculateBillAmount($conn, $serviceType, $accountType, $consumption) {
    $tariff = getTariffPrice($conn, $serviceType, $accountType, $consumption);
    
    if (!$tariff) {
        return null;
    }
    
    // Calculate: (consumption × unit_price) + service_charge + (sewage if applicable)
    $consumption = (float)$consumption;
    $baseAmount = $consumption * $tariff['unit_price'];
    $sewageAmount = ($tariff['sewage_percentage'] > 0) ? ($baseAmount * $tariff['sewage_percentage'] / 100) : 0;
    $totalAmount = $baseAmount + $tariff['service_charge'] + $sewageAmount;
    
    return round($totalAmount, 2);
}

// Handle AJAX requests FIRST (before any HTML output)
// Check both GET and POST for action parameter
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($action === 'get_loaded_segments') {
    // Return segments loaded in session as HTML table rows
    if (isset($_SESSION['loaded_segments']) && !empty($_SESSION['loaded_segments'])) {
        foreach ($_SESSION['loaded_segments'] as $segment) {
            echo '<tr data-segment="1" data-name="' . htmlspecialchars($segment['name']) . '" data-consumption="' . $segment['consumption'] . '" data-amount="' . $segment['amount'] . '" data-status="' . $segment['status'] . '" data-remarks="' . htmlspecialchars($segment['remarks']) . '"></tr>';
        }
        // Clear session data after sending
        unset($_SESSION['loaded_segments']);
        unset($_SESSION['loaded_bill_id']);
        unset($_SESSION['loaded_bill_date']);
        unset($_SESSION['loaded_due_date']);
        unset($_SESSION['loaded_total_amount']);
    }
    exit;
}

if ($action) {
    header('Content-Type: application/json');
    $conn = $db->connect();
    
    if (!$conn) {
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    
    if ($action === 'search_accounts') {
        $searchType = $_GET['searchType'] ?? 'name';
        $searchValue = $_GET['searchValue'] ?? '';
        $searchValue = $conn->real_escape_string($searchValue);
        
        $sql = "SELECT a.id, a.account_number, u.full_name, u.phone_number 
                FROM accounts a
                JOIN users u ON a.user_id = u.id
                WHERE 1=1";
        
        if ($searchType === 'name') {
            $sql .= " AND u.full_name LIKE '%$searchValue%'";
        } elseif ($searchType === 'account_id') {
            $sql .= " AND a.account_number LIKE '%$searchValue%'";
        } elseif ($searchType === 'phone') {
            $sql .= " AND u.phone_number LIKE '%$searchValue%'";
        }
        
        $result = $conn->query($sql);
        $data = [];
        
        if (!$result) {
            echo json_encode(['error' => 'Query error: ' . $conn->error]);
            exit;
        }
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'id' => $row['id'],
                    'account_number' => $row['account_number'],
                    'full_name' => $row['full_name'],
                    'phone_number' => $row['phone_number'],
                    'balance' => 'SAR 0'
                ];
            }
        }
        
        echo json_encode($data);
        exit;
    }
    
    elseif ($action === 'get_account') {
        $accountId = $_GET['accountId'] ?? 0;
        $accountId = (int)$accountId;
        
        $sql = "SELECT a.id, a.account_number, u.full_name, u.phone_number, p.address, p.city
                FROM accounts a
                JOIN users u ON a.user_id = u.id
                LEFT JOIN premises p ON a.id = p.account_id
                WHERE a.id = $accountId LIMIT 1";
        
        $result = $conn->query($sql);
        $data = null;
        
        if (!$result) {
            echo json_encode(['error' => 'Query error: ' . $conn->error]);
            exit;
        }
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $data['balance'] = 'SAR 0';
        }
        
        echo json_encode($data);
        exit;
    }
    
    elseif ($action === 'get_customer_info') {
        $accountId = $_GET['accountId'] ?? 0;
        $accountId = (int)$accountId;
        
        $sql = "SELECT a.id, a.account_number, a.account_type, u.full_name, u.phone_number, 
                       p.address, p.city, p.connection_number, p.building_area
                FROM accounts a
                JOIN users u ON a.user_id = u.id
                LEFT JOIN premises p ON a.id = p.account_id
                WHERE a.id = $accountId LIMIT 1";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            echo json_encode(['error' => 'Query error: ' . $conn->error]);
            exit;
        }
        
        $data = $result->num_rows > 0 ? $result->fetch_assoc() : null;
        
        // Get meter info - join through premises
        $meterSql = "SELECT m.id, m.meter_number, m.status FROM meters m
                     JOIN premises p ON m.premise_id = p.id
                     WHERE p.account_id = $accountId LIMIT 1";
        $meterResult = $conn->query($meterSql);
        $meter = $meterResult && $meterResult->num_rows > 0 ? $meterResult->fetch_assoc() : null;
        
        // Get latest meter reading
        $latestReadingSql = "SELECT mr.reading_date, mr.value FROM meter_readings mr
                            JOIN meters m ON mr.meter_id = m.id
                            JOIN premises p ON m.premise_id = p.id
                            WHERE p.account_id = $accountId
                            ORDER BY mr.reading_date DESC LIMIT 1";
        $latestReadingResult = $conn->query($latestReadingSql);
        $latestReading = $latestReadingResult && $latestReadingResult->num_rows > 0 ? $latestReadingResult->fetch_assoc() : null;
        
        // Get financial information
        // Last bill
        $lastBillSql = "SELECT bill_date, due_date, total_amount FROM bills 
                        WHERE account_id = $accountId 
                        ORDER BY bill_date DESC LIMIT 1";
        $lastBillResult = $conn->query($lastBillSql);
        $lastBill = $lastBillResult && $lastBillResult->num_rows > 0 ? $lastBillResult->fetch_assoc() : null;
        
        // Previous bill (2nd most recent)
        $prevBillSql = "SELECT bill_date, total_amount FROM bills 
                        WHERE account_id = $accountId 
                        ORDER BY bill_date DESC LIMIT 1 OFFSET 1";
        $prevBillResult = $conn->query($prevBillSql);
        $prevBill = $prevBillResult && $prevBillResult->num_rows > 0 ? $prevBillResult->fetch_assoc() : null;
        
        // Last payment (from payments table if it exists, otherwise use bill data)
        $lastPaymentSql = "SELECT payment_date, amount FROM payments 
                           WHERE account_id = $accountId 
                           ORDER BY payment_date DESC LIMIT 1";
        $lastPaymentResult = $conn->query($lastPaymentSql);
        $lastPayment = $lastPaymentResult && $lastPaymentResult->num_rows > 0 ? $lastPaymentResult->fetch_assoc() : null;
        
        // Calculate current balance from bills
        $balanceSql = "SELECT SUM(total_amount) as total FROM bills 
                       WHERE account_id = $accountId AND status = 'unpaid'";
        $balanceResult = $conn->query($balanceSql);
        $balanceData = $balanceResult ? $balanceResult->fetch_assoc() : null;
        $currentBalance = $balanceData && $balanceData['total'] ? $balanceData['total'] : 0;
        
        // Get service agreements
        $agreementSql = "SELECT sa.id, sa.agreement_type, sa.start_date, sa.end_date, sa.status
                         FROM service_agreements sa
                         JOIN premises p ON sa.premise_id = p.id
                         WHERE p.account_id = $accountId
                         ORDER BY sa.start_date DESC LIMIT 1";
        $agreementResult = $conn->query($agreementSql);
        $agreement = $agreementResult && $agreementResult->num_rows > 0 ? $agreementResult->fetch_assoc() : null;
        
        echo json_encode([
            'account' => $data, 
            'meter' => $meter,
            'latestReading' => $latestReading,
            'lastBill' => $lastBill,
            'prevBill' => $prevBill,
            'lastPayment' => $lastPayment,
            'agreement' => $agreement,
            'currentBalance' => $currentBalance
        ]);
        exit;
    }
    
    elseif ($action === 'get_all_customer_bills') {
        $accountNumber = $_GET['accountNumber'] ?? '';
        $accountNumber = $conn->real_escape_string($accountNumber);
        
        // Get account ID from account number
        $accountSql = "SELECT id FROM accounts WHERE account_number = '$accountNumber' LIMIT 1";
        $accountResult = $conn->query($accountSql);
        
        if (!$accountResult || $accountResult->num_rows === 0) {
            echo json_encode(['error' => 'Account not found']);
            exit;
        }
        
        $accountRow = $accountResult->fetch_assoc();
        $accountId = $accountRow['id'];
        
        // Get ALL bills for this account with their segment count
        $billsSql = "SELECT b.id, b.bill_date, b.due_date, b.total_amount, b.status, b.created_at,
                            COUNT(bs.id) as segment_count
                     FROM bills b
                     LEFT JOIN bill_segments bs ON b.id = bs.bill_id
                     WHERE b.account_id = $accountId
                     GROUP BY b.id
                     ORDER BY b.created_at DESC";
        
        $billsResult = $conn->query($billsSql);
        $bills = [];
        
        if ($billsResult && $billsResult->num_rows > 0) {
            while ($row = $billsResult->fetch_assoc()) {
                // Get segments for this bill
                $segmentsSql = "SELECT id, name, consumption, amount, status, remarks FROM bill_segments 
                                WHERE bill_id = " . $row['id'] . " 
                                ORDER BY id ASC";
                $segmentsResult = $conn->query($segmentsSql);
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
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'accountId' => $accountNumber,
            'billCount' => count($bills),
            'bills' => $bills
        ]);
        exit;
    }
    
    elseif ($action === 'get_billing_info') {
        $accountId = $_GET['accountId'] ?? 0;
        $accountId = (int)$accountId;
        
        $sql = "SELECT b.id, b.bill_date, b.due_date, b.total_amount, b.status
                FROM bills b
                WHERE b.account_id = $accountId
                ORDER BY b.bill_date DESC
                LIMIT 12";
        
        $result = $conn->query($sql);
        $bills = [];
        
        if (!$result) {
            echo json_encode(['error' => 'Query error: ' . $conn->error]);
            exit;
        }
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $bills[] = $row;
            }
        }
        
        // Get meter readings for this account
        $readingsSql = "SELECT mr.reading_date, mr.value
                       FROM meter_readings mr
                       JOIN meters m ON mr.meter_id = m.id
                       JOIN premises p ON m.premise_id = p.id
                       WHERE p.account_id = $accountId
                       ORDER BY mr.reading_date DESC
                       LIMIT 12";
        
        $readingsResult = $conn->query($readingsSql);
        $readings = [];
        
        if ($readingsResult && $readingsResult->num_rows > 0) {
            while ($row = $readingsResult->fetch_assoc()) {
                $readings[] = $row;
            }
        }
        
        echo json_encode(['bills' => $bills, 'readings' => $readings]);
        exit;
    }
    
    elseif ($action === 'search_bills') {
        $personName = $_GET['personName'] ?? '';
        $accountId = $_GET['accountId'] ?? '';
        $billId = $_GET['billId'] ?? '';
        $billDate = $_GET['billDate'] ?? '';
        $crNote = $_GET['crNote'] ?? '';
        
        // Build base SQL - join bills with accounts and users
        $sql = "SELECT b.id, b.bill_date, b.due_date, b.total_amount, b.status, 
                       b.account_id, a.account_number, u.full_name
                FROM bills b
                JOIN accounts a ON b.account_id = a.id
                JOIN users u ON a.user_id = u.id
                WHERE 1=1";
        
        // Add search filters
        if (!empty($personName)) {
            $personName = $conn->real_escape_string($personName);
            $sql .= " AND u.full_name LIKE '%$personName%'";
        }
        
        if (!empty($accountId)) {
            $accountId = $conn->real_escape_string($accountId);
            $sql .= " AND a.account_number LIKE '%$accountId%'";
        }
        
        if (!empty($billId)) {
            $billId = $conn->real_escape_string($billId);
            $sql .= " AND b.id LIKE '%$billId%'";
        }
        
        if (!empty($billDate)) {
            $billDate = $conn->real_escape_string($billDate);
            $sql .= " AND DATE(b.bill_date) = '$billDate'";
        }
        
        if (!empty($crNote)) {
            $crNote = $conn->real_escape_string($crNote);
            // CR Note might be stored in bill segments remarks or as a separate field
            $sql .= " AND (b.id IN (SELECT bill_id FROM bill_segments WHERE remarks LIKE '%$crNote%'))";
        }
        
        // Order by bill date descending and limit results
        $sql .= " ORDER BY b.bill_date DESC LIMIT 100";
        
        $result = $conn->query($sql);
        $bills = [];
        
        if (!$result) {
            echo json_encode(['error' => 'Query error: ' . $conn->error]);
            exit;
        }
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Format status with readable text
                $status = $row['status'];
                if ($status === 'unpaid') $status = 'Pending';
                elseif ($status === 'paid') $status = 'Paid';
                elseif ($status === 'overdue') $status = 'Overdue';
                elseif ($status === 'partial') $status = 'Partial';
                
                $bills[] = [
                    'id' => $row['id'],
                    'billId' => 'BILL-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT),
                    'personName' => $row['full_name'],
                    'status' => $status,
                    'billDate' => $row['bill_date'],
                    'dueDate' => $row['due_date'],
                    'amount' => 'SAR ' . number_format($row['total_amount'], 2),
                    'accountId' => $row['account_number'],
                    'rawAmount' => $row['total_amount']
                ];
            }
        }
        
        echo json_encode($bills);
        exit;
    }
    
    elseif ($action === 'get_account_info') {
        $accountId = $_GET['accountId'] ?? '';
        $accountId = $conn->real_escape_string($accountId);
        
        $sql = "SELECT a.id, a.account_number, a.account_type, u.full_name, u.phone_number,
                       p.address, p.city, p.connection_number
                FROM accounts a
                JOIN users u ON a.user_id = u.id
                LEFT JOIN premises p ON a.id = p.account_id
                WHERE a.account_number = '$accountId' LIMIT 1";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            echo json_encode(['error' => 'Query error: ' . $conn->error]);
            exit;
        }
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            // Store account ID in session for tariff calculations
            $_SESSION['current_account_id'] = $row['id'];
            echo json_encode([
                'id' => $row['id'],
                'account_id' => $row['account_number'],
                'person_name' => $row['full_name'],
                'phone' => $row['phone_number'],
                'address' => $row['address'],
                'city' => $row['city'],
                'connection_number' => $row['connection_number'],
                'account_type' => $row['account_type']
            ]);
        } else {
            echo json_encode(['error' => 'Account not found']);
        }
        exit;
    }
    
    elseif ($action === 'save_bill_draft') {
        // Handle POST data for bill saving
        header('Content-Type: application/json');
        $accountId = $_POST['accountId'] ?? '';
        $billDate = $_POST['billDate'] ?? '';
        $dueDate = $_POST['dueDate'] ?? '';
        $totalAmount = $_POST['totalAmount'] ?? 0;
        
        if (empty($accountId) || empty($billDate) || empty($dueDate)) {
            echo json_encode(['error' => 'Missing required fields: accountId, billDate, dueDate']);
            exit;
        }
        
        // Get account ID from account number
        $accountId = $conn->real_escape_string($accountId);
        $sql = "SELECT id FROM accounts WHERE account_number = '$accountId' LIMIT 1";
        $result = $conn->query($sql);
        
        if (!$result || $result->num_rows === 0) {
            echo json_encode(['error' => 'Account not found']);
            exit;
        }
        
        $accRow = $result->fetch_assoc();
        $accountDbId = $accRow['id'];
        
        // Insert bill record
        $billDate = $conn->real_escape_string($billDate);
        $dueDate = $conn->real_escape_string($dueDate);
        $totalAmount = (float)$totalAmount;
        
        $insertSql = "INSERT INTO bills (account_id, bill_date, due_date, total_amount, status, created_at)
                      VALUES ($accountDbId, '$billDate', '$dueDate', $totalAmount, 'draft', NOW())";
        
        if ($conn->query($insertSql)) {
            $billId = $conn->insert_id;
            echo json_encode([
                'success' => true,
                'billId' => 'BILL-' . str_pad($billId, 5, '0', STR_PAD_LEFT),
                'id' => $billId
            ]);
        } else {
            echo json_encode(['error' => 'Failed to save bill: ' . $conn->error]);
        }
        exit;
    }
    
    elseif ($action === 'calculate_segment_amount') {
        // Calculate segment amount based on current tariffs
        header('Content-Type: application/json');
        $segmentName = $_GET['segmentName'] ?? '';
        $consumption = $_GET['consumption'] ?? 0;
        
        // Get account type from session (employee's current working account)
        $accountId = $_SESSION['current_account_id'] ?? 1;
        
        $accountSql = "SELECT account_type FROM accounts WHERE id = " . (int)$accountId . " LIMIT 1";
        $accountResult = $conn->query($accountSql);
        
        if (!$accountResult || $accountResult->num_rows === 0) {
            echo json_encode(['error' => 'Account not found']);
            exit;
        }
        
        $accountRow = $accountResult->fetch_assoc();
        $accountType = $accountRow['account_type'];
        
        // Calculate amount based on tariff
        $calculatedAmount = calculateBillAmount($conn, $segmentName, $accountType, (float)$consumption);
        
        if ($calculatedAmount === null) {
            echo json_encode(['error' => 'No tariff found for this service type']);
            exit;
        }
        
        echo json_encode(['success' => true, 'amount' => $calculatedAmount]);
        exit;
    }
    
    elseif ($action === 'save_segment') {
        // Save individual segment to database with tariff-based amount calculation
        header('Content-Type: application/json');
        $billId = $_POST['billId'] ?? 0;
        $segmentName = $_POST['segmentName'] ?? '';
        $consumption = $_POST['consumption'] ?? 0;
        $status = $_POST['status'] ?? 'pending';
        $remarks = $_POST['remarks'] ?? '';
        
        if (empty($billId) || empty($segmentName) || empty($consumption)) {
            echo json_encode(['error' => 'Missing required fields: billId, segmentName, consumption']);
            exit;
        }
        
        $billId = (int)$billId;
        $segmentName = $conn->real_escape_string($segmentName);
        $consumption = (float)$consumption;
        $status = $conn->real_escape_string($status);
        $remarks = $conn->real_escape_string($remarks);
        
        // Get account info to determine account type for tariff lookup
        $billSql = "SELECT a.id as account_id, a.account_type FROM bills b 
                   JOIN accounts a ON b.account_id = a.id 
                   WHERE b.id = $billId LIMIT 1";
        $billResult = $conn->query($billSql);
        
        if (!$billResult || $billResult->num_rows === 0) {
            echo json_encode(['error' => 'Bill not found']);
            exit;
        }
        
        $billRow = $billResult->fetch_assoc();
        $accountType = $billRow['account_type'];
        
        // Calculate amount based on tariff
        $calculatedAmount = calculateBillAmount($conn, $segmentName, $accountType, $consumption);
        
        if ($calculatedAmount === null) {
            echo json_encode(['error' => 'No tariff found for this service type and account type']);
            exit;
        }
        
        // Get premise_id for this account
        $premiseSql = "SELECT id FROM premises WHERE account_id = " . $billRow['account_id'] . " LIMIT 1";
        $premiseResult = $conn->query($premiseSql);
        
        if (!$premiseResult || $premiseResult->num_rows === 0) {
            echo json_encode(['error' => 'No premise found for this account']);
            exit;
        }
        
        $premiseRow = $premiseResult->fetch_assoc();
        $premiseId = $premiseRow['id'];
        
        // Insert segment with calculated amount
        $insertSegmentSql = "INSERT INTO bill_segments (bill_id, premise_id, name, consumption, amount, status, remarks)
                             VALUES ($billId, $premiseId, '$segmentName', $consumption, $calculatedAmount, '$status', '$remarks')";
        
        if ($conn->query($insertSegmentSql)) {
            echo json_encode(['success' => true, 'calculatedAmount' => $calculatedAmount]);
        } else {
            echo json_encode(['error' => 'Failed to save segment: ' . $conn->error]);
        }
        exit;
    }

    elseif ($action === 'delete_segments') {
        // Handle deletion of selected segments
        $segmentIds = $_POST['segmentIds'] ?? '[]';
        $segmentIds = json_decode($segmentIds, true);
        
        if (empty($segmentIds) || !is_array($segmentIds)) {
            echo json_encode(['success' => false, 'error' => 'No segments to delete']);
            exit;
        }
        
        $deletedCount = 0;
        foreach ($segmentIds as $segmentId) {
            $segmentId = (int)$segmentId;
            $deleteSql = "DELETE FROM bill_segments WHERE id = $segmentId";
            if ($conn->query($deleteSql)) {
                $deletedCount++;
            }
        }
        
        if ($deletedCount > 0) {
            echo json_encode(['success' => true, 'message' => "$deletedCount segment(s) deleted successfully"]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete segments']);
        }
        exit;
    }

    elseif ($action === 'freeze_complete_segments') {
        // Handle freezing/completing selected segments
        $segmentIds = $_POST['segmentIds'] ?? '[]';
        $segmentIds = json_decode($segmentIds, true);
        
        if (empty($segmentIds) || !is_array($segmentIds)) {
            echo json_encode(['success' => false, 'error' => 'No segments to freeze']);
            exit;
        }
        
        $updatedCount = 0;
        foreach ($segmentIds as $segmentId) {
            $segmentId = (int)$segmentId;
            $updateSql = "UPDATE bill_segments SET status = 'completed' WHERE id = $segmentId";
            if ($conn->query($updateSql)) {
                $updatedCount++;
            }
        }
        
        echo json_encode(['success' => true, 'message' => "$updatedCount segment(s) marked as Completed"]);
        exit;
    }

    elseif ($action === 'reopen_segments') {
        // Handle reopening selected segments
        $segmentIds = $_POST['segmentIds'] ?? '[]';
        $segmentIds = json_decode($segmentIds, true);
        
        if (empty($segmentIds) || !is_array($segmentIds)) {
            echo json_encode(['success' => false, 'error' => 'No segments to reopen']);
            exit;
        }
        
        $updatedCount = 0;
        foreach ($segmentIds as $segmentId) {
            $segmentId = (int)$segmentId;
            $updateSql = "UPDATE bill_segments SET status = 'pending_review' WHERE id = $segmentId";
            if ($conn->query($updateSql)) {
                $updatedCount++;
            }
        }
        
        echo json_encode(['success' => true, 'message' => "$updatedCount segment(s) reopened to pending review"]);
        exit;
    }

    elseif ($action === 'correction_note_segments') {
        // Handle adding correction note to segments
        $segmentIds = $_POST['segmentIds'] ?? '[]';
        $note = $_POST['note'] ?? '';
        $segmentIds = json_decode($segmentIds, true);
        
        if (empty($segmentIds) || !is_array($segmentIds) || empty($note)) {
            echo json_encode(['success' => false, 'error' => 'Missing segment IDs or note']);
            exit;
        }
        
        $note = $conn->real_escape_string($note);
        $updatedCount = 0;
        foreach ($segmentIds as $segmentId) {
            $segmentId = (int)$segmentId;
            $updateSql = "UPDATE bill_segments SET remarks = CONCAT(IFNULL(remarks, ''), '\n[CORRECTION NOTE] $note'), status = 'correction_note' WHERE id = $segmentId";
            if ($conn->query($updateSql)) {
                $updatedCount++;
            }
        }
        
        echo json_encode(['success' => true, 'message' => "$updatedCount segment(s) marked with correction note"]);
        exit;
    }

    elseif ($action === 'undo_correction_note_segments') {
        // Handle removing correction note from segments
        $segmentIds = $_POST['segmentIds'] ?? '[]';
        $segmentIds = json_decode($segmentIds, true);
        
        if (empty($segmentIds) || !is_array($segmentIds)) {
            echo json_encode(['success' => false, 'error' => 'No segments to update']);
            exit;
        }
        
        $updatedCount = 0;
        foreach ($segmentIds as $segmentId) {
            $segmentId = (int)$segmentId;
            // Remove [CORRECTION NOTE] prefix from remarks and reset status
            $updateSql = "UPDATE bill_segments SET remarks = REPLACE(remarks, '[CORRECTION NOTE]', ''), status = 'pending_review' WHERE id = $segmentId";
            if ($conn->query($updateSql)) {
                $updatedCount++;
            }
        }
        
        echo json_encode(['success' => true, 'message' => "$updatedCount segment(s) correction note undone"]);
        exit;
    }
    
    elseif ($action === 'submit_bill_for_review') {
        // Handle POST data for bill submission
        $accountId = $_POST['accountId'] ?? '';
        $billDate = $_POST['billDate'] ?? '';
        $dueDate = $_POST['dueDate'] ?? '';
        $totalAmount = $_POST['totalAmount'] ?? 0;
        $segments = $_POST['segments'] ?? '[]';
        
        if (empty($accountId) || empty($billDate)) {
            echo 'error: Missing required fields';
            exit;
        }
        
        // Get account ID from account number
        $accountId = $conn->real_escape_string($accountId);
        $sql = "SELECT id FROM accounts WHERE account_number = '$accountId' LIMIT 1";
        $result = $conn->query($sql);
        
        if (!$result || $result->num_rows === 0) {
            echo 'error: Account not found';
            exit;
        }
        
        $accRow = $result->fetch_assoc();
        $accountDbId = $accRow['id'];
        
        // Get a premise for this account
        $premiseSql = "SELECT id FROM premises WHERE account_id = $accountDbId LIMIT 1";
        $premiseResult = $conn->query($premiseSql);
        $premiseId = 1;
        if ($premiseResult && $premiseResult->num_rows > 0) {
            $premiseRow = $premiseResult->fetch_assoc();
            $premiseId = $premiseRow['id'];
        }
        
        // Insert bill record with pending_review status
        $billDate = $conn->real_escape_string($billDate);
        $dueDate = $conn->real_escape_string($dueDate);
        $totalAmount = (float)$totalAmount;
        
        $insertSql = "INSERT INTO bills (account_id, bill_date, due_date, total_amount, status, created_at)
                      VALUES ($accountDbId, '$billDate', '$dueDate', $totalAmount, 'pending_review', NOW())";
        
        if ($conn->query($insertSql)) {
            $billId = $conn->insert_id;
            
            // Now save the segments to this bill
            $segmentsData = json_decode($segments, true);
            if (!empty($segmentsData) && is_array($segmentsData)) {
                foreach ($segmentsData as $segment) {
                    $segName = $conn->real_escape_string($segment['name'] ?? '');
                    $segAmount = (float)($segment['amount'] ?? 0);
                    $segConsumption = (float)($segment['consumption'] ?? 0);
                    $segStatus = $conn->real_escape_string($segment['status'] ?? 'pending_review');
                    $segRemarks = $conn->real_escape_string($segment['remarks'] ?? '');
                    
                    $segmentSql = "INSERT INTO bill_segments (bill_id, premise_id, name, consumption, amount, status, remarks)
                                   VALUES ($billId, $premiseId, '$segName', $segConsumption, $segAmount, '$segStatus', '$segRemarks')";
                    $conn->query($segmentSql);
                }
            }
            
            echo 'success: Bill BILL-' . str_pad($billId, 5, '0', STR_PAD_LEFT) . ' submitted for review';
        } else {
            echo 'error: Failed to submit bill: ' . $conn->error;
        }
        exit;
    }
    
    echo '';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png" />
    <link rel="icon" type="image/png" href="./assets/img/favicon.png" />
    <title>NWC Billing System - Control Central</title>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link href="./assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="./assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <link href="./assets/css/argon-dashboard-tailwind.css?v=1.0.1" rel="stylesheet" />
    <link href="./assets/css/perfect-scrollbar.css" rel="stylesheet" />
    <link href="./assets/css/tooltips.css" rel="stylesheet" />
    <style>
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .page-fade-in { animation: fadeIn 0.3s ease-in-out; }
        
        body { position: relative; }
        .absolute.bg-blue-500 { position: fixed !important; top: 0 !important; z-index: 1 !important; }
        main { position: relative; z-index: 10; }
        
        .section-header { cursor: pointer; user-select: none; transition: color 0.2s; }
        .section-header:hover { color: #3b82f6; }
        .section-header i { transition: transform 0.3s ease; display: inline-block; }
        
        .section-content { display: block; max-height: 1000px; overflow: hidden; transition: max-height 0.3s ease, padding 0.3s ease; }
        .section-header.collapsed ~ .section-content { max-height: 0 !important; padding: 0 !important; }
        .section-header.collapsed i { transform: rotate(-90deg); }
    </style>
</head>
<body class="m-0 font-sans text-base antialiased font-normal dark:bg-slate-900 leading-default bg-gray-50 text-slate-500">
    <div class="absolute w-full bg-blue-500 dark:hidden min-h-75"></div>
    
    <!-- SIDEBAR NAVIGATION -->
    <aside id="sidenav-main" class="fixed inset-y-0 flex-wrap items-center justify-between block w-full p-0 my-4 overflow-y-auto antialiased transition-transform duration-200 -translate-x-full bg-white border-0 shadow-xl dark:shadow-none dark:bg-slate-850 max-w-64 ease-nav-brand z-990 xl:ml-6 rounded-2xl xl:left-0 xl:translate-x-0" aria-expanded="false">
        <div class="h-19">
            <i class="absolute top-0 right-0 p-4 opacity-50 cursor-pointer fas fa-times dark:text-white text-slate-400 xl:hidden" sidenav-close></i>
            <a class="block px-8 py-6 m-0 text-sm whitespace-nowrap dark:text-white text-slate-700" href="javascript:void(0)" onclick="app.navigate('dashboard')">
                <i class="ni ni-water-bottle text-2xl text-blue-500"></i>
                <span class="ml-1 font-semibold transition-all duration-200 ease-nav-brand">NWC Billing</span>
            </a>
        </div>

        <hr class="h-px mt-0 bg-transparent bg-gradient-to-r from-transparent via-black/40 to-transparent dark:bg-gradient-to-r dark:from-transparent dark:via-white dark:to-transparent" />

        <div class="items-center block w-auto max-h-screen overflow-auto h-sidenav grow basis-full">
            <ul class="flex flex-col pl-0 mb-0">
                <li class="mt-0.5 w-full"><a class="py-2.7 bg-blue-500/13 dark:text-white dark:opacity-80 text-sm ease-nav-brand my-0 mx-2 flex items-center whitespace-nowrap rounded-lg px-4 font-semibold text-slate-700 transition-colors nav-link active" href="javascript:void(0)" onclick="app.navigate('dashboard')" data-page="dashboard"><i class="ni ni-tv-2 text-xl text-blue-500 mr-2"></i><span>Dashboard</span></a></li>
                <li class="mt-0.5 w-full"><a class="dark:text-white dark:opacity-80 py-2.7 text-sm ease-nav-brand my-0 mx-2 flex items-center whitespace-nowrap px-4 transition-colors nav-link" href="javascript:void(0)" onclick="app.navigate('search')" data-page="search"><i class="ni ni-zoom-split-in text-xl text-cyan-500 mr-2"></i><span>Search Accounts</span></a></li>
                <li class="mt-0.5 w-full"><a class="dark:text-white dark:opacity-80 py-2.7 text-sm ease-nav-brand my-0 mx-2 flex items-center whitespace-nowrap px-4 transition-colors nav-link" href="javascript:void(0)" onclick="app.navigate('meter')" data-page="meter"><i class="ni ni-chart-bar-32 text-xl text-emerald-500 mr-2"></i><span>Meter Readings</span></a></li>
                <li class="mt-0.5 w-full"><a class="dark:text-white dark:opacity-80 py-2.7 text-sm ease-nav-brand my-0 mx-2 flex items-center whitespace-nowrap px-4 transition-colors nav-link" href="javascript:void(0)" onclick="app.navigate('bills')" data-page="bills"><i class="ni ni-credit-card text-xl text-orange-500 mr-2"></i><span>Bills</span></a></li>
                <li class="mt-0.5 w-full"><a class="dark:text-white dark:opacity-80 py-2.7 text-sm ease-nav-brand my-0 mx-2 flex items-center whitespace-nowrap px-4 transition-colors nav-link" href="javascript:void(0)" onclick="app.logout()" data-page="logout"><i class="ni ni-button-pause text-xl text-red-600 mr-2"></i><span>Logout</span></a></li>
            </ul>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <main class="relative h-full max-h-screen transition-all duration-200 ease-in-out xl:ml-68 rounded-xl">
        <!-- NAVBAR -->
        <nav class="relative flex flex-wrap items-center justify-between px-0 py-2 mx-6 transition-all ease-in shadow-none duration-250 rounded-2xl lg:flex-nowrap lg:justify-start" navbar-main navbar-scroll="false">
            <div class="flex items-center justify-between w-full px-4 py-1 mx-auto flex-wrap-inherit">
                <nav>
                    <ol class="flex flex-wrap pt-1 mr-12 bg-transparent rounded-lg sm:mr-16">
                        <li class="text-sm leading-normal"><a class="text-white opacity-50" href="javascript:void(0)">Pages</a></li>
                        <li class="text-sm pl-2 capitalize leading-normal text-white before:float-left before:pr-2 before:text-white before:content-['/']" id="breadcrumb">Dashboard</li>
                    </ol>
                    <h6 class="mb-0 font-bold text-white capitalize" id="pageTitle">Dashboard</h6>
                </nav>

                <div class="flex items-center mt-2 grow sm:mt-0 sm:mr-6 md:mr-0 lg:flex lg:basis-auto">
                    <div class="flex md:mr-4">
                        <input type="text" placeholder="Type here..." class="px-3 py-2 text-sm border border-slate-200 rounded-lg dark:bg-slate-850 dark:border-slate-600 dark:text-white" />
                    </div>
                    <ul class="flex flex-row justify-end pl-0 mb-0 list-none md-max:w-full">
                        <li class="flex items-center"><a href="javascript:void(0)" class="block px-0 py-2 text-white transition-all ease-nav-brand text-sm"><i class="fa fa-user"></i></a></li>
                        <li class="flex items-center"><a href="javascript:void(0)" class="block px-0 py-2 text-white transition-all ease-nav-brand text-sm"><i class="fa fa-cog"></i></a></li>
                        <li class="flex items-center"><a href="javascript:void(0)" class="block px-0 py-2 text-white transition-all ease-nav-brand text-sm"><i class="fa fa-bell"></i></a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- PAGE CONTENT -->
        <div class="w-full px-6 py-6 mx-auto" id="pageContent"></div>
    </main>

    <!-- SIDENAV TOGGLE FOR MOBILE -->
    <div class="fixed inset-0 z-50 hidden bg-black/20" sidenav-backdrop></div>

    <script src="./assets/js/perfect-scrollbar.js"></script>
    <script src="./assets/js/argon-dashboard-tailwind.js?v=1.0.0"></script>

    <script>
        const app = {
            currentPage: 'dashboard',
            billSegments: [],
            selectedSegments: {}, // Track selected segments for actions
            currentBillId: null,

            navigate(pageName) {
                app.currentPage = pageName;
                
                // Hide bill modal when navigating away from Bills
                const billModal = document.getElementById('billsModal');
                if (billModal && pageName !== 'bills') {
                    billModal.style.display = 'none';
                }
                
                const pageContent = document.getElementById('pageContent');
                const breadcrumb = document.getElementById('breadcrumb');
                const pageTitle = document.getElementById('pageTitle');
                
                if (!pageContent || !breadcrumb || !pageTitle) {
                    console.warn('Page elements not found');
                    return;
                }
                
                if (pages[pageName]) {
                    const page = pages[pageName];
                    pageTitle.textContent = page.title;
                    breadcrumb.textContent = page.breadcrumb;
                    pageContent.className = 'w-full px-6 py-6 mx-auto page-fade-in';
                    page.render(pageContent);
                    
                    // Update nav link
                    const navLinks = document.querySelectorAll('.nav-link');
                    if (navLinks) {
                        navLinks.forEach(link => {
                            link.classList.remove('bg-blue-500/13', 'active');
                        });
                    }
                    const currentNavLink = document.querySelector(`[data-page="${pageName}"]`);
                    if (currentNavLink) {
                        currentNavLink.classList.add('bg-blue-500/13', 'active');
                    }
                    
                    // Close mobile sidebar
                    if (window.innerWidth < 1024) {
                        const sidebar = document.querySelector('[sidenav-main]');
                        if (sidebar) {
                            sidebar.classList.add('-translate-x-full');
                        }
                    }
                }
            },

            toggleSection(event) {
                const btn = event.currentTarget;
                btn.classList.toggle('collapsed');
                event.preventDefault();
                event.stopPropagation();
            },

            updateSearchFields() {
                const searchType = document.getElementById('searchType')?.value;
                const fieldsDiv = document.getElementById('searchFields');
                if (!fieldsDiv) return;
                const config = {
                    name: '<input type="text" placeholder="Name" class="px-4 py-2 border border-gray-300 rounded-lg dark:bg-slate-800 dark:text-white"><input type="text" placeholder="Address" class="px-4 py-2 border border-gray-300 rounded-lg dark:bg-slate-800 dark:text-white"><input type="text" placeholder="City" class="px-4 py-2 border border-gray-300 rounded-lg dark:bg-slate-800 dark:text-white">',
                    account_id: '<input type="text" placeholder="Account ID" class="px-4 py-2 border border-gray-300 rounded-lg dark:bg-slate-800 dark:text-white">',
                    phone: '<input type="tel" placeholder="Phone" class="px-4 py-2 border border-gray-300 rounded-lg dark:bg-slate-800 dark:text-white">'
                };
                fieldsDiv.innerHTML = config[searchType] || '';
            },

            performSearch() {
                const searchType = document.getElementById('searchType')?.value;
                const fieldsDiv = document.getElementById('searchFields');
                let searchValue = '';
                
                const inputs = fieldsDiv?.querySelectorAll('input');
                if (inputs && inputs.length > 0) {
                    searchValue = inputs[0].value;
                }
                
                if (!searchValue) {
                    alert('Please enter a search value');
                    return;
                }
                
                const params = new URLSearchParams();
                params.append('action', 'search_accounts');
                params.append('searchType', searchType);
                params.append('searchValue', searchValue);
                
                fetch('/NWCBilling/build/Employee.php?' + params.toString())
                    .then(response => response.text())
                    .then(text => {
                        try {
                            const data = JSON.parse(text);
                            const resultsList = document.getElementById('searchResultsList');
                            
                            if (data && data.length > 0) {
                                resultsList.innerHTML = data.map(acc => `
                                    <div class="border-b border-gray-200 dark:border-slate-700 last:border-b-0 hover:bg-blue-50 dark:hover:bg-slate-700 cursor-pointer transition-colors p-4" onclick="app.loadAccountDetails('${acc.id}')">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <p class="font-semibold text-slate-700 dark:text-white text-sm">${acc.full_name}</p>
                                                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">Account: <span class="font-mono font-semibold">${acc.account_number}</span></p>
                                                <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">Phone: ${acc.phone_number || 'N/A'}</p>
                                            </div>
                                            <div class="text-right ml-4">
                                                <p class="font-semibold text-emerald-600 dark:text-emerald-400 text-sm">${acc.balance || 'SAR 0'}</p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Balance</p>
                                            </div>
                                        </div>
                                    </div>
                                `).join('');
                                document.getElementById('resultsSection').style.display = 'block';
                            } else {
                                resultsList.innerHTML = '<div class="p-6 text-center"><p class="text-slate-500 dark:text-slate-400">No accounts found</p></div>';
                                document.getElementById('resultsSection').style.display = 'block';
                            }
                        } catch (e) {
                            console.error('Parse error:', text);
                            alert('Error: ' + text);
                        }
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        alert('Error searching accounts.');
                    });
            },

            loadAccountDetails(accountId) {
                const params = new URLSearchParams();
                params.append('action', 'get_customer_info');
                params.append('accountId', accountId);
                
                console.log('Loading account details for ID:', accountId);
                
                fetch('/NWCBilling/build/Employee.php?' + params.toString())
                    .then(response => response.text())
                    .then(text => {
                        console.log('Response text:', text);
                        try {
                            const data = JSON.parse(text);
                            console.log('Parsed data:', data);
                            if (data && data.account) {
                                const account = data.account;
                                
                                // Format phone number (mask some digits)
                                const phone = account.phone_number || '-';
                                const maskedPhone = phone.length > 4 ? '*'.repeat(phone.length - 4) + phone.slice(-4) : phone;
                                
                                // Format account info with type
                                const accountInfo = account.account_number ? 
                                    `${account.account_number} - ${account.account_type || 'Residential'} Customer Class` : '-';
                                
                                // Format premise address with connection number
                                const premiseAddress = account.address ? 
                                    `${account.address}, ${account.city || ''} ${account.connection_number ? '(Connection #' + account.connection_number + ')' : ''}`.trim() : 'N/A';
                                
                                // Update Current Context section
                                document.getElementById('ctx-person').textContent = `${account.full_name || '-'} - Mobile Phone ${maskedPhone}`;
                                document.getElementById('ctx-accountId').textContent = accountInfo;
                                document.getElementById('ctx-balance').textContent = 'SAR 0 - Good';
                                document.getElementById('ctx-premise').textContent = premiseAddress;
                                
                                // Update Summary Balance
                                const currentBalance = parseFloat(data.currentBalance || 0);
                                document.getElementById('summaryBalance').textContent = 'SAR ' + currentBalance.toFixed(2);
                                
                                // Update Financial Information
                                document.getElementById('fin-balance').textContent = 'SAR ' + currentBalance.toFixed(2);
                                
                                if (data.lastPayment) {
                                    document.getElementById('fin-lastPayment').textContent = 
                                        new Date(data.lastPayment.payment_date).toLocaleDateString('en-GB') + ', SAR ' + parseFloat(data.lastPayment.amount).toFixed(2);
                                } else {
                                    document.getElementById('fin-lastPayment').textContent = '-';
                                }
                                
                                if (data.lastBill) {
                                    document.getElementById('fin-lastBilled').textContent = 
                                        new Date(data.lastBill.bill_date).toLocaleDateString('en-GB') + ', SAR ' + parseFloat(data.lastBill.total_amount).toFixed(2) + 
                                        ', Due: ' + new Date(data.lastBill.due_date).toLocaleDateString('en-GB');
                                } else {
                                    document.getElementById('fin-lastBilled').textContent = '-';
                                }
                                
                                if (data.prevBill) {
                                    document.getElementById('fin-prevBill').textContent = 
                                        new Date(data.prevBill.bill_date).toLocaleDateString('en-GB') + ', SAR ' + parseFloat(data.prevBill.total_amount).toFixed(2);
                                } else {
                                    document.getElementById('fin-prevBill').textContent = '-';
                                }
                                
                                // Update Customer Info
                                document.getElementById('cust-accountNumber').textContent = account.account_number || '-';
                                document.getElementById('cust-class').textContent = (account.account_type || 'Residential') + ' Customer';
                                document.getElementById('cust-balance').textContent = 'SAR ' + currentBalance.toFixed(2);
                                
                                // Update Meter Info
                                if (data.meter) {
                                    console.log('Meter data:', data.meter);
                                    const meterId = document.getElementById('meter-id');
                                    const meterStatus = document.getElementById('meter-status');
                                    if (meterId) meterId.textContent = data.meter.meter_number || 'N/A';
                                    if (meterStatus) meterStatus.textContent = (data.meter.status || 'unknown').toUpperCase();
                                    
                                    const meterInfoId = document.getElementById('meterInfo-id');
                                    const meterInfoStatus = document.getElementById('meterInfo-status');
                                    if (meterInfoId) meterInfoId.textContent = data.meter.meter_number || 'N/A';
                                    if (meterInfoStatus) meterInfoStatus.textContent = (data.meter.status || 'unknown').toUpperCase();
                                } else {
                                    console.warn('No meter data available');
                                }
                                
                                // Update Latest Meter Reading
                                if (data.latestReading) {
                                    console.log('Latest reading:', data.latestReading);
                                    const meterReading = document.getElementById('meter-reading');
                                    const meterDate = document.getElementById('meter-date');
                                    const meterInfoLastReading = document.getElementById('meterInfo-lastReading');
                                    const meterInfoReadingDate = document.getElementById('meterInfo-readingDate');
                                    
                                    if (meterReading) meterReading.textContent = data.latestReading.value + ' m³';
                                    if (meterDate) meterDate.textContent = new Date(data.latestReading.reading_date).toLocaleDateString('en-GB');
                                    if (meterInfoLastReading) meterInfoLastReading.textContent = data.latestReading.value + ' m³';
                                    if (meterInfoReadingDate) meterInfoReadingDate.textContent = new Date(data.latestReading.reading_date).toLocaleDateString('en-GB');
                                } else {
                                    console.warn('No latest reading data');
                                    const meterReading = document.getElementById('meter-reading');
                                    const meterDate = document.getElementById('meter-date');
                                    const meterInfoLastReading = document.getElementById('meterInfo-lastReading');
                                    const meterInfoReadingDate = document.getElementById('meterInfo-readingDate');
                                    if (meterReading) meterReading.textContent = 'No reading';
                                    if (meterDate) meterDate.textContent = '-';
                                    if (meterInfoLastReading) meterInfoLastReading.textContent = 'No reading';
                                    if (meterInfoReadingDate) meterInfoReadingDate.textContent = '-';
                                }
                                
                                // Update Service Agreements
                                if (data.agreement) {
                                    console.log('Service agreement:', data.agreement);
                                    const agreementId = document.getElementById('agreement-id');
                                    const agreementType = document.getElementById('agreement-type');
                                    const agreementStatus = document.getElementById('agreement-status');
                                    const agreementStartDate = document.getElementById('agreement-startDate');
                                    
                                    if (agreementId) agreementId.textContent = data.agreement.id || '-';
                                    if (agreementType) agreementType.textContent = (data.agreement.agreement_type || 'Water Supply').toUpperCase();
                                    if (agreementStatus) agreementStatus.textContent = (data.agreement.status || 'Active').toUpperCase();
                                    if (agreementStartDate) agreementStartDate.textContent = data.agreement.start_date ? new Date(data.agreement.start_date).toLocaleDateString('en-GB') : '-';
                                } else {
                                    console.log('No service agreement data');
                                    const agreementId = document.getElementById('agreement-id');
                                    const agreementType = document.getElementById('agreement-type');
                                    const agreementStatus = document.getElementById('agreement-status');
                                    const agreementStartDate = document.getElementById('agreement-startDate');
                                    if (agreementId) agreementId.textContent = '-';
                                    if (agreementType) agreementType.textContent = '-';
                                    if (agreementStatus) agreementStatus.textContent = 'Active';
                                    if (agreementStartDate) agreementStartDate.textContent = '-';
                                }
                                
                                // Show the details section
                                document.getElementById('accountDetailsSection').style.display = 'block';
                                
                                // Load billing information
                                app.loadBillingInfo(accountId);
                                
                                // Scroll to details section
                                setTimeout(() => {
                                    document.getElementById('accountDetailsSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
                                }, 100);
                            } else {
                                console.error('No account data in response:', data);
                            }
                        } catch (e) {
                            console.error('Parse error:', e, 'Text:', text);
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                    });
            },

            loadBillingInfo(accountId) {
                const params = new URLSearchParams();
                params.append('action', 'get_billing_info');
                params.append('accountId', accountId);
                
                fetch('/NWCBilling/build/Employee.php?' + params.toString())
                    .then(response => response.text())
                    .then(text => {
                        console.log('Raw response:', text);
                        try {
                            const data = JSON.parse(text);
                            console.log('Parsed billing data:', data);
                            
                            if (data.error) {
                                console.error('Server error:', data.error);
                                return;
                            }
                            
                            const billsList = document.getElementById('billPaymentList');
                            
                            if (data.bills && data.bills.length > 0) {
                                billsList.innerHTML = data.bills.map(bill => `
                                    <div class="ml-0 mt-2 flex items-start">
                                        <span class="cursor-pointer hover:text-blue-600 text-slate-700 dark:text-slate-300">
                                            📄 Bill - Date: ${bill.bill_date}, ${bill.status}, Due: ${bill.due_date}, Amount: ${bill.total_amount}
                                        </span>
                                    </div>
                                `).join('');
                            } else {
                                billsList.innerHTML = '<div class="ml-4 flex items-start"><span class="text-slate-700 dark:text-slate-300">No bills available</span></div>';
                            }
                            
                            // Update Water Billing History section with meter readings
                            if (data.readings && data.readings.length > 0) {
                                const readings = data.readings;
                                
                                // Last 3 months usage
                                const last3Months = readings.slice(0, 3).map(r => r.value).join(', ');
                                const histElement = document.getElementById('hist-3months');
                                if (histElement) histElement.textContent = last3Months || '-';
                                
                                // Average usage
                                const totalUsage = readings.reduce((sum, r) => sum + parseFloat(r.value || 0), 0);
                                const avgUsage = (totalUsage / readings.length).toFixed(2);
                                const avgElement = document.getElementById('hist-avgUsage');
                                if (avgElement) avgElement.textContent = avgUsage + ' m³';
                                
                                // Trend (compare first half vs second half)
                                if (readings.length >= 6) {
                                    const firstHalf = readings.slice(3, 6).map(r => parseFloat(r.value || 0)).reduce((a, b) => a + b, 0) / 3;
                                    const secondHalf = readings.slice(0, 3).map(r => parseFloat(r.value || 0)).reduce((a, b) => a + b, 0) / 3;
                                    const trend = secondHalf > firstHalf ? '📈 Increasing' : secondHalf < firstHalf ? '📉 Decreasing' : '➡️ Stable';
                                    const trendElement = document.getElementById('hist-trend');
                                    if (trendElement) trendElement.textContent = trend;
                                }
                            }
                        } catch (e) {
                            console.error('Parse error:', e, 'Text:', text);
                        }
                    })
                    .catch(error => console.error('Error:', error));
            },

            clearSearch() {
                document.getElementById('resultsSection').style.display = 'none';
                document.getElementById('accountDetailsSection').style.display = 'none';
            },

            switchTab(event) {
                const btn = event.target;
                const tab = btn.getAttribute('data-tab');
                document.querySelectorAll('.accountTab').forEach(b => {
                    b.classList.remove('bg-blue-500', 'text-white');
                    b.classList.add('bg-gray-200', 'text-slate-700', 'dark:bg-slate-700');
                });
                btn.classList.remove('bg-gray-200', 'text-slate-700', 'dark:bg-slate-700');
                btn.classList.add('bg-blue-500', 'text-white');
                document.querySelectorAll('.accountTabContent').forEach(c => c.style.display = 'none');
                const tabElement = document.getElementById(tab + '-tab');
                if (tabElement) tabElement.style.display = 'block';
            },

            searchBills() {
                // Get form values
                const personNameInput = document.querySelector('#billsModal input[placeholder="Enter customer name"]');
                const accountIdInput = document.querySelector('#billsModal input[placeholder="Enter account ID"]');
                const billIdInput = document.querySelector('#billsModal input[placeholder="Enter bill ID"]');
                const billDateInput = document.querySelector('#billsModal input[type="date"]');
                const crNoteInput = document.querySelector('#billsModal input[placeholder="Enter CR note or reference number"]');
                
                const personName = personNameInput?.value || '';
                const accountId = accountIdInput?.value || '';
                const billId = billIdInput?.value || '';
                const billDate = billDateInput?.value || '';
                const crNote = crNoteInput?.value || '';
                
                // At least one search field must be filled
                if (!personName && !accountId && !billId && !billDate && !crNote) {
                    alert('Please enter at least one search criteria');
                    return;
                }
                
                // Build fetch URL with parameters
                const params = new URLSearchParams();
                params.append('action', 'search_bills');
                if (personName) params.append('personName', personName);
                if (accountId) params.append('accountId', accountId);
                if (billId) params.append('billId', billId);
                if (billDate) params.append('billDate', billDate);
                if (crNote) params.append('crNote', crNote);
                
                // Fetch from backend
                fetch('/NWCBilling/build/Employee.php?' + params.toString())
                    .then(response => response.text())
                    .then(text => {
                        try {
                            const bills = JSON.parse(text);
                            
                            if (bills.error) {
                                alert('Error: ' + bills.error);
                                return;
                            }
                            
                            const tbody = document.getElementById('billsTableBody');
                            tbody.innerHTML = '';
                            
                            if (bills.length === 0) {
                                tbody.innerHTML = '<tr style="border-bottom: 1px solid #e5e7eb;"><td colspan="8" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem;">No bills found matching your criteria</td></tr>';
                                return;
                            }
                            
                            bills.forEach(bill => {
                                const statusColor = bill.status === 'Paid' ? '#10b981' : 
                                                   bill.status === 'Pending' ? '#f59e0b' : 
                                                   bill.status === 'Overdue' ? '#ef4444' : '#6b7280';
                                
                                const row = document.createElement('tr');
                                row.style.borderBottom = '1px solid #e5e7eb';
                                row.innerHTML = `
                                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; color: #374151; font-size: 0.875rem;">${bill.personName}</td>
                                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb;"><span style="background-color: ${statusColor}20; color: ${statusColor}; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">${bill.status}</span></td>
                                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; color: #374151; font-size: 0.875rem;">${bill.billDate}</td>
                                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; color: #374151; font-size: 0.875rem;">${bill.dueDate}</td>
                                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; color: #374151; font-weight: bold; font-size: 0.875rem;">${bill.amount}</td>
                                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; color: #3b82f6; font-size: 0.875rem;">${bill.billId}</td>
                                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; color: #6b7280; font-size: 0.875rem;">${bill.accountId}</td>
                                    <td style="padding: 1rem; text-align: center;"><button onclick="alert('Bill ID: ${bill.billId}\\nAmount: ${bill.amount}\\nDue Date: ${bill.dueDate}')" style="background-color: #3b82f6; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.375rem; cursor: pointer; font-size: 0.75rem; font-weight: 600;">View</button></td>
                                `;
                                tbody.appendChild(row);
                            });
                        } catch (e) {
                            console.error('Parse error:', text);
                            alert('Error: ' + text);
                        }
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        alert('Error searching bills: ' + error.message);
                    });
            },

            initGenerateBill() {
                // Reset bill segments array
                app.billSegments = [];
                
                // Clear the segments container
                const container = document.getElementById('billSegmentsContainer');
                if (container) {
                    container.innerHTML = '';
                }
                
                // Set default dates
                const today = new Date();
                const firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                const lastOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                
                document.getElementById('billDate').valueAsDate = firstOfMonth;
                document.getElementById('billDueDate').valueAsDate = lastOfMonth;
            },

            openBillSearch() {
                const modal = document.getElementById('billsModal');
                if (modal) {
                    modal.style.display = 'flex';
                }
            },

            loadBillAccountInfo() {
                const accountId = document.getElementById('billAccountId').value;
                
                if (!accountId) {
                    alert('Please enter an Account ID');
                    return;
                }
                
                // Fetch account information
                const params = new URLSearchParams();
                params.append('action', 'get_account_info');
                params.append('accountId', accountId);
                
                fetch('/NWCBilling/build/Employee.php?' + params.toString())
                    .then(response => response.text())
                    .then(text => {
                        try {
                            const data = JSON.parse(text);
                            
                            if (data.error) {
                                alert('Account not found: ' + data.error);
                                return;
                            }
                            
                            // Update form fields safely
                            const nameField = document.getElementById('billCustomerName');
                            const summaryAcct = document.getElementById('summaryAccount');
                            const summaryCustomer = document.getElementById('summaryCustomer');
                            
                            if (nameField) nameField.value = data.person_name || '-';
                            if (summaryAcct) summaryAcct.textContent = data.account_id || '-';
                            if (summaryCustomer) summaryCustomer.textContent = data.person_name || '-';
                            
                        } catch (e) {
                            console.error('Parse error:', e);
                            alert('Error loading account information');
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        alert('Error fetching account information');
                    });
            },

            loadDraftSegments() {
                const accountId = document.getElementById('billAccountId').value;
                
                if (!accountId) {
                    alert('Please enter an Account ID first');
                    return;
                }
                
                console.log('Loading ALL segments for account:', accountId);
                
                // First load account info
                app.loadBillAccountInfo();
                
                // Then fetch ALL bill segments via AJAX
                fetch('/NWCBilling/build/Employee.php?action=get_all_customer_bills&accountNumber=' + accountId)
                    .then(response => {
                        console.log('Response received:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Data received:', data);
                        if (data.success) {
                            app.displayAllCustomerSegments(data);
                        } else {
                            console.error('Error response:', data.error);
                            alert('Error: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        alert('Error loading segments: ' + error.message);
                    });
            },

            displayAllCustomerSegments(data) {
                const tbody = document.getElementById('billSegmentsTableBody');
                if (!tbody) {
                    console.warn('billSegmentsTableBody element not found');
                    return;
                }
                tbody.innerHTML = '';
                app.selectedSegments = {}; // Reset selection
                
                // Display ALL segments from ALL bills
                if (data.bills && data.bills.length > 0) {
                    data.bills.forEach(bill => {
                        if (bill.segments && bill.segments.length > 0) {
                            bill.segments.forEach((segment, index) => {
                                const row = document.createElement('tr');
                                row.style.borderBottom = '1px solid #e5e7eb';
                                const statusColor = segment.status === 'draft' ? '#FCD34D' : 
                                                  segment.status === 'pending_review' ? '#60A5FA' : 
                                                  segment.status === 'active' ? '#10B981' : '#EF4444';
                                
                                const billInfo = `Bill ${bill.id} (${bill.status})`;
                                const billDateStr = bill.created_at ? new Date(bill.created_at).toLocaleDateString() : 'N/A';
                                const segmentId = `seg_${bill.id}_${segment.id}`;
                                
                                row.innerHTML = `
                                    <td style="padding: 1rem; text-align: center; border-right: 1px solid #e5e7eb;"><input type="checkbox" class="segmentCheckbox" value="${segmentId}" data-segment-id="${segment.id}" data-bill-id="${bill.id}" style="cursor: pointer;"></td>
                                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; color: #6b7280; font-size: 0.875rem;">${segment.name}</td>
                                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; font-size: 0.875rem;">${parseFloat(segment.consumption).toFixed(2)}</td>
                                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; font-weight: 600; font-size: 0.875rem;">SAR ${parseFloat(segment.amount).toFixed(2)}</td>
                                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; font-size: 0.875rem;"><span style="background-color: ${statusColor}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem;">${segment.status}</span></td>
                                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; font-size: 0.75rem; color: #6b7280;">${billInfo}</td>
                                    <td style="padding: 1rem; font-size: 0.75rem; color: #9ca3af;">${billDateStr}</td>
                                `;
                                
                                // Add change listener to checkbox
                                const checkbox = row.querySelector('.segmentCheckbox');
                                checkbox.addEventListener('change', (e) => {
                                    if (e.target.checked) {
                                        app.selectedSegments[segmentId] = {
                                            segmentId: segment.id,
                                            billId: bill.id,
                                            name: segment.name,
                                            consumption: segment.consumption,
                                            amount: segment.amount,
                                            status: segment.status
                                        };
                                    } else {
                                        delete app.selectedSegments[segmentId];
                                    }
                                    // Update total when checkbox changes
                                    app.updateSelectedTotal();
                                });
                                
                                tbody.appendChild(row);
                            });
                        }
                    });
                    
                    // Initialize total to 0
                    app.updateSelectedTotal();
                } else {
                    const emptyRow = document.createElement('tr');
                    emptyRow.innerHTML = '<td colspan="7" style="padding: 3rem 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem;">No segments added yet for this customer</td>';
                    tbody.appendChild(emptyRow);
                    
                    const totalElement = document.getElementById('billTotalAmount');
                    if (totalElement) {
                        totalElement.textContent = '0.00';
                    }
                }
            },

            updateSelectedTotal() {
                // Calculate total from only SELECTED segments
                let selectedTotal = 0;
                Object.keys(app.selectedSegments).forEach(key => {
                    selectedTotal += parseFloat(app.selectedSegments[key].amount) || 0;
                });
                
                const totalElement = document.getElementById('billTotalAmount');
                if (totalElement) {
                    totalElement.textContent = selectedTotal.toFixed(2);
                }
            },

            calculateSegmentAmount() {
                const segmentName = document.getElementById('formSegmentName').value;
                const consumption = parseFloat(document.getElementById('formSegmentConsumption').value) || 0;
                
                if (!segmentName || consumption <= 0) {
                    document.getElementById('formSegmentAmount').value = '';
                    return;
                }
                
                // Fetch calculated amount from backend
                const params = new URLSearchParams({
                    action: 'calculate_segment_amount',
                    segmentName: segmentName,
                    consumption: consumption
                });
                
                fetch('/NWCBilling/build/Employee.php?' + params.toString())
                    .then(response => response.text())
                    .then(text => {
                        try {
                            const data = JSON.parse(text);
                            if (data.error) {
                                console.error('Error:', data.error);
                                return;
                            }
                            // Display calculated amount
                            document.getElementById('formSegmentAmount').value = parseFloat(data.amount).toFixed(2);
                        } catch (e) {
                            console.error('Parse error:', e);
                        }
                    })
                    .catch(error => {
                        console.error('Calculation error:', error);
                    });
            },

            displayLoadedSegments(data) {
                // Clear existing segments
                const tbody = document.getElementById('billSegmentsTableBody');
                if (!tbody) {
                    console.warn('billSegmentsTableBody element not found');
                    return;
                }
                tbody.innerHTML = '';
                
                // Store bill info
                document.billId = data.billId;
                document.billStatus = data.billStatus;
                
                // Show message if bill is already submitted
                if (data.billStatus !== 'draft') {
                    const msgDiv = document.createElement('div');
                    msgDiv.style.cssText = 'background-color: #dbeafe; border: 1px solid #93c5fd; border-radius: 0.5rem; padding: 0.75rem; margin-bottom: 1rem; color: #1e40af;';
                    msgDiv.textContent = `📋 Bill Status: ${data.billStatus.toUpperCase()} - This bill has been submitted for review. New segments cannot be added.`;
                    tbody.parentElement.parentElement.insertBefore(msgDiv, tbody.parentElement);
                }
                
                // Display each segment as a table row
                if (data.segments && data.segments.length > 0) {
                    data.segments.forEach((segment, index) => {
                        const row = document.createElement('tr');
                        const statusColor = segment.status === 'draft' ? '#FCD34D' : 
                                          segment.status === 'pending_review' ? '#60A5FA' : '#10B981';
                        row.innerHTML = `
                            <td class="text-sm">${segment.name}</td>
                            <td class="text-sm">${segment.consumption}</td>
                            <td class="text-sm">SAR ${parseFloat(segment.amount).toFixed(2)}</td>
                            <td class="text-sm"><span style="background-color: ${statusColor}; color: white; padding: 4px 8px; border-radius: 4px;">${segment.status}</span></td>
                            <td class="text-sm">${segment.remarks || ''}</td>
                            <td>
                                <button type="button" style="background-color: #ef4444; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer;" onclick="app.removeSegment(${index})" ${data.billStatus !== 'draft' ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>Remove</button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                }
            },

            addBillSegmentFromForm() {
                // Validate that customer info is provided
                const accountId = document.getElementById('billAccountId').value;
                const customerName = document.getElementById('billCustomerName') ? document.getElementById('billCustomerName').value : '';
                const customerPhone = document.getElementById('billCustomerPhone') ? document.getElementById('billCustomerPhone').value : '';
                
                if (!accountId && !customerName && !customerPhone) {
                    alert('Please enter either:\n- Account ID\n- Customer Name\n- Phone Number\n\nbefore adding segments');
                    return;
                }
                
                // Get form values
                const name = document.getElementById('formSegmentName').value;
                const consumption = document.getElementById('formSegmentConsumption').value;
                const amount = document.getElementById('formSegmentAmount').value;
                const status = document.getElementById('formSegmentStatus').value;
                const remarks = document.getElementById('formSegmentRemarks').value;
                
                // Validate
                if (!name) {
                    alert('Please select a segment type');
                    return;
                }
                
                if (!consumption || parseFloat(consumption) <= 0) {
                    alert('Please enter a valid consumption value');
                    return;
                }
                
                if (!amount || parseFloat(amount) <= 0) {
                    alert('Please enter a valid amount');
                    return;
                }
                
                // Initialize segments array if needed
                if (!app.billSegments) app.billSegments = [];
                
                // If this is the first segment, create a draft bill first
                if (app.billSegments.length === 0) {
                    const billDate = document.getElementById('billDate').value;
                    const dueDate = document.getElementById('billDueDate').value;
                    const total = parseFloat(amount);
                    
                    const billData = new URLSearchParams({
                        action: 'save_bill_draft',
                        accountId: accountId,
                        billDate: billDate,
                        dueDate: dueDate,
                        totalAmount: total
                    });
                    
                    fetch('/NWCBilling/build/Employee.php', {
                        method: 'POST',
                        body: billData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                alert('Error creating draft bill: ' + (data.error || 'Unknown error'));
                                return;
                            }
                            
                            // Store bill ID for future segment saves
                            app.currentBillId = data.id;
                            app.addSegmentToDB(name, consumption, status, remarks);
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error creating draft bill');
                        });
                } else {
                    // Bill already exists, just add segment
                    app.addSegmentToDB(name, consumption, status, remarks);
                }
            },

            addSegmentToDB(name, consumption, status, remarks) {
                const billId = app.currentBillId;
                
                if (!billId) {
                    alert('Error: Bill ID not found');
                    return;
                }
                
                const segmentData = new URLSearchParams({
                    action: 'save_segment',
                    billId: billId,
                    segmentName: name,
                    consumption: consumption,
                    status: status,
                    remarks: remarks
                });
                
                fetch('/NWCBilling/build/Employee.php', {
                    method: 'POST',
                    body: segmentData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            alert('Error saving segment: ' + (data.error || 'Unknown error'));
                            return;
                        }
                        
                        // Use calculated amount from server
                        const calculatedAmount = data.calculatedAmount || consumption;
                        app.addSegmentToUI(name, calculatedAmount, consumption, status, remarks);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error saving segment');
                    });
            },

            addSegmentToUI(name, amount, consumption, status, remarks) {
                // Add to segments array
                const segmentIndex = app.billSegments.length;
                app.billSegments.push({
                    name: name,
                    amount: parseFloat(amount),
                    consumption: parseFloat(consumption),
                    status: status,
                    remarks: remarks
                });
                
                // Get table body
                const tbody = document.getElementById('billSegmentsTableBody');
                const emptyPlaceholder = document.getElementById('emptySegmentPlaceholder');
                
                // Remove placeholder if this is first segment
                if (emptyPlaceholder && app.billSegments.length === 1) {
                    emptyPlaceholder.remove();
                }
                
                // Create and add row to table
                const row = document.createElement('tr');
                row.id = `segment-row-${segmentIndex}`;
                row.style.borderBottom = '1px solid #e5e7eb';
                row.innerHTML = `
                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; text-align: center;">
                        <input type="checkbox" id="segment-checkbox-${segmentIndex}" onchange="app.updateSegmentSelection()" style="cursor: pointer;">
                    </td>
                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; font-weight: 600;">${name}</td>
                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; text-align: right;">${parseFloat(consumption).toFixed(2)}</td>
                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb; text-align: right;">SAR ${parseFloat(amount).toFixed(2)}</td>
                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb;">
                        <select id="segment-status-${segmentIndex}" onchange="app.updateSegment(${segmentIndex}, 'status', this.value)" style="padding: 0.25rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; font-size: 0.75rem;">
                            <option value="pending" ${status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="approved" ${status === 'approved' ? 'selected' : ''}>Approved</option>
                            <option value="rejected" ${status === 'rejected' ? 'selected' : ''}>Rejected</option>
                            <option value="completed" ${status === 'completed' ? 'selected' : ''}>Completed</option>
                            <option value="frozen" ${status === 'frozen' ? 'selected' : ''}>Frozen</option>
                            <option value="correction_note" ${status === 'correction_note' ? 'selected' : ''}>Correction Note</option>
                        </select>
                    </td>
                    <td style="padding: 1rem; border-right: 1px solid #e5e7eb;">${remarks || '-'}</td>
                    <td style="padding: 1rem; text-align: right; white-space: nowrap;">
                        <button onclick="app.removeBillSegment(${segmentIndex})" style="background-color: #ef4444; color: white; padding: 0.375rem 0.75rem; border: none; border-radius: 0.375rem; cursor: pointer; font-size: 0.75rem; font-weight: 600; margin-right: 0.25rem;">Remove</button>
                        <button onclick="app.generateSegment(${segmentIndex})" style="background-color: #3b82f6; color: white; padding: 0.375rem 1rem; border: none; border-radius: 0.375rem; cursor: pointer; font-size: 0.75rem; font-weight: 600; margin-right: 0.25rem;">GENERATE</button>
                        <button onclick="app.cancelFrozenSegment(${segmentIndex})" style="background-color: #6b7280; color: white; padding: 0.375rem 1rem; border: none; border-radius: 0.375rem; cursor: pointer; font-size: 0.75rem; font-weight: 600;">CANCEL</button>
                    </td>
                `;
                
                tbody.appendChild(row);
                
                // Clear form
                document.getElementById('formSegmentName').value = '';
                document.getElementById('formSegmentAmount').value = '0';
                document.getElementById('formSegmentStatus').value = 'pending';
                document.getElementById('formSegmentRemarks').value = '';
                
                // Update total
                app.updateBillTotal();
            },

            addBillSegment() {
                // This is kept for backward compatibility but shouldn't be called directly
                this.addBillSegmentFromForm();
            },

            updateSegment(index, field, value) {
                if (!app.billSegments) app.billSegments = [];
                if (app.billSegments[index]) {
                    app.billSegments[index][field] = field === 'amount' ? parseFloat(value) : value;
                }
            },

            removeBillSegment(index) {
                const element = document.getElementById(`segment-row-${index}`);
                if (element) {
                    element.remove();
                    app.billSegments.splice(index, 1);
                    
                    // If no segments left, show placeholder
                    const tbody = document.getElementById('billSegmentsTableBody');
                    if (tbody && tbody.rows.length === 0) {
                        const placeholder = document.createElement('tr');
                        placeholder.id = 'emptySegmentPlaceholder';
                        placeholder.style.borderBottom = '1px solid #e5e7eb';
                        placeholder.innerHTML = `<td colspan="6" style="padding: 3rem 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem;">No segments added yet</td>`;
                        tbody.appendChild(placeholder);
                    }
                    
                    app.updateBillTotal();
                }
            },

            updateBillTotal() {
                if (!app.billSegments) return;
                
                const total = app.billSegments.reduce((sum, segment) => sum + (parseFloat(segment.amount) || 0), 0);
                
                const billTotalEl = document.getElementById('billTotalAmount');
                if (billTotalEl) billTotalEl.textContent = total.toFixed(2);
            },

            saveBillAsDraft() {
                const accountId = document.getElementById('billAccountId').value;
                const billDate = document.getElementById('billDate').value;
                const dueDate = document.getElementById('billDueDate').value;
                
                // Validate customer identification
                if (!accountId) {
                    alert('Please enter Account ID before saving bill');
                    return;
                }
                
                if (!billDate || !dueDate) {
                    alert('Please fill in Account ID, Bill Date, and Due Date');
                    return;
                }
                
                if (!app.billSegments || app.billSegments.length === 0) {
                    alert('Please add at least one bill segment');
                    return;
                }
                
                const total = app.billSegments.reduce((sum, s) => sum + (parseFloat(s.amount) || 0), 0);
                
                const billData = {
                    action: 'save_bill_draft',
                    accountId: accountId,
                    billDate: billDate,
                    dueDate: dueDate,
                    totalAmount: total,
                    segments: JSON.stringify(app.billSegments),
                    status: 'draft'
                };
                
                const params = new URLSearchParams(billData);
                
                fetch('/NWCBilling/build/Employee.php', {
                    method: 'POST',
                    body: params
                })
                    .then(response => response.text())
                    .then(text => {
                        try {
                            const data = JSON.parse(text);
                            if (data.success) {
                                alert('Bill saved as draft with ID: ' + data.billId);
                                app.navigate('bills');
                            } else {
                                alert('Error: ' + (data.error || 'Unknown error'));
                            }
                        } catch (e) {
                            console.error('Parse error:', e);
                            alert('Error: ' + text);
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        alert('Error saving bill: ' + error.message);
                    });
            },

            submitBillForReview() {
                const accountId = document.getElementById('billAccountId').value;
                const billDate = document.getElementById('billDate').value;
                const dueDate = document.getElementById('billDueDate').value;
                
                if (!accountId) {
                    alert('Please enter Account ID before submitting bill');
                    return;
                }
                
                if (!billDate || !dueDate) {
                    alert('Please fill in Account ID, Bill Date, and Due Date');
                    return;
                }
                
                if (!app.billSegments || app.billSegments.length === 0) {
                    alert('Please add at least one bill segment');
                    return;
                }
                
                const total = app.billSegments.reduce((sum, s) => sum + (parseFloat(s.amount) || 0), 0);
                
                if (total <= 0) {
                    alert('Total amount must be greater than 0');
                    return;
                }
                
                const billData = {
                    action: 'submit_bill_for_review',
                    accountId: accountId,
                    billDate: billDate,
                    dueDate: dueDate,
                    totalAmount: total,
                    segments: JSON.stringify(app.billSegments),
                    status: 'pending_review'
                };
                
                const params = new URLSearchParams(billData);
                
                fetch('/NWCBilling/build/Employee.php', {
                    method: 'POST',
                    body: params
                })
                    .then(response => response.text())
                    .then(text => {
                        console.log('Response:', text);
                        if (text.includes('success')) {
                            const billIdMatch = text.match(/BILL-\d+/);
                            const billId = billIdMatch ? billIdMatch[0] : 'N/A';
                            
                            // Show success message
                            const msgDiv = document.createElement('div');
                            msgDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; background-color: #22c55e; color: white; padding: 1rem 1.5rem; border-radius: 0.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 9999; font-weight: bold;';
                            msgDiv.textContent = '✅ Bill ' + billId + ' submitted for review! Admin will review it.';
                            document.body.appendChild(msgDiv);
                            
                            setTimeout(() => msgDiv.remove(), 5000);
                            
                            // Update segment statuses to pending_review WITHOUT clearing
                            const rows = document.querySelectorAll('#billSegmentsTableBody tr');
                            rows.forEach(row => {
                                const statusCell = row.querySelector('td:nth-child(4)');
                                if (statusCell) {
                                    statusCell.innerHTML = '<span style="background-color: #60A5FA; color: white; padding: 4px 8px; border-radius: 4px;">pending_review</span>';
                                }
                            });
                            
                            // Keep everything else visible - DON'T clear the form
                        } else {
                            alert('Error submitting bill: ' + text.substring(0, 200));
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        alert('Error submitting bill: ' + error.message);
                    });
            },

            generateSegment(index) {
                // Mark segment as generated and lock it
                if (app.billSegments && app.billSegments[index]) {
                    app.billSegments[index].status = 'generated';
                    app.billSegments[index].locked = true;
                    
                    const statusEl = document.getElementById(`segment-status-${index}`);
                    if (statusEl) {
                        statusEl.value = 'generated';
                        statusEl.disabled = true;
                    }
                    
                    alert('Segment generated successfully!');
                }
            },

            cancelFrozenSegment(index) {
                // Unlock segment for editing
                if (app.billSegments && app.billSegments[index]) {
                    app.billSegments[index].locked = false;
                    
                    const statusEl = document.getElementById(`segment-status-${index}`);
                    if (statusEl) {
                        statusEl.disabled = false;
                    }
                    
                    alert('Segment unlocked - ready for editing');
                }
            },

            generateBillSegments() {
                // Submit entire bill to admin for review
                this.submitBillForReview();
            },

            cancelFrozenSegments() {
                if (confirm('Are you sure you want to cancel all frozen segments?')) {
                    app.billSegments = [];
                    const tbody = document.getElementById('billSegmentsTableBody');
                    tbody.innerHTML = `<tr id="emptySegmentPlaceholder" style="border-bottom: 1px solid #e5e7eb;">
                                <td colspan="6" style="padding: 3rem 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem;">No segments added yet</td>
                            </tr>`;
                    app.updateBillTotal();
                }
            },

            getSelectedSegments() {
                return Object.keys(app.selectedSegments).length > 0 ? Object.keys(app.selectedSegments) : [];
            },

            updateSegmentSelection() {
                // Called when a segment checkbox is toggled
                const selected = this.getSelectedSegments();
                // Can be used to enable/disable action buttons based on selection
            },

            toggleSelectAll(checkbox) {
                const tbody = document.getElementById('billSegmentsTableBody');
                if (tbody) {
                    const checkboxes = tbody.querySelectorAll('input[type="checkbox"].segmentCheckbox');
                    checkboxes.forEach(cb => {
                        cb.checked = checkbox.checked;
                        // Trigger change event
                        cb.dispatchEvent(new Event('change'));
                    });
                }
            },

            freezeCompleteSegments() {
                const selectedKeys = Object.keys(app.selectedSegments);
                if (selectedKeys.length === 0) {
                    alert('Please select at least one segment');
                    return;
                }
                
                // Extract segment IDs
                const segmentIds = selectedKeys.map(key => app.selectedSegments[key].segmentId);
                
                fetch('/NWCBilling/build/Employee.php?action=freeze_complete_segments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'segmentIds=' + JSON.stringify(segmentIds)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`✅ ${data.message}`);
                        app.selectedSegments = {};
                        const accountId = document.getElementById('billAccountId').value;
                        if (accountId) {
                            app.loadDraftSegments();
                        }
                    } else {
                        alert('❌ Error: ' + (data.error || 'Failed'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                });
            },

            deleteSegments() {
                const selectedKeys = Object.keys(app.selectedSegments);
                if (selectedKeys.length === 0) {
                    alert('Please select at least one segment');
                    return;
                }
                if (confirm(`Are you sure you want to delete ${selectedKeys.length} segment(s)?`)) {
                    // Extract segment IDs from selected segments
                    const segmentIds = selectedKeys.map(key => app.selectedSegments[key].segmentId);
                    
                    // Send delete request to backend
                    fetch('/NWCBilling/build/Employee.php?action=delete_segments', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'segmentIds=' + JSON.stringify(segmentIds)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(`✅ ${selectedKeys.length} segment(s) deleted successfully`);
                            app.selectedSegments = {}; // Clear selection
                            
                            // Reload segments to refresh table
                            const accountId = document.getElementById('billAccountId').value;
                            if (accountId) {
                                app.loadDraftSegments();
                            }
                        } else {
                            alert('❌ Error: ' + (data.error || 'Failed to delete segments'));
                        }
                    })
                    .catch(error => {
                        console.error('Delete error:', error);
                        alert('Error deleting segments: ' + error.message);
                    });
                }
            },

            reopenSegments() {
                const selectedKeys = Object.keys(app.selectedSegments);
                if (selectedKeys.length === 0) {
                    alert('Please select at least one segment');
                    return;
                }
                
                const segmentIds = selectedKeys.map(key => app.selectedSegments[key].segmentId);
                
                fetch('/NWCBilling/build/Employee.php?action=reopen_segments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'segmentIds=' + JSON.stringify(segmentIds)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`✅ ${data.message}`);
                        app.selectedSegments = {};
                        const accountId = document.getElementById('billAccountId').value;
                        if (accountId) {
                            app.loadDraftSegments();
                        }
                    } else {
                        alert('❌ Error: ' + (data.error || 'Failed'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                });
            },

            addCorrectionNote() {
                const selectedKeys = Object.keys(app.selectedSegments);
                if (selectedKeys.length === 0) {
                    alert('Please select at least one segment');
                    return;
                }
                
                const note = prompt('Enter correction note:');
                if (!note) {
                    return;
                }
                
                const segmentIds = selectedKeys.map(key => app.selectedSegments[key].segmentId);
                
                fetch('/NWCBilling/build/Employee.php?action=correction_note_segments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'segmentIds=' + JSON.stringify(segmentIds) + '&note=' + encodeURIComponent(note)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`✅ ${data.message}`);
                        app.selectedSegments = {};
                        const accountId = document.getElementById('billAccountId').value;
                        if (accountId) {
                            app.loadDraftSegments();
                        }
                    } else {
                        alert('❌ Error: ' + (data.error || 'Failed'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                });
            },

            undoCorrectionNote() {
                const selectedKeys = Object.keys(app.selectedSegments);
                if (selectedKeys.length === 0) {
                    alert('Please select at least one segment');
                    return;
                }
                
                const segmentIds = selectedKeys.map(key => app.selectedSegments[key].segmentId);
                
                fetch('/NWCBilling/build/Employee.php?action=undo_correction_note_segments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'segmentIds=' + JSON.stringify(segmentIds)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`✅ ${data.message}`);
                        app.selectedSegments = {};
                        const accountId = document.getElementById('billAccountId').value;
                        if (accountId) {
                            app.loadDraftSegments();
                        }
                    } else {
                        alert('❌ Error: ' + (data.error || 'Failed'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                });
            },

            generateBill() {
                const selectedKeys = Object.keys(app.selectedSegments);
                if (selectedKeys.length === 0) {
                    alert('Please select segments to generate bill');
                    return;
                }
                
                // Check if all selected segments have active status
                let allActive = true;
                selectedKeys.forEach(key => {
                    if (app.selectedSegments[key].status !== 'active') {
                        allActive = false;
                    }
                });
                
                if (!allActive) {
                    alert('⚠️ Bill can only be generated after Admin approval (Active status). Please ensure all selected segments are approved.');
                    return;
                }
                
                const accountId = document.getElementById('billAccountId').value;
                const billId = selectedKeys[0].split('_')[1]; // Extract bill ID from segment key
                
                if (!accountId || !billId) {
                    alert('Error: Missing account or bill information');
                    return;
                }
                
                // Open bill in new window/tab
                window.open(`/NWCBilling/build/pages/generate-bill.php?accountId=${accountId}&billId=${billId}`, '_blank', 'width=900,height=700');
                alert(`✅ Bill ${billId} generated successfully and opened in new window`);
            },

            logout() {
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = '/NWCBilling/build/pages/sign-in.php';
                }
            }
        };

        const pages = {
            dashboard: {
                title: 'Dashboard',
                breadcrumb: 'Dashboard',
                render(container) {
                    container.innerHTML = `<div class="flex flex-wrap -mx-3"><div class="w-full max-w-full px-3 mb-6 sm:w-1/2 sm:flex-none xl:mb-0 xl:w-1/4"><div class="relative flex flex-col min-w-0 break-words bg-white shadow-xl dark:bg-slate-850 dark:shadow-dark-xl rounded-2xl bg-clip-border"><div class="flex-auto p-4"><div class="flex flex-row -mx-3"><div class="flex-none w-2/3 max-w-full px-3"><div><p class="mb-0 font-sans text-sm font-semibold leading-normal uppercase dark:text-white dark:opacity-60">Total Accounts</p><h5 class="mb-2 font-bold dark:text-white">1,245</h5><p class="mb-0 dark:text-white dark:opacity-60"><span class="text-sm font-bold leading-normal text-emerald-500">+12%</span> this month</p></div></div><div class="px-3 text-right basis-1/3"><div class="inline-block w-12 h-12 text-center rounded-circle bg-gradient-to-tl from-blue-500 to-violet-500"><i class="ni leading-none ni-single-02 text-lg relative top-3.5 text-white"></i></div></div></div></div></div></div><div class="w-full max-w-full px-3 mb-6 sm:w-1/2 sm:flex-none xl:mb-0 xl:w-1/4"><div class="relative flex flex-col min-w-0 break-words bg-white shadow-xl dark:bg-slate-850 dark:shadow-dark-xl rounded-2xl bg-clip-border"><div class="flex-auto p-4"><div class="flex flex-row -mx-3"><div class="flex-none w-2/3 max-w-full px-3"><div><p class="mb-0 font-sans text-sm font-semibold leading-normal uppercase dark:text-white dark:opacity-60">Water Consumed</p><h5 class="mb-2 font-bold dark:text-white">45.2K m³</h5><p class="mb-0 dark:text-white dark:opacity-60"><span class="text-sm font-bold leading-normal text-red-600">+5%</span> from last month</p></div></div><div class="px-3 text-right basis-1/3"><div class="inline-block w-12 h-12 text-center rounded-circle bg-gradient-to-tl from-emerald-500 to-teal-400"><i class="ni leading-none ni-water-bottle text-lg relative top-3.5 text-white"></i></div></div></div></div></div></div><div class="w-full max-w-full px-3 mb-6 sm:w-1/2 sm:flex-none xl:mb-0 xl:w-1/4"><div class="relative flex flex-col min-w-0 break-words bg-white shadow-xl dark:bg-slate-850 dark:shadow-dark-xl rounded-2xl bg-clip-border"><div class="flex-auto p-4"><div class="flex flex-row -mx-3"><div class="flex-none w-2/3 max-w-full px-3"><div><p class="mb-0 font-sans text-sm font-semibold leading-normal uppercase dark:text-white dark:opacity-60">Total Revenue</p><h5 class="mb-2 font-bold dark:text-white">$125.3K</h5><p class="mb-0 dark:text-white dark:opacity-60"><span class="text-sm font-bold leading-normal text-emerald-500">+8.5%</span> growth</p></div></div><div class="px-3 text-right basis-1/3"><div class="inline-block w-12 h-12 text-center rounded-circle bg-gradient-to-tl from-orange-500 to-yellow-500"><i class="ni leading-none ni-money-coins text-lg relative top-3.5 text-white"></i></div></div></div></div></div></div><div class="w-full max-w-full px-3 sm:w-1/2 sm:flex-none xl:w-1/4"><div class="relative flex flex-col min-w-0 break-words bg-white shadow-xl dark:bg-slate-850 dark:shadow-dark-xl rounded-2xl bg-clip-border"><div class="flex-auto p-4"><div class="flex flex-row -mx-3"><div class="flex-none w-2/3 max-w-full px-3"><div><p class="mb-0 font-sans text-sm font-semibold leading-normal uppercase dark:text-white dark:opacity-60">Pending Bills</p><h5 class="mb-2 font-bold dark:text-white">234</h5><p class="mb-0 dark:text-white dark:opacity-60"><span class="text-sm font-bold leading-normal text-red-600">3 overdue</span></p></div></div><div class="px-3 text-right basis-1/3"><div class="inline-block w-12 h-12 text-center rounded-circle bg-gradient-to-tl from-red-600 to-orange-600"><i class="ni leading-none ni-single-copy-04 text-lg relative top-3.5 text-white"></i></div></div></div></div></div></div></div><div class="flex flex-wrap mt-6 -mx-3"><div class="w-full max-w-full px-3 mt-0"><div class="border-black/12.5 dark:bg-slate-850 dark:shadow-dark-xl shadow-xl relative z-20 flex min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border"><div class="border-black/12.5 mb-0 rounded-t-2xl border-b border-solid p-6 pt-4 pb-3"><h6 class="capitalize dark:text-white">Welcome to NWC Billing System</h6></div><div class="flex-auto p-6"><p class="dark:text-white mb-4">Fast, seamless internal navigation with Oracle-like experience. No page reloads, just smooth transitions.</p><p class="dark:text-white"><strong>Navigation:</strong> Use the sidebar menu to switch between sections instantly.</p></div></div></div></div>`;
                }
            },
            search: {
                title: 'Search Accounts',
                breadcrumb: 'Search / Accounts',
                render(container) {
                    container.innerHTML = `<div class="w-full max-w-full"><div class="border-black/12.5 dark:bg-slate-850 dark:shadow-dark-xl shadow-xl relative z-20 flex min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border mb-6"><div class="border-black/12.5 mb-0 rounded-t-2xl border-b-0 border-solid p-6 pt-4 pb-0"><h6 class="capitalize dark:text-white">Account Search</h6></div><div class="flex-auto p-6"><div class="mb-4"><label class="block text-sm font-semibold text-slate-700 dark:text-white mb-2">Search By</label><select id="searchType" onchange="app.updateSearchFields()" class="w-full px-4 py-2 border border-solid border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none dark:bg-slate-800 dark:text-white"><option value="name">Name and Address</option><option value="account_id">Account ID</option><option value="phone">Phone Number</option></select></div><div id="searchFields" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4"></div><div class="flex gap-2"><button onclick="app.performSearch()" class="inline-flex items-center px-8 py-2 font-bold text-white uppercase bg-blue-500 border-0 rounded-lg text-sm"><i class="ni ni-zoom-split-in mr-2"></i>Search</button><button onclick="app.clearSearch()" class="inline-flex items-center px-8 py-2 font-bold text-slate-700 uppercase bg-gray-200 border-0 rounded-lg text-sm dark:bg-slate-700 dark:text-white"><i class="ni ni-reload mr-2"></i>Clear</button></div></div></div></div><div id="resultsSection" class="w-full max-w-full" style="display:none"><div class="border-black/12.5 dark:bg-slate-850 dark:shadow-dark-xl shadow-xl relative z-20 flex min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border mb-6"><div class="border-black/12.5 mb-0 rounded-t-2xl border-b border-solid p-6 pt-4 pb-3"><h6 class="capitalize dark:text-white">Search Results - Select an Account</h6></div><div class="flex-auto p-6"><div id="searchResultsList" class="bg-gray-50 dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 max-h-96 overflow-y-auto"></div></div></div></div><div id="accountDetailsSection" class="w-full max-w-full" style="display:none"><div class="grid grid-cols-1 lg:grid-cols-3 gap-6"><div class="lg:col-span-2"><div class="border-black/12.5 dark:bg-slate-850 dark:shadow-dark-xl shadow-xl relative z-20 flex min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border"><div class="border-black/12.5 mb-0 rounded-t-2xl border-b border-solid p-6 pt-4 pb-0"><div class="flex gap-2 mb-4 flex-wrap"><button class="accountTab px-4 py-2 bg-blue-500 text-white rounded-lg font-semibold text-sm" data-tab="accountInfo" onclick="app.switchTab(event)">Account Info</button><button class="accountTab px-4 py-2 bg-gray-200 text-slate-700 rounded-lg dark:bg-slate-700 dark:text-white text-sm" data-tab="customerInfo" onclick="app.switchTab(event)">Customer</button><button class="accountTab px-4 py-2 bg-gray-200 text-slate-700 rounded-lg dark:bg-slate-700 dark:text-white text-sm" data-tab="billingInfo" onclick="app.switchTab(event)">Billing Tree</button><button class="accountTab px-4 py-2 bg-gray-200 text-slate-700 rounded-lg dark:bg-slate-700 dark:text-white text-sm" data-tab="meterInfo" onclick="app.switchTab(event)">Meter</button></div></div><div class="flex-auto p-6"><div id="accountInfo-tab" class="accountTabContent"><div class="space-y-0"><div class="border-b border-gray-200 dark:border-slate-700"><button class="w-full section-header flex items-center justify-between py-4 font-semibold text-slate-700 dark:text-white text-sm" onclick="app.toggleSection(event)"><span>Premise Tree</span><i class="ni ni-chevron-down text-sm"></i></button><div class="section-content pb-4 px-2"><div class="text-xs space-y-1"><div class="ml-0 flex items-start"><span class="cursor-pointer hover:text-blue-600 text-slate-700 dark:text-slate-300">🏠 Premise - Address</span></div><div class="ml-4 text-slate-600 dark:text-slate-400">(Connection Number)</div></div></div></div><div class="border-b border-gray-200 dark:border-slate-700"><button class="w-full section-header flex items-center justify-between py-4 font-semibold text-slate-700 dark:text-white text-sm" onclick="app.toggleSection(event)"><span>Current Context</span><i class="ni ni-chevron-down text-sm"></i></button><div class="section-content pb-4 px-2"><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div><p class="text-xs font-semibold text-slate-500 uppercase">Person</p><p id="ctx-person" class="text-sm dark:text-white font-semibold">-</p></div><div><p class="text-xs font-semibold text-slate-500 uppercase">Account ID</p><p id="ctx-accountId" class="text-sm dark:text-white font-semibold">-</p></div><div><p class="text-xs font-semibold text-slate-500 uppercase">Balance</p><p id="ctx-balance" class="text-sm font-semibold text-emerald-500">-</p></div><div><p class="text-xs font-semibold text-slate-500 uppercase">Premise</p><p id="ctx-premise" class="text-sm dark:text-white font-semibold">-</p></div></div></div></div><div class="border-b border-gray-200 dark:border-slate-700"><button class="w-full section-header flex items-center justify-between py-4 font-semibold text-slate-700 dark:text-white text-sm" onclick="app.toggleSection(event)"><span>Financial Information</span><i class="ni ni-chevron-down text-sm"></i></button><div class="section-content pb-4 px-2"><div class="space-y-2"><div class="p-3 bg-gray-50 dark:bg-slate-800 rounded"><p class="text-xs font-semibold text-slate-500 uppercase">Current Balance</p><p id="fin-balance" class="text-sm font-semibold text-emerald-500">SAR 0</p></div><div class="p-3 bg-gray-50 dark:bg-slate-800 rounded"><p class="text-xs font-semibold text-slate-500 uppercase">Last Payment</p><p id="fin-lastPayment" class="text-sm dark:text-white">-</p></div><div class="p-3 bg-gray-50 dark:bg-slate-800 rounded"><p class="text-xs font-semibold text-slate-500 uppercase">Last Billed</p><p id="fin-lastBilled" class="text-sm dark:text-white">-</p></div><div class="p-3 bg-gray-50 dark:bg-slate-800 rounded"><p class="text-xs font-semibold text-slate-500 uppercase">Previous Bill</p><p id="fin-prevBill" class="text-sm dark:text-white">-</p></div></div></div></div><div class="border-b border-gray-200 dark:border-slate-700"><button class="w-full section-header flex items-center justify-between py-4 font-semibold text-slate-700 dark:text-white text-sm" onclick="app.toggleSection(event)"><span>Water Billing History</span><i class="ni ni-chevron-down text-sm"></i></button><div class="section-content pb-4 px-2"><div class="space-y-2"><div class="p-3 bg-gray-50 dark:bg-slate-800 rounded"><p class="text-xs font-semibold text-slate-500 uppercase">Last 3 Months</p><p id="hist-3months" class="text-sm dark:text-white">-</p></div><div class="p-3 bg-gray-50 dark:bg-slate-800 rounded"><p class="text-xs font-semibold text-slate-500 uppercase">Average Usage</p><p id="hist-avgUsage" class="text-sm dark:text-white">-</p></div><div class="p-3 bg-gray-50 dark:bg-slate-800 rounded"><p class="text-xs font-semibold text-slate-500 uppercase">Trend</p><p id="hist-trend" class="text-sm text-orange-500">-</p></div></div></div></div><div class="border-b border-gray-200 dark:border-slate-700"><button class="w-full section-header flex items-center justify-between py-4 font-semibold text-slate-700 dark:text-white text-sm" onclick="app.toggleSection(event)"><span>Meter Status</span><i class="ni ni-chevron-down text-sm"></i></button><div class="section-content pb-4 px-2"><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div><p class="text-xs font-semibold text-slate-500 uppercase">Meter ID</p><p id="meter-id" class="text-sm font-bold dark:text-white">-</p></div><div><p class="text-xs font-semibold text-slate-500 uppercase">Status</p><p id="meter-status" class="text-sm font-bold text-emerald-500">-</p></div><div><p class="text-xs font-semibold text-slate-500 uppercase">Last Reading</p><p id="meter-reading" class="text-sm dark:text-white">-</p></div><div><p class="text-xs font-semibold text-slate-500 uppercase">Reading Date</p><p id="meter-date" class="text-sm dark:text-white">-</p></div></div></div></div><div><button class="w-full section-header flex items-center justify-between py-4 font-semibold text-slate-700 dark:text-white text-sm" onclick="app.toggleSection(event)"><span>Service Agreements</span><i class="ni ni-chevron-down text-sm"></i></button><div class="section-content pb-4 px-2"><div class="space-y-2"><div class="p-3 bg-gray-50 dark:bg-slate-800 rounded"><p class="text-xs font-semibold text-slate-500 uppercase">Agreement ID</p><p id="agreement-id" class="text-sm dark:text-white">-</p></div><div class="p-3 bg-gray-50 dark:bg-slate-800 rounded"><p class="text-xs font-semibold text-slate-500 uppercase">Service Type</p><p id="agreement-type" class="text-sm dark:text-white">-</p></div><div class="p-3 bg-gray-50 dark:bg-slate-800 rounded"><p class="text-xs font-semibold text-slate-500 uppercase">Status</p><p id="agreement-status" class="text-sm font-semibold text-emerald-500">Active</p></div><div class="p-3 bg-gray-50 dark:bg-slate-800 rounded"><p class="text-xs font-semibold text-slate-500 uppercase">Start Date</p><p id="agreement-startDate" class="text-sm dark:text-white">-</p></div></div></div></div></div></div><div id="customerInfo-tab" class="accountTabContent" style="display:none"><div class="overflow-x-auto"><table class="w-full text-sm border-collapse"><thead><tr class="bg-gray-100 dark:bg-slate-700"><th class="px-6 py-4 text-left font-semibold text-slate-700 dark:text-white uppercase text-xs border-b">Account Info</th><th class="px-6 py-4 text-left font-semibold text-slate-700 dark:text-white uppercase text-xs border-b">Balance</th><th class="px-6 py-4 text-left font-semibold text-slate-700 dark:text-white uppercase text-xs border-b">Arrears</th><th class="px-6 py-4 text-left font-semibold text-slate-700 dark:text-white uppercase text-xs border-b">Last Contact</th></tr></thead><tbody><tr class="border-b border-gray-200 dark:border-slate-700"><td class="px-6 py-4"><div class="font-semibold text-blue-600" id="cust-accountNumber">-</div><div class="text-xs text-slate-600 dark:text-slate-400" id="cust-class">Customer</div></td><td class="px-6 py-4"><div class="font-semibold text-slate-700 dark:text-white" id="cust-balance">SAR 0</div></td><td class="px-6 py-4"><div class="text-slate-700 dark:text-white text-xs font-semibold" id="cust-arrears">Clear</div></td><td class="px-6 py-4"><div class="text-blue-600 text-xs" id="cust-contact">-</div></td></tr></tbody></table></div></div><div id="billingInfo-tab" class="accountTabContent" style="display:none"><div class="space-y-0"><div class="border-b border-gray-200 dark:border-slate-700"><button class="w-full section-header flex items-center justify-between py-4 font-semibold text-slate-700 dark:text-white text-sm" onclick="app.toggleSection(event)"><span>Bill/Payment Tree</span><i class="ni ni-chevron-down text-sm"></i></button><div class="section-content pb-4 px-2"><div class="text-xs space-y-2"><div class="ml-0 flex items-start"><span class="font-semibold text-slate-700 dark:text-slate-300">📋 Account - <span id="bill-accountId">-</span></span></div><div class="ml-4 text-slate-600 dark:text-slate-400 mb-3"><span id="bill-accountClass">-</span></div><div id="billPaymentList" class="ml-4 space-y-2"><div class="flex items-start"><span class="text-slate-700 dark:text-slate-300">📄 No bills available</span></div></div></div></div></div></div></div><div id="meterInfo-tab" class="accountTabContent" style="display:none"><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div><p class="text-xs font-semibold text-slate-500 uppercase">Meter ID</p><p id="meterInfo-id" class="text-lg font-bold dark:text-white">-</p></div><div><p class="text-xs font-semibold text-slate-500 uppercase">Status</p><p id="meterInfo-status" class="text-lg font-bold text-emerald-500">-</p></div><div><p class="text-xs font-semibold text-slate-500 uppercase">Last Reading</p><p id="meterInfo-lastReading" class="text-sm dark:text-white">-</p></div><div><p class="text-xs font-semibold text-slate-500 uppercase">Reading Date</p><p id="meterInfo-readingDate" class="text-sm dark:text-white">-</p></div></div></div></div></div></div><div class="lg:col-span-1"><div class="border-black/12.5 dark:bg-slate-850 dark:shadow-dark-xl shadow-xl relative z-20 flex min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border mb-6"><div class="flex-auto p-6"><div class="flex flex-row -mx-3"><div class="flex-none w-2/3 max-w-full px-3"><p class="mb-0 font-sans text-sm font-semibold uppercase dark:opacity-60">Balance</p><h5 id="summaryBalance" class="mb-2 font-bold dark:text-white text-2xl">-</h5><p class="mb-0 dark:opacity-60"><span id="summaryStatus" class="text-sm font-bold text-emerald-500">Good Standing</span></p></div><div class="px-3 text-right basis-1/3"><div class="inline-block w-12 h-12 text-center rounded-circle bg-gradient-to-tl from-emerald-500 to-teal-400"><i class="ni leading-none ni-money-coins text-lg relative top-3.5 text-white"></i></div></div></div></div></div><div class="border-black/12.5 dark:bg-slate-850 dark:shadow-dark-xl shadow-xl relative z-20 flex min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border"><div class="border-black/12.5 mb-0 rounded-t-2xl border-b border-solid p-6 pt-4 pb-3"><h6 class="capitalize dark:text-white text-sm">Actions</h6></div><div class="flex-auto p-6"><div class="flex flex-col gap-3"><button class="inline-flex items-center justify-center w-full px-6 py-3 font-bold text-white uppercase bg-blue-500 border-0 rounded-lg text-sm hover:bg-blue-600"><i class="ni ni-single-copy-04 mr-2"></i>Generate Bill</button><button class="inline-flex items-center justify-center w-full px-6 py-3 font-bold text-white uppercase bg-emerald-500 border-0 rounded-lg text-sm hover:bg-emerald-600"><i class="ni ni-chart-bar-32 mr-2"></i>Submit Reading</button></div></div></div></div></div></div></div>`;
                    setTimeout(() => {
                        app.updateSearchFields();
                    }, 100);
                }
            },
            meter: {
                title: 'Meter Readings',
                breadcrumb: 'Meter Readings',
                render(container) {
                    container.innerHTML = `<div class="border-black/12.5 dark:bg-slate-850 dark:shadow-dark-xl shadow-xl relative z-20 flex min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border"><div class="border-black/12.5 mb-0 rounded-t-2xl border-b border-solid p-6 pt-4 pb-3"><h6 class="capitalize dark:text-white">Meter Readings</h6></div><div class="flex-auto p-6"><p class="dark:text-white mb-4">Submit and track meter readings for all customer accounts.</p><button class="inline-flex items-center px-8 py-2 font-bold text-white uppercase bg-blue-500 border-0 rounded-lg text-sm hover:bg-blue-600"><i class="ni ni-plus mr-2"></i>New Reading</button></div></div>`;
                }
            },
            bills: {
                title: 'Bills Management',
                breadcrumb: 'Bills',
                render(container) {
                    // Show the search bills modal automatically
                    setTimeout(() => {
                        const modal = document.getElementById('billsModal');
                        if (modal) {
                            modal.style.display = 'flex';
                        }
                    }, 100);
                    
                    container.innerHTML = `
<div class="w-full">
    <div class="border-black/12.5 dark:bg-slate-850 dark:shadow-dark-xl shadow-xl relative z-20 flex min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border">
        <div class="flex-auto p-6">
            <!-- Bill Info Section -->
            <div class="mb-6">
                <h6 class="text-sm font-bold text-slate-700 dark:text-white mb-4">Bill Info</h6>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Account ID</label>
                        <div class="flex items-center gap-2">
                            <input type="text" id="billAccountId" placeholder="" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none dark:bg-slate-800 dark:text-white" onchange="app.loadBillAccountInfo()">
                            <button onclick="app.loadDraftSegments()" class="px-3 py-2 text-gray-600 dark:text-gray-400" title="Load draft bill segments">🔍</button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Customer Name</label>
                        <input type="text" id="billCustomerName" placeholder="" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 dark:bg-slate-700 dark:text-white" readonly>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Bill Status</label>
                        <input type="text" id="billStatus" placeholder="" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 dark:bg-slate-700 dark:text-white" readonly>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Due Date</label>
                        <input type="date" id="billDueDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none dark:bg-slate-800 dark:text-white">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Bill Date</label>
                        <input type="date" id="billDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none dark:bg-slate-800 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Create Date/Time</label>
                        <input type="text" id="billCreateDate" placeholder="" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 dark:bg-slate-700 dark:text-white text-xs" readonly>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Completion Date/Time</label>
                        <input type="text" id="billCompletionDate" placeholder="" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 dark:bg-slate-700 dark:text-white text-xs" readonly>
                    </div>
                </div>
            </div>

            <!-- Bill Segments Form Section -->
            <div class="mb-6">
                <h6 class="text-sm font-bold text-slate-700 dark:text-white mb-4">Add Bill Segment</h6>
                
                <div style="border: 1px solid #d1d5db; border-radius: 0.75rem; padding: 1.5rem; background-color: #f9fafb;">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Bill Segment</label>
                            <select id="formSegmentName" onchange="app.calculateSegmentAmount()" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; dark:bg-slate-800 dark:text-white;">
                                <option value="">-- Select Segment --</option>
                                <option value="Water Supply">Water Supply</option>
                                <option value="Sewage">Sewage</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Consumption (m³)</label>
                            <input type="number" id="formSegmentConsumption" placeholder="0.00" value="0" step="0.01" onchange="app.calculateSegmentAmount()" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; dark:bg-slate-800 dark:text-white;">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Bill Amount (SAR)</label>
                            <input type="number" id="formSegmentAmount" placeholder="0.00" value="0" step="0.01" readonly style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; dark:bg-slate-800 dark:text-white; background-color: #f3f4f6;">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Status</label>
                            <select id="formSegmentStatus" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; dark:bg-slate-800 dark:text-white;">
                                <option value="draft">Draft</option>
                                <option value="pending_review">Pending Review</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Remarks</label>
                            <input type="text" id="formSegmentRemarks" placeholder="Notes..." style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; dark:bg-slate-800 dark:text-white;">
                        </div>
                    </div>
                    <button onclick="app.addBillSegmentFromForm()" class="inline-flex items-center px-4 py-2 font-bold text-white uppercase bg-blue-500 border-0 rounded-lg text-xs hover:bg-blue-600">+ Add Segment</button>
                </div>
            </div>

            <!-- Bill Segments Table Section -->
            <div class="mb-6">
                <h6 class="text-sm font-bold text-slate-700 dark:text-white mb-4">Bill Segment</h6>
                
                <div style="border: 1px solid #d1d5db; border-radius: 0.75rem; overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; background-color: white;">
                        <thead style="background-color: #f3f4f6; border-bottom: 2px solid #d1d5db;">
                            <tr>
                                <th style="padding: 1rem; text-align: center; font-size: 0.875rem; font-weight: 700; color: #374151; border-right: 1px solid #e5e7eb; width: 50px;"><input type="checkbox" id="selectAllSegments" onchange="app.toggleSelectAll(this)" style="cursor: pointer;"></th>
                                <th style="padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 700; color: #374151; border-right: 1px solid #e5e7eb;">Segment Type</th>
                                <th style="padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 700; color: #374151; border-right: 1px solid #e5e7eb;">Consumption (m³)</th>
                                <th style="padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 700; color: #374151; border-right: 1px solid #e5e7eb;">Amount (SAR)</th>
                                <th style="padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 700; color: #374151; border-right: 1px solid #e5e7eb;">Status</th>
                                <th style="padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 700; color: #374151; border-right: 1px solid #e5e7eb;">Bill Info</th>
                                <th style="padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 700; color: #374151;">Date</th>
                            </tr>
                        </thead>
                        <tbody id="billSegmentsTableBody">
                            <tr style="border-bottom: 1px solid #e5e7eb;" id="emptySegmentPlaceholder">
                                <td colspan="6" style="padding: 3rem 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem;">No segments added yet</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Total Generated Charge -->
            <div class="mb-6">
                <p class="text-sm font-semibold text-slate-700 dark:text-white">Total Generated Charge <span id="billTotalAmount" class="font-bold text-blue-600">0.00</span></p>
            </div>

            <!-- Bill Action (Bottom) -->
            <div>
                <h6 class="text-sm font-bold text-slate-700 dark:text-white mb-3">Bill Action</h6>
                <div class="flex gap-3 flex-wrap mb-4">
                    <button onclick="app.submitBillForReview()" style="display: inline-flex; align-items: center; padding: 0.5rem 1.5rem; font-weight: bold; color: white; text-transform: uppercase; background-color: #22c55e; border: none; border-radius: 0.5rem; font-size: 0.875rem; cursor: pointer;">Submit For Review</button>
                    <button onclick="app.generateBill()" style="display: inline-flex; align-items: center; padding: 0.5rem 1.5rem; font-weight: bold; color: white; text-transform: uppercase; background-color: #3b82f6; border: none; border-radius: 0.5rem; font-size: 0.875rem; cursor: pointer;">Generate Bill</button>
                    <button onclick="app.freezeCompleteSegments()" class="inline-flex items-center px-6 py-2 font-bold text-gray-700 uppercase bg-gray-300 border-0 rounded-lg text-sm hover:bg-gray-400">Freeze/Complete</button>
                    <button onclick="app.deleteSegments()" class="inline-flex items-center px-6 py-2 font-bold text-gray-700 uppercase bg-gray-300 border-0 rounded-lg text-sm hover:bg-gray-400">Delete</button>
                    <button onclick="app.reopenSegments()" class="inline-flex items-center px-6 py-2 font-bold text-gray-700 uppercase bg-gray-300 border-0 rounded-lg text-sm hover:bg-gray-400">Reopen</button>
                    <button onclick="app.addCorrectionNote()" class="inline-flex items-center px-6 py-2 font-bold text-gray-700 uppercase bg-gray-300 border-0 rounded-lg text-sm hover:bg-gray-400">Correction Note</button>
                    <button onclick="app.undoCorrectionNote()" class="inline-flex items-center px-6 py-2 font-bold text-gray-700 uppercase bg-gray-300 border-0 rounded-lg text-sm hover:bg-gray-400">Undo Correction Note</button>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">💡 Tip: Check the boxes next to segments, then click an action button</p>
            </div>
        </div>
    </div>
</div>
                    `;
                    setTimeout(() => {
                        app.initGenerateBill();
                    }, 100);
                }
            },
            profile: {
                title: 'User Profile',
                breadcrumb: 'Profile',
                render(container) {
                    container.innerHTML = `<div class="border-black/12.5 dark:bg-slate-850 dark:shadow-dark-xl shadow-xl relative z-20 flex min-w-0 flex-col break-words rounded-2xl border-0 border-solid bg-white bg-clip-border"><div class="border-black/12.5 mb-0 rounded-t-2xl border-b border-solid p-6 pt-4 pb-3"><h6 class="capitalize dark:text-white">Profile Settings</h6></div><div class="flex-auto p-6"><p class="dark:text-white">Manage your user profile, preferences, and account settings.</p></div></div>`;
                }
            }
        };

        // Handle sidebar close button
        document.addEventListener('click', function(e) {
            if (e.target.getAttribute('sidenav-close')) {
                document.querySelector('[sidenav-main]').classList.add('-translate-x-full');
            }
        });

        // Initialize dashboard on page load
        window.addEventListener('load', () => {
            // Check if segments were loaded from session
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('loaded') === 'true') {
                const accountNumber = urlParams.get('accountNumber');
                if (accountNumber) {
                    document.getElementById('billAccountId').value = accountNumber;
                    
                    // Fetch the loaded data from server
                    fetch('/NWCBilling/build/Employee.php?action=get_loaded_segments')
                        .then(response => response.text())
                        .then(html => {
                            // Parse the HTML response and populate segments
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            
                            // Extract segment data from the response
                            const segmentRows = doc.querySelectorAll('tr[data-segment]');
                            const tbody = document.getElementById('billSegmentsTableBody');
                            
                            if (tbody) {
                                tbody.innerHTML = '';
                                app.billSegments = [];
                                
                                segmentRows.forEach((row, index) => {
                                    const name = row.getAttribute('data-name');
                                    const consumption = parseFloat(row.getAttribute('data-consumption'));
                                    const amount = parseFloat(row.getAttribute('data-amount'));
                                    const status = row.getAttribute('data-status');
                                    const remarks = row.getAttribute('data-remarks') || '';
                                    
                                    app.billSegments.push({name, consumption, amount, status, remarks});
                                    
                                    // Create UI row
                                    const newRow = document.createElement('tr');
                                    newRow.style.borderBottom = '1px solid #e5e7eb';
                                    newRow.innerHTML = `
                                        <td style="padding: 1rem; border-right: 1px solid #e5e7eb; text-align: center;"><input type="checkbox"></td>
                                        <td style="padding: 1rem; border-right: 1px solid #e5e7eb;">${name}</td>
                                        <td style="padding: 1rem; border-right: 1px solid #e5e7eb; text-align: right;">${consumption.toFixed(2)}</td>
                                        <td style="padding: 1rem; border-right: 1px solid #e5e7eb; text-align: right;">SAR ${amount.toFixed(2)}</td>
                                        <td style="padding: 1rem; border-right: 1px solid #e5e7eb;"><select><option>${status}</option></select></td>
                                        <td style="padding: 1rem; border-right: 1px solid #e5e7eb;">${remarks}</td>
                                        <td style="padding: 1rem;"><button onclick="app.removeBillSegment(${index})" style="background-color: #ef4444; color: white; padding: 0.375rem 0.75rem; border: none; border-radius: 0.375rem; cursor: pointer;">Remove</button></td>
                                    `;
                                    tbody.appendChild(newRow);
                                });
                                
                                app.updateBillTotal();
                                alert('Segments loaded from database!');
                            }
                        })
                        .catch(error => console.error('Error loading segments:', error));
                }
            }
            
            app.navigate('dashboard');
        });
    </script>

    <!-- Bill Search Modal -->
    <div id="billsModal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.5); z-index: 9999; display: none; align-items: center; justify-content: center;">
        <div style="background-color: white; border-radius: 1rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); width: 95%; max-width: 1200px; max-height: 85vh; overflow: hidden; display: flex; flex-direction: column;">
            <!-- Modal Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 2rem; border-bottom: 1px solid #e5e7eb; background: linear-gradient(to right, #3b82f6, #1d4ed8); flex-shrink: 0;">
                <h2 style="font-size: 1.875rem; font-weight: bold; color: white; margin: 0;">Bill Search</h2>
                <button type="button" onclick="document.getElementById('billsModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: white; font-size: 2rem; padding: 0;">✕</button>
            </div>
            
            <!-- Modal Body - Scrollable -->
            <div style="flex: 1; overflow-y: auto; padding: 2rem; display: flex; flex-direction: column; gap: 2rem;">
                <!-- Search Form Section -->
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <h3 style="font-size: 1.125rem; font-weight: bold; color: #374151; margin: 0;">Search Criteria</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Person Name</label>
                            <input type="text" placeholder="Enter customer name" style="width: 100%; padding: 0.75rem; border: 2px solid #d1d5db; border-radius: 0.5rem; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Account ID</label>
                            <input type="text" placeholder="Enter account ID" style="width: 100%; padding: 0.75rem; border: 2px solid #d1d5db; border-radius: 0.5rem; box-sizing: border-box;">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Bill ID</label>
                            <input type="text" placeholder="Enter bill ID" style="width: 100%; padding: 0.75rem; border: 2px solid #d1d5db; border-radius: 0.5rem; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Bill Date</label>
                            <input type="date" style="width: 100%; padding: 0.75rem; border: 2px solid #d1d5db; border-radius: 0.5rem; box-sizing: border-box;">
                        </div>
                    </div>
                    
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">CR Note/Reference</label>
                        <input type="text" placeholder="Enter CR note or reference number" style="width: 100%; padding: 0.75rem; border: 2px solid #d1d5db; border-radius: 0.5rem; box-sizing: border-box;">
                    </div>
                </div>

                <!-- Bills Results Section -->
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <h3 style="font-size: 1.125rem; font-weight: bold; color: #374151; margin: 0;">Bills Results</h3>
                    <div style="border: 1px solid #d1d5db; border-radius: 0.75rem; overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; background-color: white;">
                            <thead style="background-color: #f3f4f6; border-bottom: 2px solid #d1d5db;">
                                <tr>
                                    <th style="padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 700; color: #374151; border-right: 1px solid #e5e7eb;">Person Name</th>
                                    <th style="padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 700; color: #374151; border-right: 1px solid #e5e7eb;">Bill Status</th>
                                    <th style="padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 700; color: #374151; border-right: 1px solid #e5e7eb;">Bill Date</th>
                                    <th style="padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 700; color: #374151; border-right: 1px solid #e5e7eb;">Due Date</th>
                                    <th style="padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 700; color: #374151; border-right: 1px solid #e5e7eb;">Amount</th>
                                    <th style="padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 700; color: #374151; border-right: 1px solid #e5e7eb;">Bill ID</th>
                                    <th style="padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 700; color: #374151; border-right: 1px solid #e5e7eb;">Account ID</th>
                                    <th style="padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 700; color: #374151;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="billsTableBody">
                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                    <td colspan="8" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem;">Click Search Bills to view results</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div style="display: flex; justify-content: flex-end; gap: 1rem; padding: 2rem; border-top: 1px solid #e5e7eb; background-color: #f9fafb; flex-shrink: 0;">
                <button type="button" onclick="document.getElementById('billsModal').style.display='none'" style="padding: 0.75rem 1.5rem; background-color: #d1d5db; color: #374151; font-weight: bold; border: none; border-radius: 0.5rem; cursor: pointer;">Cancel</button>
                <button type="button" onclick="app.searchBills()" style="padding: 0.75rem 2rem; background-color: #3b82f6; color: white; font-weight: bold; border: none; border-radius: 0.5rem; cursor: pointer;">Search Bills</button>
            </div>
        </div>
    </div>

    <script>
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('billsModal');
            if (modal && e.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('billsModal');
                if (modal) {
                    modal.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>
