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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

                    <!-- Navigation Tabs -->
                    <div class="mb-6">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8">
                                <a href="#" class="border-green-500 text-green-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    All Invoices
                                </a>
                                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Overdue
                                </a>
                                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Paid
                                </a>
                                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Aging Report
                                </a>
                            </nav>
                        </div>
                    </div>

                    <!-- Invoices Table -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm">
                        <div class="mb-6">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4">All Invoices</h3>
                            <p class="text-gray-600">Manage and track all customer invoices and payments.</p>
                        </div>
                        
                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issue Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service Type</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (count($invoices) > 0): ?>
                                            <?php foreach ($invoices as $invoice): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱<?php echo number_format($invoice['amount'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱<?php echo number_format($invoice['balance'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('Y-m-d', strtotime($invoice['invoice_date'])); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('Y-m-d', strtotime($invoice['due_date'])); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                        $status_class = '';
                                                        $status_text = '';
                                                        switch ($invoice['status']) {
                                                            case 'paid':
                                                                $status_class = 'bg-green-100 text-green-800';
                                                                $status_text = 'Paid';
                                                                break;
                                                            case 'pending':
                                                                $status_class = 'bg-amber-100 text-amber-800';
                                                                $status_text = 'Pending';
                                                                break;
                                                            case 'overdue':
                                                                $status_class = 'bg-red-100 text-red-800';
                                                                $status_text = 'Overdue';
                                                                break;
                                                            case 'disputed':
                                                                $status_class = 'bg-orange-100 text-orange-800';
                                                                $status_text = 'Disputed';
                                                                break;
                                                            default:
                                                                $status_class = 'bg-gray-100 text-gray-800';
                                                                $status_text = ucfirst($invoice['status']);
                                                        }
                                                        ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                                            <?php echo $status_text; ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($invoice['service_type'] ?? 'N/A'); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <button onclick="viewInvoice(<?php echo $invoice['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                                                        <?php if ($invoice['status'] == 'pending'): ?>
                                                            <button onclick="sendReminder(<?php echo $invoice['id']; ?>)" class="text-green-600 hover:text-green-900 mr-3">Remind</button>
                                                        <?php elseif ($invoice['status'] == 'overdue'): ?>
                                                            <button onclick="escalateInvoice(<?php echo $invoice['id']; ?>)" class="text-red-600 hover:text-red-900 mr-3">Escalate</button>
                                                        <?php else: ?>
                                                            <button onclick="downloadReceipt(<?php echo $invoice['id']; ?>)" class="text-green-600 hover:text-green-900 mr-3">Receipt</button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">
                                                    No invoices found.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <!-- Aging Analysis -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm mt-6">
                        <div class="mb-6">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4">Aging Analysis</h3>
                            <p class="text-gray-600">Breakdown of receivables by aging period.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Current -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-800 mb-1">Current</h4>
                                <p class="text-2xl font-bold text-green-600 mb-2">₱<?php echo number_format($aging_data['current']['amount'], 2); ?></p>
                                <p class="text-sm text-gray-500"><?php echo $aging_data['current']['count']; ?> invoices</p>
                                <div class="mt-3">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $aging_data['current']['percentage']; ?>%"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- 1-30 Days -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
                                <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i data-lucide="clock" class="w-6 h-6 text-amber-600"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-800 mb-1">1-30 Days</h4>
                                <p class="text-2xl font-bold text-amber-600 mb-2">₱<?php echo number_format($aging_data['1_30_days']['amount'], 2); ?></p>
                                <p class="text-sm text-gray-500"><?php echo $aging_data['1_30_days']['count']; ?> invoices</p>
                                <div class="mt-3">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-amber-500 h-2 rounded-full" style="width: <?php echo $aging_data['1_30_days']['percentage']; ?>%"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- 31-60 Days -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
                                <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i data-lucide="alert-circle" class="w-6 h-6 text-orange-600"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-800 mb-1">31-60 Days</h4>
                                <p class="text-2xl font-bold text-orange-600 mb-2">₱<?php echo number_format($aging_data['31_60_days']['amount'], 2); ?></p>
                                <p class="text-sm text-gray-500"><?php echo $aging_data['31_60_days']['count']; ?> invoices</p>
                                <div class="mt-3">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-orange-500 h-2 rounded-full" style="width: <?php echo $aging_data['31_60_days']['percentage']; ?>%"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- 60+ Days -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
                                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i data-lucide="alert-triangle" class="w-6 h-6 text-red-600"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-800 mb-1">60+ Days</h4>
                                <p class="text-2xl font-bold text-red-600 mb-2">₱<?php echo number_format($aging_data['60_plus_days']['amount'], 2); ?></p>
                                <p class="text-sm text-gray-500"><?php echo $aging_data['60_plus_days']['count']; ?> invoices</p>
                                <div class="mt-3">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-red-500 h-2 rounded-full" style="width: <?php echo $aging_data['60_plus_days']['percentage']; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

   

   
    <!-- View Invoice Modal -->
    <div id="viewInvoiceModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Invoice Details</h3>
                    <button onclick="closeModal('viewInvoiceModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div id="invoiceDetails" class="space-y-4">
                    <!-- Invoice details will be loaded here -->
                </div>
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h4 class="text-lg font-semibold text-gray-800 mb-4">Actions</h4>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="deleteInvoice(currentInvoiceId)" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                            Delete
                        </button>
                        <button onclick="markForCompliance(currentInvoiceId)" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center gap-2">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                            For Compliance
                        </button>
                        <button onclick="markAsReceived(currentInvoiceId)" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                            Received
                        </button>
                        <button onclick="issueInvoice(currentInvoiceId)" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                            <i data-lucide="file-text" class="w-4 h-4"></i>
                            Issue Invoice
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                
                const response = await fetch('../API/accounts_receivable_api.php', {
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
                
                const response = await fetch('../API/accounts_receivable_api.php', {
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
                const response = await fetch(`../API/accounts_receivable_api.php?action=get_invoice&id=${invoiceId}`);
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
                        const response = await fetch('../API/accounts_receivable_api.php', {
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