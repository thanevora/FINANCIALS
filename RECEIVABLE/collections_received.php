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

// Fetch received collections stats
$stats = [
    'total_received' => 0,
    'total_amount' => 0,
    'avg_amount' => 0,
    'today_received' => 0,
    'today_amount' => 0,
    'this_week_received' => 0,
    'this_week_amount' => 0,
    'this_month_received' => 0,
    'this_month_amount' => 0
];

// Total received
$sql = "SELECT COUNT(*) as count, SUM(amount) as total FROM collections WHERE status = 'RECEIVED'";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $stats['total_received'] = (int)$row['count'];
    $stats['total_amount'] = (float)$row['total'];
    $stats['avg_amount'] = $row['count'] > 0 ? $row['total'] / $row['count'] : 0;
}

// Today's received
$today = date('Y-m-d');
$sql = "SELECT COUNT(*) as count, SUM(amount) as total FROM collections WHERE status = 'RECEIVED' AND DATE(collection_date) = '$today'";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $stats['today_received'] = (int)$row['count'];
    $stats['today_amount'] = (float)$row['total'];
}

// This week's received
$week_start = date('Y-m-d', strtotime('this week'));
$sql = "SELECT COUNT(*) as count, SUM(amount) as total FROM collections WHERE status = 'RECEIVED' AND DATE(collection_date) >= '$week_start'";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $stats['this_week_received'] = (int)$row['count'];
    $stats['this_week_amount'] = (float)$row['total'];
}

// This month's received
$month_start = date('Y-m-01');
$sql = "SELECT COUNT(*) as count, SUM(amount) as total FROM collections WHERE status = 'RECEIVED' AND DATE(collection_date) >= '$month_start'";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $stats['this_month_received'] = (int)$row['count'];
    $stats['this_month_amount'] = (float)$row['total'];
}

// Fetch received collections
$collections = [];
$sql = "SELECT c.*, ar.invoice_number 
        FROM collections c 
        LEFT JOIN accounts_receivable ar ON c.id = ar.collection_id 
        WHERE c.status = 'RECEIVED' 
        ORDER BY c.collection_date DESC, c.created_at DESC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $collections[] = $row;
    }
}

$page_title = "Received Collections";
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
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
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
            <main class="p-6">
                <div class="container mx-auto">
                    <!-- Header -->
                    <div class="mb-8">
                        <h1 class="text-3xl font-bold text-gray-800">Received Collections</h1>
                        <p class="text-gray-600 mt-2">View all successfully received and approved collections</p>
                        <div class="flex gap-2 mt-4">
                            <a href="collections_pending.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Pending Approval
                            </a>
                            <a href="collections_received.php" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                Received Collections
                            </a>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                                <span class="p-2 mr-3 rounded-lg bg-green-100/50 text-green-600">
                                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                                </span>
                                Received Collections Overview
                            </h2>
                        </div>

                        <!-- Stats Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                            <!-- Total Received -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-green-600">Total Received</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            <?php echo $stats['total_received']; ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                                        <i data-lucide="check-circle" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="text-lg font-bold text-gray-800">₱<?php echo number_format($stats['total_amount'], 2); ?></p>
                                    <p class="text-xs text-gray-500">Total Amount</p>
                                </div>
                            </div>

                            <!-- Today's Received -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-blue-600">Today</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            <?php echo $stats['today_received']; ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                        <i data-lucide="calendar" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="text-lg font-bold text-gray-800">₱<?php echo number_format($stats['today_amount'], 2); ?></p>
                                    <p class="text-xs text-gray-500">Today's Amount</p>
                                </div>
                            </div>

                            <!-- This Week -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-indigo-600">This Week</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            <?php echo $stats['this_week_received']; ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                                        <i data-lucide="calendar-days" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="text-lg font-bold text-gray-800">₱<?php echo number_format($stats['this_week_amount'], 2); ?></p>
                                    <p class="text-xs text-gray-500">Weekly Amount</p>
                                </div>
                            </div>

                            <!-- This Month -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-purple-600">This Month</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            <?php echo $stats['this_month_received']; ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                        <i data-lucide="calendar-range" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="text-lg font-bold text-gray-800">₱<?php echo number_format($stats['this_month_amount'], 2); ?></p>
                                    <p class="text-xs text-gray-500">Monthly Amount</p>
                                </div>
                            </div>

                            <!-- Average Amount -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-amber-600">Average</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            ₱<?php echo number_format($stats['avg_amount'], 2); ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                                        <i data-lucide="dollar-sign" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">Per collection</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Received Collections Table -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm">
                        <div class="mb-6">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4">All Received Collections</h3>
                            <p class="text-gray-600">View and manage all received collections and their corresponding invoices.</p>
                        </div>
                        
                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service Type</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Collection Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (count($collections) > 0): ?>
                                            <?php foreach ($collections as $collection): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($collection['invoice_number'] ?? 'N/A'); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($collection['request_id']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo htmlspecialchars($collection['customer_name']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                                        ₱<?php echo number_format($collection['amount'], 2); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($collection['service_type']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($collection['payment_method']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo date('Y-m-d', strtotime($collection['collection_date'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                            <?php echo htmlspecialchars($collection['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo date('Y-m-d', strtotime($collection['created_at'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div class="flex space-x-2">
                                                            <button onclick="viewCollection(<?php echo $collection['id']; ?>)" 
                                                                    class="text-blue-600 hover:text-blue-900">
                                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                                            </button>
                                                            <?php if (!empty($collection['invoice_number'])): ?>
                                                            <button onclick="viewInvoice('<?php echo htmlspecialchars($collection['invoice_number']); ?>')" 
                                                                    class="text-purple-600 hover:text-purple-900">
                                                                <i data-lucide="file-text" class="w-4 h-4"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                            <button onclick="downloadReceipt(<?php echo $collection['id']; ?>)" 
                                                                    class="text-green-600 hover:text-green-900">
                                                                <i data-lucide="download" class="w-4 h-4"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="10" class="px-6 py-4 text-center text-sm text-gray-500">
                                                    No received collections found.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <!-- View Collection Modal -->
    <div id="viewCollectionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Received Collection Details</h3>
                    <button onclick="closeModal('viewCollectionModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div id="collectionDetails" class="space-y-4">
                    <!-- Collection details will be loaded here -->
                </div>
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

        // View collection details
        async function viewCollection(collectionId) {
            try {
                const response = await fetch(`../API/collections_api.php?action=get_collection&id=${collectionId}`);
                const result = await response.json();
                
                if (result.status === 'success') {
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
            
            detailsContainer.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Request ID</label>
                            <p class="text-lg font-semibold text-gray-900">${collection.request_id}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Number</label>
                            <p class="text-lg font-semibold text-gray-900">${collection.invoice_number || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer Name</label>
                            <p class="text-gray-900">${collection.customer_name}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                            <p class="text-gray-900">${collection.service_type}</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                ${collection.status}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                            <p class="text-2xl font-bold text-gray-900">₱${parseFloat(collection.amount).toFixed(2)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                            <p class="text-gray-900">${collection.payment_method}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Collection Date</label>
                            <p class="text-gray-900">${collection.collection_date}</p>
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
                        <p class="text-gray-900">${collection.created_at}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Updated</label>
                        <p class="text-gray-900">${collection.updated_at || collection.created_at}</p>
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

        // View Invoice
        function viewInvoice(invoiceNumber) {
            if (invoiceNumber !== 'N/A') {
                // Redirect to accounts receivable view or open in new tab
                window.open(`../accounts_receivable/view_invoice.php?invoice_number=${invoiceNumber}`, '_blank');
            } else {
                showNotification('No invoice associated with this collection', 'warning');
            }
        }

        // Download Receipt
        function downloadReceipt(collectionId) {
            Swal.fire({
                title: 'Download Receipt',
                text: "Generate and download collection receipt?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Download PDF'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Generate receipt PDF
                    window.open(`../API/generate_receipt.php?collection_id=${collectionId}`, '_blank');
                    showNotification('Receipt generated successfully', 'success');
                }
            });
        }

        // Notification function
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg text-white z-50 transform transition-transform duration-300 ${
                type === 'success' ? 'bg-green-500' :
                type === 'error' ? 'bg-red-500' :
                type === 'warning' ? 'bg-amber-500' :
                'bg-blue-500'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            // You can implement auto-refresh logic here
            console.log('Auto-refreshing data...');
        }, 30000);
    </script>
</body>
</html>