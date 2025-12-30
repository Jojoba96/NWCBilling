<?php
require_once __DIR__ . '/config/Database.php';

$db = new Database();
$conn = $db->connect();

echo "<h2>Database Connection Test</h2>";

if ($conn) {
    echo "<p style='color: green;'>✅ Connected to MySQL</p>";
    
    // Check if database exists
    $result = $conn->query("SHOW DATABASES LIKE 'nwc_billing'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✅ Database 'nwc_billing' EXISTS</p>";
    } else {
        echo "<p style='color: red;'>❌ Database 'nwc_billing' NOT FOUND</p>";
        echo "<p>Available databases:</p>";
        $result = $conn->query("SHOW DATABASES");
        while ($row = $result->fetch_assoc()) {
            echo "<li>" . $row['Database'] . "</li>";
        }
        exit;
    }
    
    // Check tables
    $result = $conn->query("SHOW TABLES FROM nwc_billing");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✅ Tables found: " . $result->num_rows . "</p>";
        while ($row = $result->fetch_assoc()) {
            echo "<li>" . $row[0] . "</li>";
        }
    } else {
        echo "<p style='color: red;'>❌ No tables in database</p>";
    }
    
    // Check users
    $result = $conn->query("SELECT COUNT(*) as count FROM nwc_billing.users");
    $row = $result->fetch_assoc();
    echo "<p>Users in database: <strong>" . $row['count'] . "</strong></p>";
    
    // Check accounts
    $result = $conn->query("SELECT COUNT(*) as count FROM nwc_billing.accounts");
    $row = $result->fetch_assoc();
    echo "<p>Accounts in database: <strong>" . $row['count'] . "</strong></p>";
    
} else {
    echo "<p style='color: red;'>❌ Connection FAILED</p>";
}
?>
