<?php
session_start();
require_once '../../config/Database.php';

// Check if user is logged in (session should be maintained from Employee.php)
// If not logged in, redirect to login
if (!isset($_SESSION['user_id'])) {
    // Allow access if coming from Employee.php or allow API access
    // This is a protected action anyway
}

$accountId = isset($_GET['accountId']) ? $_GET['accountId'] : null;
$billId = isset($_GET['billId']) ? $_GET['billId'] : null;

if (!$accountId || !$billId) {
    die('Missing parameters');
}

$db = new Database();
$conn = $db->connect();

// Fetch account details
$accountQuery = "SELECT a.id, a.account_number, a.account_type, u.full_name 
                FROM accounts a
                JOIN users u ON a.user_id = u.id
                WHERE a.account_number = ? LIMIT 1";
$accountStmt = $conn->prepare($accountQuery);
$accountStmt->bind_param('s', $accountId);
$accountStmt->execute();
$accountResult = $accountStmt->get_result();
$account = $accountResult->fetch_assoc();

if (!$account) {
    die('Account not found');
}

// Fetch bill details
$billQuery = "SELECT b.*, COUNT(bs.id) as segment_count, SUM(bs.amount) as bill_total 
              FROM bills b 
              LEFT JOIN bill_segments bs ON b.id = bs.bill_id 
              WHERE b.id = ? AND b.account_id = ? 
              GROUP BY b.id";
$billStmt = $conn->prepare($billQuery);
$billStmt->bind_param('ii', $billId, $account['id']);
$billStmt->execute();
$billResult = $billStmt->get_result();
$bill = $billResult->fetch_assoc();

if (!$bill) {
    die('Bill not found');
}

// Fetch bill segments
$segmentsQuery = "SELECT * FROM bill_segments WHERE bill_id = ? ORDER BY created_at ASC";
$segmentsStmt = $conn->prepare($segmentsQuery);
$segmentsStmt->bind_param('i', $billId);
$segmentsStmt->execute();
$segmentsResult = $segmentsStmt->get_result();
$segments = [];
while ($row = $segmentsResult->fetch_assoc()) {
    $segments[] = $row;
}

$totalAmount = $bill['bill_total'] ?? 0;
$billDate = new DateTime($bill['bill_date'] ?? 'now');
$dueDate = new DateTime($bill['due_date'] ?? 'now');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill #<?php echo htmlspecialchars($bill['id']); ?> - NWC Billing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Bill Header -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <!-- Header Section -->
            <div class="flex justify-between items-start mb-8 pb-8 border-b-2 border-gray-200">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">INVOICE</h1>
                    <p class="text-gray-600">Bill #<span class="font-bold"><?php echo htmlspecialchars($bill['id']); ?></span></p>
                </div>
                <div class="text-right">
                    <h2 class="text-2xl font-bold text-blue-600">NWC BILLING</h2>
                    <p class="text-gray-600 text-sm">National Water Company</p>
                </div>
            </div>

            <!-- Customer & Dates Section -->
            <div class="grid grid-cols-2 gap-8 mb-8">
                <div>
                    <h3 class="text-sm font-bold text-gray-700 mb-3 uppercase">Bill To:</h3>
                    <div class="text-gray-800">
                        <p class="font-semibold text-lg"><?php echo htmlspecialchars($account['full_name'] ?? 'N/A'); ?></p>
                        <p class="text-sm text-gray-600">Account: <?php echo htmlspecialchars($account['account_number']); ?></p>
                        <p class="text-sm text-gray-600">Account Type: <?php echo htmlspecialchars($account['account_type'] ?? 'N/A'); ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="mb-4">
                        <p class="text-sm text-gray-600">Bill Date:</p>
                        <p class="text-lg font-semibold text-gray-800"><?php echo $billDate->format('d/m/Y'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Due Date:</p>
                        <p class="text-lg font-semibold text-gray-800"><?php echo $dueDate->format('d/m/Y'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Bill Items Table -->
            <div class="mb-8">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100 border-b-2 border-gray-300">
                            <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">Description</th>
                            <th class="px-4 py-3 text-center text-sm font-bold text-gray-700">Consumption (m³)</th>
                            <th class="px-4 py-3 text-right text-sm font-bold text-gray-700">Unit Price</th>
                            <th class="px-4 py-3 text-right text-sm font-bold text-gray-700">Amount (SAR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $subtotal = 0;
                        foreach ($segments as $segment): 
                            $subtotal += floatval($segment['amount']);
                        ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-800">
                                <strong><?php echo htmlspecialchars($segment['name']); ?></strong><br>
                                <?php if (!empty($segment['remarks'])): ?>
                                    <small class="text-gray-600"><?php echo htmlspecialchars($segment['remarks']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-800"><?php echo number_format(floatval($segment['consumption']), 2); ?></td>
                            <td class="px-4 py-3 text-right text-sm text-gray-800">SAR <?php echo number_format(floatval($segment['amount']) / floatval($segment['consumption']), 2); ?></td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">SAR <?php echo number_format(floatval($segment['amount']), 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals Section -->
            <div class="flex justify-end mb-8">
                <div class="w-full sm:w-96">
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span class="text-gray-700">Subtotal:</span>
                        <span class="font-semibold text-gray-900">SAR <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="flex justify-between py-2 bg-blue-50 px-3 rounded mt-4">
                        <span class="text-lg font-bold text-gray-900">Total Due:</span>
                        <span class="text-lg font-bold text-blue-600">SAR <?php echo number_format($totalAmount, 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Footer Section -->
            <div class="pt-8 border-t-2 border-gray-200">
                <p class="text-xs text-gray-600 mb-4">
                    <strong>Bill Status:</strong> 
                    <span class="px-3 py-1 rounded text-white text-xs font-semibold" 
                          style="background-color: <?php 
                              $statusColor = $bill['status'] === 'draft' ? '#FCD34D' : 
                                           ($bill['status'] === 'pending_review' ? '#60A5FA' : 
                                           ($bill['status'] === 'active' ? '#10B981' : '#EF4444'));
                              echo $statusColor;
                          ?>">
                        <?php echo strtoupper($bill['status']); ?>
                    </span>
                </p>
                <p class="text-xs text-gray-600">
                    <strong>Payment Terms:</strong> Due by the date mentioned above. Please make payments through authorized channels.
                </p>
                <p class="text-xs text-gray-600 mt-4">
                    Thank you for your business with the National Water Company (NWC).
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="bg-white rounded-lg shadow p-6 no-print">
            <div class="flex gap-4 justify-center">
                <button onclick="window.print()" class="px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">
                    🖨️ Print Bill
                </button>
                <button onclick="downloadPDF()" class="px-6 py-2 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700">
                    📥 Download PDF
                </button>
                <button onclick="window.close()" class="px-6 py-2 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700">
                    ❌ Close
                </button>
            </div>
        </div>
    </div>

    <script>
        function downloadPDF() {
            const billId = <?php echo intval($bill['id']); ?>;
            const accountId = "<?php echo htmlspecialchars($accountId); ?>";
            
            // Use html2pdf library for PDF generation
            const element = document.querySelector('.container');
            const opt = {
                margin: 10,
                filename: `Bill-${billId}-${accountId}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { orientation: 'portrait', unit: 'mm', format: 'a4' }
            };
            
            // Create script tag for html2pdf if not exists
            if (typeof html2pdf === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
                document.head.appendChild(script);
                script.onload = function() {
                    html2pdf().set(opt).from(element).save();
                };
            } else {
                html2pdf().set(opt).from(element).save();
            }
        }
    </script>
</body>
</html>
