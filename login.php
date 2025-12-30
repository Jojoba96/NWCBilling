<?php
session_start();
require_once __DIR__ . '/config/Database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        $db = new Database();
        $conn = $db->getConnection();
        
        $query = "SELECT id, username, full_name, role FROM users WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // For demo: password = username
            if ($password === $username) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                if ($user['role'] == 2) {
                    header('Location: /NWCBilling/build/Employee.php');
                } else {
                    header('Location: /NWCBilling/build/Customer.php');
                }
                exit;
            } else {
                $error = 'Invalid password';
            }
        } else {
            $error = 'User not found';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NWC Billing</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-600 to-blue-800 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-lg shadow-xl p-8">
            <h1 class="text-3xl font-bold text-center text-gray-800 mb-2">NWC Billing</h1>
            <p class="text-center text-gray-600 mb-6">Water Utility Billing System</p>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Username</label>
                    <input type="text" name="username" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600" required>
                </div>
                
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Password</label>
                    <input type="password" name="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600" required>
                </div>
                
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 transition">Login</button>
            </form>
            
            <hr class="my-6">
            
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-sm text-gray-700 font-bold mb-3">Demo Accounts:</p>
                <p class="text-sm text-gray-600 mb-2"><strong>Employee:</strong> emp_saleh / emp_saleh</p>
                <p class="text-sm text-gray-600"><strong>Customer:</strong> ahmed_ali / ahmed_ali</p>
            </div>
        </div>
    </div>
</body>
</html>
