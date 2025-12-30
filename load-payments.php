<?php
// Load database connection
require 'config/Database.php';

// Create instance and connect
$database = new Database();
$conn = $database->connect();

if (!$conn) {
    die('Connection failed');
}

// SQL to insert payment records
$sql = "INSERT INTO payments (account_id, bill_id, payment_date, amount, status) VALUES
(1, 1, '2025-02-10', 85.50, 'completed'),
(1, 2, '2025-03-10', 92.75, 'completed'),
(1, 3, '2025-04-10', 78.25, 'completed'),
(1, 4, '2025-05-10', 95.50, 'completed'),
(1, 5, '2025-06-10', 110.00, 'completed'),
(1, 6, '2025-07-10', 125.75, 'completed'),
(1, 7, '2025-08-10', 135.50, 'completed'),
(1, 8, '2025-09-10', 128.00, 'completed'),
(1, 9, '2025-10-10', 98.50, 'completed'),
(1, 10, '2025-11-10', 87.75, 'completed'),
(2, 13, '2025-02-10', 65.30, 'completed'),
(2, 14, '2025-03-10', 72.50, 'completed'),
(3, 25, '2025-02-10', 78.00, 'completed')";

if ($conn->query($sql) === TRUE) {
    echo "✅ Payment records inserted successfully!";
} else {
    echo "❌ Error inserting payment records: " . $conn->error;
}

$conn->close();
?>
