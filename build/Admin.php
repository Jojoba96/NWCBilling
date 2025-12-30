<?php
session_start();
require_once __DIR__ . '/../config/Database.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 3) {
    header('Location: /NWCBilling/build/pages/sign-in.php');
    exit;
}

$db = new Database();

// Handle POST actions (Approve/Reject bills)
if (isset($_POST['action'])) {
    $conn = $db->connect();
    
    if (!$conn) {
        echo 'error: Database connection failed';
        exit;
    }
    
    $action = $_POST['action'];
    
    if ($action === 'approve_bill') {
        $billId = intval($_POST['billId']);
        
        // Update bill status
        $billSql = "UPDATE bills SET status = 'active' WHERE id = $billId";
        
        if ($conn->query($billSql)) {
            // Also update all segments in this bill to 'active' status
            $segmentSql = "UPDATE bill_segments SET status = 'active' WHERE bill_id = $billId";
            $conn->query($segmentSql);
            
            echo 'success: Bill approved and saved to database';
        } else {
            echo 'error: Failed to approve bill';
        }
        exit;
    }
    
    elseif ($action === 'reject_bill') {
        $billId = intval($_POST['billId']);
        
        // Update bill status
        $billSql = "UPDATE bills SET status = 'rejected' WHERE id = $billId";
        
        if ($conn->query($billSql)) {
            // Also update all segments in this bill to 'rejected' status
            $segmentSql = "UPDATE bill_segments SET status = 'rejected' WHERE bill_id = $billId";
            $conn->query($segmentSql);
            
            echo 'success: Bill rejected and saved to database';
        } else {
            echo 'error: Failed to reject bill';
        }
        exit;
    }
}

// Handle AJAX requests FIRST (before any HTML output)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $conn = $db->connect();
    
    if (!$conn) {
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    
    $action = $_GET['action'];
    
    if ($action === 'get_pending_bills') {
        // Fetch all bills with pending_review status
        $sql = "SELECT b.id, b.bill_date, b.due_date, b.total_amount, b.status, 
                       b.account_id, a.account_number, u.full_name
                FROM bills b
                JOIN accounts a ON b.account_id = a.id
                JOIN users u ON a.user_id = u.id
                WHERE b.status = 'pending_review'
                ORDER BY b.bill_date DESC";
        
        $result = $conn->query($sql);
        $bills = [];
        
        if (!$result) {
            echo json_encode(['error' => 'Query error: ' . $conn->error]);
            exit;
        }
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $bills[] = [
                    'id' => $row['id'],
                    'billId' => 'BILL-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT),
                    'personName' => $row['full_name'],
                    'accountId' => $row['account_number'],
                    'billDate' => $row['bill_date'],
                    'dueDate' => $row['due_date'],
                    'totalAmount' => $row['total_amount'],
                    'status' => $row['status']
                ];
            }
        }
        
        echo json_encode(['bills' => $bills]);
        exit;
    }
    
    elseif ($action === 'get_all_employees') {
        // Fetch all employees (role = 2)
        $sql = "SELECT id, full_name, email, phone_number, role, created_at
                FROM users
                WHERE role = 2
                ORDER BY full_name ASC";
        
        $result = $conn->query($sql);
        $employees = [];
        
        if (!$result) {
            echo json_encode(['error' => 'Query error: ' . $conn->error]);
            exit;
        }
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $employees[] = [
                    'id' => $row['id'],
                    'name' => $row['full_name'],
                    'email' => $row['email'],
                    'phone' => $row['phone_number'],
                    'role' => $row['role'],
                    'createdAt' => $row['created_at']
                ];
            }
        }
        
        echo json_encode(['employees' => $employees]);
        exit;
    }
    
    echo json_encode([]);
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
    <title>NWC Billing System - Admin Dashboard</title>
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
    <!-- Hidden form for bill actions -->
    <form id="billActionForm" method="POST" style="display:none;">
        <input type="hidden" id="billIdInput" name="billId" value="">
    </form>
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
                <li class="mt-0.5 w-full"><a class="dark:text-white dark:opacity-80 py-2.7 text-sm ease-nav-brand my-0 mx-2 flex items-center whitespace-nowrap px-4 transition-colors nav-link" href="javascript:void(0)" onclick="app.navigate('manage-employee')" data-page="manage-employee"><i class="ni ni-single-02 text-xl text-cyan-500 mr-2"></i><span>Manage Employee</span></a></li>
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
            employees: [],
            pendingBills: [],

            navigate(pageName) {
                app.currentPage = pageName;
                
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

            logout() {
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = '/NWCBilling/build/pages/sign-in.php';
                }
            },

            loadEmployees() {
                fetch('Admin.php?action=get_all_employees')
                    .then(response => response.json())
                    .then(data => {
                        if (data.employees) {
                            app.employees = data.employees;
                        }
                    })
                    .catch(error => console.error('Error loading employees:', error));
            },

            loadPendingBills() {
                fetch('Admin.php?action=get_pending_bills')
                    .then(response => response.json())
                    .then(data => {
                        if (data.bills) {
                            app.pendingBills = data.bills;
                        }
                    })
                    .catch(error => console.error('Error loading bills:', error));
            },

            approveBill(billId, billElement) {
                if (!confirm('Approve this bill?')) return;
                
                const params = new URLSearchParams();
                params.append('action', 'approve_bill');
                params.append('billId', billId);
                
                fetch('Admin.php', {
                    method: 'POST',
                    body: params
                })
                .then(response => response.text())
                .then(data => {
                    if (data.includes('success')) {
                        // Remove bill from the table
                        billElement.remove();
                        app.loadPendingBills();
                        alert('Bill approved! Status updated in database.');
                    } else {
                        alert('Error approving bill');
                    }
                })
                .catch(error => alert('Error: ' + error));
            },

            rejectBill(billId, billElement) {
                if (!confirm('Reject this bill?')) return;
                
                const params = new URLSearchParams();
                params.append('action', 'reject_bill');
                params.append('billId', billId);
                
                fetch('Admin.php', {
                    method: 'POST',
                    body: params
                })
                .then(response => response.text())
                .then(data => {
                    if (data.includes('success')) {
                        // Remove bill from the table
                        billElement.remove();
                        app.loadPendingBills();
                        alert('Bill rejected! Status updated in database.');
                    } else {
                        alert('Error rejecting bill');
                    }
                })
                .catch(error => alert('Error: ' + error));
            }
        };

        // PAGE DEFINITIONS
        const pages = {
            dashboard: {
                title: 'Dashboard',
                breadcrumb: 'Dashboard',
                render(container) {
                    container.innerHTML = `
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                            <!-- Stats Cards -->
                            <div class="bg-white rounded-lg shadow p-6 dark:bg-slate-850">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-gray-500 text-sm dark:text-gray-400">Pending Bills</p>
                                        <p class="text-3xl font-bold text-slate-700 dark:text-white">0</p>
                                    </div>
                                    <i class="ni ni-credit-card text-4xl text-orange-500 opacity-50"></i>
                                </div>
                            </div>
                            <div class="bg-white rounded-lg shadow p-6 dark:bg-slate-850">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-gray-500 text-sm dark:text-gray-400">Total Employees</p>
                                        <p class="text-3xl font-bold text-slate-700 dark:text-white">0</p>
                                    </div>
                                    <i class="ni ni-single-02 text-4xl text-cyan-500 opacity-50"></i>
                                </div>
                            </div>
                            <div class="bg-white rounded-lg shadow p-6 dark:bg-slate-850">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-gray-500 text-sm dark:text-gray-400">Approved Bills</p>
                                        <p class="text-3xl font-bold text-slate-700 dark:text-white">0</p>
                                    </div>
                                    <i class="ni ni-check-bold text-4xl text-green-500 opacity-50"></i>
                                </div>
                            </div>
                            <div class="bg-white rounded-lg shadow p-6 dark:bg-slate-850">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-gray-500 text-sm dark:text-gray-400">Rejected Bills</p>
                                        <p class="text-3xl font-bold text-slate-700 dark:text-white">0</p>
                                    </div>
                                    <i class="ni ni-fat-remove text-4xl text-red-500 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6 dark:bg-slate-850">
                            <h3 class="text-lg font-semibold text-slate-700 dark:text-white mb-4">Admin Dashboard</h3>
                            <p class="text-gray-600 dark:text-gray-400">Welcome to the Admin Dashboard. Use the sidebar to manage employees and review bills.</p>
                        </div>
                    `;
                }
            },

            'manage-employee': {
                title: 'Manage Employees',
                breadcrumb: 'Manage Employees',
                render(container) {
                    app.loadEmployees();
                    container.innerHTML = `
                        <div class="bg-white rounded-lg shadow p-6 dark:bg-slate-850">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-lg font-semibold text-slate-700 dark:text-white">Employees List</h3>
                                <button onclick="app.navigate('add-employee')" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition">
                                    <i class="ni ni-fat-add mr-2"></i>Add Employee
                                </button>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-100 dark:bg-slate-800">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-white">Name</th>
                                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-white">Email</th>
                                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-white">Phone</th>
                                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-white">Joined</th>
                                            <th class="px-4 py-3 text-center font-semibold text-slate-700 dark:text-white">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="employeesTable">
                                        <tr class="border-b border-gray-200 dark:border-slate-700">
                                            <td colspan="5" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">Loading employees...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                    
                    // Wait a moment for page render, then populate employees
                    setTimeout(() => {
                        const employeesTable = document.getElementById('employeesTable');
                        if (!employeesTable) return;
                        
                        if (app.employees.length === 0) {
                            employeesTable.innerHTML = '<tr class="border-b border-gray-200 dark:border-slate-700"><td colspan="5" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">No employees found</td></tr>';
                            return;
                        }
                        
                        employeesTable.innerHTML = app.employees.map(emp => `
                            <tr class="border-b border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700">
                                <td class="px-4 py-3 text-slate-700 dark:text-white">${emp.name}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-white">${emp.email}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-white">${emp.phone}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-white">${new Date(emp.createdAt).toLocaleDateString()}</td>
                                <td class="px-4 py-3 text-center">
                                    <button class="text-blue-500 hover:text-blue-700 mr-3" title="Edit"><i class="ni ni-settings"></i></button>
                                    <button class="text-red-500 hover:text-red-700" title="Delete"><i class="ni ni-fat-remove"></i></button>
                                </td>
                            </tr>
                        `).join('');
                    }, 100);
                }
            },

            bills: {
                title: 'Manage Bills',
                breadcrumb: 'Bills',
                render(container) {
                    app.loadPendingBills();
                    container.innerHTML = `
                        <div class="bg-white rounded-lg shadow p-6 dark:bg-slate-850">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-lg font-semibold text-slate-700 dark:text-white">Pending Bills for Review</h3>
                                <span class="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-sm font-semibold dark:bg-orange-900 dark:text-orange-200">
                                    <span id="billCount">0</span> Bills
                                </span>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-100 dark:bg-slate-800">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-white">Bill ID</th>
                                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-white">Customer Name</th>
                                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-white">Account</th>
                                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-white">Amount</th>
                                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-white">Bill Date</th>
                                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-white">Status</th>
                                            <th class="px-4 py-3 text-center font-semibold text-slate-700 dark:text-white">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="billsTable">
                                        <tr class="border-b border-gray-200 dark:border-slate-700">
                                            <td colspan="7" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">Loading bills...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                    
                    // Wait a moment for page render, then populate bills
                    setTimeout(() => {
                        const billsTable = document.getElementById('billsTable');
                        const billCount = document.getElementById('billCount');
                        
                        if (!billsTable) return;
                        
                        if (app.pendingBills.length === 0) {
                            billsTable.innerHTML = '<tr class="border-b border-gray-200 dark:border-slate-700"><td colspan="7" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">No pending bills</td></tr>';
                            if (billCount) billCount.textContent = '0';
                            return;
                        }
                        
                        billsTable.innerHTML = app.pendingBills.map(bill => `
                            <tr id="bill-row-${bill.id}" class="border-b border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700">
                                <td class="px-4 py-3 text-slate-700 dark:text-white font-semibold">${bill.billId}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-white">${bill.personName}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-white">${bill.accountId}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-white">SAR ${Number(bill.totalAmount).toFixed(2)}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-white">${new Date(bill.billDate).toLocaleDateString()}</td>
                                <td class="px-4 py-3">
                                    <span class="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-xs font-semibold dark:bg-orange-900 dark:text-orange-200">
                                        ${bill.status}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button onclick="app.approveBill(${bill.id}, document.getElementById('bill-row-${bill.id}'))" style="background-color: #22c55e; color: white; padding: 0.25rem 0.75rem; border-radius: 0.375rem; border: none; cursor: pointer; font-weight: bold; margin-right: 0.5rem;" title="Approve">✓ Approve</button>
                                    <button onclick="app.rejectBill(${bill.id}, document.getElementById('bill-row-${bill.id}'))" style="background-color: #ef4444; color: white; padding: 0.25rem 0.75rem; border-radius: 0.375rem; border: none; cursor: pointer; font-weight: bold;" title="Reject">✗ Reject</button>
                                </td>
                            </tr>
                        `).join('');
                        
                        if (billCount) billCount.textContent = app.pendingBills.length;
                    }, 100);
                }
            }
        };

        // Initialize on page load
        window.addEventListener('load', () => {
            app.navigate('dashboard');
        });
    </script>
</body>
</html>
