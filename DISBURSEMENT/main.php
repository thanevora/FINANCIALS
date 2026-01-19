<?php
session_start();
include("../API_gateway.php");

// Database configuration
$db_name = "fina_budget";

// Check database connection
if (!isset($connections[$db_name])) {
    die("❌ Connection not found for $db_name");
}

$conn = $connections[$db_name];

// Fetch disbursement data from database
$disbursement_data = [];
$department_allocations = [];
$pending_requests = [];

try {
    // Get total disbursements
    $total_query = "SELECT SUM(amount) as total_disbursements FROM disbursements WHERE status = 'approved'";
    $total_result = mysqli_query($conn, $total_query);
    $total_row = mysqli_fetch_assoc($total_result);
    $total_disbursements = $total_row['total_disbursements'] ?? 0;

    // Get monthly disbursements
    $monthly_query = "SELECT SUM(amount) as monthly_total FROM disbursements 
                     WHERE status = 'approved' AND MONTH(disbursement_date) = MONTH(CURRENT_DATE()) 
                     AND YEAR(disbursement_date) = YEAR(CURRENT_DATE())";
    $monthly_result = mysqli_query($conn, $monthly_query);
    $monthly_row = mysqli_fetch_assoc($monthly_result);
    $monthly_total = $monthly_row['monthly_total'] ?? 0;

    // Get pending requests count
    $pending_query = "SELECT COUNT(*) as pending_count, SUM(amount) as pending_total 
                     FROM disbursements WHERE status = 'pending'";
    $pending_result = mysqli_query($conn, $pending_query);
    $pending_row = mysqli_fetch_assoc($pending_result);
    $pending_count = $pending_row['pending_count'] ?? 0;
    $pending_total = $pending_row['pending_total'] ?? 0;

    // Get approved this month count
    $approved_query = "SELECT COUNT(*) as approved_count FROM disbursements 
                      WHERE status = 'approved' AND MONTH(disbursement_date) = MONTH(CURRENT_DATE()) 
                      AND YEAR(disbursement_date) = YEAR(CURRENT_DATE())";
    $approved_result = mysqli_query($conn, $approved_query);
    $approved_row = mysqli_fetch_assoc($approved_result);
    $approved_count = $approved_row['approved_count'] ?? 0;

    // Get departments count
    $dept_query = "SELECT COUNT(DISTINCT department) as dept_count FROM disbursements WHERE status = 'approved'";
    $dept_result = mysqli_query($conn, $dept_query);
    $dept_row = mysqli_fetch_assoc($dept_result);
    $dept_count = $dept_row['dept_count'] ?? 0;

    // Get recent disbursement requests
    $requests_query = "SELECT * FROM disbursements ORDER BY created_at DESC LIMIT 10";
    $requests_result = mysqli_query($conn, $requests_query);
    while ($row = mysqli_fetch_assoc($requests_result)) {
        $disbursement_data[] = $row;
    }

    // Get department allocations
    $allocations_query = "SELECT department, SUM(amount) as total_allocated, 
                         (SELECT SUM(amount) FROM budget_allocations WHERE department = d.department) as total_budget
                         FROM disbursements d 
                         WHERE status = 'approved' 
                         GROUP BY department";
    $allocations_result = mysqli_query($conn, $allocations_query);
    while ($row = mysqli_fetch_assoc($allocations_result)) {
        $department_allocations[] = $row;
    }

    // Get pending requests for table
    $pending_requests_query = "SELECT * FROM disbursements WHERE status = 'pending' ORDER BY created_at DESC";
    $pending_requests_result = mysqli_query($conn, $pending_requests_query);
    while ($row = mysqli_fetch_assoc($pending_requests_result)) {
        $pending_requests[] = $row;
    }

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
}

// Helper function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Helper function to get status badge class
function getStatusBadge($status) {
    switch ($status) {
        case 'approved':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-amber-100 text-amber-800';
        case 'rejected':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disbursement Management | System Name</title>
            <?php include '../COMPONENTS/header.php'; ?>

</head>
<body class="bg-base-100 min-h-screen bg-white">
  <div class="flex h-screen">
    <!-- Sidebar -->
    <?php include '../COMPONENTS/sidebar.php'; ?>

    <!-- Content Area -->
    <div class="flex flex-col flex-1 overflow-auto">
        <!-- Navbar -->
        <?php include '../COMPONENTS/navbar.php'; ?>

        <!-- Main Content -->
        <main class="p-6">
            <div class="container mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-800">Disbursement Management</h1>
                    <p class="text-gray-600 mt-2">Manage and track all outgoing payments and fund allocations</p>
                </div>

                <!-- Disbursement Summary Cards -->
                <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                            <span class="p-2 mr-3 rounded-lg bg-blue-100/50 text-blue-600">
                                <i data-lucide="wallet" class="w-5 h-5"></i>
                            </span>
                            Disbursement Overview
                        </h2>
                        <div class="flex gap-2">
                            <button onclick="openModal('disburseAllocationModal')" class="px-4 py-2 bg-blue-600 text-white rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors">
                                <i data-lucide="dollar-sign" class="w-4 h-4"></i>
                                Disburse Allocation
                            </button>
                            <button onclick="openModal('disbursementRequestModal')" class="px-4 py-2 bg-green-600 text-white rounded-lg flex items-center gap-2 hover:bg-green-700 transition-colors">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                                Disbursement Request
                            </button>
                            <button onclick="window.location.href='pending_disbursement.php'" class="px-4 py-2 bg-amber-500 text-white rounded-lg flex items-center gap-2 hover:bg-amber-600 transition-colors">
                                <i data-lucide="clock" class="w-4 h-4"></i>
                                Pending Disbursement
                            </button>
                        </div>
                    </div>

                    <!-- Disbursement Dashboard Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 h-full">
                        
                        <!-- Total Disbursements Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Total Disbursements</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo formatCurrency($total_disbursements); ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full" style="background:#F7B32B1A; color:#F7B32B;">
                                    <i data-lucide="wallet" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full" style="background:#F7B32B; width: <?php echo $monthly_total > 0 ? min(100, ($monthly_total / $total_disbursements) * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>This month</span>
                                    <span class="font-medium"><?php echo formatCurrency($monthly_total); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Requests Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Pending Requests</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $pending_count; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                                    <i data-lucide="clock" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo $pending_count > 0 ? min(100, ($pending_count / ($pending_count + $approved_count)) * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Awaiting approval</span>
                                    <span class="font-medium"><?php echo formatCurrency($pending_total); ?> total</span>
                                </div>
                            </div>
                        </div>

                        <!-- Approved This Month Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Approved This Month</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo formatCurrency($monthly_total); ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-green-100 text-green-600">
                                    <i data-lucide="check-circle" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $approved_count > 0 ? min(100, ($approved_count / ($approved_count + $pending_count)) * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Processed payments</span>
                                    <span class="font-medium"><?php echo $approved_count; ?> transactions</span>
                                </div>
                            </div>
                        </div>

                        <!-- Departments Allocation Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Departments</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $dept_count; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                    <i data-lucide="building" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-purple-500 rounded-full" style="width: 100%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Active departments</span>
                                    <span class="font-medium">All allocated</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Navigation Tabs -->
                <div class="mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8">
                            <a href="#" class="border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Disbursement Request
                            </a>
                            <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Pending Disbursement
                            </a>
                            <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Disbursement Transactions
                            </a>
                            <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Disbursement Allocation
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Content Area -->
                <section class="glass-effect p-6 rounded-2xl shadow-sm">
                    <div class="mb-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Disbursement Requests</h3>
                        <p class="text-gray-600">Manage and review all disbursement requests from various departments.</p>
                    </div>
                    
                    <!-- Disbursement Requests Table -->
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($disbursement_data)): ?>
                                        <tr>
                                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                                <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                                                <p>No disbursement requests found</p>
                                                <p class="text-sm">Disbursement requests will appear here once submitted</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($disbursement_data as $request): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($request['request_id'] ?? 'N/A'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo formatCurrency($request['amount'] ?? 0); ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($request['purpose'] ?? 'No purpose specified'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo date('Y-m-d', strtotime($request['created_at'] ?? '')); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusBadge($request['status'] ?? ''); ?>">
                                                        <?php echo ucfirst($request['status'] ?? 'unknown'); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button onclick="viewRequest(<?php echo $request['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                                                    <?php if ($request['status'] === 'pending'): ?>
                                                        <button onclick="approveRequest(<?php echo $request['id']; ?>)" class="text-green-600 hover:text-green-900">Approve</button>
                                                    <?php else: ?>
                                                        <button class="text-gray-400 cursor-not-allowed" disabled>Processed</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Department Allocation Overview -->
                <section class="glass-effect p-6 rounded-2xl shadow-sm mt-6">
                    <div class="mb-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Department Allocation Overview</h3>
                        <p class="text-gray-600">Current budget allocation and utilization across departments.</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php if (empty($department_allocations)): ?>
                            <div class="col-span-3 text-center py-8 text-gray-500">
                                <i data-lucide="building" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                                <p>No department allocation data available</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            // Group departments by type for better organization
                            $department_groups = [];
                            foreach ($department_allocations as $dept) {
                                $dept_name = $dept['department'] ?? 'Unknown';
                                if (strpos($dept_name, 'HR') !== false) {
                                    $department_groups['hr'][] = $dept;
                                } elseif (strpos($dept_name, 'Logistics') !== false) {
                                    $department_groups['logistics'][] = $dept;
                                } else {
                                    $department_groups['other'][] = $dept;
                                }
                            }
                            ?>
                            
                            <!-- HR Departments -->
                            <?php if (!empty($department_groups['hr'])): ?>
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                    <i data-lucide="users" class="w-5 h-5 mr-2 text-blue-600"></i>
                                    HR Departments
                                </h4>
                                <div class="space-y-4">
                                    <?php foreach ($department_groups['hr'] as $dept): ?>
                                        <div>
                                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                                <span><?php echo htmlspecialchars($dept['department']); ?></span>
                                                <span><?php echo formatCurrency($dept['total_allocated'] ?? 0); ?> / <?php echo formatCurrency($dept['total_budget'] ?? 0); ?></span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <?php 
                                                $percentage = ($dept['total_budget'] > 0) ? (($dept['total_allocated'] / $dept['total_budget']) * 100) : 0;
                                                ?>
                                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(100, $percentage); ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Logistics Departments -->
                            <?php if (!empty($department_groups['logistics'])): ?>
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                    <i data-lucide="truck" class="w-5 h-5 mr-2 text-green-600"></i>
                                    Logistics Departments
                                </h4>
                                <div class="space-y-4">
                                    <?php foreach ($department_groups['logistics'] as $dept): ?>
                                        <div>
                                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                                <span><?php echo htmlspecialchars($dept['department']); ?></span>
                                                <span><?php echo formatCurrency($dept['total_allocated'] ?? 0); ?> / <?php echo formatCurrency($dept['total_budget'] ?? 0); ?></span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <?php 
                                                $percentage = ($dept['total_budget'] > 0) ? (($dept['total_allocated'] / $dept['total_budget']) * 100) : 0;
                                                ?>
                                                <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo min(100, $percentage); ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Other Departments -->
                            <?php if (!empty($department_groups['other'])): ?>
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                    <i data-lucide="building" class="w-5 h-5 mr-2 text-purple-600"></i>
                                    Other Departments
                                </h4>
                                <div class="space-y-4">
                                    <?php foreach ($department_groups['other'] as $dept): ?>
                                        <div>
                                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                                <span><?php echo htmlspecialchars($dept['department']); ?></span>
                                                <span><?php echo formatCurrency($dept['total_allocated'] ?? 0); ?> / <?php echo formatCurrency($dept['total_budget'] ?? 0); ?></span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <?php 
                                                $percentage = ($dept['total_budget'] > 0) ? (($dept['total_allocated'] / $dept['total_budget']) * 100) : 0;
                                                ?>
                                                <div class="bg-purple-600 h-2 rounded-full" style="width: <?php echo min(100, $percentage); ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Disburse Allocation Modal -->
    <div id="disburseAllocationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Disburse Allocation</h3>
                    <button onclick="closeModal('disburseAllocationModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form id="allocationForm" method="POST" action="API/disburse_allocation.php">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Department</label>
                        <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Choose department...</option>
                            <?php
                            // Fetch departments from database
                            $depts_query = "SELECT DISTINCT department FROM disbursements ORDER BY department";
                            $depts_result = mysqli_query($conn, $depts_query);
                            while ($dept = mysqli_fetch_assoc($depts_result)):
                            ?>
                                <option value="<?php echo htmlspecialchars($dept['department']); ?>">
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Allocation Amount (₱)</label>
                        <input type="number" name="amount" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="0.00" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quarter</label>
                        <select name="quarter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="Q1">Q1 <?php echo date('Y'); ?></option>
                            <option value="Q2">Q2 <?php echo date('Y'); ?></option>
                            <option value="Q3">Q3 <?php echo date('Y'); ?></option>
                            <option value="Q4">Q4 <?php echo date('Y'); ?></option>
                        </select>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                        <textarea name="purpose" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Purpose of this allocation..." required></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeModal('disburseAllocationModal')" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Allocate Funds
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Disbursement Request Modal -->
    <div id="disbursementRequestModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">New Disbursement Request</h3>
                    <button onclick="closeModal('disbursementRequestModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form id="disbursementForm" method="POST" action="API/create_disbursement_request.php" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Requesting Department</label>
                            <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <?php
                                $depts_query = "SELECT DISTINCT department FROM disbursements ORDER BY department";
                                $depts_result = mysqli_query($conn, $depts_query);
                                while ($dept = mysqli_fetch_assoc($depts_result)):
                                ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>">
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₱)</label>
                            <input type="number" name="amount" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="Salaries & Benefits">Salaries & Benefits</option>
                            <option value="Operations">Operations</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Travel & Accommodation">Travel & Accommodation</option>
                            <option value="Supplies">Supplies</option>
                            <option value="Training & Development">Training & Development</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                        <textarea name="purpose" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Detailed purpose of this disbursement request..." required></textarea>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Supporting Documents</label>
                        <input type="file" name="documents[]" multiple class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Upload supporting documents (PDF, JPG, PNG)</p>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeModal('disbursementRequestModal')" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // View request details
        function viewRequest(requestId) {
            // Implement view request functionality
            console.log('View request:', requestId);
            // You can redirect to a detailed view or open a modal with request details
            window.location.href = 'disbursement_details.php?id=' + requestId;
        }

        // Approve request
        function approveRequest(requestId) {
            if (confirm('Are you sure you want to approve this disbursement request?')) {
                // Implement approval logic via AJAX or form submission
                fetch('API/approve_disbursement.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        action: 'approve'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Request approved successfully!');
                        location.reload();
                    } else {
                        alert('Error approving request: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error approving request');
                });
            }
        }

        // Add glass effect style
        const style = document.createElement('style');
        style.textContent = `
            .glass-effect {
                background: rgba(255, 255, 255, 0.7);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>