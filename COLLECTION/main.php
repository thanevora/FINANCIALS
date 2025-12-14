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

// Fetch collections data using API
$api_url = "API/collections_api.php?action=get_stats";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Content-Type: application/json'
    ]
]);

$response = file_get_contents($api_url, false, $context);
$data = json_decode($response, true);

if ($data && $data['status'] === 'success') {
    $stats = $data['data'];
    $total_collections = $stats['total_collections'] ?? 0;
    $pending_collections = $stats['pending_collections'] ?? 0;
    $pending_amount = $stats['pending_amount'] ?? 0;
    $successful_this_month = $stats['successful_this_month'] ?? 0;
    $collection_rate = $stats['collection_rate'] ?? 0;
    $total_count = $stats['total_count'] ?? 0;
    $completed_count = $stats['completed_count'] ?? 0;
    $monthly_count = $stats['monthly_count'] ?? 0;
    $transaction_status = $stats['transaction_status'] ?? [];
    $payment_methods = $stats['payment_methods'] ?? [];
} else {
    // Fallback to direct database queries if API fails
    include("direct_queries.php"); // You can create this as backup
}

// Fetch collection requests for the table
$collection_requests = [];
$sql = "SELECT * FROM collections ORDER BY due_date DESC LIMIT 50";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $collection_requests[] = $row;
    }
}
?>

<!-- Your existing HTML and stat cards remain the same -->
<!-- The stat cards will now use the data from the API -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | System Name</title>
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
        <div class="flex gap-2">
            <button onclick="openModal('collectionRequestModal')" class="px-4 py-2 bg-green-600 text-white rounded-lg flex items-center gap-2 hover:bg-green-700 transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Collection Request
            </button>
            <button onclick="window.location.href='collection_status.php'" class="px-4 py-2 bg-blue-600 text-white rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors">
                <i data-lucide="eye" class="w-4 h-4"></i>
                Collection Status
            </button>
        </div>
    </div>

    <!-- All Stats Cards in Unified Design -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        
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

        <!-- Successful This Month Card -->
        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color:#001f54;">Successful This Month</p>
                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                        ₱<?php echo number_format($successful_this_month, 2); ?>
                    </h3>
                </div>
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <i data-lucide="check-circle" class="w-6 h-6"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $total_collections > 0 ? ($successful_this_month / $total_collections * 100) : 0; ?>%"></div>
                </div>
                <div class="flex justify-between text-xs text-gray-500 mt-1">
                    <span>Processed payments</span>
                    <span class="font-medium"><?php echo $monthly_count ?? 0; ?> transactions</span>
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

                    <!-- Navigation Tabs -->
                    <div class="mb-6">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8">
                                <a href="#" class="border-green-500 text-green-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Collection Request
                                </a>
                                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Collection Status
                                </a>
                                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Collection Transactions
                                </a>
                            </nav>
                        </div>
                    </div>

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
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service Type</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (count($collection_requests) > 0): ?>
                                            <?php foreach ($collection_requests as $request): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['request_id']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($request['customer_name']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱<?php echo number_format($request['amount'], 2); ?></td>
                                                    <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($request['service_type'] ?? 'N/A'); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('Y-m-d', strtotime($request['due_date'])); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                        $status_class = '';
                                                        $status_text = '';
                                                        switch (strtolower($request['status'])) {
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
                                                        <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                                                        <?php if (strtolower($request['status']) == 'pending'): ?>
                                                            <button class="text-green-600 hover:text-green-900">Remind</button>
                                                        <?php elseif (in_array(strtolower($request['status']), ['overdue', 'failed'])): ?>
                                                            <button class="text-red-600 hover:text-red-900">Escalate</button>
                                                        <?php else: ?>
                                                            <button class="text-green-600 hover:text-green-900">Receipt</button>
                                                        <?php endif; ?>
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
                        </div>
                    </section>

                    
                    <!-- Payment Methods Breakdown -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm mt-6">
                        <div class="mb-6">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4">Payment Methods</h3>
                            <p class="text-gray-600">Distribution of collections by payment method.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <?php if (count($payment_methods) > 0): ?>
                                <?php foreach ($payment_methods as $method): ?>
                                    <?php
                                    $method_icons = [
                                        'credit card' => ['icon' => 'credit-card', 'color' => 'blue'],
                                        'bank transfer' => ['icon' => 'building', 'color' => 'green'],
                                        'digital wallet' => ['icon' => 'smartphone', 'color' => 'purple'],
                                        'cash' => ['icon' => 'dollar-sign', 'color' => 'amber'],
                                        'check' => ['icon' => 'file-text', 'color' => 'indigo']
                                    ];
                                    
                                    $method_lower = strtolower($method['payment_method']);
                                    $icon_data = $method_icons[$method_lower] ?? ['icon' => 'credit-card', 'color' => 'gray'];
                                    ?>
                                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                                <i data-lucide="<?php echo $icon_data['icon']; ?>" class="w-5 h-5 mr-2 text-<?php echo $icon_data['color']; ?>-600"></i>
                                                <?php echo htmlspecialchars(ucwords($method['payment_method'])); ?>
                                            </h4>
                                            <span class="px-2 py-1 text-xs font-medium bg-<?php echo $icon_data['color']; ?>-100 text-<?php echo $icon_data['color']; ?>-800 rounded-full">
                                                <?php echo $method['percentage']; ?>%
                                            </span>
                                        </div>
                                        <div class="space-y-3">
                                            <div>
                                                <div class="flex justify-between text-sm text-gray-600 mb-1">
                                                    <span>Total Amount</span>
                                                    <span>₱<?php echo number_format($method['amount'], 2); ?></span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="bg-<?php echo $icon_data['color']; ?>-500 h-2 rounded-full" style="width: <?php echo $method['percentage']; ?>%"></div>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="flex justify-between text-sm text-gray-600 mb-1">
                                                    <span>Transactions</span>
                                                    <span><?php echo $method['count']; ?></span>
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

    <!-- Collection Request Modal -->
    <div id="collectionRequestModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">New Collection Request</h3>
                    <button onclick="closeModal('collectionRequestModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form id="collectionForm" method="POST" action="../API/collections_api.php">
                    <input type="hidden" name="action" value="create_collection">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                            <input type="text" name="customer_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Enter customer name" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₱)</label>
                            <input type="number" name="amount" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                        <select name="service_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" required>
                            <option value="Tour Package">Tour Package</option>
                            <option value="Hotel Booking">Hotel Booking</option>
                            <option value="Transportation">Transportation</option>
                            <option value="Event Reservation">Event Reservation</option>
                            <option value="Custom Package">Custom Package</option>
                            <option value="Other Service">Other Service</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                        <input type="date" name="due_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select name="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" required>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Digital Wallet">Digital Wallet</option>
                            <option value="Cash">Cash</option>
                            <option value="Check">Check</option>
                        </select>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Additional details about this collection request..."></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeModal('collectionRequestModal')" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            Create Request
                        </button>
                                            </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Notification -->
    <div id="successNotification" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300">
        <div class="flex items-center gap-2">
            <i data-lucide="check-circle" class="w-5 h-5"></i>
            <span>Collection request created successfully!</span>
        </div>
    </div>

    <!-- Error Notification -->
    <div id="errorNotification" class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300">
        <div class="flex items-center gap-2">
            <i data-lucide="alert-circle" class="w-5 h-5"></i>
            <span>Error creating collection request!</span>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });

        // Form submission handling
        document.getElementById('collectionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../API/collections_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success');
                    closeModal('collectionRequestModal');
                    this.reset();
                    // Reload the page to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('error', data.message || 'An error occurred');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Network error occurred');
            });
        });

        // Notification function
        function showNotification(type, message = '') {
            const notification = document.getElementById(type + 'Notification');
            
            if (message) {
                notification.querySelector('span').textContent = message;
            }
            
            notification.style.transform = 'translateX(0)';
            
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
            }, 3000);
        }

        // Format currency display
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(amount);
        }

        // Set minimum date for due date to today
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="due_date"]').min = today;

        // Add search functionality to the table
        function addTableSearch() {
            const table = document.querySelector('table');
            const rows = table.querySelectorAll('tbody tr');
            const searchInput = document.createElement('input');
            
            searchInput.type = 'text';
            searchInput.placeholder = 'Search collection requests...';
            searchInput.className = 'w-full px-4 py-2 border border-gray-300 rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-green-500';
            
            const tableContainer = table.parentElement;
            tableContainer.parentElement.insertBefore(searchInput, tableContainer);
            
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }

        // Initialize table search when page loads
        document.addEventListener('DOMContentLoaded', function() {
            addTableSearch();
            
            // Add status filter
            const statusFilter = document.createElement('select');
            statusFilter.innerHTML = `
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="completed">Completed</option>
                <option value="failed">Failed</option>
                <option value="overdue">Overdue</option>
                <option value="refund">Refund</option>
                <option value="for_approval">For Approval</option>
            `;
            statusFilter.className = 'px-4 py-2 border border-gray-300 rounded-lg mb-4 ml-4 focus:outline-none focus:ring-2 focus:ring-green-500';
            
            const searchInput = document.querySelector('input[placeholder*="Search"]');
            if (searchInput) {
                searchInput.parentElement.appendChild(statusFilter);
                
                statusFilter.addEventListener('change', function(e) {
                    const selectedStatus = e.target.value.toLowerCase();
                    const rows = document.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const statusCell = row.querySelector('td:nth-child(6)');
                        if (statusCell) {
                            const status = statusCell.textContent.toLowerCase().trim();
                            if (!selectedStatus || status === selectedStatus) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        }
                    });
                });
            }
        });

        // Export functionality
        function exportCollections(format) {
            const url = `../API/collections_api.php?action=export_collections&format=${format}`;
            window.open(url, '_blank');
        }

        // Add export buttons
        document.addEventListener('DOMContentLoaded', function() {
            const header = document.querySelector('h3.text-xl.font-semibold');
            if (header) {
                const exportDiv = document.createElement('div');
                exportDiv.className = 'flex gap-2 mt-4';
                exportDiv.innerHTML = `
                    <button onclick="exportCollections('csv')" class="px-4 py-2 bg-blue-600 text-white rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        Export CSV
                    </button>
                    <button onclick="exportCollections('pdf')" class="px-4 py-2 bg-red-600 text-white rounded-lg flex items-center gap-2 hover:bg-red-700 transition-colors">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        Export PDF
                    </button>
                `;
                header.parentElement.appendChild(exportDiv);
            }
        });

        // Auto-refresh data every 5 minutes
        setInterval(() => {
            // You can implement partial page refresh here if needed
            console.log('Auto-refresh collections data');
        }, 300000);

    </script>
</body>
</html>