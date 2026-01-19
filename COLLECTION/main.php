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

// ============ FILTER SETUP ============
// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_customer = $_GET['customer'] ?? '';
$filter_service_type = $_GET['service_type'] ?? '';
$filter_payment_method = $_GET['payment_method'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_amount_min = $_GET['amount_min'] ?? '';
$filter_amount_max = $_GET['amount_max'] ?? '';

// ============ PAGINATION SETUP ============
$limit = 15; // LIMIT to 15 records per page as requested
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $limit;

// Build WHERE clause for filters
$where_clauses = ["status != 'deleted'"];
$params = [];
$param_types = "";

// Status filter
if (!empty($filter_status)) {
    if ($filter_status === 'all') {
        // Show all except deleted
        $where_clauses[] = "status != 'deleted'";
    } else {
        $where_clauses[] = "status = ?";
        $params[] = $filter_status;
        $param_types .= "s";
    }
}

// Customer filter
if (!empty($filter_customer)) {
    $where_clauses[] = "(customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?)";
    $params[] = "%$filter_customer%";
    $params[] = "%$filter_customer%";
    $params[] = "%$filter_customer%";
    $param_types .= "sss";
}

// Service type filter
if (!empty($filter_service_type)) {
    $where_clauses[] = "service_type = ?";
    $params[] = $filter_service_type;
    $param_types .= "s";
}

// Payment method filter
if (!empty($filter_payment_method)) {
    $where_clauses[] = "payment_method = ?";
    $params[] = $filter_payment_method;
    $param_types .= "s";
}

// Date range filter
if (!empty($filter_date_from) && !empty($filter_date_to)) {
    $where_clauses[] = "DATE(due_date) BETWEEN ? AND ?";
    $params[] = $filter_date_from;
    $params[] = $filter_date_to;
    $param_types .= "ss";
} elseif (!empty($filter_date_from)) {
    $where_clauses[] = "DATE(due_date) >= ?";
    $params[] = $filter_date_from;
    $param_types .= "s";
} elseif (!empty($filter_date_to)) {
    $where_clauses[] = "DATE(due_date) <= ?";
    $params[] = $filter_date_to;
    $param_types .= "s";
}

// Amount range filter
if (!empty($filter_amount_min) && !empty($filter_amount_max)) {
    $where_clauses[] = "amount BETWEEN ? AND ?";
    $params[] = $filter_amount_min;
    $params[] = $filter_amount_max;
    $param_types .= "dd";
} elseif (!empty($filter_amount_min)) {
    $where_clauses[] = "amount >= ?";
    $params[] = $filter_amount_min;
    $param_types .= "d";
} elseif (!empty($filter_amount_max)) {
    $where_clauses[] = "amount <= ?";
    $params[] = $filter_amount_max;
    $param_types .= "d";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "WHERE status != 'deleted'";

// Get total count of records for pagination
$total_records = 0;
if (!empty($params)) {
    $count_sql = "SELECT COUNT(*) as total FROM collections $where_sql";
    $count_stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    if ($count_result) {
        $row = mysqli_fetch_assoc($count_result);
        $total_records = $row['total'];
    }
} else {
    $count_sql = "SELECT COUNT(*) as total FROM collections $where_sql";
    $count_result = mysqli_query($conn, $count_sql);
    if ($count_result) {
        $row = mysqli_fetch_assoc($count_result);
        $total_records = $row['total'];
    }
}

// Calculate total pages
$total_pages = ceil($total_records / $limit);

// Fetch collection requests with filters and pagination
$collection_requests = [];
$params_for_query = $params;
$params_for_query[] = $limit;
$params_for_query[] = $offset;
$param_types_for_query = $param_types . "ii";

$sql = "SELECT * FROM collections $where_sql ORDER BY due_date DESC LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types_for_query, ...$params_for_query);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $collection_requests[] = $row;
    }
}

// ============ STATISTICS SETUP ============
$total_collections = 0;
$pending_collections = 0;
$pending_amount = 0;
$successful_this_month = 0;
$collection_rate = 0;
$total_count = 0;
$completed_count = 0;
$monthly_count = 0;
$transaction_status = [
    'RECEIVED' => ['count' => 0, 'amount' => 0],
    'PAID' => ['count' => 0, 'amount' => 0],
    'PENDING' => ['count' => 0, 'amount' => 0],
    'FOR COMPLIANCE' => ['count' => 0, 'amount' => 0],
    'OVERDUE' => ['count' => 0, 'amount' => 0],
    'REFUND' => ['count' => 0, 'amount' => 0],
    'FOR APPROVAL' => ['count' => 0, 'amount' => 0],
    'FAILED' => ['count' => 0, 'amount' => 0]
];
$payment_methods = [];

// Get unique values for filter dropdowns
$service_types = [];
$payment_method_options = [];
$status_options = ['RECEIVED', 'PAID', 'PENDING', 'FOR COMPLIANCE', 'OVERDUE', 'REFUND', 'FOR APPROVAL', 'FAILED'];

// Fetch unique service types
$service_sql = "SELECT DISTINCT service_type FROM collections WHERE service_type IS NOT NULL AND service_type != '' AND status != 'deleted' ORDER BY service_type";
$service_result = mysqli_query($conn, $service_sql);
if ($service_result) {
    while ($row = mysqli_fetch_assoc($service_result)) {
        $service_types[] = $row['service_type'];
    }
}

// Fetch unique payment methods
$payment_sql = "SELECT DISTINCT payment_method FROM collections WHERE payment_method IS NOT NULL AND payment_method != '' AND status != 'deleted' ORDER BY payment_method";
$payment_result = mysqli_query($conn, $payment_sql);
if ($payment_result) {
    while ($row = mysqli_fetch_assoc($payment_result)) {
        $payment_method_options[] = $row['payment_method'];
    }
}

// Statistics queries
$query_total = "SELECT COALESCE(SUM(amount), 0) as total FROM collections WHERE status IN ('PAID', 'RECEIVED', 'COMPLETED')";
$result_total = mysqli_query($conn, $query_total);
if ($result_total) {
    $row = mysqli_fetch_assoc($result_total);
    $total_collections = $row['total'] ?? 0;
}

$query_pending = "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM collections WHERE status IN ('PENDING', 'FOR APPROVAL', 'FOR COMPLIANCE')";
$result_pending = mysqli_query($conn, $query_pending);
if ($result_pending) {
    $row = mysqli_fetch_assoc($result_pending);
    $pending_collections = $row['count'] ?? 0;
    $pending_amount = $row['total'] ?? 0;
}

// Query for this month's successful collections
$query_month = "SELECT COALESCE(SUM(amount), 0) as total FROM collections 
               WHERE status IN ('PAID', 'RECEIVED', 'COMPLETED') 
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
$query_completed = "SELECT COUNT(*) as completed FROM collections WHERE status IN ('PAID', 'RECEIVED', 'COMPLETED')";
$result_completed = mysqli_query($conn, $query_completed);
if ($result_completed) {
    $row = mysqli_fetch_assoc($result_completed);
    $completed_count = $row['completed'] ?? 0;
}

// Calculate collection rate
if ($total_count > 0) {
    $collection_rate = round(($completed_count / $total_count) * 100, 2);
}

// Query transaction status breakdown for all statuses
foreach ($status_options as $status) {
    $query = "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as amount 
              FROM collections WHERE status = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $status);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
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
                 AND payment_method != ''
                 AND status != 'deleted'
                 GROUP BY payment_method
                 ORDER BY amount DESC";
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Collections Management'; ?> | System Name</title>
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
                        <div class="text-sm text-gray-600">
                            Showing <?php echo min(($page - 1) * $limit + 1, $total_records); ?>-<?php echo min($page * $limit, $total_records); ?> of <?php echo $total_records; ?> collections
                        </div>
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

                        <!-- RECEIVED Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">RECEIVED</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $transaction_status['RECEIVED']['count']; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-green-100 text-green-600">
                                    <i data-lucide="check-circle" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $total_count > 0 ? ($transaction_status['RECEIVED']['count'] / $total_count * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Received</span>
                                    <span class="font-medium">₱<?php echo number_format($transaction_status['RECEIVED']['amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- PAID Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">PAID</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $transaction_status['PAID']['count']; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                    <i data-lucide="dollar-sign" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-500 rounded-full" style="width: <?php echo $total_count > 0 ? ($transaction_status['PAID']['count'] / $total_count * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Paid</span>
                                    <span class="font-medium">₱<?php echo number_format($transaction_status['PAID']['amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- PENDING Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">PENDING</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $transaction_status['PENDING']['count']; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                                    <i data-lucide="clock" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo $total_count > 0 ? ($transaction_status['PENDING']['count'] / $total_count * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Pending</span>
                                    <span class="font-medium">₱<?php echo number_format($transaction_status['PENDING']['amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- FOR COMPLIANCE Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">FOR COMPLIANCE</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $transaction_status['FOR COMPLIANCE']['count']; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                    <i data-lucide="shield-check" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-purple-500 rounded-full" style="width: <?php echo $total_count > 0 ? ($transaction_status['FOR COMPLIANCE']['count'] / $total_count * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>For Compliance</span>
                                    <span class="font-medium">₱<?php echo number_format($transaction_status['FOR COMPLIANCE']['amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- OVERDUE Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">OVERDUE</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $transaction_status['OVERDUE']['count']; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-red-100 text-red-600">
                                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-red-500 rounded-full" style="width: <?php echo $total_count > 0 ? ($transaction_status['OVERDUE']['count'] / $total_count * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Overdue</span>
                                    <span class="font-medium">₱<?php echo number_format($transaction_status['OVERDUE']['amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- REFUND Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">REFUND</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $transaction_status['REFUND']['count']; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                                    <i data-lucide="rotate-ccw" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500 rounded-full" style="width: <?php echo $total_count > 0 ? ($transaction_status['REFUND']['count'] / $total_count * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Refunded</span>
                                    <span class="font-medium">₱<?php echo number_format($transaction_status['REFUND']['amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Payment Methods Breakdown -->
                <section class="glass-effect p-6 rounded-2xl shadow-sm mt-6">
                    <div class="mb-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Payment Methods</h3>
                        <p class="text-gray-600">Distribution of collections by payment method.</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                        <?php if (!empty($payment_methods) && is_array($payment_methods)): ?>
                            <?php foreach ($payment_methods as $method): ?>
                                <?php
                                $method_icons = [
                                    'credit card' => ['icon' => 'credit-card', 'color' => 'blue'],
                                    'bank transfer' => ['icon' => 'building', 'color' => 'green'],
                                    'digital wallet' => ['icon' => 'smartphone', 'color' => 'purple'],
                                    'cash' => ['icon' => 'dollar-sign', 'color' => 'amber'],
                                    'check' => ['icon' => 'file-text', 'color' => 'indigo'],
                                    'online' => ['icon' => 'globe', 'color' => 'red'],
                                    'paypal' => ['icon' => 'credit-card', 'color' => 'blue'],
                                    'gcash' => ['icon' => 'smartphone', 'color' => 'green']
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
                 
               <!-- Collection Requests Table Section -->
               <section class="glass-effect p-6 rounded-2xl shadow-sm">
                    <div class="mb-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div>
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">Collection Requests</h3>
                                <p class="text-gray-600">Manage and track all collection requests from customers and partners.</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" onclick="refreshData()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                    Refresh
                                </button>
                            </div>
                        </div>
                    </div>

                     <!-- Filter Section -->
                <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Collections</h3>
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Status Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Status</option>
                                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All (Except Deleted)</option>
                                <?php foreach ($status_options as $status): ?>
                                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $filter_status === $status ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($status); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Customer Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                            <input type="text" name="customer" value="<?php echo htmlspecialchars($filter_customer); ?>" 
                                   placeholder="Search by name, email, or phone..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <!-- Service Type Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                            <select name="service_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Services</option>
                                <?php foreach ($service_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" 
                                            <?php echo $filter_service_type === $type ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Payment Method Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                            <select name="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Methods</option>
                                <?php foreach ($payment_method_options as $method): ?>
                                    <option value="<?php echo htmlspecialchars($method); ?>" 
                                            <?php echo $filter_payment_method === $method ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($method); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Date Range Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date Range</label>
                            <div class="flex gap-2">
                                <input type="date" name="date_from" value="<?php echo $filter_date_from; ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <input type="date" name="date_to" value="<?php echo $filter_date_to; ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <!-- Amount Range Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount Range</label>
                            <div class="flex gap-2">
                                <input type="number" name="amount_min" value="<?php echo $filter_amount_min; ?>" 
                                       placeholder="Min" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <input type="number" name="amount_max" value="<?php echo $filter_amount_max; ?>" 
                                       placeholder="Max" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <!-- Filter Action Buttons -->
                        <div class="md:col-span-4 flex gap-2 mt-2">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                                <i data-lucide="filter" class="w-4 h-4"></i>
                                Apply Filters
                            </button>
                            <a href="?" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors flex items-center gap-2">
                                <i data-lucide="x" class="w-4 h-4"></i>
                                Clear Filters
                            </a>
                            <button type="button" onclick="exportToExcel()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2 ml-auto">
                                <i data-lucide="download" class="w-4 h-4"></i>
                                Export to Excel
                            </button>
                        </div>
                        
                        <!-- Active Filters Display -->
                        <?php 
                        $active_filters = [];
                        if (!empty($filter_status)) $active_filters[] = "Status: $filter_status";
                        if (!empty($filter_customer)) $active_filters[] = "Customer: $filter_customer";
                        if (!empty($filter_service_type)) $active_filters[] = "Service: $filter_service_type";
                        if (!empty($filter_payment_method)) $active_filters[] = "Payment: $filter_payment_method";
                        if (!empty($filter_date_from) || !empty($filter_date_to)) $active_filters[] = "Date Range: " . ($filter_date_from ?: 'Any') . " to " . ($filter_date_to ?: 'Any');
                        if (!empty($filter_amount_min) || !empty($filter_amount_max)) $active_filters[] = "Amount: " . ($filter_amount_min ?: '0') . " - " . ($filter_amount_max ?: 'Any');
                        
                        if (!empty($active_filters)):
                        ?>
                        <div class="md:col-span-4 mt-4 p-3 bg-blue-50 rounded-lg">
                            <div class="flex items-center gap-2 mb-2">
                                <i data-lucide="filter" class="w-4 h-4 text-blue-600"></i>
                                <span class="text-sm font-medium text-blue-800">Active Filters:</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($active_filters as $filter): ?>
                                    <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                        <?php echo htmlspecialchars($filter); ?>
                                    </span>
                                <?php endforeach; ?>
                                <a href="?" class="text-xs text-blue-600 hover:text-blue-800 hover:underline ml-2">
                                    Clear All
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>
                </section>
                    
                    <!-- Collection Requests Table -->
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table id="collectionsTable" class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (count($collection_requests) > 0): ?>
                                        <?php foreach ($collection_requests as $request): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($request['request_id']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($request['customer_name']); ?>
                                                        </div>
                                                        <?php if (!empty($request['customer_email'])): ?>
                                                            <div class="text-xs text-gray-500">
                                                                <?php echo htmlspecialchars($request['customer_email']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                                    ₱<?php echo number_format($request['amount'], 2); ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($request['service_type'] ?? 'N/A'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($request['payment_method'] ?? 'N/A'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo date('M d, Y', strtotime($request['due_date'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php
                                                    $status_class = '';
                                                    $status_text = '';
                                                    $status = strtoupper($request['status']);
                                                    switch ($status) {
                                                        case 'RECEIVED':
                                                            $status_class = 'bg-green-100 text-green-800';
                                                            $status_text = 'RECEIVED';
                                                            break;
                                                        case 'PAID':
                                                            $status_class = 'bg-blue-100 text-blue-800';
                                                            $status_text = 'PAID';
                                                            break;
                                                        case 'PENDING':
                                                            $status_class = 'bg-amber-100 text-amber-800';
                                                            $status_text = 'PENDING';
                                                            break;
                                                        case 'FOR COMPLIANCE':
                                                        case 'FOR_COMPLIANCE':
                                                            $status_class = 'bg-purple-100 text-purple-800';
                                                            $status_text = 'FOR COMPLIANCE';
                                                            break;
                                                        case 'OVERDUE':
                                                            $status_class = 'bg-red-100 text-red-800';
                                                            $status_text = 'OVERDUE';
                                                            break;
                                                        case 'REFUND':
                                                            $status_class = 'bg-indigo-100 text-indigo-800';
                                                            $status_text = 'REFUND';
                                                            break;
                                                        case 'FOR APPROVAL':
                                                        case 'FOR_APPROVAL':
                                                            $status_class = 'bg-yellow-100 text-yellow-800';
                                                            $status_text = 'FOR APPROVAL';
                                                            break;
                                                        case 'FAILED':
                                                            $status_class = 'bg-red-100 text-red-800';
                                                            $status_text = 'FAILED';
                                                            break;
                                                        default:
                                                            $status_class = 'bg-gray-100 text-gray-800';
                                                            $status_text = $status;
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
                                                        class="px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2 text-xs"
                                                    >
                                                        <i data-lucide="eye" class="w-3 h-3"></i>
                                                        View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="px-6 py-8 text-center">
                                                <div class="flex flex-col items-center justify-center">
                                                    <i data-lucide="file-text" class="w-12 h-12 text-gray-400 mb-3"></i>
                                                    <p class="text-gray-500 font-medium">No collection requests found</p>
                                                    <p class="text-sm text-gray-400 mt-1">
                                                        <?php echo !empty(array_filter([$filter_status, $filter_customer, $filter_service_type, $filter_payment_method, $filter_date_from, $filter_date_to, $filter_amount_min, $filter_amount_max])) ? 
                                                            'Try adjusting your filters' : 
                                                            'Create your first collection request'; ?>
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="px-6 py-4 border-t border-gray-200">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                <div class="text-sm text-gray-700">
                                    Showing 
                                    <span class="font-medium"><?php echo min(($offset + 1), $total_records); ?></span> 
                                    to 
                                    <span class="font-medium"><?php echo min(($offset + $limit), $total_records); ?></span> 
                                    of 
                                    <span class="font-medium"><?php echo $total_records; ?></span> 
                                    results
                                    <?php if (!empty(array_filter([$filter_status, $filter_customer, $filter_service_type, $filter_payment_method, $filter_date_from, $filter_date_to, $filter_amount_min, $filter_amount_max]))): ?>
                                        <span class="text-gray-500">(filtered)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-2">
                                    <!-- Page size selector -->
                                    <div class="flex items-center gap-2 mr-4">
                                        <span class="text-sm text-gray-700">Show:</span>
                                        <select id="pageSize" onchange="changePageSize(this.value)" class="text-sm border border-gray-300 rounded-md px-2 py-1 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                            <option value="15" <?php echo $limit == 15 ? 'selected' : ''; ?>>15</option>
                                            <option value="30" <?php echo $limit == 30 ? 'selected' : ''; ?>>30</option>
                                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                                        </select>
                                        <span class="text-sm text-gray-500">per page</span>
                                    </div>
                                    
                                    <!-- Previous Button -->
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo ($page - 1); ?><?php echo buildFilterQueryString(); ?>" 
                                           class="px-3 py-1 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-1">
                                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                            Previous
                                        </a>
                                    <?php else: ?>
                                        <span class="px-3 py-1 text-sm border border-gray-300 rounded-lg text-gray-400 cursor-not-allowed flex items-center gap-1">
                                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
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
                                        echo '<a href="?page=1' . buildFilterQueryString() . '" class="px-3 py-1 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">1</a>';
                                        if ($start_page > 2) {
                                            echo '<span class="px-2 text-gray-400">...</span>';
                                        }
                                    }
                                    
                                    // Show page numbers
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        if ($i == $page) {
                                            echo '<span class="px-3 py-1 text-sm bg-blue-600 text-white rounded-lg font-medium">' . $i . '</span>';
                                        } else {
                                            echo '<a href="?page=' . $i . buildFilterQueryString() . '" class="px-3 py-1 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">' . $i . '</a>';
                                        }
                                    }
                                    
                                    // Show last page if not in range
                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) {
                                            echo '<span class="px-2 text-gray-400">...</span>';
                                        }
                                        echo '<a href="?page=' . $total_pages . buildFilterQueryString() . '" class="px-3 py-1 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">' . $total_pages . '</a>';
                                    }
                                    ?>
                                    
                                    <!-- Next Button -->
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo ($page + 1); ?><?php echo buildFilterQueryString(); ?>" 
                                           class="px-3 py-1 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-1">
                                            Next
                                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="px-3 py-1 text-sm border border-gray-300 rounded-lg text-gray-400 cursor-not-allowed flex items-center gap-1">
                                            Next
                                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
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
                                            <div id="modalContactInfo">
                                                <!-- Contact info will be loaded here -->
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
                                            <button onclick="confirmDelete()" class="px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2">
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
            </div>
        </main>
    </div>
  </div>

  <script>
// Global variable to store current transaction data
let currentTransaction = null;

// Initialize Lucide icons
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});

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
        
        // Update contact information
        const contactInfoDiv = document.getElementById('modalContactInfo');
        let contactInfoHTML = '';
        if (transactionData.customer_email) {
            contactInfoHTML += `<p class="text-sm text-gray-500">Email</p><p class="text-sm text-gray-800">${transactionData.customer_email}</p>`;
        }
        if (transactionData.customer_phone) {
            if (contactInfoHTML) contactInfoHTML += '<div class="mt-2"></div>';
            contactInfoHTML += `<p class="text-sm text-gray-500">Phone</p><p class="text-sm text-gray-800">${transactionData.customer_phone}</p>`;
        }
        if (!contactInfoHTML) {
            contactInfoHTML = '<p class="text-sm text-gray-500">No contact information provided</p>';
        }
        contactInfoDiv.innerHTML = contactInfoHTML;
        
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

// Mark as collected with SweetAlert
function markAsCollected() {
    if (!currentTransaction) return;
    
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
                        <i class="mr-1">ℹ️</i> This will update the status to "RECEIVED"
                    </p>
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

// Open status update modal
function openStatusModal() {
    if (!currentTransaction) return;
    
    const statusOptions = [
        { value: 'RECEIVED', label: 'RECEIVED', color: '#10b981' },
        { value: 'PAID', label: 'PAID', color: '#3b82f6' },
        { value: 'PENDING', label: 'PENDING', color: '#f59e0b' },
        { value: 'FOR COMPLIANCE', label: 'FOR COMPLIANCE', color: '#8b5cf6' },
        { value: 'OVERDUE', label: 'OVERDUE', color: '#ef4444' },
        { value: 'REFUND', label: 'REFUND', color: '#8b5cf6' },
        { value: 'FOR APPROVAL', label: 'FOR APPROVAL', color: '#f59e0b' },
        { value: 'FAILED', label: 'FAILED', color: '#ef4444' }
    ];
    
    let statusOptionsHTML = '';
    statusOptions.forEach(({ value, label, color }) => {
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
        'RECEIVED': 'RECEIVED',
        'PAID': 'PAID',
        'PENDING': 'PENDING',
        'FOR COMPLIANCE': 'FOR COMPLIANCE',
        'OVERDUE': 'OVERDUE',
        'REFUND': 'REFUND',
        'FOR APPROVAL': 'FOR APPROVAL',
        'FAILED': 'FAILED'
    };
    return statusMap[status.toUpperCase()] || status.toUpperCase();
}

// Get status class
function getStatusClass(status) {
    const statusUpper = status.toUpperCase();
    if (statusUpper === 'RECEIVED') return 'bg-green-100 text-green-800';
    if (statusUpper === 'PAID') return 'bg-blue-100 text-blue-800';
    if (statusUpper === 'PENDING') return 'bg-amber-100 text-amber-800';
    if (statusUpper === 'FOR COMPLIANCE') return 'bg-purple-100 text-purple-800';
    if (statusUpper === 'OVERDUE') return 'bg-red-100 text-red-800';
    if (statusUpper === 'REFUND') return 'bg-indigo-100 text-indigo-800';
    if (statusUpper === 'FOR APPROVAL') return 'bg-yellow-100 text-yellow-800';
    if (statusUpper === 'FAILED') return 'bg-red-100 text-red-800';
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
                        <p class="mt-2 text-sm text-gray-600">Status updated to "FOR RECEIVABLE APPROVAL"</p>
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
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update_status',
                transaction_id: currentTransaction.id,
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
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete',
                transaction_id: currentTransaction.id,
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
        const statusUpper = status.toUpperCase();
        if (statusUpper === 'RECEIVED') return 'background-color: #d1fae5; color: #065f46;';
        if (statusUpper === 'PAID') return 'background-color: #dbeafe; color: #1e40af;';
        if (statusUpper === 'PENDING') return 'background-color: #fef3c7; color: #92400e;';
        if (statusUpper === 'FOR COMPLIANCE') return 'background-color: #f3e8ff; color: #6b21a8;';
        if (statusUpper === 'OVERDUE') return 'background-color: #fee2e2; color: #991b1b;';
        if (statusUpper === 'REFUND') return 'background-color: #e0e7ff; color: #3730a3;';
        if (statusUpper === 'FOR APPROVAL') return 'background-color: #fef3c7; color: #92400e;';
        if (statusUpper === 'FAILED') return 'background-color: #fee2e2; color: #991b1b;';
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

// Export to Excel function
function exportToExcel() {
    // Show loading indicator
    Swal.fire({
        title: 'Preparing Excel Export...',
        text: 'Please wait while we prepare your data for download',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Build query string with current filters
    const queryParams = new URLSearchParams(window.location.search);
    queryParams.set('export', 'excel');
    
    // Create a hidden form to submit the export request
    const form = document.createElement('form');
    form.method = 'GET';
    form.action = 'API/export_collections.php';
    form.target = '_blank';
    
    // Add all query parameters to the form
    queryParams.forEach((value, key) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    });
    
    // Add the form to the page and submit it
    document.body.appendChild(form);
    form.submit();
    
    // Remove the form after submission
    document.body.removeChild(form);
    
    // Close the loading indicator after a short delay
    setTimeout(() => {
        Swal.close();
        
        // Show success notification
        showNotification('Excel export started. Your download should begin shortly.', 'success');
    }, 1000);
}

// Refresh data function
function refreshData() {
    Swal.fire({
        title: 'Refreshing Data...',
        text: 'Please wait while we refresh the data',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    setTimeout(() => {
        window.location.reload();
    }, 500);
}

// Change page size
function changePageSize(size) {
    const queryParams = new URLSearchParams(window.location.search);
    queryParams.set('limit', size);
    queryParams.delete('page'); // Go to first page when changing page size
    
    // Show loading indicator
    Swal.fire({
        title: 'Changing Page Size...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Redirect to new page with updated limit
    setTimeout(() => {
        window.location.href = '?' + queryParams.toString();
    }, 300);
}

// Notification function
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg text-white z-50 transform transition-transform duration-300 ${
        type === 'success' ? 'bg-green-500' :
        type === 'error' ? 'bg-red-500' :
        'bg-blue-500'
    }`;
    
    notification.innerHTML = `
        <div class="flex items-center gap-2">
            <i data-lucide="${type === 'success' ? 'check-circle' : type === 'error' ? 'alert-circle' : 'info'}" class="w-5 h-5"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    lucide.createIcons({ icons: notification.querySelectorAll('i') });
    
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
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

// Initialize DataTable
document.addEventListener('DOMContentLoaded', function() {
    $('#collectionsTable').DataTable({
        paging: false,
        searching: false,
        info: false,
        ordering: true,
        order: [[5, 'desc']], // Default sort by due date
        language: {
            emptyTable: "No collections found."
        }
    });
});
</script>
</body>
</html>

<?php
// Helper function to build filter query string for pagination links
function buildFilterQueryString() {
    $queryParams = [];
    
    $filters = [
        'status' => $_GET['status'] ?? '',
        'customer' => $_GET['customer'] ?? '',
        'service_type' => $_GET['service_type'] ?? '',
        'payment_method' => $_GET['payment_method'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'amount_min' => $_GET['amount_min'] ?? '',
        'amount_max' => $_GET['amount_max'] ?? '',
        'limit' => $_GET['limit'] ?? '15'
    ];
    
    foreach ($filters as $key => $value) {
        if (!empty($value)) {
            $queryParams[] = $key . '=' . urlencode($value);
        }
    }
    
    return !empty($queryParams) ? '&' . implode('&', $queryParams) : '';
}
?>