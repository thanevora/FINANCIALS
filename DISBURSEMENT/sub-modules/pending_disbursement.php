<?php
session_start();
include("../../API_gateway.php");

// Database configuration
$db_name = "fina_budget";

// Check database connection
if (!isset($connections[$db_name])) {
    die("❌ Connection not found for $db_name");
}

$conn = $connections[$db_name];

// Fetch pending disbursement data
$total_pending = 0;
$total_pending_amount = 0;
$high_priority_count = 0;
$avg_processing_time = 0;

try {
    // Get pending disbursements
    $pending_query = "SELECT COUNT(*) as pending_count, SUM(amount) as pending_total 
                     FROM disbursement_requests WHERE status = 'pending'";
    $pending_result = mysqli_query($conn, $pending_query);
    $pending_row = mysqli_fetch_assoc($pending_result);
    $total_pending = $pending_row['pending_count'] ?? 0;
    $total_pending_amount = $pending_row['pending_total'] ?? 0;

    // Get high priority count
    $high_priority_query = "SELECT COUNT(*) as high_count FROM disbursement_requests 
                           WHERE status = 'pending' AND priority IN ('high', 'urgent')";
    $high_priority_result = mysqli_query($conn, $high_priority_query);
    $high_priority_row = mysqli_fetch_assoc($high_priority_result);
    $high_priority_count = $high_priority_row['high_count'] ?? 0;

    // Get average processing time (in days)
    $avg_query = "SELECT AVG(DATEDIFF(COALESCE(updated_at, NOW()), created_at)) as avg_days 
                 FROM disbursement_requests WHERE status = 'approved'";
    $avg_result = mysqli_query($conn, $avg_query);
    $avg_row = mysqli_fetch_assoc($avg_result);
    $avg_processing_time = round($avg_row['avg_days'] ?? 2.1, 1);

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
}

// Fetch pending requests for table
$pending_requests = [];
try {
    $requests_query = "SELECT * FROM disbursement_requests WHERE status = 'pending' ORDER BY 
                      CASE priority 
                          WHEN 'urgent' THEN 1
                          WHEN 'high' THEN 2
                          WHEN 'medium' THEN 3
                          ELSE 4
                      END, created_at ASC";
    $requests_result = mysqli_query($conn, $requests_query);
    while ($row = mysqli_fetch_assoc($requests_result)) {
        $pending_requests[] = $row;
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
}

// Helper function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Helper function to get priority badge class
function getPriorityBadge($priority) {
    switch ($priority) {
        case 'urgent':
            return 'bg-red-100 text-red-800';
        case 'high':
            return 'bg-orange-100 text-orange-800';
        case 'medium':
            return 'bg-blue-100 text-blue-800';
        case 'low':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Helper function to get days pending
function getDaysPending($createdDate) {
    $created = new DateTime($createdDate);
    $now = new DateTime();
    $interval = $created->diff($now);
    return $interval->days;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Disbursement | System Name</title>
           <?php include '../../COMPONENTS/header.php'; ?>

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
        <main class="p-6">
            <div class="container mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-800">Pending Disbursement</h1>
                    <p class="text-gray-600 mt-2">Review and process pending disbursement requests</p>
                </div>

                <!-- Pending Stats Cards -->
                <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                            <span class="p-2 mr-3 rounded-lg bg-amber-100/50 text-amber-600">
                                <i data-lucide="clock" class="w-5 h-5"></i>
                            </span>
                            Pending Overview
                        </h2>
                        <div class="flex gap-2">
                            <button onclick="processAll()" class="px-4 py-2 bg-amber-500 text-white rounded-lg flex items-center gap-2 hover:bg-amber-600 transition-colors">
                                <i data-lucide="play" class="w-4 h-4"></i>
                                Process All
                            </button>
                            <button onclick="exportPending()" class="px-4 py-2 bg-blue-600 text-white rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors">
                                <i data-lucide="download" class="w-4 h-4"></i>
                                Export
                            </button>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 h-full">
                        
                        <!-- Total Pending Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Total Pending</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $total_pending; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full" style="background:#F7B32B1A; color:#F7B32B;">
                                    <i data-lucide="alert-circle" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full" style="background:#F7B32B; width: <?php echo $total_pending > 0 ? min(100, ($high_priority_count / $total_pending) * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>High priority</span>
                                    <span class="font-medium"><?php echo $high_priority_count; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Amount Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Pending Amount</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo formatCurrency($total_pending_amount); ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                                    <i data-lucide="dollar-sign" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo $total_pending_amount > 0 ? min(100, ($total_pending_amount / ($total_pending_amount + 1000000)) * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Total pending</span>
                                    <span class="font-medium"><?php echo $total_pending; ?> requests</span>
                                </div>
                            </div>
                        </div>

                        <!-- Avg. Processing Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Avg. Processing</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $avg_processing_time; ?>d
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-green-100 text-green-600">
                                    <i data-lucide="zap" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-green-500 rounded-full" style="width: <?php echo min(100, (5 - $avg_processing_time) / 5 * 100); ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Turnaround time</span>
                                    <span class="font-medium">Efficient</span>
                                </div>
                            </div>
                        </div>

                        <!-- Urgent Requests Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Urgent Requests</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php 
                                        $urgent_query = "SELECT COUNT(*) as urgent_count FROM disbursement_requests 
                                                       WHERE status = 'pending' AND priority = 'urgent'";
                                        $urgent_result = mysqli_query($conn, $urgent_query);
                                        $urgent_row = mysqli_fetch_assoc($urgent_result);
                                        echo $urgent_row['urgent_count'] ?? 0;
                                        ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-red-100 text-red-600">
                                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-red-500 rounded-full" style="width: <?php echo $total_pending > 0 ? min(100, (($urgent_row['urgent_count'] ?? 0) / $total_pending) * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Require attention</span>
                                    <span class="font-medium">Immediate</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Navigation Tabs -->
                <div class="mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8">
                            <a href="disbursement_request.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Disbursement Request
                            </a>
                            <a href="#" class="border-amber-500 text-amber-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Pending Disbursement
                            </a>
                            <a href="disburse_allocation.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Disburse Allocation
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Pending Requests Section -->
                <section class="glass-effect p-6 rounded-2xl shadow-sm">
                    <div class="mb-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Pending Disbursement Requests</h3>
                        <p class="text-gray-600">Review and take action on pending disbursement requests.</p>
                    </div>
                    
                    <!-- Pending Requests Table -->
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="p-4 border-b border-gray-100">
                            <div class="flex justify-between items-center">
                                <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <i data-lucide="list" class="w-5 h-5 mr-2 text-gray-600"></i>
                                    Pending Requests (<?php echo $total_pending; ?>)
                                </h4>
                                <div class="flex gap-2">
                                    <input type="text" placeholder="Search pending requests..." class="px-3 py-2 border border-gray-300 rounded-lg text-sm w-64">
                                    <select class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                        <option>All Priorities</option>
                                        <option>Urgent</option>
                                        <option>High</option>
                                        <option>Medium</option>
                                        <option>Low</option>
                                    </select>
                                    <button class="p-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                                        <i data-lucide="filter" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Pending</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($pending_requests)): ?>
                                        <tr>
                                            <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                                <i data-lucide="check-circle" class="w-12 h-12 mx-auto mb-3 opacity-50 text-green-500"></i>
                                                <p>No pending disbursement requests</p>
                                                <p class="text-sm">All requests have been processed</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pending_requests as $request): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($request['request_code'] ?? 'N/A'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">
                                                    <?php echo formatCurrency($request['amount'] ?? 0); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($request['category'] ?? 'N/A'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getPriorityBadge($request['priority'] ?? 'medium'); ?>">
                                                        <?php echo ucfirst($request['priority'] ?? 'medium'); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php 
                                                    $daysPending = getDaysPending($request['created_at'] ?? '');
                                                    $daysClass = $daysPending > 7 ? 'text-red-600 font-semibold' : 
                                                                ($daysPending > 3 ? 'text-amber-600' : 'text-gray-600');
                                                    ?>
                                                    <span class="<?php echo $daysClass; ?>">
                                                        <?php echo $daysPending; ?> days
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo date('M j, Y', strtotime($request['created_at'] ?? '')); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex gap-2">
                                                        <button onclick="viewRequest(<?php echo $request['id']; ?>)" class="text-blue-600 hover:text-blue-900 p-1 rounded transition-colors" title="View Details">
                                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                                        </button>
                                                        <button onclick="approveRequest(<?php echo $request['id']; ?>)" class="text-green-600 hover:text-green-900 p-1 rounded transition-colors" title="Approve">
                                                            <i data-lucide="check" class="w-4 h-4"></i>
                                                        </button>
                                                        <button onclick="rejectRequest(<?php echo $request['id']; ?>)" class="text-red-600 hover:text-red-900 p-1 rounded transition-colors" title="Reject">
                                                            <i data-lucide="x" class="w-4 h-4"></i>
                                                        </button>
                                                        <button onclick="holdRequest(<?php echo $request['id']; ?>)" class="text-amber-600 hover:text-amber-900 p-1 rounded transition-colors" title="Put on Hold">
                                                            <i data-lucide="pause" class="w-4 h-4"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <?php if (!empty($pending_requests)): ?>
                    <div class="mt-6 flex justify-between items-center">
                        <div class="text-sm text-gray-600">
                            <span><?php echo count($pending_requests); ?> requests pending review</span>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="approveAll()" class="px-4 py-2 bg-green-600 text-white rounded-lg flex items-center gap-2 hover:bg-green-700 transition-colors text-sm">
                                <i data-lucide="check-circle" class="w-4 h-4"></i>
                                Approve All
                            </button>
                            <button onclick="exportPending()" class="px-4 py-2 bg-blue-600 text-white rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors text-sm">
                                <i data-lucide="download" class="w-4 h-4"></i>
                                Export List
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // View request details
        function viewRequest(requestId) {
            window.location.href = 'request_details.php?id=' + requestId;
        }

        // Approve request
        function approveRequest(requestId) {
            if (confirm('Are you sure you want to approve this request?')) {
                // Implement approval logic
                console.log('Approving request:', requestId);
                // Add AJAX call here
            }
        }

        // Reject request
        function rejectRequest(requestId) {
            if (confirm('Are you sure you want to reject this request?')) {
                // Implement rejection logic
                console.log('Rejecting request:', requestId);
                // Add AJAX call here
            }
        }

        // Hold request
        function holdRequest(requestId) {
            if (confirm('Put this request on hold?')) {
                // Implement hold logic
                console.log('Holding request:', requestId);
                // Add AJAX call here
            }
        }

        // Bulk actions
        function approveAll() {
            if (confirm('Approve all pending requests?')) {
                // Implement bulk approval
                console.log('Approving all requests');
            }
        }

        function exportPending() {
            // Implement export functionality
            console.log('Exporting pending requests');
        }

        function processAll() {
            if (confirm('Process all pending disbursements?')) {
                // Implement bulk processing
                console.log('Processing all disbursements');
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
    </script>
</body>
</html>