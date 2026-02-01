<?php
session_start();
include("../../API_gateway.php");

// Database connection
$db_name = "fina_budget";
if (!isset($connections[$db_name])) {
    die("❌ Connection not found for $db_name");
}
$conn = $connections[$db_name];

// Stats Query
$query = "SELECT 
  (SELECT COUNT(*) FROM budget_proposals) AS total_proposals,
  (SELECT COUNT(*) FROM budget_proposals WHERE status = 'under_review') AS pending,
  (SELECT COUNT(*) FROM budget_proposals WHERE status = 'approved') AS approved,
  (SELECT COUNT(*) FROM budget_proposals WHERE status = 'rejected') AS rejected,
  (SELECT SUM(amount) FROM budget_proposals WHERE status = 'under_review') AS pending_total,
  (SELECT SUM(amount) FROM budget_proposals WHERE status = 'approved') AS approved_total,
  (SELECT SUM(amount) FROM budget_proposals WHERE status = 'rejected') AS rejected_total";

$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

$total_proposals_count = $row['total_proposals'] ?? 0;
$pending_count = $row['pending'] ?? 0;
$approved_count = $row['approved'] ?? 0;
$rejected_count = $row['rejected'] ?? 0;
$pending_total = $row['pending_total'] ?? 0;
$approved_total = $row['approved_total'] ?? 0;
$rejected_total = $row['rejected_total'] ?? 0;

// Calculate approval rate
$total_processed = $approved_count + $rejected_count;
$approval_rate = $total_processed > 0 ? round(($approved_count / $total_processed) * 100) : 0;

// Search and Filter Parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$department_filter = isset($_GET['department']) ? mysqli_real_escape_string($conn, $_GET['department']) : '';

// Build query with filters
$where_conditions = [];
$query_params = [];

if (!empty($search)) {
    $where_conditions[] = "(budget_title LIKE '%$search%' OR proposal_code LIKE '%$search%' OR description LIKE '%$search%')";
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

// Fetch proposals with pagination and filters
$proposals_query = "SELECT * FROM budget_proposals $where_clause ORDER BY id DESC LIMIT $limit OFFSET $offset";
$proposals_result = mysqli_query($conn, $proposals_query);
$proposals = [];
if ($proposals_result) {
    while ($row = mysqli_fetch_assoc($proposals_result)) {
        $proposals[] = $row;
    }
}

// Get total pages for pagination with filters
$total_pages_query = "SELECT COUNT(*) as total FROM budget_proposals $where_clause";
$total_pages_result = mysqli_query($conn, $total_pages_query);
$total_pages_row = mysqli_fetch_assoc($total_pages_result);
$total_pages = ceil($total_pages_row['total'] / $limit);

// Get unique departments for filter
$departments_query = "SELECT DISTINCT department FROM budget_proposals WHERE department IS NOT NULL";
$departments_result = mysqli_query($conn, $departments_query);
$departments = [];
if ($departments_result) {
    while ($row = mysqli_fetch_assoc($departments_result)) {
        $departments[] = $row['department'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Proposal | Travel & Tour System</title>
          <?php include '../../COMPONENTS/header.php'; ?>

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
        .proposal-card {
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }
        .proposal-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .status-under_review { background-color: #fef3c7; color: #d97706; }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-rejected { background-color: #fee2e2; color: #dc2626; }
        .pagination-active {
            background-color: #3b82f6;
            color: white;
        }
        .filter-active {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
    </style>
</head>
<body class="bg-white min-h-screen">
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
                        <h1 class="text-3xl font-bold text-gray-800">Budget Proposal</h1>
                        <p class="text-gray-600 mt-2">Create, submit, and track budget proposals</p>
                    </header>

                    <!-- Stats Cards -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                                <span class="p-2 mr-3 rounded-lg bg-blue-100/50 text-blue-600">
                                    <i data-lucide="file-text" class="w-5 h-5"></i>
                                </span>
                                Proposal Overview
                            </h2>
                            <div class="flex gap-2">
                                <button onclick="openModal('proposeBudgetModal')" class="px-4 py-2 bg-blue-600 text-white rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                    Propose Budget
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 h-full">
                            <!-- Pending Proposals Card -->
                            <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Under Review</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800"><?php echo $pending_count; ?></h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                                        <i data-lucide="clock" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo $total_proposals_count > 0 ? round(($pending_count / $total_proposals_count) * 100) : 0; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Awaiting approval</span>
                                        <span class="font-medium">₱<?php echo number_format($pending_total, 2); ?> total</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Approved Proposals Card -->
                            <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Approved Proposals</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800"><?php echo $approved_count; ?></h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                                        <i data-lucide="check-circle" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $total_proposals_count > 0 ? round(($approved_count / $total_proposals_count) * 100) : 0; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Approved proposals</span>
                                        <span class="font-medium">₱<?php echo number_format($approved_total, 2); ?> total</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Rejected Proposals Card -->
                            <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Rejected Proposals</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800"><?php echo $rejected_count; ?></h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                                        <i data-lucide="x-circle" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-red-500 rounded-full" style="width: <?php echo $total_proposals_count > 0 ? round(($rejected_count / $total_proposals_count) * 100) : 0; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Rejected proposals</span>
                                        <span class="font-medium">₱<?php echo number_format($rejected_total, 2); ?> total</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Approval Rate Card -->
                            <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Approval Rate</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800"><?php echo $approval_rate; ?>%</h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                        <i data-lucide="trending-up" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-purple-500 rounded-full" style="width: <?php echo $approval_rate; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Proposal success</span>
                                        <span class="font-medium">
                                            <?php 
                                            if ($approval_rate >= 80) echo 'High rate';
                                            elseif ($approval_rate >= 60) echo 'Good rate';
                                            elseif ($approval_rate >= 40) echo 'Average rate';
                                            else echo 'Low rate';
                                            ?>
                                        </span>
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
                                <a href="budget-allocating.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Budget Allocating
                                </a>
                                <a href="budget-proposal.php" class="border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
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

                    <!-- Budget Proposals List -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                                <span class="p-2 mr-3 rounded-lg bg-blue-100/50 text-blue-600">
                                    <i data-lucide="files" class="w-5 h-5"></i>
                                </span>
                                All Budget Proposals
                            </h2>
                            <div class="flex gap-2">
                                <button onclick="refreshProposals()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg flex items-center gap-2 hover:bg-gray-200 transition-colors">
                                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                    Refresh
                                </button>
                            </div>
                        </div>

                        <!-- Search and Filter Section -->
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <form id="searchFilterForm" method="GET" class="flex flex-col sm:flex-row gap-4">
                                <!-- Search Bar -->
                                <div class="flex-1">
                                    <div class="relative">
                                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                               placeholder="Search by proposal code, title, or description...">
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

                        <?php if (empty($proposals)): ?>
                            <div class="text-center py-12">
                                <i data-lucide="file-text" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">No Proposals Found</h3>
                                <p class="text-gray-600 mb-6"><?php echo (!empty($search) || !empty($status_filter) || !empty($department_filter)) ? 'Try adjusting your search or filters.' : 'Get started by creating your first budget proposal.'; ?></p>
                                <?php if (empty($search) && empty($status_filter) && empty($department_filter)): ?>
                                    <button onclick="openModal('proposeBudgetModal')" class="px-6 py-3 bg-blue-600 text-white rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors mx-auto">
                                        <i data-lucide="plus" class="w-4 h-4"></i>
                                        Create First Proposal
                                    </button>
                                <?php else: ?>
                                    <button onclick="clearFilters()" class="px-6 py-3 bg-blue-600 text-white rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors mx-auto">
                                        <i data-lucide="x" class="w-4 h-4"></i>
                                        Clear Filters
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php foreach ($proposals as $proposal): ?>
                                    <div class="proposal-card bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                        <div class="flex justify-between items-start mb-4">
                                            <div>
                                                <h3 class="font-semibold text-gray-800 text-lg mb-1"><?php echo htmlspecialchars($proposal['budget_title']); ?></h3>
                                                <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full status-<?php echo $proposal['status']; ?>">
                                                    <?php echo ucfirst($proposal['status']); ?>
                                                </span>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-2xl font-bold text-gray-800">₱<?php echo number_format($proposal['amount'], 2); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($proposal['department']); ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="space-y-3 mb-4">
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i data-lucide="calendar" class="w-4 h-4 mr-2"></i>
                                                <span><?php echo htmlspecialchars($proposal['timeline']); ?></span>
                                            </div>
                                            <?php if (isset($proposal['proposal_code'])): ?>
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i data-lucide="hash" class="w-4 h-4 mr-2"></i>
                                                <span class="font-mono"><?php echo htmlspecialchars($proposal['proposal_code']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <p class="text-sm text-gray-600 mb-4 line-clamp-2"><?php echo htmlspecialchars($proposal['description']); ?></p>
                                        
                                        <div class="flex justify-between items-center">
                                            <div class="text-xs text-gray-500">
                                                ID: <?php echo $proposal['id']; ?>
                                            </div>
                                            <button onclick="viewProposal(<?php echo $proposal['id']; ?>)" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                                View
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="flex justify-center items-center space-x-2 mt-8">
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo buildPaginationUrl($page - 1, $search, $status_filter, $department_filter); ?>" class="px-3 py-2 text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-2">
                                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                        Previous
                                    </a>
                                <?php endif; ?>

                                <div class="flex space-x-1">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <a href="?<?php echo buildPaginationUrl($i, $search, $status_filter, $department_filter); ?>" class="px-3 py-2 rounded-lg <?php echo $i == $page ? 'pagination-active' : 'text-gray-600 bg-white border border-gray-300 hover:bg-gray-50'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo buildPaginationUrl($page + 1, $search, $status_filter, $department_filter); ?>" class="px-3 py-2 text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-2">
                                        Next
                                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <!-- Propose Budget Modal -->
    <div id="proposeBudgetModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-xl">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Propose New Budget</h3>
                    <button onclick="closeModal('proposeBudgetModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form id="budgetProposalForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Budget Title</label>
                        <input type="text" name="budget_title" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., Q3 Marketing Campaign" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Select Department</option>
                            <option value="HR">HR</option>
                            <option value="LOGISTIC">LOGISTIC</option>
                            <option value="ADMINISTRATIVE">ADMINISTRATIVE</option>
                            <option value="FINANCIALS">FINANCIALS</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₱)</label>
                        <input type="number" name="amount" id="budgetAmount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="0.00" min="1" max="1000000" step="0.01" required>
                        <p class="text-xs text-gray-500 mt-1">Maximum budget: ₱1,000,000</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
        <input type="date" name="start_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
        <input type="date" name="end_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
    </div>
</div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Brief description of the budget proposal..." required></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeModal('proposeBudgetModal')" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            Submit Proposal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Proposal Modal -->
    <div id="viewProposalModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Proposal Details</h3>
                    <button onclick="closeModal('viewProposalModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div id="proposalDetails" class="space-y-4">
                    <!-- Proposal details will be loaded here -->
                </div>
                <div class="flex justify-end gap-2 mt-6 pt-6 border-t border-gray-200">
                    <button onclick="approveProposal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                        Approve
                    </button>
                    <button onclick="rejectProposal()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2">
                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                        Reject
                    </button>
                    <button onclick="editProposal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                        <i data-lucide="edit" class="w-4 h-4"></i>
                        Edit
                    </button>
                    <button onclick="deleteProposal()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex items-center gap-2">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

       <!-- Edit Proposal Modal -->
    <div id="editProposalModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-xl">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Edit Budget Proposal</h3>
                    <button onclick="closeModal('editProposalModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form id="editProposalForm">
                    <!-- Form content will be dynamically loaded here -->
                    <div class="flex justify-center items-center py-8">
                        <i data-lucide="loader-2" class="w-8 h-8 animate-spin text-blue-600"></i>
                    </div>
                </form>
            </div>
        </div>
    </div>
<script>
    // Initialize Lucide icons
    lucide.createIcons();

    let currentProposalId = null;
    let currentProposalStatus = null;

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

    // View proposal function
    function viewProposal(proposalId) {
        currentProposalId = proposalId;
        
        // Show loading state
        document.getElementById('proposalDetails').innerHTML = `
            <div class="flex justify-center items-center py-8">
                <i data-lucide="loader-2" class="w-8 h-8 animate-spin text-blue-600"></i>
            </div>
        `;
        lucide.createIcons();
        
        // Fetch proposal details
        fetch(`../API/get_proposal.php?id=${proposalId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const proposal = data.proposal;
                    currentProposalStatus = proposal.status;
                    
                    document.getElementById('proposalDetails').innerHTML = `
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <i data-lucide="hash" class="w-4 h-4 text-blue-600"></i>
                <h4 class="font-semibold text-gray-700">Proposal Code</h4>
            </div>
            <p class="font-mono text-gray-900 ml-6">${proposal.proposal_code || 'N/A'}</p>
        </div>
        
        <div>
            <div class="flex items-center gap-2 mb-1">
                <i data-lucide="file-text" class="w-4 h-4 text-blue-600"></i>
                <h4 class="font-semibold text-gray-700">Budget Title</h4>
            </div>
            <p class="text-gray-900 ml-6">${proposal.budget_title || 'N/A'}</p>
        </div>
        
        <div>
            <div class="flex items-center gap-2 mb-1">
                <i data-lucide="building" class="w-4 h-4 text-blue-600"></i>
                <h4 class="font-semibold text-gray-700">Department</h4>
            </div>
            <p class="text-gray-900 ml-6">${proposal.department || 'N/A'}</p>
        </div>
        
        <div>
            <div class="flex items-center gap-2 mb-1">
                <i data-lucide="dollar-sign" class="w-4 h-4 text-blue-600"></i>
                <h4 class="font-semibold text-gray-700">Amount</h4>
            </div>
            <p class="text-2xl font-bold text-gray-900 ml-6">₱${parseFloat(proposal.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
        </div>
        
        <div>
            <div class="flex items-center gap-2 mb-1">
                <i data-lucide="alert-circle" class="w-4 h-4 text-blue-600"></i>
                <h4 class="font-semibold text-gray-700">Status</h4>
            </div>
            <div class="ml-6">
                <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full status-${proposal.status}">
                    ${proposal.status ? proposal.status.charAt(0).toUpperCase() + proposal.status.slice(1).replace('_', ' ') : 'N/A'}
                </span>
            </div>
        </div>
        
        <div>
            <div class="flex items-center gap-2 mb-1">
                <i data-lucide="calendar" class="w-4 h-4 text-blue-600"></i>
                <h4 class="font-semibold text-gray-700">Start Date</h4>
            </div>
            <p class="text-gray-900 ml-6">${proposal.start_date ? new Date(proposal.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
        </div>
        
        <div>
            <div class="flex items-center gap-2 mb-1">
                <i data-lucide="calendar" class="w-4 h-4 text-blue-600"></i>
                <h4 class="font-semibold text-gray-700">End Date</h4>
            </div>
            <p class="text-gray-900 ml-6">${proposal.end_date ? new Date(proposal.end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
        </div>
        
        <div class="md:col-span-2">
            <div class="flex items-center gap-2 mb-1">
                <i data-lucide="calendar-range" class="w-4 h-4 text-blue-600"></i>
                <h4 class="font-semibold text-gray-700">Duration</h4>
            </div>
            <p class="text-gray-900 ml-6">
                ${proposal.start_date && proposal.end_date ? 
                    `${calculateDuration(proposal.start_date, proposal.end_date)}` : 
                    'N/A'
                }
            </p>
        </div>
        
        <div class="md:col-span-2">
            <div class="flex items-center gap-2 mb-1">
                <i data-lucide="align-left" class="w-4 h-4 text-blue-600"></i>
                <h4 class="font-semibold text-gray-700">Description</h4>
            </div>
            <p class="text-gray-900 ml-6">${proposal.description || 'No description provided'}</p>
        </div>
    </div>
`;

                    // Re-initialize Lucide icons after updating the content
                    lucide.createIcons();
                    
                    // Update button visibility based on status
                    updateActionButtons(proposal.status);
                    
                } else {
                    document.getElementById('proposalDetails').innerHTML = `
                        <div class="text-center py-8 text-red-600">
                            <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-4"></i>
                            <p>${data.message || 'Failed to load proposal details'}</p>
                        </div>
                    `;
                    lucide.createIcons();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('proposalDetails').innerHTML = `
                    <div class="text-center py-8 text-red-600">
                        <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-4"></i>
                        <p>Error loading proposal details: ${error.message}</p>
                    </div>
                `;
                lucide.createIcons();
            });
        
        openModal('viewProposalModal');
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

    // Update action buttons based on proposal status
    function updateActionButtons(status) {
        const approveBtn = document.querySelector('button[onclick="approveProposal()"]');
        const rejectBtn = document.querySelector('button[onclick="rejectProposal()"]');
        const editBtn = document.querySelector('button[onclick="editProposal()"]');
        const deleteBtn = document.querySelector('button[onclick="deleteProposal()"]');
        
        if (status === 'approved' || status === 'rejected') {
            // Disable approve/reject buttons for finalized proposals
            if (approveBtn) approveBtn.disabled = true;
            if (rejectBtn) rejectBtn.disabled = true;
            if (editBtn) editBtn.disabled = true;
            
            // Update button styles
            [approveBtn, rejectBtn, editBtn].forEach(btn => {
                if (btn) {
                    btn.classList.add('opacity-50', 'cursor-not-allowed');
                    btn.classList.remove('hover:bg-green-700', 'hover:bg-red-700', 'hover:bg-blue-700');
                }
            });
        } else {
            // Enable buttons for under_review proposals
            [approveBtn, rejectBtn, editBtn, deleteBtn].forEach(btn => {
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            });
        }
    }

    // CRUD Actions with SweetAlert
    function approveProposal() {
        if (!currentProposalId) return;
        
        Swal.fire({
            title: 'Approve Proposal?',
            text: 'This will approve the budget proposal and cannot be undone.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Approve!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                updateProposalStatus('approved');
            }
        });
    }

    function rejectProposal() {
        if (!currentProposalId) return;
        
        Swal.fire({
            title: 'Reject Proposal?',
            text: 'This will reject the budget proposal and cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Reject!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                updateProposalStatus('rejected');
            }
        });
    }

    // Edit proposal function
    function editProposal() {
        if (!currentProposalId) return;
        
        // Show loading state
        document.getElementById('editProposalForm').innerHTML = `
            <div class="flex justify-center items-center py-8">
                <i data-lucide="loader-2" class="w-8 h-8 animate-spin text-blue-600"></i>
            </div>
        `;
        lucide.createIcons();
        
        // Fetch proposal details for editing
        fetch(`../API/get_proposal.php?id=${currentProposalId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const proposal = data.proposal;
                    
                    document.getElementById('editProposalForm').innerHTML = `
                        <input type="hidden" name="id" id="editProposalId" value="${proposal.id}">
                        
                        <!-- Non-editable fields (display only) -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Proposal Code</label>
                            <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500">
                                <span id="editProposalCodeDisplay">${proposal.proposal_code || 'N/A'}</span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500">
                                <span id="editDepartmentDisplay">${proposal.department}</span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500">
                                <span id="editStatusDisplay">${proposal.status.charAt(0).toUpperCase() + proposal.status.slice(1)}</span>
                            </div>
                        </div>

                        <!-- Editable fields -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Budget Title</label>
                            <input type="text" name="budget_title" id="editBudgetTitle" value="${proposal.budget_title}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₱)</label>
                            <input type="number" name="amount" id="editAmount" value="${proposal.amount}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="0.00" min="1" max="1000000" step="0.01" required>
                            <p class="text-xs text-gray-500 mt-1">Maximum budget: ₱1,000,000</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <input type="date" name="start_date" id="editStartDate" value="${proposal.start_date}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                <input type="date" name="end_date" id="editEndDate" value="${proposal.end_date}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" id="editDescription" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>${proposal.description}</textarea>
                        </div>
                        
                        <div class="flex justify-end gap-3">
                            <button type="button" onclick="closeModal('editProposalModal')" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                                <i data-lucide="save" class="w-4 h-4"></i>
                                Update Proposal
                            </button>
                        </div>
                    `;
                    
                    // Re-attach event listeners after rebuilding the form
                    attachEditFormEvents();
                    
                    // Show edit modal
                    closeModal('viewProposalModal');
                    openModal('editProposalModal');
                    
                } else {
                    Swal.fire('Error!', data.message || 'Failed to load proposal for editing.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'Failed to load proposal for editing: ' + error.message, 'error');
            });
    }

    // Attach event listeners to edit form
    function attachEditFormEvents() {
        const editForm = document.getElementById('editProposalForm');
        if (editForm) {
            // Remove existing event listener first to avoid duplicates
            const newEditForm = editForm.cloneNode(true);
            editForm.parentNode.replaceChild(newEditForm, editForm);
            
            // Add event listener to the new form
            newEditForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const amount = parseFloat(formData.get('amount'));
                const startDate = formData.get('start_date');
                const endDate = formData.get('end_date');
                
                // Validate amount
                if (amount > 1000000) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Budget Limit Exceeded',
                        text: 'Maximum budget allowed is ₱1,000,000. Please adjust your amount.',
                        confirmButtonColor: '#dc2626'
                    });
                    return;
                }
                
                if (amount <= 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Amount',
                        text: 'Budget amount must be greater than 0.',
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
                
                // Submit edit form
                updateProposal(formData);
            });
        }
        
        // Amount validation for edit form
        const editAmount = document.getElementById('editAmount');
        if (editAmount) {
            editAmount.addEventListener('input', function(e) {
                validateAmount(this);
            });
        }
    }

    // Update proposal function
    function updateProposal(formData) {
        // Show loading state
        const submitBtn = document.querySelector('#editProposalForm button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Updating...';
        submitBtn.disabled = true;
        
        lucide.createIcons();
        
        fetch('../API/update_proposal.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeModal('editProposalModal');
                Swal.fire({
                    icon: 'success',
                    title: 'Proposal Updated!',
                    text: data.message,
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'OK'
                }).then(() => {
                    location.reload();
                });
            } else {
                throw new Error(data.message || 'Failed to update proposal');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: error.message || 'An error occurred while updating the proposal.',
                confirmButtonColor: '#dc2626'
            });
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            lucide.createIcons();
        });
    }

    function deleteProposal() {
        if (!currentProposalId) return;
        
        Swal.fire({
            title: 'Delete Proposal?',
            text: 'This action cannot be undone!',
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Delete!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Implement delete functionality
                deleteProposalAPI(currentProposalId);
            }
        });
    }

    // Delete proposal API call
    function deleteProposalAPI(proposalId) {
        fetch('../API/delete_proposal.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: proposalId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Deleted!', data.message, 'success').then(() => {
                    closeModal('viewProposalModal');
                    location.reload();
                });
            } else {
                Swal.fire('Error!', data.message || 'Failed to delete proposal.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error!', 'Failed to delete proposal.', 'error');
        });
    }

    function updateProposalStatus(status) {
        // Show loading in SweetAlert
        Swal.fire({
            title: 'Updating...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        fetch('../API/update_proposal_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: currentProposalId,
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
                    text: `Proposal ${status} successfully.`,
                    confirmButtonColor: '#10b981',
                    confirmButtonText: 'OK'
                }).then(() => {
                    closeModal('viewProposalModal');
                    location.reload();
                });
            } else {
                Swal.fire('Error!', data.message || 'Failed to update proposal.', 'error');
            }
        })
        .catch(error => {
            Swal.close();
            console.error('Error:', error);
            Swal.fire('Error!', 'Failed to update proposal.', 'error');
        });
    }

    // Refresh proposals
    function refreshProposals() {
        location.reload();
    }

    // Form submission handler for new proposal
    document.getElementById('budgetProposalForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const amount = parseFloat(formData.get('amount'));
        const startDate = formData.get('start_date');
        const endDate = formData.get('end_date');
        
        // Validate amount
        if (amount > 1000000) {
            Swal.fire({
                icon: 'error',
                title: 'Budget Limit Exceeded',
                text: 'Maximum budget allowed is ₱1,000,000. Please adjust your amount.',
                confirmButtonColor: '#dc2626'
            });
            return;
        }
        
        if (amount <= 0) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Amount',
                text: 'Budget amount must be greater than 0.',
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
        submitBudgetProposal(formData);
    });

    function submitBudgetProposal(formData) {
        // Show loading state
        const submitBtn = document.querySelector('#budgetProposalForm button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Submitting...';
        submitBtn.disabled = true;
        
        lucide.createIcons();
        
        fetch('../API/budget_proposal.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeModal('proposeBudgetModal');
                Swal.fire({
                    icon: 'success',
                    title: 'Proposal Submitted!',
                    html: `
                        <div class="text-center">
                            <div class="mb-4">
                                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i data-lucide="clock" class="w-8 h-8 text-blue-600"></i>
                                </div>
                                <p class="mb-2 text-lg font-semibold">${data.message}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Proposal Code:</span>
                                    <span class="text-sm font-semibold">${data.proposal_code}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Status:</span>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Under Review
                                    </span>
                                </div>
                            </div>
                        </div>
                    `,
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'OK'
                }).then(() => {
                    document.getElementById('budgetProposalForm').reset();
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
                text: error.message || 'An error occurred while submitting the proposal. Please try again.',
                confirmButtonColor: '#dc2626'
            });
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            lucide.createIcons();
        });
    }

    // Real-time amount validation
    document.getElementById('budgetAmount').addEventListener('input', function(e) {
        validateAmount(this);
    });

    function validateAmount(inputElement) {
        const amount = parseFloat(inputElement.value) || 0;
        const errorElement = inputElement.parentNode.querySelector('.amount-error');
        
        if (amount > 1000000) {
            inputElement.classList.add('border-red-500', 'bg-red-50');
            inputElement.classList.remove('border-gray-300');
            if (!errorElement) {
                const errorMsg = document.createElement('p');
                errorMsg.className = 'amount-error text-xs text-red-600 mt-1';
                errorMsg.textContent = 'Amount exceeds maximum budget of ₱1,000,000';
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

    // Filter functions
    function clearFilters() {
        window.location.href = 'budget-proposal.php';
    }

    function removeFilter(filterType) {
        const url = new URL(window.location.href);
        url.searchParams.delete(filterType);
        window.location.href = url.toString();
    }

    // Close modal when clicking outside or pressing ESC
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeModal(e.target.id);
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('proposeBudgetModal');
            closeModal('viewProposalModal');
            closeModal('editProposalModal');
        }
    });
</script>
</body>
</html>

<?php
// Helper function to build pagination URLs with filters
function buildPaginationUrl($page, $search, $status_filter, $department_filter) {
    $params = ['page' => $page];
    if (!empty($search)) $params['search'] = $search;
    if (!empty($status_filter)) $params['status'] = $status_filter;
    if (!empty($department_filter)) $params['department'] = $department_filter;
    return http_build_query($params);
}
?>