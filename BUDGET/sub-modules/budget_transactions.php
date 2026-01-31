<?php
session_start();
include("../../API_gateway.php");

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

// Get allocation statistics for transactions
$allocations_query = "SELECT 
    COUNT(*) as total_transactions,
    SUM(amount) as total_amount,
    COUNT(CASE WHEN status = 'under_review' THEN 1 END) as pending_count,
    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
    COUNT(CASE WHEN status = 'for_compliance' THEN 1 END) as compliance_count,
    SUM(CASE WHEN status = 'under_review' THEN amount ELSE 0 END) as pending_amount,
    SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_amount,
    SUM(CASE WHEN status = 'rejected' THEN amount ELSE 0 END) as rejected_amount,
    SUM(CASE WHEN status = 'for_compliance' THEN amount ELSE 0 END) as compliance_amount
FROM budget_allocations";

$allocations_result = mysqli_query($conn, $allocations_query);
$allocations_row = mysqli_fetch_assoc($allocations_result);

$total_transactions = $allocations_row['total_transactions'] ?? 0;
$total_amount = $allocations_row['total_amount'] ?? 0;
$pending_count = $allocations_row['pending_count'] ?? 0;
$approved_count = $allocations_row['approved_count'] ?? 0;
$rejected_count = $allocations_row['rejected_count'] ?? 0;
$compliance_count = $allocations_row['compliance_count'] ?? 0;
$pending_amount = $allocations_row['pending_amount'] ?? 0;
$approved_amount = $allocations_row['approved_amount'] ?? 0;
$rejected_amount = $allocations_row['rejected_amount'] ?? 0;
$compliance_amount = $allocations_row['compliance_amount'] ?? 0;

// Calculate average transaction
$average_transaction = $total_transactions > 0 ? $total_amount / $total_transactions : 0;

// Get recent transactions (all allocations)
$transactions_query = "SELECT 
    ba.*,
    bp.budget_title as proposal_title
FROM budget_allocations ba
LEFT JOIN budget_proposals bp ON ba.department = bp.department
ORDER BY ba.created_at DESC 
LIMIT 20";

$transactions_result = mysqli_query($conn, $transactions_query);
$transactions = [];
while ($row = mysqli_fetch_assoc($transactions_result)) {
    $transactions[] = $row;
}

// Get department-wise transaction counts
$dept_stats_query = "SELECT 
    department,
    COUNT(*) as transaction_count,
    SUM(amount) as total_amount,
    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count
FROM budget_allocations 
GROUP BY department 
ORDER BY total_amount DESC";

$dept_stats_result = mysqli_query($conn, $dept_stats_query);
$department_stats = [];
while ($row = mysqli_fetch_assoc($dept_stats_result)) {
    $department_stats[] = $row;
}

// Calculate monthly trends (last 6 months)
$monthly_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as transaction_count,
    SUM(amount) as monthly_amount
FROM budget_allocations 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY month DESC";

$monthly_result = mysqli_query($conn, $monthly_query);
$monthly_trends = [];
while ($row = mysqli_fetch_assoc($monthly_result)) {
    $monthly_trends[] = $row;
}

// Calculate success rate
$success_rate = $total_transactions > 0 ? round(($approved_count / $total_transactions) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Transactions | Travel & Tour System</title>
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
        .status-for_compliance { background-color: #fef3c7; color: #d97706; }
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
            <h1 class="text-3xl font-bold text-gray-800">Budget Transactions</h1>
            <p class="text-gray-600 mt-2">Track all budget-related transactions and activities</p>
        </header>

        <!-- Stats Cards -->
        <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <span class="p-2 mr-3 rounded-lg bg-purple-100/50 text-purple-600">
                    <i data-lucide="credit-card" class="w-5 h-5"></i>
                </span>
                Transaction Overview
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-3 gap-4 h-full">
                
                <!-- Total Transactions Card -->
                <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color:#001f54;">Total Transactions</p>
                            <h3 class="text-2xl font-bold mt-1 text-gray-800">
                                <?php echo $total_transactions; ?>
                            </h3>
                        </div>
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i data-lucide="list" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width: <?php echo min(100, ($total_transactions / 50) * 100); ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>All allocations</span>
                            <span class="font-medium">Total count</span>
                        </div>
                    </div>
                </div>

                <!-- Total Amount Card -->
                <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color:#001f54;">Total Amount</p>
                            <h3 class="text-2xl font-bold mt-1 text-gray-800">
                                ₱<?php echo number_format($total_amount / 1000000, 2); ?>M
                            </h3>
                        </div>
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i data-lucide="dollar-sign" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $total_budget > 0 ? min(100, ($total_amount / $total_budget) * 100) : 0; ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>Requested</span>
                            <span class="font-medium">All allocations</span>
                        </div>
                    </div>
                </div>

                <!-- Approved Transactions Card -->
                <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color:#001f54;">Approved</p>
                            <h3 class="text-2xl font-bold mt-1 text-gray-800">
                                <?php echo $approved_count; ?>
                            </h3>
                        </div>
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i data-lucide="check-circle" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $total_transactions > 0 ? ($approved_count / $total_transactions * 100) : 0; ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>Approved</span>
                            <span class="font-medium">₱<?php echo number_format($approved_amount, 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Pending Transactions Card -->
                <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color:#001f54;">Pending</p>
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
                            <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo $total_transactions > 0 ? ($pending_count / $total_transactions * 100) : 0; ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>Under Review</span>
                            <span class="font-medium">₱<?php echo number_format($pending_amount, 2); ?></span>
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
                            <div class="h-full bg-purple-500 rounded-full" style="width: <?php echo $total_transactions > 0 ? ($compliance_count / $total_transactions * 100) : 0; ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>Compliance Review</span>
                            <span class="font-medium">₱<?php echo number_format($compliance_amount, 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Success Rate Card -->
                <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color:#001f54;">Success Rate</p>
                            <h3 class="text-2xl font-bold mt-1 text-gray-800">
                                <?php echo $success_rate; ?>%
                            </h3>
                        </div>
                        <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                            <i data-lucide="trending-up" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-indigo-500 rounded-full" style="width: <?php echo $success_rate; ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>Approval rate</span>
                            <span class="font-medium">Overall</span>
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
                    <a href="budget-proposal.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Budget Proposal
                    </a>
                    <a href="budget-transactions.php" class="border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
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
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Budget Transactions</h3>
                    <p class="text-gray-600">View and manage all budget-related transactions.</p>
                </div>
                <div class="flex gap-2">
                    <button class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg flex items-center gap-2 hover:bg-gray-50 transition-colors">
                        <i data-lucide="filter" class="w-4 h-4"></i>
                        Filter
                    </button>
                    <button class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg flex items-center gap-2 hover:bg-gray-50 transition-colors">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        Export
                    </button>
                </div>
            </div>
            
            <!-- Transactions Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <?php if (empty($transactions)): ?>
                    <div class="text-center py-12 text-gray-500">
                        <i data-lucide="inbox" class="w-16 h-16 mx-auto mb-4 opacity-50"></i>
                        <p class="text-lg">No transactions found</p>
                        <p class="text-sm">Transactions will appear here once budget allocations are created</p>
                    </div>
                <?php else: ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Allocation Code</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timeline</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($transactions as $transaction): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($transaction['allocation_code']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($transaction['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($transaction['department']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                        <?php echo htmlspecialchars($transaction['purpose']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                        ₱<?php echo number_format($transaction['amount'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full status-<?php echo $transaction['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $transaction['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($transaction['start_date'])); ?> - 
                                        <?php echo date('M j, Y', strtotime($transaction['end_date'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if (!empty($transactions)): ?>
            <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 rounded-b-xl">
                <div class="flex flex-1 justify-between sm:hidden">
                    <a href="#" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                    <a href="#" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
                </div>
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing
                            <span class="font-medium">1</span>
                            to
                            <span class="font-medium"><?php echo min(20, count($transactions)); ?></span>
                            of
                            <span class="font-medium"><?php echo $total_transactions; ?></span>
                            results
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <a href="#" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                <span class="sr-only">Previous</span>
                                <i data-lucide="chevron-left" class="w-5 h-5"></i>
                            </a>
                            <a href="#" aria-current="page" class="relative z-10 inline-flex items-center bg-blue-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">1</a>
                            <a href="#" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">2</a>
                            <a href="#" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                <span class="sr-only">Next</span>
                                <i data-lucide="chevron-right" class="w-5 h-5"></i>
                            </a>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>