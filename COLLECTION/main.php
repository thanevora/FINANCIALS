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

// Initialize all variables with defaults
$total_collections = 0;
$pending_collections = 0;
$pending_amount = 0;
$successful_this_month = 0;
$collection_rate = 0;
$total_count = 0;
$completed_count = 0;
$monthly_count = 0;
$transaction_status = [
    'completed' => ['count' => 0, 'amount' => 0],
    'pending' => ['count' => 0, 'amount' => 0],
    'failed' => ['count' => 0, 'amount' => 0],
    'for_approval' => ['count' => 0, 'amount' => 0],
    'overdue' => ['count' => 0, 'amount' => 0],
    'refund' => ['count' => 0, 'amount' => 0]
];
$payment_methods = [];

// Try to fetch collections data using API
$api_url = "API/collections_crud_api.php?action=get_stats";
$api_success = false;

// Check if API file exists before trying to use it
if (file_exists("API/collections_crud_api.php")) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5, // 5 second timeout
            'ignore_errors' => true
        ]
    ]);

    try {
        $response = @file_get_contents($api_url, false, $context);
        if ($response !== false) {
            $data = json_decode($response, true);
            if ($data && isset($data['status']) && $data['status'] === 'success') {
                $stats = $data['data'];
                $total_collections = $stats['total_collections'] ?? 0;
                $pending_collections = $stats['pending_collections'] ?? 0;
                $pending_amount = $stats['pending_amount'] ?? 0;
                $successful_this_month = $stats['successful_this_month'] ?? 0;
                $collection_rate = $stats['collection_rate'] ?? 0;
                $total_count = $stats['total_count'] ?? 0;
                $completed_count = $stats['completed_count'] ?? 0;
                $monthly_count = $stats['monthly_count'] ?? 0;
                $transaction_status = $stats['transaction_status'] ?? $transaction_status;
                $payment_methods = $stats['payment_methods'] ?? [];
                $api_success = true;
            }
        }
    } catch (Exception $e) {
        // API call failed, will use fallback
    }
}

// If API failed or doesn't exist, use direct database queries
if (!$api_success) {
    // Check if backup file exists
    if (file_exists("API/direct_queries.php")) {
        include("API/direct_queries.php");
    } else {
        // If backup file doesn't exist, run queries directly
        // Direct database queries as fallback
        $query_total = "SELECT COALESCE(SUM(amount), 0) as total FROM collections WHERE status = 'completed'";
        $result_total = mysqli_query($conn, $query_total);
        if ($result_total) {
            $row = mysqli_fetch_assoc($result_total);
            $total_collections = $row['total'] ?? 0;
        }

        $query_pending = "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM collections WHERE status = 'pending'";
        $result_pending = mysqli_query($conn, $query_pending);
        if ($result_pending) {
            $row = mysqli_fetch_assoc($result_pending);
            $pending_collections = $row['count'] ?? 0;
            $pending_amount = $row['total'] ?? 0;
        }

        // Query for this month's successful collections
        $query_month = "SELECT COALESCE(SUM(amount), 0) as total FROM collections 
                       WHERE status = 'completed' 
                       AND MONTH(collection_date) = MONTH(CURRENT_DATE()) 
                       AND YEAR(collection_date) = YEAR(CURRENT_DATE())";
        $result_month = mysqli_query($conn, $query_month);
        if ($result_month) {
            $row = mysqli_fetch_assoc($result_month);
            $successful_this_month = $row['total'] ?? 0;
        }

        // Query total count
        $query_count = "SELECT COUNT(*) as total FROM collections WHERE status != 'deleted'";
        $result_count = mysqli_query($conn, $query_count);
        if ($result_count) {
            $row = mysqli_fetch_assoc($result_count);
            $total_count = $row['total'] ?? 0;
        }

        // Query completed count
        $query_completed = "SELECT COUNT(*) as completed FROM collections WHERE status = 'completed'";
        $result_completed = mysqli_query($conn, $query_completed);
        if ($result_completed) {
            $row = mysqli_fetch_assoc($result_completed);
            $completed_count = $row['completed'] ?? 0;
        }

        // Calculate collection rate
        if ($total_count > 0) {
            $collection_rate = round(($completed_count / $total_count) * 100, 2);
        }

        // Query transaction status breakdown
        $statuses = ['completed', 'pending', 'failed', 'for_approval', 'overdue', 'refund'];
        foreach ($statuses as $status) {
            $query = "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as amount 
                     FROM collections WHERE LOWER(status) = '$status'";
            $result = mysqli_query($conn, $query);
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $transaction_status[$status] = [
                    'count' => $row['count'] ?? 0,
                    'amount' => $row['amount'] ?? 0
                ];
            }
        }

        // Query payment methods
        $query_payment = "SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as amount 
                         FROM collections 
                         WHERE payment_method IS NOT NULL 
                         AND status != 'deleted'
                         GROUP BY payment_method";
        $result_payment = mysqli_query($conn, $query_payment);
        if ($result_payment) {
            while ($row = mysqli_fetch_assoc($result_payment)) {
                $payment_methods[] = $row;
            }
        }

        // Calculate percentages for payment methods
        $total_payment_amount = 0;
        foreach ($payment_methods as $method) {
            $total_payment_amount += $method['amount'];
        }
        foreach ($payment_methods as &$method) {
            $method['percentage'] = $total_payment_amount > 0 ? 
                round(($method['amount'] / $total_payment_amount) * 100, 2) : 0;
        }
    }
}

// ============ PAGINATION SETUP ============
$limit = 25; // Number of records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $limit;

// Get total count of records for pagination
$total_records = 0;
$count_sql = "SELECT COUNT(*) as total FROM collections WHERE status != 'deleted'";
$count_result = mysqli_query($conn, $count_sql);
if ($count_result) {
    $row = mysqli_fetch_assoc($count_result);
    $total_records = $row['total'];
}

// Calculate total pages
$total_pages = ceil($total_records / $limit);

// Fetch collection requests for the table with pagination
$collection_requests = [];
$sql = "SELECT * FROM collections WHERE status != 'deleted' ORDER BY due_date DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $collection_requests[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Collections Management'; ?> | System Name</title>
    <!-- Add SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
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
                    <h1 class="text-3xl font-bold text-gray-800">Collections Management</h1>
                    <p class="text-gray-600 mt-2">Track and manage all incoming payments and revenue streams</p>
                </div>

                <!-- Collections Summary Cards -->
                <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                            <span class="p-2 mr-3 rounded-lg bg-green-100/50 text-green-600">
                                <i data-lucide="credit-card" class="w-5 h-5"></i>
                            </span>
                            Collections Overview
                        </h2>
                    </div>

                    <!-- All Stats Cards in Unified Design -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-4">
                        
                        <!-- Total Collections Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Total Collections</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        ₱<?php echo number_format($total_collections, 2); ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full" style="background:#F7B32B1A; color:#F7B32B;">
                                    <i data-lucide="credit-card" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full" style="background:#F7B32B; width: <?php echo $total_collections > 0 ? min(($successful_this_month / $total_collections * 100), 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>This month</span>
                                    <span class="font-medium">₱<?php echo number_format($successful_this_month, 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Collections Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Pending Collections</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $pending_collections; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                                    <i data-lucide="clock" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo $total_count > 0 ? ($pending_collections / $total_count * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Awaiting payment</span>
                                    <span class="font-medium">₱<?php echo number_format($pending_amount, 2); ?> total</span>
                                </div>
                            </div>
                        </div>

                        <!-- Collection Rate Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Collection Rate</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $collection_rate; ?>%
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                    <i data-lucide="trending-up" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-purple-500 rounded-full" style="width: <?php echo $collection_rate; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Success rate</span>
                                    <span class="font-medium"><?php echo $completed_count; ?> of <?php echo $total_count; ?> requests</span>
                                </div>
                            </div>
                        </div>

                        <!-- Success Transactions Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Success</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $transaction_status['completed']['count']; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-green-100 text-green-600">
                                    <i data-lucide="check-circle" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $total_count > 0 ? ($transaction_status['completed']['count'] / $total_count * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Completed</span>
                                    <span class="font-medium">₱<?php echo number_format($transaction_status['completed']['amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Failed Transactions Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Failed</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $transaction_status['failed']['count']; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-red-100 text-red-600">
                                    <i data-lucide="x-circle" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-red-500 rounded-full" style="width: <?php echo $total_count > 0 ? ($transaction_status['failed']['count'] / $total_count * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Failed payments</span>
                                    <span class="font-medium">₱<?php echo number_format($transaction_status['failed']['amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Transactions Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Pending</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $transaction_status['pending']['count']; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                                    <i data-lucide="clock" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo $total_count > 0 ? ($transaction_status['pending']['count'] / $total_count * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Awaiting action</span>
                                    <span class="font-medium">₱<?php echo number_format($transaction_status['pending']['amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- For Approval Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">For Approval</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $transaction_status['for_approval']['count']; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                    <i data-lucide="shield-question" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-purple-500 rounded-full" style="width: <?php echo $total_count > 0 ? ($transaction_status['for_approval']['count'] / $total_count * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Needs review</span>
                                    <span class="font-medium">₱<?php echo number_format($transaction_status['for_approval']['amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Overdue Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Overdue</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $transaction_status['overdue']['count']; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-orange-500 rounded-full" style="width: <?php echo $total_count > 0 ? ($transaction_status['overdue']['count'] / $total_count * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Past due date</span>
                                    <span class="font-medium">₱<?php echo number_format($transaction_status['overdue']['amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Refund Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Refund</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $transaction_status['refund']['count']; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                    <i data-lucide="rotate-ccw" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-500 rounded-full" style="width: <?php echo $total_count > 0 ? ($transaction_status['refund']['count'] / $total_count * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Refunded</span>
                                    <span class="font-medium">₱<?php echo number_format($transaction_status['refund']['amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                 
               <!-- Content Area -->
<section class="glass-effect p-6 rounded-2xl shadow-sm">
    <div class="mb-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Collection Requests</h3>
        <p class="text-gray-600">Manage and track all collection requests from customers and partners.</p>
    </div>
    
    <!-- Collection Requests Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                    // Fetch only 25 collection requests for the table
                    $collection_requests = [];
                    $sql = "SELECT * FROM collections ORDER BY due_date DESC LIMIT 25";
                    $result = mysqli_query($conn, $sql);
                    if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $collection_requests[] = $row;
                        }
                    }
                    
                    if (count($collection_requests) > 0): ?>
                        <?php foreach ($collection_requests as $request): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['request_id']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱<?php echo number_format($request['amount'], 2); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($request['service_type'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    $status_lower = strtolower($request['status']);
                                    switch ($status_lower) {
                                        case 'completed':
                                            $status_class = 'bg-green-100 text-green-800';
                                            $status_text = 'Paid';
                                            break;
                                        case 'pending':
                                            $status_class = 'bg-amber-100 text-amber-800';
                                            $status_text = 'Pending';
                                            break;
                                        case 'failed':
                                            $status_class = 'bg-red-100 text-red-800';
                                            $status_text = 'Failed';
                                            break;
                                        case 'overdue':
                                            $status_class = 'bg-red-100 text-red-800';
                                            $status_text = 'Overdue';
                                            break;
                                        case 'refund':
                                            $status_class = 'bg-blue-100 text-blue-800';
                                            $status_text = 'Refund';
                                            break;
                                        case 'for_approval':
                                            $status_class = 'bg-purple-100 text-purple-800';
                                            $status_text = 'For Approval';
                                            break;
                                        case 'for_compliance':
                                            $status_class = 'bg-purple-100 text-purple-800';
                                            $status_text = 'For Compliance';
                                            break;
                                        case 'for_receivable_approval':
                                            $status_class = 'bg-indigo-100 text-indigo-800';
                                            $status_text = 'For Receivable Approval';
                                            break;
                                        case 'received':
                                            $status_class = 'bg-green-100 text-green-800';
                                            $status_text = 'Received';
                                            break;
                                        default:
                                            $status_class = 'bg-gray-100 text-gray-800';
                                            $status_text = ucfirst($request['status']);
                                    }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button 
                                        onclick="openViewModal(this)"
                                        data-transaction='<?php echo htmlspecialchars(json_encode($request), ENT_QUOTES, 'UTF-8'); ?>'
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
                                    >
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                        View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                No collection requests found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
      <!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="px-6 py-4 border-t border-gray-200">
    <div class="flex items-center justify-between">
        <div class="text-sm text-gray-700">
            Showing 
            <span class="font-medium"><?php echo ($offset + 1); ?></span> 
            to 
            <span class="font-medium"><?php echo min(($offset + $limit), $total_records); ?></span> 
            of 
            <span class="font-medium"><?php echo $total_records; ?></span> 
            results
        </div>
        <div class="flex space-x-2">
            <!-- Previous Button -->
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo ($page - 1); ?>" class="px-3 py-1 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Previous
                </a>
            <?php else: ?>
                <span class="px-3 py-1 text-sm border border-gray-300 rounded-lg text-gray-400 cursor-not-allowed">
                    Previous
                </span>
            <?php endif; ?>
            
            <!-- Page Numbers -->
            <?php 
            // Determine which page numbers to show
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            // Show first page if not in range
            if ($start_page > 1) {
                echo '<a href="?page=1" class="px-3 py-1 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">1</a>';
                if ($start_page > 2) {
                    echo '<span class="px-3 py-1 text-sm">...</span>';
                }
            }
            
            // Show page numbers
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo '<span class="px-3 py-1 text-sm bg-blue-600 text-white rounded-lg">' . $i . '</span>';
                } else {
                    echo '<a href="?page=' . $i . '" class="px-3 py-1 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">' . $i . '</a>';
                }
            }
            
            // Show last page if not in range
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span class="px-3 py-1 text-sm">...</span>';
                }
                echo '<a href="?page=' . $total_pages . '" class="px-3 py-1 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">' . $total_pages . '</a>';
            }
            ?>
            
            <!-- Next Button -->
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo ($page + 1); ?>" class="px-3 py-1 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Next
                </a>
            <?php else: ?>
                <span class="px-3 py-1 text-sm border border-gray-300 rounded-lg text-gray-400 cursor-not-allowed">
                    Next
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>


    </div>
</section>

<!-- View Transaction Modal -->
<div id="viewTransactionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-semibold text-gray-800">Transaction Details</h3>
                    <p class="text-sm text-gray-600 mt-1" id="modalRequestId"></p>
                </div>
                <button onclick="closeModal('viewTransactionModal')" class="text-gray-400 hover:text-gray-500 p-2 rounded-full hover:bg-gray-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>
        
        <!-- Modal Content -->
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Customer Information -->
                    <div class="bg-gray-50 p-5 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                            <i data-lucide="user" class="w-5 h-5 text-blue-600"></i>
                            Customer Information
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-gray-500">Customer Name</p>
                                <p class="text-lg font-medium text-gray-800" id="modalCustomerName"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Contact Information</p>
                                <p class="text-sm text-gray-800" id="modalContactInfo">No contact information provided</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Transaction Details -->
                    <div class="bg-gray-50 p-5 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                            <i data-lucide="credit-card" class="w-5 h-5 text-green-600"></i>
                            Transaction Details
                        </h4>
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500">Amount</p>
                                    <p class="text-2xl font-bold text-gray-800" id="modalAmount"></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Payment Method</p>
                                    <p class="text-lg font-medium text-gray-800" id="modalPaymentMethod"></p>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Service Type</p>
                                <p class="text-lg font-medium text-gray-800" id="modalServiceType"></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dates Information -->
                    <div class="bg-gray-50 p-5 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                            <i data-lucide="calendar" class="w-5 h-5 text-purple-600"></i>
                            Dates Information
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Due Date</p>
                                <p class="text-lg font-medium text-gray-800" id="modalDueDate"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Collection Date</p>
                                <p class="text-lg font-medium text-gray-800" id="modalCollectionDate"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Created At</p>
                                <p class="text-lg font-medium text-gray-800" id="modalCreatedAt"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Last Updated</p>
                                <p class="text-lg font-medium text-gray-800" id="modalUpdatedAt"></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Status Information -->
                    <div class="bg-gray-50 p-5 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                            <i data-lucide="activity" class="w-5 h-5 text-amber-600"></i>
                            Status Information
                        </h4>
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-500">Current Status</p>
                                <span class="inline-block px-4 py-2 text-sm font-semibold rounded-lg mt-2" id="modalStatusBadge"></span>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Transaction ID</p>
                                <p class="text-sm font-mono text-gray-800" id="modalTransactionId"></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div class="bg-gray-50 p-5 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                            <i data-lucide="file-text" class="w-5 h-5 text-indigo-600"></i>
                            Description & Notes
                        </h4>
                        <div class="bg-white p-4 rounded-lg border border-gray-200 min-h-[120px]">
                            <p class="text-gray-700 whitespace-pre-line" id="modalDescription">No description provided</p>
                        </div>
                    </div>
                    
                    <!-- CRUD Actions -->
                    <div class="bg-gray-50 p-5 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                            <i data-lucide="settings" class="w-5 h-5 text-red-600"></i>
                            Transaction Actions
                        </h4>
                        <div class="grid grid-cols-2 gap-3">
                            <!-- Compliance Button -->
                            <button onclick="openComplianceModal()" class="px-4 py-3 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors flex items-center justify-center gap-2">
                                <i data-lucide="shield-check" class="w-4 h-4"></i>
                                Compliance
                            </button>
                            
                            <!-- Mark as Collected Button -->
                            <button onclick="markAsCollected()" class="px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center gap-2">
                                <i data-lucide="check-circle" class="w-4 h-4"></i>
                                Mark as Collected
                            </button>
                            
                            <!-- Delete Button -->
                            <button onclick="confirmDelete()" class="px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2 col-span-2">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="p-6 border-t border-gray-200">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    <span id="modalRecordAge"></span>
                </div>
                <div class="flex gap-3">
                    <button onclick="closeModal('viewTransactionModal')" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Close
                    </button>
                    <button onclick="printTransaction()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>




<script>
// Function to load page via AJAX (optional enhancement)
function loadPage(pageNumber) {
    // Create a form to submit the page number
    const form = document.createElement('form');
    form.method = 'GET';
    form.action = '';
    
    // Add page parameter
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'page';
    input.value = pageNumber;
    form.appendChild(input);
    
    // Add any existing GET parameters
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.forEach((value, key) => {
        if (key !== 'page') {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = key;
            hiddenInput.value = value;
            form.appendChild(hiddenInput);
        }
    });
    
    // Submit the form
    document.body.appendChild(form);
    form.submit();
}
</script>







<script>
// Global variable to store current transaction data
let currentTransaction = null;

// Open view modal with transaction details
function openViewModal(button) {
    try {
        // Parse the JSON data from data-transaction attribute
        const transactionData = JSON.parse(button.getAttribute('data-transaction'));
        currentTransaction = transactionData;
        
        // Update modal content
        document.getElementById('modalRequestId').textContent = transactionData.request_id;
        document.getElementById('modalCustomerName').textContent = transactionData.customer_name;
        document.getElementById('modalAmount').textContent = '₱' + parseFloat(transactionData.amount).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        document.getElementById('modalPaymentMethod').textContent = transactionData.payment_method || 'Not specified';
        document.getElementById('modalServiceType').textContent = transactionData.service_type || 'Not specified';
        document.getElementById('modalDueDate').textContent = formatDate(transactionData.due_date);
        document.getElementById('modalCollectionDate').textContent = transactionData.collection_date ? formatDateTime(transactionData.collection_date) : 'Not collected yet';
        document.getElementById('modalCreatedAt').textContent = formatDateTime(transactionData.created_at);
        document.getElementById('modalUpdatedAt').textContent = formatDateTime(transactionData.updated_at);
        document.getElementById('modalDescription').textContent = transactionData.description || 'No description provided';
        document.getElementById('modalTransactionId').textContent = transactionData.id;
        
        // Calculate record age
        const createdDate = new Date(transactionData.created_at);
        const now = new Date();
        const diffTime = Math.abs(now - createdDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        document.getElementById('modalRecordAge').textContent = `Record created ${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
        
        // Update status badge
        const statusBadge = document.getElementById('modalStatusBadge');
        statusBadge.textContent = getStatusText(transactionData.status);
        statusBadge.className = 'inline-block px-4 py-2 text-sm font-semibold rounded-lg mt-2 ' + getStatusClass(transactionData.status);
        
        // Show modal
        document.getElementById('viewTransactionModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Refresh Lucide icons
        setTimeout(() => {
            lucide.createIcons();
        }, 100);
        
    } catch (error) {
        console.error('Error parsing transaction data:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error loading transaction details. Please try again.'
        });
    }
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    if (modalId === 'viewTransactionModal') {
        document.body.style.overflow = 'auto';
        currentTransaction = null;
    }
}

// Open compliance modal with SweetAlert
function openComplianceModal() {
    if (!currentTransaction) return;
    
    // Check if status is "Received" - cannot be modified
    if (currentTransaction.status === 'Received') {
        Swal.fire({
            icon: 'warning',
            title: 'Action Not Allowed',
            text: 'Cannot modify transaction with "Received" status',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }
    
    Swal.fire({
        title: 'Add Compliance Notes',
        html: `
            <div class="text-left">
                <p class="mb-2 text-sm text-gray-600">Transaction: <span class="font-semibold">${currentTransaction.request_id}</span></p>
                <textarea id="complianceNotes" rows="5" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" placeholder="Enter compliance notes, remarks, or special instructions..."></textarea>
                <div class="mt-3">
                    <label class="flex items-center">
                        <input type="checkbox" id="markCompliant" class="mr-2 rounded border-gray-300 text-yellow-600 focus:ring-yellow-500">
                        <span class="text-sm text-gray-700">Mark as compliant and set status to "For Compliance"</span>
                    </label>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Save Compliance Notes',
        cancelButtonText: 'Cancel',
        focusConfirm: false,
        preConfirm: () => {
            const notes = document.getElementById('complianceNotes').value.trim();
            
            if (!notes) {
                Swal.showValidationMessage('Please enter compliance notes');
                return false;
            }
            
            const markCompliant = document.getElementById('markCompliant').checked;
            
            return { notes, markCompliant };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { notes, markCompliant } = result.value;
            saveCompliance(notes, markCompliant);
        }
    });
}

// Mark as collected with SweetAlert
function markAsCollected() {
    if (!currentTransaction) return;
    
    // Check if status is "Received" - cannot be modified
    if (currentTransaction.status === 'Received') {
        Swal.fire({
            icon: 'warning',
            title: 'Action Not Allowed',
            text: 'Cannot modify transaction with "Received" status',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }
    
    Swal.fire({
        title: 'Mark as Collected',
        html: `
            <div class="text-left">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-green-100 rounded-full">
                        <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Mark ${currentTransaction.request_id} as collected?</p>
                        <p class="text-sm text-gray-600 mt-1">Customer: ${currentTransaction.customer_name}</p>
                        <p class="text-sm text-gray-600">Amount: ₱${parseFloat(currentTransaction.amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</p>
                    </div>
                </div>
                <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <i class="mr-1">ℹ️</i> This will:
                    </p>
                    <ul class="text-sm text-blue-800 mt-2 pl-4 list-disc">
                        <li>Set status to "For Receivable Approval"</li>
                        <li>Add note: "Waiting for final review of receivable module"</li>
                        <li>Set collection date to current date/time</li>
                    </ul>
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Mark as Collected',
        cancelButtonText: 'Cancel',
        focusConfirm: false,
    }).then((result) => {
        if (result.isConfirmed) {
            markAsCollectedAPI();
        }
    });
}

// Confirm delete with SweetAlert
function confirmDelete() {
    if (!currentTransaction) return;
    
    // Check if status is "Received" - cannot be deleted
    if (currentTransaction.status === 'Received') {
        Swal.fire({
            icon: 'warning',
            title: 'Action Not Allowed',
            text: 'Cannot delete transaction with "Received" status',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }
    
    Swal.fire({
        title: 'Delete Transaction',
        html: `
            <div class="text-left">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-red-100 rounded-full">
                        <i data-lucide="alert-triangle" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Delete ${currentTransaction.request_id}?</p>
                        <p class="text-sm text-gray-600 mt-1">Customer: ${currentTransaction.customer_name}</p>
                        <p class="text-sm text-gray-600">Amount: ₱${parseFloat(currentTransaction.amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</p>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason for deletion</label>
                    <input type="text" id="deleteReason" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Enter reason for deletion..." autocomplete="off">
                </div>
                <div class="mt-3">
                    <label class="flex items-center">
                        <input type="checkbox" id="confirmDelete" class="mr-2 rounded border-gray-300 text-red-600 focus:ring-red-500">
                        <span class="text-sm text-red-700">I understand this action cannot be undone</span>
                    </label>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Delete Permanently',
        cancelButtonText: 'Cancel',
        focusConfirm: false,
        preConfirm: () => {
            const reason = document.getElementById('deleteReason').value.trim();
            const confirmed = document.getElementById('confirmDelete').checked;
            
            if (!reason) {
                Swal.showValidationMessage('Please provide a reason for deletion');
                return false;
            }
            
            if (!confirmed) {
                Swal.showValidationMessage('Please confirm you understand this action cannot be undone');
                return false;
            }
            
            return { reason };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            deleteTransaction(result.value.reason);
        }
    });
}

// Format date helper function
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Format date-time helper function
function formatDateTime(dateTimeString) {
    if (!dateTimeString) return 'N/A';
    const date = new Date(dateTimeString);
    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Get status text
function getStatusText(status) {
    const statusMap = {
        'completed': 'Paid',
        'pending': 'Pending',
        'failed': 'Failed',
        'overdue': 'Overdue',
        'refund': 'Refunded',
        'for_approval': 'For Approval',
        'for_compliance': 'For Compliance',
        'for_receivable_approval': 'For Receivable Approval',
        'received': 'Received'
    };
    return statusMap[status.toLowerCase()] || status.charAt(0).toUpperCase() + status.slice(1);
}

// Get status class
function getStatusClass(status) {
    const statusLower = status.toLowerCase();
    if (statusLower === 'completed' || statusLower === 'received') return 'bg-green-100 text-green-800';
    if (statusLower === 'pending') return 'bg-amber-100 text-amber-800';
    if (statusLower === 'failed' || statusLower === 'overdue') return 'bg-red-100 text-red-800';
    if (statusLower === 'refund') return 'bg-blue-100 text-blue-800';
    if (statusLower === 'for_approval' || statusLower === 'for_compliance') return 'bg-purple-100 text-purple-800';
    if (statusLower === 'for_receivable_approval') return 'bg-indigo-100 text-indigo-800';
    return 'bg-gray-100 text-gray-800';
}

// Save compliance notes with SweetAlert
async function saveCompliance(notes, markCompliant = false) {
    const swal = Swal.fire({
        title: 'Saving Compliance Notes...',
        text: 'Please wait while we save your notes',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        // Make API call to save compliance notes
        const response = await fetch('API/collections_crud_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'save_compliance',
                transaction_id: currentTransaction.id,
                notes: notes,
                mark_compliant: markCompliant
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            let message = 'Compliance notes saved successfully';
            if (data.status_updated) {
                message = 'Compliance notes saved and status updated to "For Compliance"';
            }
            
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                closeModal('viewTransactionModal');
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            });
        } else {
            throw new Error(data.message || 'Failed to save compliance notes');
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

// Mark as collected API call
async function markAsCollectedAPI() {
    const swal = Swal.fire({
        title: 'Marking as Collected...',
        text: 'Please wait while we process your request',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        // Make API call to mark as collected
        const response = await fetch('API/collections_crud_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'mark_collected',
                transaction_id: currentTransaction.id
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                html: `
                    <div class="text-left">
                        <p>Transaction marked as collected successfully</p>
                        <p class="mt-2 text-sm text-gray-600">Status updated to "For Receivable Approval"</p>
                    </div>
                `,
                timer: 3000,
                showConfirmButton: false
            }).then(() => {
                closeModal('viewTransactionModal');
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            });
        } else {
            throw new Error(data.message || 'Failed to mark as collected');
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

// Delete transaction with SweetAlert
async function deleteTransaction(reason) {
    const swal = Swal.fire({
        title: 'Deleting Transaction...',
        text: 'Please wait while we process your request',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        // Make API call to delete
        const response = await fetch('API/collections_crud_api.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: currentTransaction.id,
                reason: reason
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                text: 'Transaction has been deleted successfully',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                closeModal('viewTransactionModal');
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            });
        } else {
            throw new Error(data.message || 'Failed to delete transaction');
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

// Print transaction details
function printTransaction() {
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Transaction Details - ${currentTransaction.request_id}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .print-container { max-width: 600px; margin: 0 auto; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .company { font-size: 24px; font-weight: bold; color: #333; }
                .section { margin: 25px 0; }
                .section-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                .detail-row { display: flex; justify-content: space-between; margin: 8px 0; }
                .label { font-weight: bold; color: #555; width: 40%; }
                .value { width: 60%; }
                .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold; }
                .footer { text-align: center; margin-top: 40px; color: #888; font-size: 12px; }
                @media print {
                    body { margin: 20px; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="print-container">
                <div class="header">
                    <div class="company">COLLECTIONS REPORT</div>
                    <div style="color: #666; margin-top: 10px;">Transaction Details Report</div>
                    <div style="font-size: 14px; color: #888; margin-top: 5px;">Generated on ${new Date().toLocaleString()}</div>
                </div>
                
                <div class="section">
                    <div class="section-title">Transaction Information</div>
                    <div class="detail-row">
                        <div class="label">Request ID:</div>
                        <div class="value">${currentTransaction.request_id}</div>
                    </div>
                    <div class="detail-row">
                        <div class="label">Transaction ID:</div>
                        <div class="value">${currentTransaction.id}</div>
                    </div>
                    <div class="detail-row">
                        <div class="label">Customer:</div>
                        <div class="value">${currentTransaction.customer_name}</div>
                    </div>
                    <div class="detail-row">
                        <div class="label">Status:</div>
                        <div class="value">
                            <span class="status-badge" style="${getStatusStyle(currentTransaction.status)}">
                                ${getStatusText(currentTransaction.status)}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Financial Details</div>
                    <div class="detail-row">
                        <div class="label">Amount:</div>
                        <div class="value">₱${parseFloat(currentTransaction.amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>
                    </div>
                    <div class="detail-row">
                        <div class="label">Payment Method:</div>
                        <div class="value">${currentTransaction.payment_method}</div>
                    </div>
                    <div class="detail-row">
                        <div class="label">Service Type:</div>
                        <div class="value">${currentTransaction.service_type}</div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Date Information</div>
                    <div class="detail-row">
                        <div class="label">Due Date:</div>
                        <div class="value">${formatDate(currentTransaction.due_date)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="label">Collection Date:</div>
                        <div class="value">${currentTransaction.collection_date ? formatDateTime(currentTransaction.collection_date) : 'Not collected'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="label">Created:</div>
                        <div class="value">${formatDateTime(currentTransaction.created_at)}</div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Description</div>
                    <div style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; min-height: 80px; white-space: pre-line;">
                        ${currentTransaction.description || 'No description provided'}
                    </div>
                </div>
                
                <div class="footer">
                    <div>--- End of Report ---</div>
                    <div style="margin-top: 10px;">This is a computer-generated report</div>
                </div>
            </div>
        </body>
        </html>
    `;
    
    function getStatusStyle(status) {
        const statusLower = status.toLowerCase();
        if (statusLower === 'completed' || statusLower === 'received') return 'background-color: #d1fae5; color: #065f46;';
        if (statusLower === 'pending') return 'background-color: #fef3c7; color: #92400e;';
        if (statusLower === 'failed' || statusLower === 'overdue') return 'background-color: #fee2e2; color: #991b1b;';
        if (statusLower === 'refund') return 'background-color: #dbeafe; color: #1e40af;';
        if (statusLower === 'for_approval' || statusLower === 'for_compliance') return 'background-color: #f3e8ff; color: #6b21a8;';
        if (statusLower === 'for_receivable_approval') return 'background-color: #e0e7ff; color: #3730a3;';
        return 'background-color: #f3f4f6; color: #374151;';
    }
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
    }, 250);
}

// Load page for pagination (basic implementation)
function loadPage(pageNumber) {
    // Basic pagination implementation
    Swal.fire({
        title: 'Loading...',
        text: 'Loading page ' + pageNumber,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // In a real implementation, you would make an AJAX request here
    // For now, we'll just reload with a page parameter
    setTimeout(() => {
        window.location.href = '?page=' + pageNumber;
    }, 500);
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    if (event.target.id === 'viewTransactionModal') {
        closeModal('viewTransactionModal');
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        if (!document.getElementById('viewTransactionModal').classList.contains('hidden')) {
            closeModal('viewTransactionModal');
        }
    }
});

// Initialize Lucide icons
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});
</script>

                <!-- View Transaction Modal -->
                <div id="viewTransactionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden">
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
                        <!-- Modal Header -->
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-800">Transaction Details</h3>
                                    <p class="text-sm text-gray-600 mt-1" id="modalRequestId"></p>
                                </div>
                                <button onclick="closeModal('viewTransactionModal')" class="text-gray-400 hover:text-gray-500 p-2 rounded-full hover:bg-gray-100">
                                    <i data-lucide="x" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Modal Content -->
                        <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Left Column -->
                                <div class="space-y-6">
                                    <!-- Customer Information -->
                                    <div class="bg-gray-50 p-5 rounded-lg">
                                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                                            <i data-lucide="user" class="w-5 h-5 text-blue-600"></i>
                                            Customer Information
                                        </h4>
                                        <div class="space-y-3">
                                            <div>
                                                <p class="text-sm text-gray-500">Customer Name</p>
                                                <p class="text-lg font-medium text-gray-800" id="modalCustomerName"></p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">Contact Information</p>
                                                <p class="text-sm text-gray-800" id="modalContactInfo">No contact information provided</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Transaction Details -->
                                    <div class="bg-gray-50 p-5 rounded-lg">
                                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                                            <i data-lucide="credit-card" class="w-5 h-5 text-green-600"></i>
                                            Transaction Details
                                        </h4>
                                        <div class="space-y-3">
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <p class="text-sm text-gray-500">Amount</p>
                                                    <p class="text-2xl font-bold text-gray-800" id="modalAmount"></p>
                                                </div>
                                                <div>
                                                    <p class="text-sm text-gray-500">Payment Method</p>
                                                    <p class="text-lg font-medium text-gray-800" id="modalPaymentMethod"></p>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">Service Type</p>
                                                <p class="text-lg font-medium text-gray-800" id="modalServiceType"></p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Dates Information -->
                                    <div class="bg-gray-50 p-5 rounded-lg">
                                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                                            <i data-lucide="calendar" class="w-5 h-5 text-purple-600"></i>
                                            Dates Information
                                        </h4>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <p class="text-sm text-gray-500">Due Date</p>
                                                <p class="text-lg font-medium text-gray-800" id="modalDueDate"></p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">Collection Date</p>
                                                <p class="text-lg font-medium text-gray-800" id="modalCollectionDate"></p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">Created At</p>
                                                <p class="text-lg font-medium text-gray-800" id="modalCreatedAt"></p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">Last Updated</p>
                                                <p class="text-lg font-medium text-gray-800" id="modalUpdatedAt"></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Right Column -->
                                <div class="space-y-6">
                                    <!-- Status Information -->
                                    <div class="bg-gray-50 p-5 rounded-lg">
                                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                                            <i data-lucide="activity" class="w-5 h-5 text-amber-600"></i>
                                            Status Information
                                        </h4>
                                        <div class="space-y-4">
                                            <div>
                                                <p class="text-sm text-gray-500">Current Status</p>
                                                <span class="inline-block px-4 py-2 text-sm font-semibold rounded-lg mt-2" id="modalStatusBadge"></span>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">Transaction ID</p>
                                                <p class="text-sm font-mono text-gray-800" id="modalTransactionId"></p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Description -->
                                    <div class="bg-gray-50 p-5 rounded-lg">
                                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                                            <i data-lucide="file-text" class="w-5 h-5 text-indigo-600"></i>
                                            Description & Notes
                                        </h4>
                                        <div class="bg-white p-4 rounded-lg border border-gray-200 min-h-[120px]">
                                            <p class="text-gray-700" id="modalDescription">No description provided</p>
                                        </div>
                                    </div>
                                    
                                    <!-- CRUD Actions -->
                                    <div class="bg-gray-50 p-5 rounded-lg">
                                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                                            <i data-lucide="settings" class="w-5 h-5 text-red-600"></i>
                                            Transaction Actions
                                        </h4>
                                        <div class="grid grid-cols-2 gap-3">
                                            <!-- Compliance Button -->
                                            <button onclick="openComplianceModal()" class="px-4 py-3 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors flex items-center justify-center gap-2">
                                                <i data-lucide="shield-check" class="w-4 h-4"></i>
                                                Compliance
                                            </button>
                                            
                                            <!-- Delete Button -->
                                            <button onclick="confirmDelete()" class="px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                Delete
                                            </button>
                                            
                                            <!-- Update Status -->
                                            <button onclick="openStatusModal()" class="px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-2 col-span-2">
                                                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                                Update Status
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modal Footer -->
                        <div class="p-6 border-t border-gray-200">
                            <div class="flex justify-between items-center">
                                <div class="text-sm text-gray-500">
                                    <span id="modalRecordAge"></span>
                                </div>
                                <div class="flex gap-3">
                                    <button onclick="closeModal('viewTransactionModal')" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                        Close
                                    </button>
                                    <button onclick="printTransaction()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                                        <i data-lucide="printer" class="w-4 h-4"></i>
                                        Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods Breakdown -->
                <section class="glass-effect p-6 rounded-2xl shadow-sm mt-6">
                    <div class="mb-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Payment Methods</h3>
                        <p class="text-gray-600">Distribution of collections by payment method.</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <?php if (!empty($payment_methods) && is_array($payment_methods)): ?>
                            <?php foreach ($payment_methods as $method): ?>
                                <?php
                                $method_icons = [
                                    'credit card' => ['icon' => 'credit-card', 'color' => 'blue'],
                                    'bank transfer' => ['icon' => 'building', 'color' => 'green'],
                                    'digital wallet' => ['icon' => 'smartphone', 'color' => 'purple'],
                                    'cash' => ['icon' => 'dollar-sign', 'color' => 'amber'],
                                    'check' => ['icon' => 'file-text', 'color' => 'indigo']
                                ];
                                
                                $method_lower = strtolower($method['payment_method'] ?? '');
                                $icon_data = $method_icons[$method_lower] ?? ['icon' => 'credit-card', 'color' => 'gray'];
                                ?>
                                <div class="bg-white rounded-xl border border-gray-200 p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                            <i data-lucide="<?php echo $icon_data['icon']; ?>" class="w-5 h-5 mr-2 text-<?php echo $icon_data['color']; ?>-600"></i>
                                            <?php echo htmlspecialchars(ucwords($method['payment_method'] ?? 'Unknown')); ?>
                                        </h4>
                                        <span class="px-2 py-1 text-xs font-medium bg-<?php echo $icon_data['color']; ?>-100 text-<?php echo $icon_data['color']; ?>-800 rounded-full">
                                            <?php echo $method['percentage'] ?? 0; ?>%
                                        </span>
                                    </div>
                                    <div class="space-y-3">
                                        <div>
                                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                                <span>Total Amount</span>
                                                <span>₱<?php echo number_format($method['amount'] ?? 0, 2); ?></span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="bg-<?php echo $icon_data['color']; ?>-500 h-2 rounded-full" style="width: <?php echo $method['percentage'] ?? 0; ?>%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                                <span>Transactions</span>
                                                <span><?php echo $method['count'] ?? 0; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-span-4 text-center py-8">
                                <i data-lucide="credit-card" class="w-12 h-12 text-gray-400 mx-auto mb-3"></i>
                                <p class="text-gray-500">No payment method data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
  </div>

  <script>
// Global variable to store current transaction data
let currentTransaction = null;

// Open view modal with transaction details
function openViewModal(button) {
    try {
        // Parse the JSON data from data-transaction attribute
        const transactionData = JSON.parse(button.getAttribute('data-transaction'));
        currentTransaction = transactionData;
        
        // Update modal content
        document.getElementById('modalRequestId').textContent = transactionData.request_id;
        document.getElementById('modalCustomerName').textContent = transactionData.customer_name;
        document.getElementById('modalAmount').textContent = '₱' + parseFloat(transactionData.amount).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        document.getElementById('modalPaymentMethod').textContent = transactionData.payment_method || 'Not specified';
        document.getElementById('modalServiceType').textContent = transactionData.service_type || 'Not specified';
        document.getElementById('modalDueDate').textContent = formatDate(transactionData.due_date);
        document.getElementById('modalCollectionDate').textContent = transactionData.collection_date ? formatDateTime(transactionData.collection_date) : 'Not collected yet';
        document.getElementById('modalCreatedAt').textContent = formatDateTime(transactionData.created_at);
        document.getElementById('modalUpdatedAt').textContent = formatDateTime(transactionData.updated_at);
        document.getElementById('modalDescription').textContent = transactionData.description || 'No description provided';
        document.getElementById('modalTransactionId').textContent = transactionData.id;
        
        // Calculate record age
        const createdDate = new Date(transactionData.created_at);
        const now = new Date();
        const diffTime = Math.abs(now - createdDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        document.getElementById('modalRecordAge').textContent = `Record created ${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
        
        // Update status badge
        const statusBadge = document.getElementById('modalStatusBadge');
        statusBadge.textContent = getStatusText(transactionData.status);
        statusBadge.className = 'inline-block px-4 py-2 text-sm font-semibold rounded-lg mt-2 ' + getStatusClass(transactionData.status);
        
        // Show modal
        document.getElementById('viewTransactionModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Refresh Lucide icons
        setTimeout(() => {
            lucide.createIcons();
        }, 100);
        
    } catch (error) {
        console.error('Error parsing transaction data:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error loading transaction details. Please try again.'
        });
    }
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    if (modalId === 'viewTransactionModal') {
        document.body.style.overflow = 'auto';
        currentTransaction = null;
    }
}

// Open compliance modal with SweetAlert
function openComplianceModal() {
    if (!currentTransaction) return;
    
    Swal.fire({
        title: 'Add Compliance Notes',
        html: `
            <div class="text-left">
                <p class="mb-2 text-sm text-gray-600">Transaction: <span class="font-semibold">${currentTransaction.request_id}</span></p>
                <textarea id="complianceNotes" rows="5" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" placeholder="Enter compliance notes, remarks, or special instructions..."></textarea>
                <div class="mt-3">
                    <label class="flex items-center">
                        <input type="checkbox" id="markCompliant" class="mr-2 rounded border-gray-300 text-yellow-600 focus:ring-yellow-500">
                        <span class="text-sm text-gray-700">Mark as compliant</span>
                    </label>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Save Compliance Notes',
        cancelButtonText: 'Cancel',
        focusConfirm: false,
        preConfirm: () => {
            const notes = document.getElementById('complianceNotes').value.trim();
            const markCompliant = document.getElementById('markCompliant').checked;
            
            if (!notes) {
                Swal.showValidationMessage('Please enter compliance notes');
                return false;
            }
            
            return { notes, markCompliant };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { notes, markCompliant } = result.value;
            saveCompliance(notes, markCompliant);
        }
    });
}

// Confirm delete with SweetAlert
function confirmDelete() {
    if (!currentTransaction) return;
    
    Swal.fire({
        title: 'Delete Transaction',
        html: `
            <div class="text-left">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-red-100 rounded-full">
                        <i data-lucide="alert-triangle" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Delete ${currentTransaction.request_id}?</p>
                        <p class="text-sm text-gray-600 mt-1">Customer: ${currentTransaction.customer_name}</p>
                        <p class="text-sm text-gray-600">Amount: ₱${parseFloat(currentTransaction.amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</p>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason for deletion</label>
                    <input type="text" id="deleteReason" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Enter reason for deletion..." autocomplete="off">
                </div>
                <div class="mt-3">
                    <label class="flex items-center">
                        <input type="checkbox" id="confirmDelete" class="mr-2 rounded border-gray-300 text-red-600 focus:ring-red-500">
                        <span class="text-sm text-red-700">I understand this action cannot be undone</span>
                    </label>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Delete Permanently',
        cancelButtonText: 'Cancel',
        focusConfirm: false,
        preConfirm: () => {
            const reason = document.getElementById('deleteReason').value.trim();
            const confirmed = document.getElementById('confirmDelete').checked;
            
            if (!reason) {
                Swal.showValidationMessage('Please provide a reason for deletion');
                return false;
            }
            
            if (!confirmed) {
                Swal.showValidationMessage('Please confirm you understand this action cannot be undone');
                return false;
            }
            
            return { reason };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            deleteTransaction(result.value.reason);
        }
    });
}

// Open status update modal with SweetAlert
function openStatusModal() {
    if (!currentTransaction) return;
    
    const statusOptions = {
        'pending': { label: 'Pending', color: '#f59e0b' },
        'completed': { label: 'Completed', color: '#10b981' },
        'failed': { label: 'Failed', color: '#ef4444' },
        'overdue': { label: 'Overdue', color: '#dc2626' },
        'for_approval': { label: 'For Approval', color: '#8b5cf6' },
        'refund': { label: 'Refund', color: '#3b82f6' }
    };
    
    let statusOptionsHTML = '';
    Object.entries(statusOptions).forEach(([value, { label, color }]) => {
        const isCurrent = value === currentTransaction.status;
        statusOptionsHTML += `
            <option value="${value}" ${isCurrent ? 'selected' : ''} style="color: ${color}; font-weight: ${isCurrent ? 'bold' : 'normal'}">
                ${label} ${isCurrent ? '(Current)' : ''}
            </option>
        `;
    });
    
    Swal.fire({
        title: 'Update Transaction Status',
        html: `
            <div class="text-left">
                <p class="mb-4 text-sm text-gray-600">Transaction: <span class="font-semibold">${currentTransaction.request_id}</span></p>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select New Status</label>
                    <select id="newStatus" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        ${statusOptionsHTML}
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                    <textarea id="statusNotes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Add notes about status change..."></textarea>
                </div>
                <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <i class="mr-1">ℹ️</i> Updating status will log this change and notify relevant parties if configured.
                    </p>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Update Status',
        cancelButtonText: 'Cancel',
        focusConfirm: false,
        preConfirm: () => {
            const newStatus = document.getElementById('newStatus').value;
            const notes = document.getElementById('statusNotes').value.trim();
            
            if (newStatus === currentTransaction.status) {
                Swal.showValidationMessage('Please select a different status from current');
                return false;
            }
            
            return { newStatus, notes };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            updateTransactionStatus(result.value.newStatus, result.value.notes);
        }
    });
}

// Format date helper function
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Format date-time helper function
function formatDateTime(dateTimeString) {
    if (!dateTimeString) return 'N/A';
    const date = new Date(dateTimeString);
    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Get status text
function getStatusText(status) {
    const statusMap = {
        'completed': 'Paid',
        'pending': 'Pending',
        'failed': 'Failed',
        'overdue': 'Overdue',
        'refund': 'Refunded',
        'for_approval': 'For Approval'
    };
    return statusMap[status.toLowerCase()] || status.charAt(0).toUpperCase() + status.slice(1);
}

// Get status class
function getStatusClass(status) {
    const statusLower = status.toLowerCase();
    if (statusLower === 'completed') return 'bg-green-100 text-green-800';
    if (statusLower === 'pending') return 'bg-amber-100 text-amber-800';
    if (statusLower === 'failed' || statusLower === 'overdue') return 'bg-red-100 text-red-800';
    if (statusLower === 'refund') return 'bg-blue-100 text-blue-800';
    if (statusLower === 'for_approval') return 'bg-purple-100 text-purple-800';
    return 'bg-gray-100 text-gray-800';
}

// Save compliance notes with SweetAlert
async function saveCompliance(notes, markCompliant = false) {
    const swal = Swal.fire({
        title: 'Saving Compliance Notes...',
        text: 'Please wait while we save your notes',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        // Make API call to save compliance notes
        const response = await fetch('API/collections_crud_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'save_compliance',
                transaction_id: currentTransaction.id,
                notes: notes,
                mark_compliant: markCompliant
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            // If marking as compliant, also update status
            if (markCompliant) {
                await updateStatusToCompliant(notes);
            } else {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Compliance notes saved successfully',
                    timer: 2000,
                    showConfirmButton: false
                });
                
                // Update modal description with compliance notes
                const currentDesc = document.getElementById('modalDescription').textContent;
                const complianceNote = `\n\n[Compliance Note: ${new Date().toLocaleDateString()}]\n${notes}`;
                document.getElementById('modalDescription').textContent = currentDesc + complianceNote;
            }
        } else {
            throw new Error(data.message || 'Failed to save compliance notes');
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

// Update status to compliant
async function updateStatusToCompliant(notes) {
    try {
        const response = await fetch('API/collections_crud_api.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: currentTransaction.id,
                status: 'completed',
                notes: `Marked as compliant: ${notes}`
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                html: `
                    <div class="text-left">
                        <p>✓ Compliance notes saved</p>
                        <p>✓ Status updated to "Completed"</p>
                        <p class="mt-2 text-sm text-gray-600">Transaction marked as compliant</p>
                    </div>
                `,
                timer: 3000,
                showConfirmButton: false
            }).then(() => {
                closeModal('viewTransactionModal');
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            });
        } else {
            throw new Error(data.message || 'Failed to update status');
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

// Delete transaction with SweetAlert
async function deleteTransaction(reason) {
    const swal = Swal.fire({
        title: 'Deleting Transaction...',
        text: 'Please wait while we process your request',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        // Make API call to delete
        const response = await fetch('API/collections_crud_api.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: currentTransaction.id,
                reason: reason
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                text: 'Transaction has been deleted successfully',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                closeModal('viewTransactionModal');
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            });
        } else {
            throw new Error(data.message || 'Failed to delete transaction');
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

// Update transaction status with SweetAlert
async function updateTransactionStatus(newStatus, notes = '') {
    const statusText = getStatusText(newStatus);
    
    const swal = Swal.fire({
        title: 'Updating Status...',
        text: `Changing status to "${statusText}"`,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        // Make API call to update status
        const response = await fetch('API/collections_crud_api.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: currentTransaction.id,
                status: newStatus,
                notes: notes
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Status Updated!',
                html: `
                    <div class="text-left">
                        <p>Transaction status has been updated to <span class="font-semibold">${statusText}</span></p>
                        ${notes ? `<p class="mt-2 text-sm text-gray-600">Notes: ${notes}</p>` : ''}
                    </div>
                `,
                timer: 3000,
                showConfirmButton: false
            }).then(() => {
                closeModal('viewTransactionModal');
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            });
        } else {
            throw new Error(data.message || 'Failed to update status');
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

// Print transaction details
function printTransaction() {
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Transaction Details - ${currentTransaction.request_id}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .print-container { max-width: 600px; margin: 0 auto; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .company { font-size: 24px; font-weight: bold; color: #333; }
                .section { margin: 25px 0; }
                .section-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                .detail-row { display: flex; justify-content: space-between; margin: 8px 0; }
                .label { font-weight: bold; color: #555; width: 40%; }
                .value { width: 60%; }
                .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold; }
                .footer { text-align: center; margin-top: 40px; color: #888; font-size: 12px; }
                @media print {
                    body { margin: 20px; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="print-container">
                <div class="header">
                    <div class="company">COLLECTIONS REPORT</div>
                    <div style="color: #666; margin-top: 10px;">Transaction Details Report</div>
                    <div style="font-size: 14px; color: #888; margin-top: 5px;">Generated on ${new Date().toLocaleString()}</div>
                </div>
                
                <div class="section">
                    <div class="section-title">Transaction Information</div>
                    <div class="detail-row">
                        <div class="label">Request ID:</div>
                        <div class="value">${currentTransaction.request_id}</div>
                    </div>
                    <div class="detail-row">
                        <div class="label">Transaction ID:</div>
                        <div class="value">${currentTransaction.id}</div>
                    </div>
                    <div class="detail-row">
                        <div class="label">Customer:</div>
                        <div class="value">${currentTransaction.customer_name}</div>
                    </div>
                    <div class="detail-row">
                        <div class="label">Status:</div>
                        <div class="value">
                            <span class="status-badge" style="${getStatusStyle(currentTransaction.status)}">
                                ${getStatusText(currentTransaction.status)}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Financial Details</div>
                    <div class="detail-row">
                        <div class="label">Amount:</div>
                        <div class="value">₱${parseFloat(currentTransaction.amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>
                    </div>
                    <div class="detail-row">
                        <div class="label">Payment Method:</div>
                        <div class="value">${currentTransaction.payment_method}</div>
                    </div>
                    <div class="detail-row">
                        <div class="label">Service Type:</div>
                        <div class="value">${currentTransaction.service_type}</div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Date Information</div>
                    <div class="detail-row">
                        <div class="label">Due Date:</div>
                        <div class="value">${formatDate(currentTransaction.due_date)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="label">Collection Date:</div>
                        <div class="value">${currentTransaction.collection_date ? formatDateTime(currentTransaction.collection_date) : 'Not collected'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="label">Created:</div>
                        <div class="value">${formatDateTime(currentTransaction.created_at)}</div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Description</div>
                    <div style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; min-height: 80px;">
                        ${currentTransaction.description || 'No description provided'}
                    </div>
                </div>
                
                <div class="footer">
                    <div>--- End of Report ---</div>
                    <div style="margin-top: 10px;">This is a computer-generated report</div>
                </div>
            </div>
        </body>
        </html>
    `;
    
    function getStatusStyle(status) {
        const statusLower = status.toLowerCase();
        if (statusLower === 'completed') return 'background-color: #d1fae5; color: #065f46;';
        if (statusLower === 'pending') return 'background-color: #fef3c7; color: #92400e;';
        if (statusLower === 'failed' || statusLower === 'overdue') return 'background-color: #fee2e2; color: #991b1b;';
        if (statusLower === 'refund') return 'background-color: #dbeafe; color: #1e40af;';
        if (statusLower === 'for_approval') return 'background-color: #f3e8ff; color: #6b21a8;';
        return 'background-color: #f3f4f6; color: #374151;';
    }
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
    }, 250);
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    if (event.target.id === 'viewTransactionModal') {
        closeModal('viewTransactionModal');
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        if (!document.getElementById('viewTransactionModal').classList.contains('hidden')) {
            closeModal('viewTransactionModal');
        }
    }
});

// Initialize Lucide icons
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});
</script>
</body>
</html>