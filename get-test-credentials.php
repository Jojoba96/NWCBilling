<?php
require_once __DIR__ . '/config/Database.php';

$db = new Database();
$conn = $db->connect();

// Check for existing customers (role = 1)
$query = "SELECT id, username, national_id, full_name, role FROM users WHERE role = 1 LIMIT 5";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "✓ Found existing customers:\n\n";
    while ($row = $result->fetch_assoc()) {
        echo "National ID: " . $row['national_id'] . "\n";
        echo "Password: " . $row['national_id'] . " (same as National ID)\n";
        echo "Name: " . $row['full_name'] . "\n";
        echo "---\n";
    }
} else {
    echo "No customers found. Creating test customer...\n\n";
    
    $testNationalId = "1234567890";
    $username = "test_customer";
    $fullName = "Test Customer";
    $email = "test@nwc.com";
    $role = 1; // Customer
    
    $insertQuery = "INSERT INTO users (username, national_id, full_name, email, role) 
                   VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param('ssssi', $username, $testNationalId, $fullName, $email, $role);
    
    if ($stmt->execute()) {
        echo "✓ Test Customer Created!\n\n";
        echo "National ID: " . $testNationalId . "\n";
        echo "Password: " . $testNationalId . "\n";
        echo "Name: " . $fullName . "\n";
    } else {
        echo "✗ Error: " . $stmt->error;
    }
}

$conn->close();
?>
