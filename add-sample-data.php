<?php
require_once __DIR__ . '/config/Database.php';

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo "Database connection failed";
    exit;
}

echo "========================================\n";
echo "Adding Sample Bills to Existing Accounts\n";
echo "========================================\n\n";

// Get existing accounts
$accounts_sql = "SELECT a.id, a.account_number, u.full_name FROM accounts a JOIN users u ON a.user_id = u.id LIMIT 10";
$accounts_result = $conn->query($accounts_sql);

if (!$accounts_result || $accounts_result->num_rows === 0) {
    echo "No existing accounts found. Please create accounts first.\n";
    $conn->close();
    exit;
}

$existing_accounts = [];
echo "Found existing accounts:\n";
while ($row = $accounts_result->fetch_assoc()) {
    $existing_accounts[] = [
        'id' => $row['id'],
        'account_number' => $row['account_number'],
        'full_name' => $row['full_name']
    ];
    echo "✓ {$row['full_name']} ({$row['account_number']})\n";
}

echo "\nAdding sample bills...\n\n";

// Sample bills for each account
$bills_data = [
    ['days_ago' => 45, 'amount' => 450.50, 'status' => 'paid'],
    ['days_ago' => 75, 'amount' => 420.75, 'status' => 'paid'],
    ['days_ago' => 15, 'amount' => 480.25, 'status' => 'unpaid'],
];

$added_count = 0;
foreach ($existing_accounts as $account) {
    echo "\nAdding bills for: {$account['full_name']} ({$account['account_number']})\n";
    
    foreach ($bills_data as $bill_info) {
        $account_id = (int)$account['id'];
        $bill_date = date('Y-m-d', strtotime("-{$bill_info['days_ago']} days"));
        $due_date = date('Y-m-d', strtotime($bill_date . " +30 days"));
        $amount = $bill_info['amount'];
        $status = $bill_info['status'];
        
        $sql = "INSERT INTO bills (account_id, bill_date, due_date, total_amount, status) 
                VALUES ($account_id, '$bill_date', '$due_date', $amount, '$status')
                ON DUPLICATE KEY UPDATE total_amount = VALUES(total_amount)";
        
        if ($conn->query($sql) === TRUE) {
            echo "  ✓ Bill dated $bill_date - SAR $amount ($status)\n";
            $added_count++;
        } else {
            echo "  ✗ Error: " . $conn->error . "\n";
        }
    }
}

echo "\n========================================\n";
echo "✓ Successfully added $added_count bills!\n";
echo "========================================\n\n";

echo "Now you can search in the Bills modal:\n\n";

echo "SEARCH OPTIONS:\n";
foreach ($existing_accounts as $account) {
    echo "  • Person Name: {$account['full_name']}\n";
    echo "    Account ID: {$account['account_number']}\n";
}

echo "\nTRY THESE SEARCHES:\n";
echo "  1. Enter a customer name\n";
echo "  2. Or enter an account ID (like ACC-00001)\n";
echo "  3. Click 'Search Bills' to see their bills\n";
echo "  4. You'll see Paid, Unpaid bills with dates and amounts\n\n";

$conn->close();
?>
