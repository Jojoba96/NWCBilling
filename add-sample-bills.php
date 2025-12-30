<?php
require_once __DIR__ . '/config/Database.php';

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    die('Database connection failed');
}

// Get all accounts
$accountSql = "SELECT id, account_number FROM accounts";
$accountResult = $conn->query($accountSql);

if (!$accountResult) {
    die('Error fetching accounts: ' . $conn->error);
}

$billsAdded = 0;

if ($accountResult->num_rows > 0) {
    while ($account = $accountResult->fetch_assoc()) {
        $accountId = $account['id'];
        
        // Create bills from Jan 1, 2025 to Dec 17, 2025 (monthly)
        $months = [
            ['2025-01-15', '2025-02-15', 'January'],
            ['2025-02-15', '2025-03-15', 'February'],
            ['2025-03-15', '2025-04-15', 'March'],
            ['2025-04-15', '2025-05-15', 'April'],
            ['2025-05-15', '2025-06-15', 'May'],
            ['2025-06-15', '2025-07-15', 'June'],
            ['2025-07-15', '2025-08-15', 'July'],
            ['2025-08-15', '2025-09-15', 'August'],
            ['2025-09-15', '2025-10-15', 'September'],
            ['2025-10-15', '2025-11-15', 'October'],
            ['2025-11-15', '2025-12-15', 'November'],
            ['2025-12-15', '2026-01-15', 'December']
        ];
        
        foreach ($months as $month) {
            $billingDate = $month[0];
            $dueDate = $month[1];
            $monthName = $month[2];
            
            // Random amount between SAR 50 and SAR 300
            $amount = rand(50, 300);
            $billNumber = $account['account_number'] . '-' . date('Ym', strtotime($billingDate));
            
            // Status: most are paid, last one is pending
            $status = (strtotime($billingDate) < strtotime('2025-12-01')) ? 'Paid' : 'Pending';
            
            $insertSql = "INSERT INTO bills (account_id, bill_number, billing_date, due_date, amount, status, created_at) 
                         VALUES ($accountId, '$billNumber', '$billingDate', '$dueDate', $amount, '$status', NOW())
                         ON DUPLICATE KEY UPDATE amount = $amount, status = '$status'";
            
            if (!$conn->query($insertSql)) {
                echo "Error inserting bill for account $accountId ($monthName): " . $conn->error . "<br>";
            } else {
                $billsAdded++;
            }
        }
    }
}

echo "<h2 style='color: green;'>✅ Sample Bills Added Successfully!</h2>";
echo "<p><strong>Total Bills Created:</strong> $billsAdded</p>";
echo "<p><strong>Period:</strong> January 1, 2025 - December 17, 2025</p>";
echo "<p><strong>Billing Cycle:</strong> Monthly</p>";
echo "<p><strong>Amount Range:</strong> SAR 50 - SAR 300</p>";
echo "<p><a href='index.php'>Back to Home</a></p>";
?>
