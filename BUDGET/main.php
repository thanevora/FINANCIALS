<?php
session_start();
include("../API_gateway.php");

// Database connection
$db_name = "fina_budget";
if (!isset($connections[$db_name])) {
    die("❌ Connection not found for $db_name");
}
$conn = $connections[$db_name];

// Get total approved budget from budget_proposals table
$budget_query = "SELECT SUM(amount) as total_budget FROM budget_proposals WHERE status = 'approved'";
$budget_result = mysqli_query($conn, $budget_query);
$budget_row = mysqli_fetch_assoc($budget_result);
$total_budget = $budget_row['total_budget'] ?? 0;

// Stats Query for Budget Allocations
$query = "SELECT 
  (SELECT COUNT(*) FROM budget_allocations) AS total_allocations,
  (SELECT COUNT(*) FROM budget_allocations WHERE status = 'under_review') AS pending,
  (SELECT COUNT(*) FROM budget_allocations WHERE status = 'approved') AS approved,
  (SELECT COUNT(*) FROM budget_allocations WHERE status = 'rejected') AS rejected,
  (SELECT COUNT(*) FROM budget_allocations WHERE status = 'for_compliance') AS for_compliance,
  (SELECT SUM(amount) FROM budget_allocations WHERE status = 'under_review') AS pending_total,
  (SELECT SUM(amount) FROM budget_allocations WHERE status = 'approved') AS approved_total,
  (SELECT SUM(amount) FROM budget_allocations WHERE status = 'rejected') AS rejected_total,
  (SELECT SUM(amount) FROM budget_allocations WHERE status = 'for_compliance') AS compliance_total,
  (SELECT SUM(amount) FROM budget_allocations) AS total_amount";

$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

$total_allocations_count = $row['total_allocations'] ?? 0;
$pending_count = $row['pending'] ?? 0;
$approved_count = $row['approved'] ?? 0;
$rejected_count = $row['rejected'] ?? 0;
$compliance_count = $row['for_compliance'] ?? 0;
$pending_total = $row['pending_total'] ?? 0;
$approved_total = $row['approved_total'] ?? 0;
$rejected_total = $row['rejected_total'] ?? 0;
$compliance_total = $row['compliance_total'] ?? 0;
$total_amount = $row['total_amount'] ?? 0;

// Calculate remaining budget (dynamic from approved proposals)
$remaining_budget = $total_budget - $approved_total;

// Calculate efficiency rate (Approved vs Total Requested)
$efficiency_rate = 0;
if ($total_amount > 0) {
    $efficiency_rate = round(($approved_total / $total_amount) * 100);
}

// Determine efficiency level
$efficiency_level = 'No data';
if ($total_allocations_count > 0) {
    if ($efficiency_rate >= 80) {
        $efficiency_level = 'High efficiency';
    } elseif ($efficiency_rate >= 50) {
        $efficiency_level = 'Medium efficiency';
    } else {
        $efficiency_level = 'Low efficiency';
    }
}

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
$limit = 9;
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
    <title>Budget Management | Travel & Tour System</title>
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
        .modal {
            transition: opacity 0.25s ease;
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
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
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
        <main class="flex-1 overflow-auto p-4 md:p-6">

    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Budget Management</h1>
            <p class="text-gray-600 mt-2">Monitor, allocate, and track your travel budgets</p>
        </header>

        <!-- Budget Summary Cards -->
        <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <span class="p-2 mr-3 rounded-lg bg-blue-100/50 text-blue-600">
                        <i data-lucide="pie-chart" class="w-5 h-5"></i>
                    </span>
                    Budget Overview
                </h2>
            </div>

           <!-- Budget Dashboard Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols- gap-4 h-full">
    
    <!-- Total Proposed Budget Card -->
    <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium" style="color:#001f54;">Total Proposed Budget</p>
                <h3 class="text-2xl font-bold mt-1 text-gray-800">
                    ₱<?php echo number_format($total_budget, 2); ?>
                </h3>
            </div>
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i data-lucide="file-text" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="mt-3">
            <div class="flex justify-between text-xs text-gray-500">
                <span>Approved proposals</span>
                <span class="font-medium">Total budget</span>
            </div>
        </div>
    </div>

    <!-- Total Allocation Requests Card -->
    <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium" style="color:#001f54;">Total Requests</p>
                <h3 class="text-2xl font-bold mt-1 text-gray-800">
                    ₱<?php echo number_format($total_amount, 2); ?>
                </h3>
            </div>
            <div class="p-3 rounded-full" style="background:#F7B32B1A; color:#F7B32B;">
                <i data-lucide="wallet" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="mt-3">
            <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full rounded-full" style="background:#F7B32B; width: <?php echo $total_budget > 0 ? min(100, ($total_amount / $total_budget) * 100) : 0; ?>%"></div>
            </div>
            <div class="flex justify-between text-xs text-gray-500 mt-1">
                <span>Requests vs Budget</span>
                <span class="font-medium">
                    <?php 
                    if ($total_budget > 0) {
                        echo number_format(($total_amount / $total_budget) * 100, 1) . '%';
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Remaining Balance Card -->
    <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium" style="color:#001f54;">Remaining Balance</p>
                <h3 class="text-2xl font-bold mt-1 text-gray-800">
                    ₱<?php echo number_format($remaining_budget, 2); ?>
                </h3>
            </div>
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i data-lucide="dollar-sign" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="mt-3">
            <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $total_budget > 0 ? min(100, ($remaining_budget / $total_budget) * 100) : 0; ?>%"></div>
            </div>
            <div class="flex justify-between text-xs text-gray-500 mt-1">
                <span>Available budget</span>
                <span class="font-medium">
                    <?php 
                    if ($total_budget > 0) {
                        echo number_format(($remaining_budget / $total_budget) * 100, 1) . '%';
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Approved Allocations Card -->
    <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium" style="color:#001f54;">Approved</p>
                <h3 class="text-2xl font-bold mt-1 text-gray-800">
                    ₱<?php echo number_format($approved_total, 2); ?>
                </h3>
            </div>
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i data-lucide="check-circle" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="mt-3">
            <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $total_amount > 0 ? ($approved_total / $total_amount * 100) : 0; ?>%"></div>
            </div>
            <div class="flex justify-between text-xs text-gray-500 mt-1">
                <span>Approved rate</span>
                <span class="font-medium"><?php echo $approved_count; ?> req</span>
            </div>
        </div>
    </div>

    <!-- Under Review Card -->
    <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium" style="color:#001f54;">Under Review</p>
                <h3 class="text-2xl font-bold mt-1 text-gray-800">
                    <?php echo $pending_count; ?>
                </h3>
            </div>
            <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                <i data-lucide="clock" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="mt-3">
            <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo $total_allocations_count > 0 ? ($pending_count / $total_allocations_count * 100) : 0; ?>%"></div>
            </div>
            <div class="flex justify-between text-xs text-gray-500 mt-1">
                <span>Pending requests</span>
                <span class="font-medium">₱<?php echo number_format($pending_total, 2); ?></span>
            </div>
        </div>
    </div>

    <!-- For Compliance Card -->
    <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium" style="color:#001f54;">For Compliance</p>
                <h3 class="text-2xl font-bold mt-1 text-gray-800">
                    <?php echo $compliance_count; ?>
                </h3>
            </div>
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i data-lucide="file-check" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="mt-3">
            <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-purple-500 rounded-full" style="width: <?php echo $total_allocations_count > 0 ? ($compliance_count / $total_allocations_count * 100) : 0; ?>%"></div>
            </div>
            <div class="flex justify-between text-xs text-gray-500 mt-1">
                <span>Compliance review</span>
                <span class="font-medium">₱<?php echo number_format($compliance_total, 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Rejected Card -->
    <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium" style="color:#001f54;">Rejected</p>
                <h3 class="text-2xl font-bold mt-1 text-gray-800">
                    <?php echo $rejected_count; ?>
                </h3>
            </div>
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i data-lucide="x-circle" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="mt-3">
            <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-red-500 rounded-full" style="width: <?php echo $total_allocations_count > 0 ? ($rejected_count / $total_allocations_count * 100) : 0; ?>%"></div>
            </div>
            <div class="flex justify-between text-xs text-gray-500 mt-1">
                <span>Rejected requests</span>
                <span class="font-medium">₱<?php echo number_format($rejected_total, 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Approval Rate Card -->
    <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium" style="color:#001f54;">Approval Rate</p>
                <h3 class="text-2xl font-bold mt-1 text-gray-800">
                    <?php echo $efficiency_rate; ?>%
                </h3>
            </div>
            <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                <i data-lucide="trending-up" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="mt-3">
            <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-indigo-500 rounded-full" style="width: <?php echo $efficiency_rate; ?>%"></div>
            </div>
            <div class="flex justify-between text-xs text-gray-500 mt-1">
                <span>Success rate</span>
                <span class="font-medium"><?php echo $efficiency_level; ?></span>
            </div>
        </div>
    </div>
</div>

        <!-- Navigation Tabs -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <a href="budget-monitoring.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Budget Monitoring
                    </a>
                    <a href="budget-allocating.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Budget Allocating
                    </a>
                    <a href="budget-proposal.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Budget Proposal
                    </a>
                    <a href="budget-transactions.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Budget Transactions
                    </a>
                    <a href="main-budget-management.php" class="border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Main Budget Management
                    </a>
                </nav>
            </div>
        </div>

               <!-- Content Area -->
        <section class="glass-effect p-6 rounded-2xl shadow-sm">
            <div class="mb-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Main Budget Management</h3>
                <p class="text-gray-600">Manage your overall budget strategy and financial planning.</p>
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
                            <option value="for_compliance" <?php echo $status_filter === 'for_compliance' ? 'selected' : ''; ?>>For Compliance</option>
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
            
           <!-- Budget Management Actions -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl border border-gray-200 p-6 text-center hover:shadow-md transition-shadow">
        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="file-text" class="w-6 h-6 text-blue-600"></i>
        </div>
        <h4 class="text-lg font-medium text-gray-700 mb-2">Annual Budget Plan</h4>
        <p class="text-gray-500 text-sm mb-4">Create and manage your annual budget strategy</p>
        <button onclick="viewAnnualBudgetPlan()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm">
            View Plan
        </button>
    </div>
    
    <div class="bg-white rounded-xl border border-gray-200 p-6 text-center hover:shadow-md transition-shadow">
        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="trending-up" class="w-6 h-6 text-green-600"></i>
        </div>
        <h4 class="text-lg font-medium text-gray-700 mb-2">Budget Forecast</h4>
        <p class="text-gray-500 text-sm mb-4">Project future budget needs and allocations</p>
        <button onclick="generateBudgetForecast()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm">
            Generate Forecast
        </button>
    </div>
    
    <div class="bg-white rounded-xl border border-gray-200 p-6 text-center hover:shadow-md transition-shadow">
        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="bar-chart-3" class="w-6 h-6 text-purple-600"></i>
        </div>
        <h4 class="text-lg font-medium text-gray-700 mb-2">Budget Reports</h4>
        <p class="text-gray-500 text-sm mb-4">Generate comprehensive budget reports</p>
        <button onclick="viewBudgetReports()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm">
            View Reports
        </button>
    </div>
</div>
            
            <!-- Allocation Requests -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex justify-between items-center mb-6">
                    <h4 class="text-lg font-medium text-gray-700">Allocation Requests</h4>
                    <div class="text-sm text-gray-500">
                        Total: <?php echo $total_allocations_count; ?> requests
                    </div>
                </div>
                
                <?php if (empty($allocations)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                    <p>No allocation requests found</p>
                    <p class="text-sm">Allocation requests will appear here once submitted</p>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($allocations as $allocation): ?>
                        <div class="allocation-card bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="font-semibold text-gray-800 text-lg mb-1"><?php echo htmlspecialchars($allocation['department']); ?> Department</h3>
                                    <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full status-<?php echo $allocation['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $allocation['status'])); ?>
                                    </span>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-gray-800">₱<?php echo number_format($allocation['amount'], 2); ?></div>
                                    <div class="text-xs text-gray-500 font-mono"><?php echo htmlspecialchars($allocation['allocation_code']); ?></div>
                                </div>
                            </div>
                            
                            <div class="space-y-3 mb-4">
                                <div class="flex items-center text-sm text-gray-600">
                                    <i data-lucide="calendar" class="w-4 h-4 mr-2"></i>
                                    <span><?php echo date('M j, Y', strtotime($allocation['start_date'])); ?> - <?php echo date('M j, Y', strtotime($allocation['end_date'])); ?></span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <i data-lucide="file-text" class="w-4 h-4 mr-2"></i>
                                    <span class="truncate"><?php echo htmlspecialchars($allocation['purpose']); ?></span>
                                </div>
                            </div>

                            <p class="text-sm text-gray-600 mb-4 line-clamp-2"><?php echo htmlspecialchars($allocation['purpose']); ?></p>
                            
                            <div class="flex justify-between items-center">
                                <div class="text-xs text-gray-500">
                                    <?php echo date('M j, Y', strtotime($allocation['created_at'])); ?>
                                </div>
                                <button onclick="viewAllocation(<?php echo $allocation['id']; ?>)" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                    View
                                </button>
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

        <!-- View Allocation Modal -->
    <div id="viewAllocationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Allocation Details</h3>
                    <button onclick="closeModal('viewAllocationModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div id="allocationDetails" class="space-y-4">
                    <!-- Allocation details will be loaded here -->
                </div>
                <div class="flex justify-end gap-2 mt-6 pt-6 border-t border-gray-200" id="actionButtons">
                    <!-- Action buttons will be loaded here based on status -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        let currentAllocationId = null;
        let currentAllocationStatus = null;
        let currentAllocationAmount = null;

        // Modal functions
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // View allocation function
        function viewAllocation(allocationId) {
            currentAllocationId = allocationId;
            
            // Show loading state
            document.getElementById('allocationDetails').innerHTML = `
                <div class="flex justify-center items-center py-8">
                    <i data-lucide="loader-2" class="w-8 h-8 animate-spin text-blue-600"></i>
                </div>
            `;
            lucide.createIcons();
            
            // Fetch allocation details
            fetch(`API/get_allocation.php?id=${allocationId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const allocation = data.allocation;
                        currentAllocationStatus = allocation.status;
                        currentAllocationAmount = parseFloat(allocation.amount);
                        
                        document.getElementById('allocationDetails').innerHTML = `
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="hash" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Allocation Code</h4>
                                    </div>
                                    <p class="font-mono text-gray-900 ml-6">${allocation.allocation_code || 'N/A'}</p>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="building" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Department</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">${allocation.department || 'N/A'}</p>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="dollar-sign" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Amount</h4>
                                    </div>
                                    <p class="text-2xl font-bold text-gray-900 ml-6">₱${currentAllocationAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="alert-circle" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Status</h4>
                                    </div>
                                    <div class="ml-6">
                                        <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full status-${allocation.status}">
                                            ${allocation.status ? allocation.status.charAt(0).toUpperCase() + allocation.status.slice(1).replace('_', ' ') : 'N/A'}
                                        </span>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="calendar" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Start Date</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">${allocation.start_date ? new Date(allocation.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="calendar" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">End Date</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">${allocation.end_date ? new Date(allocation.end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="file-text" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Purpose</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">${allocation.purpose || 'No purpose provided'}</p>
                                </div>

                                <div class="md:col-span-2">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="clock" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Created At</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">${allocation.created_at ? new Date(allocation.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}</p>
                                </div>
                            </div>
                        `;
                        
                        // Update action buttons based on status
                        updateActionButtons(allocation.status);
                        
                    } else {
                        document.getElementById('allocationDetails').innerHTML = `
                            <div class="text-center py-8 text-red-600">
                                <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-4"></i>
                                <p>${data.message || 'Failed to load allocation details'}</p>
                            </div>
                        `;
                        lucide.createIcons();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('allocationDetails').innerHTML = `
                        <div class="text-center py-8 text-red-600">
                            <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-4"></i>
                            <p>Error loading allocation details: ${error.message}</p>
                        </div>
                    `;
                    lucide.createIcons();
                });
            
            openModal('viewAllocationModal');
        }

        // Update action buttons based on allocation status
        function updateActionButtons(status) {
            const actionButtons = document.getElementById('actionButtons');
            
            if (status === 'approved' || status === 'rejected') {
                // Disable all buttons for finalized allocations
                actionButtons.innerHTML = `
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center gap-2" disabled>
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                        Approve
                    </button>
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center gap-2" disabled>
                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                        Reject
                    </button>
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center gap-2" disabled>
                        <i data-lucide="file-check" class="w-4 h-4"></i>
                        For Compliance
                    </button>
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center gap-2" disabled>
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                        Re-open
                    </button>
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center gap-2" disabled>
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                        Delete
                    </button>
                `;
            } else if (status === 'for_compliance') {
                // For Compliance status - can approve, reject, re-open, or delete
                actionButtons.innerHTML = `
                    <button onclick="approveAllocation()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                        Approve
                    </button>
                    <button onclick="rejectAllocation()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2">
                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                        Reject
                    </button>
                    <button class="px-4 py-2 bg-amber-500 text-white rounded-lg cursor-not-allowed flex items-center gap-2" disabled>
                        <i data-lucide="file-check" class="w-4 h-4"></i>
                        For Compliance
                    </button>
                    <button onclick="reopenAllocation()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                        Re-open
                    </button>
                    <button onclick="deleteAllocation()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex items-center gap-2">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                        Delete
                    </button>
                `;
            } else {
                // Under Review status - can mark for compliance, delete, or re-open (if previously changed)
                actionButtons.innerHTML = `
                    <button onclick="approveAllocation()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                        Approve
                    </button>
                    <button onclick="rejectAllocation()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2">
                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                        Reject
                    </button>
                    <button onclick="markForCompliance()" class="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors flex items-center gap-2">
                        <i data-lucide="file-check" class="w-4 h-4"></i>
                        For Compliance
                    </button>
                    <button onclick="deleteAllocation()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex items-center gap-2">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                        Delete
                    </button>
                `;
            }
            lucide.createIcons();
        }

        // CRUD Actions with SweetAlert
        function approveAllocation() {
            if (!currentAllocationId) return;
            
            // Check remaining budget first
            fetch('API/check_remaining_budget.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const remainingBudget = data.remaining_budget;
                        if (currentAllocationAmount > remainingBudget) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Insufficient Budget',
                                text: `Cannot approve allocation. Requested amount (₱${currentAllocationAmount.toLocaleString()}) exceeds remaining budget (₱${remainingBudget.toLocaleString()})`,
                                confirmButtonColor: '#dc2626'
                            });
                            return;
                        }

                        // Proceed with approval
                        Swal.fire({
                            title: 'Approve Allocation?',
                            text: 'This will approve the budget allocation and cannot be undone.',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#10b981',
                            cancelButtonColor: '#6b7280',
                            confirmButtonText: 'Yes, Approve!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                updateAllocationStatus('approved');
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error checking budget:', error);
                    Swal.fire('Error!', 'Failed to check remaining budget.', 'error');
                });
        }

        function rejectAllocation() {
            if (!currentAllocationId) return;
            
            Swal.fire({
                title: 'Reject Allocation?',
                text: 'This will reject the budget allocation and cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Reject!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    updateAllocationStatus('rejected');
                }
            });
        }

        function markForCompliance() {
            if (!currentAllocationId) return;
            
            Swal.fire({
                title: 'Mark for Compliance?',
                text: 'This will mark the allocation for compliance review.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Mark!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    updateAllocationStatus('for_compliance');
                }
            });
        }

        function reopenAllocation() {
            if (!currentAllocationId) return;
            
            Swal.fire({
                title: 'Re-open Allocation?',
                text: 'This will change the status back to Under Review for further processing.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Re-open!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    updateAllocationStatus('under_review');
                }
            });
        }

        function deleteAllocation() {
            if (!currentAllocationId) return;
            
            Swal.fire({
                title: 'Delete Allocation?',
                text: 'This action cannot be undone!',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Delete!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteAllocationAPI(currentAllocationId);
                }
            });
        }

        function updateAllocationStatus(status) {
            // Show loading in SweetAlert
            Swal.fire({
                title: 'Updating...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('API/update_allocation_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: currentAllocationId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: `Allocation ${status.replace('_', ' ')} successfully.`,
                        confirmButtonColor: '#10b981',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        closeModal('viewAllocationModal');
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message || 'Failed to update allocation.', 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'Failed to update allocation.', 'error');
            });
        }

        function deleteAllocationAPI(allocationId) {
            fetch('API/delete_allocation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: allocationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', data.message, 'success').then(() => {
                        closeModal('viewAllocationModal');
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message || 'Failed to delete allocation.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'Failed to delete allocation.', 'error');
            });
        }

        // Filter functions
        function clearFilters() {
            window.location.href = 'main-budget-management.php';
        }

        function removeFilter(filterType) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filterType);
            window.location.href = url.toString();
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeModal(e.target.id);
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal('viewAllocationModal');
            }
        });
    </script>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        let currentAllocationId = null;
        let currentAllocationStatus = null;

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // View allocation function
        function viewAllocation(allocationId) {
            currentAllocationId = allocationId;
            
            // Show loading state
            document.getElementById('allocationDetails').innerHTML = `
                <div class="flex justify-center items-center py-8">
                    <i data-lucide="loader-2" class="w-8 h-8 animate-spin text-blue-600"></i>
                </div>
            `;
            lucide.createIcons();
            
            // Fetch allocation details
            fetch(`API/get_allocation.php?id=${allocationId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const allocation = data.allocation;
                        currentAllocationStatus = allocation.status;
                        
                        document.getElementById('allocationDetails').innerHTML = `
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="hash" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Allocation Code</h4>
                                    </div>
                                    <p class="font-mono text-gray-900 ml-6">${allocation.allocation_code || 'N/A'}</p>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="building" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Department</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">${allocation.department || 'N/A'}</p>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="dollar-sign" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Amount</h4>
                                    </div>
                                    <p class="text-2xl font-bold text-gray-900 ml-6">₱${parseFloat(allocation.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="alert-circle" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Status</h4>
                                    </div>
                                    <div class="ml-6">
                                        <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full status-${allocation.status}">
                                            ${allocation.status ? allocation.status.charAt(0).toUpperCase() + allocation.status.slice(1).replace('_', ' ') : 'N/A'}
                                        </span>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="calendar" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Start Date</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">${allocation.start_date ? new Date(allocation.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="calendar" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">End Date</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">${allocation.end_date ? new Date(allocation.end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="file-text" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Purpose</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">${allocation.purpose || 'No purpose provided'}</p>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="calendar-range" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Duration</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">
                                        ${allocation.start_date && allocation.end_date ? 
                                            `${calculateDuration(allocation.start_date, allocation.end_date)}` : 
                                            'N/A'
                                        }
                                    </p>
                                </div>
                            </div>
                        `;
                        
                        // Re-initialize Lucide icons after updating the content
                        lucide.createIcons();
                        
                        // Update button visibility based on status
                        updateActionButtons(allocation.status);
                        
                    } else {
                        document.getElementById('allocationDetails').innerHTML = `
                            <div class="text-center py-8 text-red-600">
                                <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-4"></i>
                                <p>${data.message || 'Failed to load allocation details'}</p>
                            </div>
                        `;
                        lucide.createIcons();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('allocationDetails').innerHTML = `
                        <div class="text-center py-8 text-red-600">
                            <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-4"></i>
                            <p>Error loading allocation details: ${error.message}</p>
                        </div>
                    `;
                    lucide.createIcons();
                });
            
            openModal('viewAllocationModal');
        }

        // Helper function to calculate duration
        function calculateDuration(startDate, endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            const diffMonths = Math.ceil(diffDays / 30);
            
            if (diffDays < 30) {
                return `${diffDays} day${diffDays !== 1 ? 's' : ''}`;
            } else if (diffMonths < 12) {
                return `${diffMonths} month${diffMonths !== 1 ? 's' : ''}`;
            } else {
                const diffYears = Math.floor(diffMonths / 12);
                const remainingMonths = diffMonths % 12;
                return `${diffYears} year${diffYears !== 1 ? 's' : ''}${remainingMonths > 0 ? `, ${remainingMonths} month${remainingMonths !== 1 ? 's' : ''}` : ''}`;
            }
        }

        // Update action buttons based on allocation status
        function updateActionButtons(status) {
            const approveBtn = document.querySelector('button[onclick="approveAllocation()"]');
            const rejectBtn = document.querySelector('button[onclick="rejectAllocation()"]');
            const deleteBtn = document.querySelector('button[onclick="deleteAllocation()"]');
            
            if (status === 'approved' || status === 'rejected') {
                // Disable approve/reject buttons for finalized allocations
                if (approveBtn) approveBtn.disabled = true;
                if (rejectBtn) rejectBtn.disabled = true;
                
                // Update button styles
                [approveBtn, rejectBtn].forEach(btn => {
                    if (btn) {
                        btn.classList.add('opacity-50', 'cursor-not-allowed');
                        btn.classList.remove('hover:bg-green-700', 'hover:bg-red-700');
                    }
                });
            } else {
                // Enable buttons for under_review allocations
                [approveBtn, rejectBtn, deleteBtn].forEach(btn => {
                    if (btn) {
                        btn.disabled = false;
                        btn.classList.remove('opacity-50', 'cursor-not-allowed');
                    }
                });
            }
        }

        // CRUD Actions with SweetAlert
        function approveAllocation() {
            if (!currentAllocationId) return;
            
            Swal.fire({
                title: 'Approve Allocation?',
                text: 'This will approve the budget allocation and cannot be undone.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Approve!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    updateAllocationStatus('approved');
                }
            });
        }

        function rejectAllocation() {
            if (!currentAllocationId) return;
            
            Swal.fire({
                title: 'Reject Allocation?',
                text: 'This will reject the budget allocation and cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Reject!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    updateAllocationStatus('rejected');
                }
            });
        }

        function deleteAllocation() {
            if (!currentAllocationId) return;
            
            Swal.fire({
                title: 'Delete Allocation?',
                text: 'This action cannot be undone!',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Delete!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteAllocationAPI(currentAllocationId);
                }
            });
        }

        // Update allocation status
        function updateAllocationStatus(status) {
            // Show loading in SweetAlert
            Swal.fire({
                title: 'Updating...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('API/update_allocation_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: currentAllocationId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: `Allocation ${status} successfully.`,
                        confirmButtonColor: '#10b981',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        closeModal('viewAllocationModal');
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message || 'Failed to update allocation.', 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'Failed to update allocation.', 'error');
            });
        }

        // Delete allocation API call
        function deleteAllocationAPI(allocationId) {
            fetch('API/delete_allocation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: allocationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', data.message, 'success').then(() => {
                        closeModal('viewAllocationModal');
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message || 'Failed to delete allocation.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'Failed to delete allocation.', 'error');
            });
        }

        // Filter functions
        function clearFilters() {
            window.location.href = 'main-budget-management.php';
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

        // Budget Management Actions
function viewAnnualBudgetPlan() {
    Swal.fire({
        title: 'Loading Annual Budget Plan...',
        text: 'Please wait while we retrieve your budget plan',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('API/get_annual_budget_plan.php')
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                showAnnualBudgetPlanModal(data.plan);
            } else {
                Swal.fire('Error!', data.message || 'Failed to load annual budget plan.', 'error');
            }
        })
        .catch(error => {
            Swal.close();
            console.error('Error:', error);
            Swal.fire('Error!', 'Failed to load annual budget plan.', 'error');
        });
}

function generateBudgetForecast() {
    Swal.fire({
        title: 'Generating Budget Forecast...',
        text: 'Analyzing historical data and predicting future trends',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('API/generate_budget_forecast.php')
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                showBudgetForecastModal(data.forecast);
            } else {
                Swal.fire('Error!', data.message || 'Failed to generate budget forecast.', 'error');
            }
        })
        .catch(error => {
            Swal.close();
            console.error('Error:', error);
            Swal.fire('Error!', 'Failed to generate budget forecast.', 'error');
        });
}

function viewBudgetReports() {
    Swal.fire({
        title: 'Loading Budget Reports...',
        text: 'Please wait while we generate your reports',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('API/get_budget_reports.php')
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                showBudgetReportsModal(data.reports);
            } else {
                Swal.fire('Error!', data.message || 'Failed to load budget reports.', 'error');
            }
        })
        .catch(error => {
            Swal.close();
            console.error('Error:', error);
            Swal.fire('Error!', 'Failed to load budget reports.', 'error');
        });
}

// Modal display functions
function showAnnualBudgetPlanModal(plan) {
    const modalContent = `
        <div class="max-h-96 overflow-y-auto">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-blue-800 mb-2">Total Annual Budget</h4>
                    <p class="text-2xl font-bold text-blue-600">₱${parseFloat(plan.total_budget || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-green-800 mb-2">Allocated Budget</h4>
                    <p class="text-2xl font-bold text-green-600">₱${parseFloat(plan.allocated_budget || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                </div>
            </div>
            
            <div class="mb-4">
                <h4 class="font-semibold text-gray-700 mb-3">Department Allocations</h4>
                <div class="space-y-2">
                    ${plan.department_allocations ? plan.department_allocations.map(dept => `
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium">${dept.department}</span>
                            <span class="font-bold">₱${parseFloat(dept.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `).join('') : '<p class="text-gray-500 text-center py-4">No department allocations found</p>'}
                </div>
            </div>
            
            <div class="mt-4 text-sm text-gray-600">
                <p><strong>Fiscal Year:</strong> ${plan.fiscal_year || 'N/A'}</p>
                <p><strong>Last Updated:</strong> ${plan.last_updated ? new Date(plan.last_updated).toLocaleDateString() : 'N/A'}</p>
            </div>
        </div>
    `;

    Swal.fire({
        title: 'Annual Budget Plan',
        html: modalContent,
        width: 600,
        confirmButtonText: 'Close',
        confirmButtonColor: '#3b82f6'
    });
}

function showBudgetForecastModal(forecast) {
    const modalContent = `
        <div class="max-h-96 overflow-y-auto">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-green-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-green-800 mb-2">Next Quarter Forecast</h4>
                    <p class="text-xl font-bold text-green-600">₱${parseFloat(forecast.next_quarter_forecast || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                </div>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-blue-800 mb-2">Confidence Level</h4>
                    <p class="text-xl font-bold text-blue-600">${forecast.confidence_level || 'N/A'}%</p>
                </div>
            </div>
            
            <div class="mb-4">
                <h4 class="font-semibold text-gray-700 mb-3">Trend Analysis</h4>
                <div class="space-y-3">
                    ${forecast.trend_analysis ? forecast.trend_analysis.map(trend => `
                        <div class="p-3 border rounded-lg ${trend.trend === 'increasing' ? 'border-green-200 bg-green-50' : trend.trend === 'decreasing' ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-gray-50'}">
                            <div class="flex justify-between items-center">
                                <span class="font-medium">${trend.category}</span>
                                <span class="font-bold ${trend.trend === 'increasing' ? 'text-green-600' : trend.trend === 'decreasing' ? 'text-red-600' : 'text-gray-600'}">
                                    ${trend.trend === 'increasing' ? '↑' : trend.trend === 'decreasing' ? '↓' : '→'} ${trend.percentage}%
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">${trend.description}</p>
                        </div>
                    `).join('') : '<p class="text-gray-500 text-center py-4">No trend analysis available</p>'}
                </div>
            </div>
            
            <div class="mt-4 text-sm text-gray-600">
                <p><strong>Generated:</strong> ${forecast.generated_at ? new Date(forecast.generated_at).toLocaleString() : 'N/A'}</p>
                <p><strong>Model Used:</strong> ${forecast.model_used || 'Time Series Analysis'}</p>
            </div>
        </div>
    `;

    Swal.fire({
        title: 'Budget Forecast',
        html: modalContent,
        width: 600,
        confirmButtonText: 'Close',
        confirmButtonColor: '#10b981'
    });
}

function showBudgetReportsModal(reports) {
    const modalContent = `
        <div class="max-h-96 overflow-y-auto">
            <div class="space-y-4">
                ${reports && reports.length > 0 ? reports.map(report => `
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-semibold text-gray-800">${report.title}</h4>
                            <span class="px-2 py-1 text-xs rounded-full ${report.status === 'generated' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                                ${report.status}
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mb-3">${report.description}</p>
                        <div class="flex justify-between items-center text-xs text-gray-500">
                            <span>${report.period}</span>
                            <div class="space-x-2">
                                <button onclick="downloadReport('${report.id}')" class="text-blue-600 hover:text-blue-800 font-medium">
                                    Download
                                </button>
                                ${report.status === 'generated' ? `
                                    <button onclick="viewReport('${report.id}')" class="text-green-600 hover:text-green-800 font-medium">
                                        View
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `).join('') : '<p class="text-gray-500 text-center py-8">No reports available</p>'}
            </div>
            
            <div class="mt-6 flex gap-2">
                <button onclick="generateNewReport()" class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm">
                    Generate New Report
                </button>
                <button onclick="exportAllReports()" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm">
                    Export All
                </button>
            </div>
        </div>
    `;

    Swal.fire({
        title: 'Budget Reports',
        html: modalContent,
        width: 700,
        showConfirmButton: false,
        showCloseButton: true
    });
}

// Additional report functions
function downloadReport(reportId) {
    Swal.fire({
        title: 'Downloading Report...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Simulate download
    setTimeout(() => {
        Swal.close();
        Swal.fire('Success!', 'Report download started.', 'success');
    }, 1500);
}

function viewReport(reportId) {
    Swal.fire({
        title: 'Opening Report...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Simulate opening report
    setTimeout(() => {
        Swal.close();
        Swal.fire({
            title: 'Budget Report Preview',
            text: 'This would show a detailed preview of the selected report.',
            icon: 'info',
            confirmButtonText: 'Close'
        });
    }, 1000);
}

function generateNewReport() {
    Swal.fire({
        title: 'Generate New Report',
        html: `
            <div class="text-left">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                    <select id="reportType" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="monthly">Monthly Summary</option>
                        <option value="quarterly">Quarterly Analysis</option>
                        <option value="annual">Annual Report</option>
                        <option value="department">Department Breakdown</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Period</label>
                    <input type="month" id="reportPeriod" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Generate',
        confirmButtonColor: '#8b5cf6',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const type = document.getElementById('reportType').value;
            const period = document.getElementById('reportPeriod').value;
            if (!period) {
                Swal.showValidationMessage('Please select a period');
                return false;
            }
            return { type, period };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire('Success!', `Generating ${result.value.type} report for ${result.value.period}`, 'success');
        }
    });
}

function exportAllReports() {
    Swal.fire({
        title: 'Exporting All Reports...',
        text: 'This may take a few moments',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    setTimeout(() => {
        Swal.close();
        Swal.fire('Success!', 'All reports have been exported successfully.', 'success');
    }, 2000);
}
    </script>
</body>
</html>