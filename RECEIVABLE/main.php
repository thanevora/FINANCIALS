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

// Fetch AR data from database using API-like structure
$total_receivables = 0;
$overdue_amount = 0;
$due_this_week = 0;
$collection_rate = 0;
$avg_collection_period = 0;
$customer_count = 0;
$invoice_count = 0;
$paid_invoices = 0;
$pending_invoices = 0;
$overdue_invoices = 0;
$disputed_amount = 0;
$credit_balance = 0;
$disputed_invoices = 0;

// Query for total receivables
$sql = "SELECT SUM(balance) as total FROM accounts_receivable WHERE status IN ('pending', 'overdue')";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $total_receivables = $row['total'] ?? 0;
}

// Query for overdue amount
$sql = "SELECT SUM(balance) as total FROM accounts_receivable WHERE status = 'overdue'";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $overdue_amount = $row['total'] ?? 0;
}

// Query for due this week
$current_week_start = date('Y-m-d', strtotime('this week'));
$current_week_end = date('Y-m-d', strtotime('this week +6 days'));
$sql = "SELECT SUM(balance) as total FROM accounts_receivable WHERE due_date BETWEEN '$current_week_start' AND '$current_week_end' AND status = 'pending'";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $due_this_week = $row['total'] ?? 0;
}

// Query for collection rate
$sql = "SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count
        FROM accounts_receivable";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $paid_count = $row['paid_count'] ?? 0;
    $total_count = $row['total_count'] ?? 1;
    $collection_rate = $total_count > 0 ? round(($paid_count / $total_count) * 100, 1) : 0;
}

// Query for average collection period
$sql = "SELECT AVG(DATEDIFF(payment_date, invoice_date)) as avg_days 
        FROM accounts_receivable 
        WHERE status = 'paid' AND payment_date IS NOT NULL";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $avg_collection_period = round($row['avg_days'] ?? 0);
}

// Query for customer count
$sql = "SELECT COUNT(DISTINCT customer_name) as customer_count FROM accounts_receivable";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $customer_count = $row['customer_count'] ?? 0;
}

// Query for invoice counts
$sql = "SELECT 
        COUNT(*) as total_invoices,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
        SUM(CASE WHEN status = 'disputed' THEN 1 ELSE 0 END) as disputed_count
        FROM accounts_receivable";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $invoice_count = $row['total_invoices'] ?? 0;
    $paid_invoices = $row['paid_count'] ?? 0;
    $pending_invoices = $row['pending_count'] ?? 0;
    $overdue_invoices = $row['overdue_count'] ?? 0;
    $disputed_invoices = $row['disputed_count'] ?? 0;
}

// Query for disputed amount
$sql = "SELECT SUM(balance) as total FROM accounts_receivable WHERE status = 'disputed'";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $disputed_amount = $row['total'] ?? 0;
}

// Query for credit balance
$sql = "SELECT SUM(credit_balance) as total FROM accounts_receivable";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $credit_balance = $row['total'] ?? 0;
}

// Fetch invoices for the table
$invoices = [];
$sql = "SELECT * FROM accounts_receivable ORDER BY due_date DESC LIMIT 50";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $invoices[] = $row;
    }
}

// Fetch aging analysis data
$aging_data = [
    'current' => ['amount' => 0, 'count' => 0],
    '1_30_days' => ['amount' => 0, 'count' => 0],
    '31_60_days' => ['amount' => 0, 'count' => 0],
    '60_plus_days' => ['amount' => 0, 'count' => 0]
];

// Current
$sql_current = "SELECT COALESCE(SUM(balance), 0) as amount, COUNT(*) as count 
                FROM accounts_receivable 
                WHERE status IN ('pending', 'overdue') 
                AND DATEDIFF(CURDATE(), due_date) <= 0";
$result = mysqli_query($conn, $sql_current);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $aging_data['current']['amount'] = $row['amount'];
    $aging_data['current']['count'] = $row['count'];
}

// 1-30 Days
$sql_1_30 = "SELECT COALESCE(SUM(balance), 0) as amount, COUNT(*) as count 
             FROM accounts_receivable 
             WHERE status IN ('pending', 'overdue') 
             AND DATEDIFF(CURDATE(), due_date) BETWEEN 1 AND 30";
$result = mysqli_query($conn, $sql_1_30);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $aging_data['1_30_days']['amount'] = $row['amount'];
    $aging_data['1_30_days']['count'] = $row['count'];
}

// 31-60 Days
$sql_31_60 = "SELECT COALESCE(SUM(balance), 0) as amount, COUNT(*) as count 
              FROM accounts_receivable 
              WHERE status IN ('pending', 'overdue') 
              AND DATEDIFF(CURDATE(), due_date) BETWEEN 31 AND 60";
$result = mysqli_query($conn, $sql_31_60);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $aging_data['31_60_days']['amount'] = $row['amount'];
    $aging_data['31_60_days']['count'] = $row['count'];
}

// 60+ Days
$sql_60_plus = "SELECT COALESCE(SUM(balance), 0) as amount, COUNT(*) as count 
                FROM accounts_receivable 
                WHERE status IN ('pending', 'overdue') 
                AND DATEDIFF(CURDATE(), due_date) > 60";
$result = mysqli_query($conn, $sql_60_plus);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $aging_data['60_plus_days']['amount'] = $row['amount'];
    $aging_data['60_plus_days']['count'] = $row['count'];
}

// Calculate percentages for progress bars
$total_aging_amount = array_sum(array_column($aging_data, 'amount'));
foreach ($aging_data as $category => $data) {
    $aging_data[$category]['percentage'] = $total_aging_amount > 0 ? 
        round(($data['amount'] / $total_aging_amount) * 100) : 0;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | System Name</title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Accounts Receivable</h1>
                        <p class="text-gray-600 mt-2">Manage and track all incoming payments from customers and clients</p>
                    </div>

                    <!-- AR Summary Cards -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                                <span class="p-2 mr-3 rounded-lg bg-green-100/50 text-green-600">
                                    <i data-lucide="trending-up" class="w-5 h-5"></i>
                                </span>
                                Receivables Overview
                            </h2>
                            
                        </div>

                        <!-- AR Dashboard Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4 h-full">
                            
                            <!-- Total Receivables Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Total Receivables</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            ₱<?php echo number_format($total_receivables, 2); ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full" style="background:#F7B32B1A; color:#F7B32B;">
                                        <i data-lucide="dollar-sign" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full" style="background:#F7B32B; width: <?php echo $invoice_count > 0 ? round(($pending_invoices + $overdue_invoices) / $invoice_count * 100) : 0; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Active invoices</span>
                                        <span class="font-medium"><?php echo $pending_invoices + $overdue_invoices; ?> invoices</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Overdue Amount Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Overdue Amount</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            ₱<?php echo number_format($overdue_amount, 2); ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                                        <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-red-500 rounded-full" style="width: <?php echo $total_receivables > 0 ? round($overdue_amount / $total_receivables * 100) : 0; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span><?php echo $overdue_invoices; ?> overdue invoices</span>
                                        <span class="font-medium"><?php echo $total_receivables > 0 ? round($overdue_amount / $total_receivables * 100) : 0; ?>% of total</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Due This Week Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Due This Week</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            ₱<?php echo number_format($due_this_week, 2); ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                                        <i data-lucide="calendar" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo $total_receivables > 0 ? round($due_this_week / $total_receivables * 100) : 0; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Due in 7 days</span>
                                        <span class="font-medium"><?php echo $total_receivables > 0 ? round($due_this_week / $total_receivables * 100) : 0; ?>% of total</span>
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
                                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                                        <i data-lucide="trending-up" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $collection_rate; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Success rate</span>
                                        <span class="font-medium"><?php echo $paid_invoices; ?> of <?php echo $invoice_count; ?> invoices</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Average Collection Period Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Avg. Collection Period</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            <?php echo $avg_collection_period; ?> days
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                        <i data-lucide="calendar" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-blue-500 rounded-full" style="width: <?php echo min($avg_collection_period, 100); ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Days to collect</span>
                                        <span class="font-medium">Industry avg: 45 days</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Customer Count Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Active Customers</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            <?php echo $customer_count; ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                        <i data-lucide="users" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-purple-500 rounded-full" style="width: <?php echo min($customer_count * 5, 100); ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Total customers</span>
                                        <span class="font-medium">With open invoices</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Total Invoices Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Total Invoices</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            <?php echo $invoice_count; ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                                        <i data-lucide="file-text" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-indigo-500 rounded-full" style="width: <?php echo min($invoice_count * 2, 100); ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>All time</span>
                                        <span class="font-medium">This fiscal year</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Paid Invoices Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Paid Invoices</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            <?php echo $paid_invoices; ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                                        <i data-lucide="check-circle" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $invoice_count > 0 ? round($paid_invoices / $invoice_count * 100) : 0; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Completed</span>
                                        <span class="font-medium"><?php echo $invoice_count > 0 ? round($paid_invoices / $invoice_count * 100) : 0; ?>% of total</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Pending Invoices Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Pending Invoices</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            <?php echo $pending_invoices; ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                                        <i data-lucide="clock" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo $invoice_count > 0 ? round($pending_invoices / $invoice_count * 100) : 0; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Awaiting payment</span>
                                        <span class="font-medium"><?php echo $invoice_count > 0 ? round($pending_invoices / $invoice_count * 100) : 0; ?>% of total</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Disputed Amount Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Disputed Amount</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            ₱<?php echo number_format($disputed_amount, 2); ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                                        <i data-lucide="alert-octagon" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-orange-500 rounded-full" style="width: <?php echo $total_receivables > 0 ? round($disputed_amount / $total_receivables * 100) : 0; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span><?php echo $disputed_invoices; ?> disputed invoices</span>
                                        <span class="font-medium">Requires attention</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                  
                            <!-- Combined Invoices & Collections Table -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm">
                        <div class="mb-6">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-4">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-800">Invoices & Collections</h3>
                                    <p class="text-gray-600">Manage invoices and review pending collections in one place.</p>
                                </div>
                                <div class="flex gap-2">
                                    <button id="showInvoicesBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                        Invoices (<?php echo $invoice_count; ?>)
                                    </button>
                                    <button id="showCollectionsBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                        Pending Collections
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Invoices Table (Default View) -->
                        <div id="invoicesTable" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference #</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issue Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (count($invoices) > 0): ?>
                                            <?php foreach ($invoices as $invoice): ?>
                                                <tr class="hover:bg-gray-50 transition-colors">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            <i data-lucide="file-text" class="w-3 h-3 mr-1"></i>
                                                            Invoice
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <div class="font-medium"><?php echo htmlspecialchars($invoice['customer_name']); ?></div>
                                                        <?php if ($invoice['customer_email']): ?>
                                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($invoice['customer_email']); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                                        ₱<?php echo number_format($invoice['amount'], 2); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold <?php echo $invoice['balance'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                                        ₱<?php echo number_format($invoice['balance'], 2); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php 
                                                        $due_date = date('M d, Y', strtotime($invoice['due_date']));
                                                        $today = new DateTime();
                                                        $due = new DateTime($invoice['due_date']);
                                                        $interval = $today->diff($due);
                                                        $days_diff = $interval->format('%R%a');
                                                        
                                                        if ($days_diff < 0 && $invoice['status'] != 'paid') {
                                                            echo '<span class="text-red-600 font-medium">' . $due_date . '</span>';
                                                        } else {
                                                            echo $due_date;
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                        $status_class = '';
                                                        $status_text = '';
                                                        $status_icon = '';
                                                        switch ($invoice['status']) {
                                                            case 'paid':
                                                                $status_class = 'bg-green-100 text-green-800';
                                                                $status_text = 'Paid';
                                                                $status_icon = 'check-circle';
                                                                break;
                                                            case 'pending':
                                                                $status_class = 'bg-amber-100 text-amber-800';
                                                                $status_text = 'Pending';
                                                                $status_icon = 'clock';
                                                                break;
                                                            case 'overdue':
                                                                $status_class = 'bg-red-100 text-red-800';
                                                                $status_text = 'Overdue';
                                                                $status_icon = 'alert-triangle';
                                                                break;
                                                            case 'disputed':
                                                                $status_class = 'bg-orange-100 text-orange-800';
                                                                $status_text = 'Disputed';
                                                                $status_icon = 'alert-octagon';
                                                                break;
                                                            default:
                                                                $status_class = 'bg-gray-100 text-gray-800';
                                                                $status_text = ucfirst($invoice['status']);
                                                                $status_icon = 'file-text';
                                                        }
                                                        ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?php echo $status_class; ?>">
                                                            <i data-lucide="<?php echo $status_icon; ?>" class="w-3 h-3 mr-1"></i>
                                                            <?php echo $status_text; ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div class="flex items-center gap-2">
                                                            <button onclick="viewInvoice(<?php echo $invoice['id']; ?>)" 
                                                                    class="flex items-center gap-1 text-blue-600 hover:text-blue-900 px-2 py-1 rounded hover:bg-blue-50">
                                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                                                View
                                                            </button>
                                                            <?php if ($invoice['status'] == 'pending'): ?>
                                                                <button onclick="sendReminder(<?php echo $invoice['id']; ?>)" 
                                                                        class="flex items-center gap-1 text-green-600 hover:text-green-900 px-2 py-1 rounded hover:bg-green-50">
                                                                    <i data-lucide="bell" class="w-4 h-4"></i>
                                                                    Remind
                                                                </button>
                                                            <?php elseif ($invoice['status'] == 'overdue'): ?>
                                                                <button onclick="escalateInvoice(<?php echo $invoice['id']; ?>)" 
                                                                        class="flex items-center gap-1 text-red-600 hover:text-red-900 px-2 py-1 rounded hover:bg-red-50">
                                                                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                                                    Escalate
                                                                </button>
                                                            <?php elseif ($invoice['status'] == 'paid'): ?>
                                                                <button onclick="downloadReceipt(<?php echo $invoice['id']; ?>)" 
                                                                        class="flex items-center gap-1 text-green-600 hover:text-green-900 px-2 py-1 rounded hover:bg-green-50">
                                                                    <i data-lucide="download" class="w-4 h-4"></i>
                                                                    Receipt
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="px-6 py-8 text-center">
                                                    <div class="flex flex-col items-center justify-center">
                                                        <i data-lucide="file-text" class="w-12 h-12 text-gray-400 mb-3"></i>
                                                        <p class="text-gray-500">No invoices found.</p>
                                                        <p class="text-sm text-gray-400 mt-1">Create your first invoice to get started.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pending Collections Table (Hidden by Default) -->
                        <div id="collectionsTable" class="bg-white rounded-xl border border-gray-200 overflow-hidden" style="display: none;">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service Type</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Collection Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pendingCollectionsBody" class="bg-white divide-y divide-gray-200">
                                        <!-- Data will be loaded via JavaScript -->
                                        <tr id="collectionsLoadingRow">
                                            <td colspan="9" class="px-6 py-8 text-center">
                                                <div class="flex flex-col items-center justify-center">
                                                    <i data-lucide="loader-2" class="w-8 h-8 text-gray-400 mb-3 animate-spin"></i>
                                                    <p class="text-gray-500">Loading pending collections...</p>
                                                    <p class="text-sm text-gray-400 mt-1">Please wait while we fetch the data.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <script>
// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
    const invoicesBtn = document.getElementById('showInvoicesBtn');
    const collectionsBtn = document.getElementById('showCollectionsBtn');
    const invoicesTable = document.getElementById('invoicesTable');
    const collectionsTable = document.getElementById('collectionsTable');
    
    // Default state - show invoices
    invoicesBtn.classList.add('bg-blue-600', 'text-white');
    invoicesBtn.classList.remove('bg-gray-200', 'text-gray-700');
    collectionsBtn.classList.add('bg-gray-200', 'text-gray-700');
    collectionsBtn.classList.remove('bg-blue-600', 'text-white');
    
    invoicesTable.style.display = 'block';
    collectionsTable.style.display = 'none';
    
    // Load collections data initially
    loadPendingCollections();
    
    // Switch to invoices tab
    invoicesBtn.addEventListener('click', function() {
        // Update button styles
        invoicesBtn.classList.add('bg-blue-600', 'text-white');
        invoicesBtn.classList.remove('bg-gray-200', 'text-gray-700');
        collectionsBtn.classList.add('bg-gray-200', 'text-gray-700');
        collectionsBtn.classList.remove('bg-blue-600', 'text-white');
        
        // Show/hide tables
        invoicesTable.style.display = 'block';
        collectionsTable.style.display = 'none';
    });
    
    // Switch to collections tab
    collectionsBtn.addEventListener('click', function() {
        // Update button styles
        collectionsBtn.classList.add('bg-blue-600', 'text-white');
        collectionsBtn.classList.remove('bg-gray-200', 'text-gray-700');
        invoicesBtn.classList.add('bg-gray-200', 'text-gray-700');
        invoicesBtn.classList.remove('bg-blue-600', 'text-white');
        
        // Show/hide tables
        collectionsTable.style.display = 'block';
        invoicesTable.style.display = 'none';
        
        // Refresh collections data
        loadPendingCollections();
    });
});

// Updated loadPendingCollections function with better UI
async function loadPendingCollections() {
    try {
        const response = await fetch('API/collections_api.php?action=get_pending_collections');
        const result = await response.json();
        
        const tableBody = document.getElementById('pendingCollectionsTable');
        const loadingRow = document.getElementById('loadingRow');
        
        if (loadingRow) {
            loadingRow.remove();
        }
        
        if (result.status === 'success' && result.data.length > 0) {
            tableBody.innerHTML = '';
            
            result.data.forEach(collection => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50 transition-colors';
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                            <i data-lucide="dollar-sign" class="w-3 h-3 mr-1"></i>
                            Collection
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        ${collection.request_id}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div class="font-medium">${collection.customer_name}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                        ₱${parseFloat(collection.amount).toFixed(2)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${collection.service_type}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <span class="inline-flex items-center gap-1">
                            <i data-lucide="${getPaymentMethodIcon(collection.payment_method)}" class="w-3 h-3"></i>
                            ${collection.payment_method}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${collection.collection_date ? new Date(collection.collection_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${new Date(collection.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                            <i data-lucide="clock" class="w-3 h-3 mr-1"></i>
                            Pending
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="viewCollection(${collection.id})" 
                                class="flex items-center gap-1 text-blue-600 hover:text-blue-900 px-2 py-1 rounded hover:bg-blue-50">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                            Review
                        </button>
                    </td>
                `;
                tableBody.appendChild(row);
            });
            
            // Update collections button count
            const collectionsBtn = document.getElementById('showCollectionsBtn');
            collectionsBtn.innerHTML = `Pending Collections (${result.data.length})`;
            
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="10" class="px-6 py-8 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <i data-lucide="check-circle" class="w-12 h-12 text-green-400 mb-3"></i>
                            <p class="text-gray-500">No pending collections</p>
                            <p class="text-sm text-gray-400 mt-1">All collections have been processed.</p>
                        </div>
                    </td>
                </tr>
            `;
            
            // Update collections button count
            const collectionsBtn = document.getElementById('showCollectionsBtn');
            collectionsBtn.innerHTML = 'Pending Collections (0)';
        }
        
        // Reinitialize icons
        lucide.createIcons();
        
    } catch (error) {
        console.error('Error loading pending collections:', error);
        const tableBody = document.getElementById('pendingCollectionsTable');
        
        tableBody.innerHTML = `
            <tr>
                <td colspan="10" class="px-6 py-8 text-center">
                    <div class="flex flex-col items-center justify-center">
                        <i data-lucide="alert-circle" class="w-12 h-12 text-red-400 mb-3"></i>
                        <p class="text-gray-500">Error loading collections</p>
                        <p class="text-sm text-gray-400 mt-1">Please try refreshing the page.</p>
                        <button onclick="loadPendingCollections()" 
                                class="mt-3 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                            Retry
                        </button>
                    </div>
                </td>
            </tr>
        `;
        
        // Reinitialize icons
        lucide.createIcons();
    }
}

// Helper function to get payment method icon
function getPaymentMethodIcon(method) {
    switch(method.toLowerCase()) {
        case 'cash':
            return 'dollar-sign';
        case 'bank_transfer':
            return 'banknote';
        case 'credit_card':
            return 'credit-card';
        case 'check':
            return 'file-text';
        case 'online':
            return 'globe';
        default:
            return 'dollar-sign';
    }
}
</script>




    <script>
        // Global variables
let currentCollectionId = null;
let currentCollectionData = null;

// Load pending collections on page load
document.addEventListener('DOMContentLoaded', function() {
    loadPendingCollections();
});

// Load pending collections from API
async function loadPendingCollections() {
    try {
        const response = await fetch('API/collections_api.php?action=get_pending_collections');
        const result = await response.json();
        
        const tableBody = document.getElementById('pendingCollectionsTable');
        const loadingRow = document.getElementById('loadingRow');
        
        if (loadingRow) {
            loadingRow.remove();
        }
        
        if (result.status === 'success' && result.data.length > 0) {
            tableBody.innerHTML = '';
            
            result.data.forEach(collection => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        ${collection.request_id}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${collection.customer_name}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                        ₱${parseFloat(collection.amount).toFixed(2)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${collection.service_type}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${collection.due_date ? new Date(collection.due_date).toLocaleDateString() : 'N/A'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${collection.payment_method}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${collection.collection_date ? new Date(collection.collection_date).toLocaleDateString() : 'N/A'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${collection.status}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="viewCollection(${collection.id})" 
                                class="text-blue-600 hover:text-blue-900 flex items-center gap-1">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                            View
                        </button>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">
                        <div class="flex flex-col items-center justify-center py-4">
                            <i data-lucide="clipboard-check" class="w-12 h-12 text-gray-400 mb-2"></i>
                            <p>No pending collections found.</p>
                            <p class="text-xs text-gray-500">All collections have been processed.</p>
                        </div>
                    </td>
                </tr>
            `;
        }
    } catch (error) {
        console.error('Error loading pending collections:', error);
        const tableBody = document.getElementById('pendingCollectionsTable');
        const loadingRow = document.getElementById('loadingRow');
        
        if (loadingRow) {
            loadingRow.innerHTML = `
                <td colspan="9" class="px-6 py-4 text-center text-sm text-red-500">
                    <div class="flex items-center justify-center">
                        <i data-lucide="alert-circle" class="w-4 h-4 mr-2"></i>
                        Error loading data. Please try again.
                    </div>
                </td>
            `;
        }
    }
}

// View collection details
async function viewCollection(collectionId) {
    try {
        currentCollectionId = collectionId;
        
        const response = await fetch(`API/collections_api.php?action=get_collection_details&id=${collectionId}`);
        const result = await response.json();
        
        if (result.status === 'success') {
            currentCollectionData = result.data;
            displayCollectionDetails(result.data);
            openModal('viewCollectionModal');
        } else {
            showNotification('Failed to load collection details', 'error');
        }
    } catch (error) {
        showNotification('Network error: ' + error.message, 'error');
    }
}

// Display collection details in modal
function displayCollectionDetails(collection) {
    const detailsContainer = document.getElementById('collectionDetails');
    
    // Format dates
    const createdDate = new Date(collection.created_at).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    const updatedDate = collection.updated_at ? 
        new Date(collection.updated_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }) : 
        createdDate;
    
    detailsContainer.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
            
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Request ID</label>
                    <p class="text-lg font-semibold text-gray-900">${collection.request_id}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Customer Name</label>
                    <p class="text-gray-900">${collection.customer_name}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                    <p class="text-gray-900">${collection.service_type}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                    <p class="text-gray-900">${collection.payment_method}</p>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                        ${collection.status}
                    </span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <p class="text-2xl font-bold text-gray-900">₱${parseFloat(collection.amount).toFixed(2)}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                    <p class="text-gray-900">${collection.due_date ? new Date(collection.due_date).toLocaleDateString() : 'N/A'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Collection Date</label>
                    <p class="text-gray-900">${collection.collection_date ? new Date(collection.collection_date).toLocaleDateString() : 'N/A'}</p>
                </div>
            </div>
        </div>
        <div class="mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <p class="text-gray-900 bg-gray-50 p-3 rounded-lg">${collection.description || 'No description provided'}</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Created At</label>
                <p class="text-gray-900">${createdDate}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Last Updated</label>
                <p class="text-gray-900">${updatedDate}</p>
            </div>
        </div>
        ${collection.notes ? `
        <div class="mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <p class="text-gray-900 bg-yellow-50 p-3 rounded-lg">${collection.notes}</p>
        </div>
        ` : ''}
    `;
}

// Mark collection as Received
function markCollectionAsReceived() {
    if (!currentCollectionId) return;
    
    Swal.fire({
        title: 'Mark as Received',
        html: `
            <div class="text-left">
                <p class="mb-2">Customer: <strong>${currentCollectionData.customer_name}</strong></p>
                <p class="mb-4">Amount: <strong>₱${parseFloat(currentCollectionData.amount).toFixed(2)}</strong></p>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Add Notes (Optional)</label>
                    <textarea id="receiveNotes" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" rows="3" placeholder="Add any notes about this collection..."></textarea>
                </div>
                <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-md">
                    <p class="text-sm text-green-800 flex items-center gap-2">
                        <i data-lucide="info" class="w-4 h-4"></i>
                        This will create an invoice in Accounts Receivable
                    </p>
                </div>
            </div>
        `,
        icon: 'success',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Mark as Received',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            return {
                notes: document.getElementById('receiveNotes').value
            };
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            const notes = result.value.notes;
            
            try {
                const response = await fetch('API/collections_api.php?action=update_collection_status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        id: currentCollectionId,
                        status: 'RECEIVED',
                        notes: notes
                    })
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Collection marked as received and added to accounts receivable.',
                        icon: 'success',
                        confirmButtonColor: '#16a34a',
                    }).then(() => {
                        closeModal('viewCollectionModal');
                        loadPendingCollections();
                        // Reload the page to update AR statistics
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', result.message || 'Failed to update status', 'error');
                }
            } catch (error) {
                Swal.fire('Error!', 'Network error: ' + error.message, 'error');
            }
        }
    });
}

// Mark collection for Compliance
function markCollectionForCompliance() {
    if (!currentCollectionId) return;
    
    Swal.fire({
        title: 'Mark for Compliance',
        html: `
            <div class="text-left">
                <p class="mb-2">Customer: <strong>${currentCollectionData.customer_name}</strong></p>
                <p class="mb-4">Amount: <strong>₱${parseFloat(currentCollectionData.amount).toFixed(2)}</strong></p>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Compliance Notes (Required)</label>
                    <textarea id="complianceNotes" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" rows="3" placeholder="Specify compliance requirements..."></textarea>
                </div>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#7e22ce',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Mark for Compliance',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const notes = document.getElementById('complianceNotes').value;
            if (!notes.trim()) {
                Swal.showValidationMessage('Please enter compliance notes');
                return false;
            }
            return {
                notes: notes
            };
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            const notes = result.value.notes;
            
            try {
                const response = await fetch('API/collections_api.php?action=update_collection_status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        id: currentCollectionId,
                        status: 'FOR COMPLIANCE',
                        notes: notes
                    })
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Collection marked for compliance review.',
                        icon: 'success',
                        confirmButtonColor: '#7e22ce',
                    }).then(() => {
                        closeModal('viewCollectionModal');
                        loadPendingCollections();
                    });
                } else {
                    Swal.fire('Error!', result.message || 'Failed to update status', 'error');
                }
            } catch (error) {
                Swal.fire('Error!', 'Network error: ' + error.message, 'error');
            }
        }
    });
}

// Reject collection
function rejectCollection() {
    if (!currentCollectionId) return;
    
    Swal.fire({
        title: 'Reject Collection',
        html: `
            <div class="text-left">
                <p class="mb-2">Customer: <strong>${currentCollectionData.customer_name}</strong></p>
                <p class="mb-4">Amount: <strong>₱${parseFloat(currentCollectionData.amount).toFixed(2)}</strong></p>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason (Required)</label>
                    <textarea id="rejectNotes" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" rows="3" placeholder="Specify reason for rejection..."></textarea>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Reject Collection',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const notes = document.getElementById('rejectNotes').value;
            if (!notes.trim()) {
                Swal.showValidationMessage('Please enter rejection reason');
                return false;
            }
            return {
                notes: notes
            };
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            const notes = result.value.notes;
            
            try {
                const response = await fetch('API/collections_api.php?action=update_collection_status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        id: currentCollectionId,
                        status: 'REJECTED',
                        notes: notes
                    })
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    Swal.fire({
                        title: 'Rejected!',
                        text: 'Collection has been rejected.',
                        icon: 'success',
                        confirmButtonColor: '#dc2626',
                    }).then(() => {
                        closeModal('viewCollectionModal');
                        loadPendingCollections();
                    });
                } else {
                    Swal.fire('Error!', result.message || 'Failed to reject collection', 'error');
                }
            } catch (error) {
                Swal.fire('Error!', 'Network error: ' + error.message, 'error');
            }
        }
    });
}

// Add auto-refresh for pending collections
setInterval(() => {
    loadPendingCollections();
}, 30000); // Refresh every 30 seconds
    </script>



    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Global variable to store current invoice ID
        let currentInvoiceId = null;

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

        // Form submission handling with API integration
        document.getElementById('invoiceForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            
            try {
                submitButton.textContent = 'Creating...';
                submitButton.disabled = true;
                
                const response = await fetch('API/accounts_receivable_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    showNotification('Invoice created successfully!', 'success');
                    closeModal('newInvoiceModal');
                    this.reset();
                    // Reload page to show updated data
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message || 'Failed to create invoice', 'error');
                }
            } catch (error) {
                showNotification('Network error: ' + error.message, 'error');
            } finally {
                submitButton.textContent = originalText;
                submitButton.disabled = false;
            }
        });

        document.getElementById('paymentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            
            try {
                submitButton.textContent = 'Recording...';
                submitButton.disabled = true;
                
                const response = await fetch('API/accounts_receivable_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    showNotification('Payment recorded successfully!', 'success');
                    closeModal('paymentReceiptModal');
                    this.reset();
                    // Reload page to show updated data
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message || 'Failed to record payment', 'error');
                }
            } catch (error) {
                showNotification('Network error: ' + error.message, 'error');
            } finally {
                submitButton.textContent = originalText;
                submitButton.disabled = false;
            }
        });

        // View invoice details
        async function viewInvoice(invoiceId) {
            try {
                const response = await fetch(`API/accounts_receivable_api.php?action=get_invoice&id=${invoiceId}`);
                const result = await response.json();
                
                if (result.status === 'success') {
                    currentInvoiceId = invoiceId;
                    displayInvoiceDetails(result.data);
                    openModal('viewInvoiceModal');
                } else {
                    showNotification('Failed to load invoice details', 'error');
                }
            } catch (error) {
                showNotification('Network error: ' + error.message, 'error');
            }
        }

        // Display invoice details in modal
        function displayInvoiceDetails(invoice) {
            const detailsContainer = document.getElementById('invoiceDetails');
            const statusColors = {
                'paid': 'green',
                'pending': 'amber',
                'overdue': 'red',
                'disputed': 'orange'
            };
            
            const statusColor = statusColors[invoice.status] || 'gray';
            
            detailsContainer.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Number</label>
                            <p class="text-lg font-semibold text-gray-900">${invoice.invoice_number}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer Name</label>
                            <p class="text-gray-900">${invoice.customer_name}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer Email</label>
                            <p class="text-gray-900">${invoice.customer_email || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer Phone</label>
                            <p class="text-gray-900">${invoice.customer_phone || 'N/A'}</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-${statusColor}-100 text-${statusColor}-800 capitalize">
                                ${invoice.status}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                            <p class="text-gray-900">${invoice.service_type || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Date</label>
                            <p class="text-gray-900">${invoice.invoice_date}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                            <p class="text-gray-900">${invoice.due_date}</p>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                        <p class="text-2xl font-bold text-gray-900">₱${parseFloat(invoice.amount).toFixed(2)}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Balance</label>
                        <p class="text-2xl font-bold text-gray-900">₱${parseFloat(invoice.balance).toFixed(2)}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                        <p class="text-lg font-semibold text-gray-900">${invoice.payment_date || 'Not Paid'}</p>
                    </div>
                </div>
                ${invoice.notes ? `
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <p class="text-gray-900 bg-gray-50 p-3 rounded-lg">${invoice.notes}</p>
                </div>
                ` : ''}
            `;
        }

        // CRUD Operations with SweetAlert
        function deleteInvoice(invoiceId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await fetch('API/accounts_receivable_api.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'delete_invoice',
                                id: invoiceId
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.status === 'success') {
                            Swal.fire('Deleted!', 'Invoice has been deleted.', 'success');
                            closeModal('viewInvoiceModal');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            Swal.fire('Error!', result.message || 'Failed to delete invoice', 'error');
                        }
                    } catch (error) {
                        Swal.fire('Error!', 'Network error: ' + error.message, 'error');
                    }
                }
            });
        }

        function markForCompliance(invoiceId) {
            Swal.fire({
                title: 'Mark for Compliance',
                text: "This will mark the invoice for compliance review.",
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#7e22ce',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Mark for Compliance'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    // Implement compliance logic here
                    showNotification('Invoice marked for compliance', 'success');
                }
            });
        }

        function markAsReceived(invoiceId) {
            Swal.fire({
                title: 'Mark as Received',
                text: "This will mark the invoice as received.",
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Mark as Received'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    // Implement received logic here
                    showNotification('Invoice marked as received', 'success');
                }
            });
        }

        function issueInvoice(invoiceId) {
            Swal.fire({
                title: 'Issue Invoice',
                text: "This will generate a PDF invoice receipt.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Generate PDF'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    // Generate PDF - you'll need to implement this on the server side
                    window.open(`../API/generate_pdf.php?invoice_id=${invoiceId}`, '_blank');
                    showNotification('PDF invoice generated successfully', 'success');
                }
            });
        }

        // Additional action functions
        function sendReminder(invoiceId) {
            Swal.fire({
                title: 'Send Reminder',
                text: "Send payment reminder to customer?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Send Reminder'
            }).then((result) => {
                if (result.isConfirmed) {
                    showNotification('Reminder sent successfully', 'success');
                // Implement reminder logic here
                console.log('Sending reminder for invoice:', invoiceId);
                // You can add API call to send email/SMS reminder
                fetch('../API/send_reminder.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ invoice_id: invoiceId })
                });
            }
            });
        }

        function escalateInvoice(invoiceId) {
            Swal.fire({
                title: 'Escalate Invoice',
                text: "This will escalate the overdue invoice to management.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Escalate'
            }).then((result) => {
                if (result.isConfirmed) {
                    showNotification('Invoice escalated to management', 'success');
                    // Implement escalation logic here
                    console.log('Escalating invoice:', invoiceId);
                    // You can add API call to update status or send notification
                    fetch('../API/escalate_invoice.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ invoice_id: invoiceId })
                    });
                }
            });
        }

        function downloadReceipt(invoiceId) {
            // Generate receipt PDF
            window.open(`../API/generate_receipt.php?invoice_id=${invoiceId}`, '_blank');
            showNotification('Receipt downloaded successfully', 'success');
        }

        // Auto-calculate net amount
        document.querySelector('input[name="amount"]')?.addEventListener('input', calculateNetAmount);
        document.querySelector('input[name="tax_amount"]')?.addEventListener('input', calculateNetAmount);
        document.querySelector('input[name="discount_amount"]')?.addEventListener('input', calculateNetAmount);

        function calculateNetAmount() {
            const amount = parseFloat(document.querySelector('input[name="amount"]').value) || 0;
            const tax = parseFloat(document.querySelector('input[name="tax_amount"]').value) || 0;
            const discount = parseFloat(document.querySelector('input[name="discount_amount"]').value) || 0;
            
            const netAmount = amount + tax - discount;
            // You can display this somewhere or set it in a hidden field
            console.log('Net Amount:', netAmount);
        }

        // Notification function
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg text-white z-50 transform transition-transform duration-300 ${
                type === 'success' ? 'bg-green-500' :
                type === 'error' ? 'bg-red-500' :
                'bg-blue-500'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Auto-set due date to 30 days from invoice date
        document.querySelector('input[name="invoice_date"]')?.addEventListener('change', function() {
            const invoiceDate = new Date(this.value);
            if (!isNaN(invoiceDate.getTime())) {
                const dueDate = new Date(invoiceDate);
                dueDate.setDate(dueDate.getDate() + 30);
                document.querySelector('input[name="due_date"]').value = dueDate.toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>