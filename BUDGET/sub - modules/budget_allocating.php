<?php
session_start();
include("../../API_gateway.php");

// Database connection
$db_name = "fina_budget";
if (!isset($connections[$db_name])) {
    die("❌ Connection not found for $db_name");
}
$conn = $connections[$db_name];

// Stats Query for Budget Allocations
$query = "SELECT 
  (SELECT COUNT(*) FROM budget_allocations) AS total_allocations,
  (SELECT COUNT(*) FROM budget_allocations WHERE status = 'under_review') AS pending,
  (SELECT COUNT(*) FROM budget_allocations WHERE status = 'approved') AS approved,
  (SELECT COUNT(*) FROM budget_allocations WHERE status = 'rejected') AS rejected,
  (SELECT SUM(amount) FROM budget_allocations WHERE status = 'under_review') AS pending_total,
  (SELECT SUM(amount) FROM budget_allocations WHERE status = 'approved') AS approved_total,
  (SELECT SUM(amount) FROM budget_allocations WHERE status = 'rejected') AS rejected_total,
  (SELECT SUM(amount) FROM budget_allocations) AS total_amount";

$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

$total_allocations_count = $row['total_allocations'] ?? 0;
$pending_count = $row['pending'] ?? 0;
$approved_count = $row['approved'] ?? 0;
$rejected_count = $row['rejected'] ?? 0;
$pending_total = $row['pending_total'] ?? 0;
$approved_total = $row['approved_total'] ?? 0;
$rejected_total = $row['rejected_total'] ?? 0;
$total_amount = $row['total_amount'] ?? 0;

// Calculate efficiency (approved vs total requested)
$efficiency_rate = $total_amount > 0 ? round(($approved_total / $total_amount) * 100) : 0;

// Search and Filter Parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$department_filter = isset($_GET['department']) ? mysqli_real_escape_string($conn, $_GET['department']) : '';

// Build query with filters
$where_conditions = [];
$query_params = [];

if (!empty($search)) {
    $where_conditions[] = "(allocation_code LIKE '%$search%' OR purpose LIKE '%$search%')";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = '$status_filter'";
}

if (!empty($department_filter)) {
    $where_conditions[] = "department = '$department_filter'";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch allocations with pagination and filters
$allocations_query = "SELECT * FROM budget_allocations $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$allocations_result = mysqli_query($conn, $allocations_query);
$allocations = [];
if ($allocations_result) {
    while ($row = mysqli_fetch_assoc($allocations_result)) {
        $allocations[] = $row;
    }
}

// Get total pages for pagination with filters
$total_pages_query = "SELECT COUNT(*) as total FROM budget_allocations $where_clause";
$total_pages_result = mysqli_query($conn, $total_pages_query);
$total_pages_row = mysqli_fetch_assoc($total_pages_result);
$total_pages = ceil($total_pages_row['total'] / $limit);

// Get unique departments for filter
$departments_query = "SELECT DISTINCT department FROM budget_allocations WHERE department IS NOT NULL";
$departments_result = mysqli_query($conn, $departments_query);
$departments = [];
if ($departments_result) {
    while ($row = mysqli_fetch_assoc($departments_result)) {
        $departments[] = $row['department'];
    }
}

// Helper function to build pagination URLs with filters
function buildPaginationUrl($page, $search, $status_filter, $department_filter) {
    $params = ['page' => $page];
    if (!empty($search)) $params['search'] = $search;
    if (!empty($status_filter)) $params['status'] = $status_filter;
    if (!empty($department_filter)) $params['department'] = $department_filter;
    return http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Allocating | Travel & Tour System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        body {
            font-family: 'Inter', sans-serif;
        }
        .rounded-lg {
            border-radius: 0.75rem;
        }
        .rounded-xl {
            border-radius: 1rem;
        }
        .rounded-2xl {
            border-radius: 1.5rem;
        }
        .status-under_review { background-color: #fef3c7; color: #d97706; }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-rejected { background-color: #fee2e2; color: #dc2626; }
    </style>
</head>
<body class="bg-base-100 min-h-screen bg-white">
  <div class="flex h-screen">
    <!-- Sidebar -->
    <?php include '../../COMPONENTS/sidebar.php'; ?>

    <!-- Content Area -->
    <div class="flex flex-col flex-1 overflow-auto">
        <!-- Navbar -->
        <?php include '../../COMPONENTS/navbar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 overflow-auto p-4 md:p-6">

    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Budget Allocating</h1>
            <p class="text-gray-600 mt-2">Distribute and allocate budgets to departments and projects</p>
        </header>

        <!-- Stats Cards -->
        <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <span class="p-2 mr-3 rounded-lg bg-green-100/50 text-green-600">
                    <i data-lucide="dollar-sign" class="w-5 h-5"></i>
                </span>
                Allocation Overview
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 h-full">
                
                <!-- Available for Allocation Card -->
                <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color:#001f54;">Total Allocated</p>
                            <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                ₱<?php echo number_format($total_amount, 2); ?>
                            </h3>
                        </div>
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i data-lucide="wallet" class="w-6 h-6"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width: <?php echo min(100, $total_amount/3000000*100); ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>Total budget allocated</span>
                            <span class="font-medium"><?php echo number_format($total_amount/3000000*100, 1); ?>% of ₱3M</span>
                        </div>
                    </div>
                </div>

                <!-- Pending Allocation Requests Card -->
                <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
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
                            <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo $total_allocations_count > 0 ? ($pending_count/$total_allocations_count*100) : 0; ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>Awaiting approval</span>
                            <span class="font-medium">₱<?php echo number_format($pending_total, 2); ?> total</span>
                        </div>
                    </div>
                </div>

                <!-- Approved Allocations Card -->
                <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color:#001f54;">Approved Allocations</p>
                            <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                <?php echo $approved_count; ?>
                            </h3>
                        </div>
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i data-lucide="check-circle" class="w-6 h-6"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $total_allocations_count > 0 ? ($approved_count/$total_allocations_count*100) : 0; ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>This month</span>
                            <span class="font-medium">₱<?php echo number_format($approved_total, 2); ?> total</span>
                        </div>
                    </div>
                </div>

                <!-- Allocation Efficiency Card -->
                <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color:#001f54;">Allocation Efficiency</p>
                            <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                <?php echo $efficiency_rate; ?>%
                            </h3>
                        </div>
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i data-lucide="zap" class="w-6 h-6"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-purple-500 rounded-full" style="width: <?php echo $efficiency_rate; ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>Approved vs Requested</span>
                            <span class="font-medium"><?php echo $efficiency_rate >= 80 ? 'High' : ($efficiency_rate >= 50 ? 'Medium' : 'Low'); ?> efficiency</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Navigation Tabs -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <a href="budget-monitoring.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Budget Monitoring
                    </a>
                    <a href="budget-allocating.php" class="border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Budget Allocating
                    </a>
                    <a href="budget-proposal.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Budget Proposal
                    </a>
                    <a href="budget-transactions.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Budget Transactions
                    </a>
                    <a href="main-budget-management.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Main Budget Management
                    </a>
                </nav>
            </div>
        </div>

        <!-- Content Area -->
        <section class="glass-effect p-6 rounded-2xl shadow-sm">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Allocate Budget to Departments</h3>
                    <p class="text-gray-600">Distribute available budget to departments and projects.</p>
                </div>
                <button onclick="openAllocationModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg flex items-center gap-2 hover:bg-green-700 transition-colors">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    New Allocation
                </button>
            </div>
            
            <!-- Search and Filter Section -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <form id="searchFilterForm" method="GET" class="flex flex-col sm:flex-row gap-4">
                    <!-- Search Bar -->
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                   placeholder="Search by allocation code or purpose...">
                            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="sm:w-48">
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Status</option>
                            <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <!-- Department Filter -->
                    <div class="sm:w-48">
                        <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" 
                                        <?php echo $department_filter === $dept ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                            <i data-lucide="filter" class="w-4 h-4"></i>
                            Apply
                        </button>
                        <button type="button" onclick="clearFilters()" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center gap-2">
                            <i data-lucide="x" class="w-4 h-4"></i>
                            Clear
                        </button>
                    </div>
                </form>

                <!-- Active Filters Display -->
                <?php if (!empty($search) || !empty($status_filter) || !empty($department_filter)): ?>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="text-sm text-gray-600">Active filters:</span>
                    <?php if (!empty($search)): ?>
                        <span class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                            Search: "<?php echo htmlspecialchars($search); ?>"
                            <button onclick="removeFilter('search')" class="ml-1 text-blue-600 hover:text-blue-800">
                                <i data-lucide="x" class="w-3 h-3"></i>
                            </button>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($status_filter)): ?>
                        <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                            Status: <?php echo ucfirst(str_replace('_', ' ', $status_filter)); ?>
                            <button onclick="removeFilter('status')" class="ml-1 text-green-600 hover:text-green-800">
                                <i data-lucide="x" class="w-3 h-3"></i>
                            </button>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($department_filter)): ?>
                        <span class="inline-flex items-center px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">
                            Department: <?php echo htmlspecialchars($department_filter); ?>
                            <button onclick="removeFilter('department')" class="ml-1 text-purple-600 hover:text-purple-800">
                                <i data-lucide="x" class="w-3 h-3"></i>
                            </button>
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Allocations -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h4 class="text-lg font-medium text-gray-700 mb-4">Recent Allocations</h4>
                
                <?php if (empty($allocations)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                    <p>No allocations yet</p>
                    <p class="text-sm">Allocations will appear here once created</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($allocations as $allocation): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                <i data-lucide="dollar-sign" class="w-5 h-5 text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($allocation['department']); ?> Department</p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($allocation['purpose']); ?></p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs text-gray-500"><?php echo $allocation['allocation_code']; ?></span>
                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full status-<?php echo $allocation['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $allocation['status'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-gray-700">₱<?php echo number_format($allocation['amount'], 2); ?></p>
                            <p class="text-xs text-gray-500">
                                <?php echo date('M j, Y', strtotime($allocation['created_at'])); ?>
                            </p>
                            <p class="text-xs text-gray-500">
                                <?php echo date('M j, Y', strtotime($allocation['start_date'])); ?> - <?php echo date('M j, Y', strtotime($allocation['end_date'])); ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex justify-center items-center space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo buildPaginationUrl($page - 1, $search, $status_filter, $department_filter); ?>" 
                           class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-1">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <span class="text-sm text-gray-600">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo buildPaginationUrl($page + 1, $search, $status_filter, $department_filter); ?>" 
                           class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-1">
                            Next
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Allocation Modal -->
    <div id="allocationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">New Budget Allocation</h3>
                    <button onclick="closeAllocationModal()" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form id="allocationForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Department</label>
                        <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" required>
                            <option value="">Select Department</option>
                            <option value="HR">HR</option>
                            <option value="LOGISTIC">LOGISTIC</option>
                            <option value="ADMINISTRATIVE">ADMINISTRATIVE</option>
                            <option value="FINANCIALS">FINANCIALS</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Allocation Amount (₱)</label>
                        <input type="number" name="amount" id="allocationAmount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="0.00" min="1" max="3000000" step="0.01" required>
                        <p class="text-xs text-gray-500 mt-1">Maximum allocation: ₱3,000,000</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                        <textarea name="purpose" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Purpose of this budget allocation..." required></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" id="startDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" id="endDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" required>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeAllocationModal()" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            Submit Allocation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Set minimum date to today for start date
        document.getElementById('startDate').min = new Date().toISOString().split('T')[0];
        
        // Update end date min when start date changes
        document.getElementById('startDate').addEventListener('change', function() {
            document.getElementById('endDate').min = this.value;
        });

        // Amount validation
        document.getElementById('allocationAmount').addEventListener('input', function(e) {
            validateAllocationAmount(this);
        });

        function validateAllocationAmount(inputElement) {
            const amount = parseFloat(inputElement.value) || 0;
            const errorElement = inputElement.parentNode.querySelector('.amount-error');
            
            if (amount > 3000000) {
                inputElement.classList.add('border-red-500', 'bg-red-50');
                inputElement.classList.remove('border-gray-300');
                if (!errorElement) {
                    const errorMsg = document.createElement('p');
                    errorMsg.className = 'amount-error text-xs text-red-600 mt-1';
                    errorMsg.textContent = 'Amount exceeds maximum allocation of ₱3,000,000';
                    inputElement.parentNode.appendChild(errorMsg);
                }
            } else {
                inputElement.classList.remove('border-red-500', 'bg-red-50');
                inputElement.classList.add('border-gray-300');
                if (errorElement) {
                    errorElement.remove();
                }
            }
        }

        // Form submission handler
        document.getElementById('allocationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const amount = parseFloat(formData.get('amount'));
            const startDate = formData.get('start_date');
            const endDate = formData.get('end_date');
            
            // Validate amount
            if (amount > 3000000) {
                Swal.fire({
                    icon: 'error',
                    title: 'Allocation Limit Exceeded',
                    text: 'Maximum allocation allowed is ₱3,000,000. Please adjust your amount.',
                    confirmButtonColor: '#dc2626'
                });
                return;
            }
            
            if (amount <= 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Amount',
                    text: 'Allocation amount must be greater than 0.',
                    confirmButtonColor: '#dc2626'
                });
                return;
            }
            
            // Validate dates
            if (new Date(startDate) > new Date(endDate)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Date Range',
                    text: 'End date must be after start date.',
                    confirmButtonColor: '#dc2626'
                });
                return;
            }
            
            // Submit form
            submitBudgetAllocation(formData);
        });

        function submitBudgetAllocation(formData) {
            // Show loading state
            const submitBtn = document.querySelector('#allocationForm button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Submitting...';
            submitBtn.disabled = true;
            
            lucide.createIcons();
            
            fetch('../API/create_allocation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAllocationModal();
                    Swal.fire({
                        icon: 'success',
                        title: 'Allocation Submitted!',
                        html: `
                            <div class="text-center">
                                <div class="mb-4">
                                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="clock" class="w-8 h-8 text-green-600"></i>
                                    </div>
                                    <p class="mb-2 text-lg font-semibold">${data.message}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Allocation Code:</span>
                                        <span class="text-sm font-semibold">${data.allocation_code}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Status:</span>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">
                                            Under Review
                                        </span>
                                    </div>
                                </div>
                            </div>
                        `,
                        confirmButtonColor: '#16a34a',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        document.getElementById('allocationForm').reset();
                        location.reload();
                    });
                } else {
                    throw new Error(data.message || 'Unknown error occurred');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Submission Failed',
                    text: error.message || 'An error occurred while submitting the allocation. Please try again.',
                    confirmButtonColor: '#dc2626'
                });
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                lucide.createIcons();
            });
        }

        // Modal functions
        function openAllocationModal() {
            document.getElementById('allocationModal').style.display = 'flex';
        }
        
        function closeAllocationModal() {
            document.getElementById('allocationModal').style.display = 'none';
        }
        
        // Filter functions
        function clearFilters() {
            window.location.href = 'budget-allocating.php';
        }

        function removeFilter(filterType) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filterType);
            window.location.href = url.toString();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>