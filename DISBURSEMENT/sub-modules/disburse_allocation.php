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

// Fetch allocation data
$total_allocations = 0;
$monthly_allocations = 0;
$departments_count = 0;
$pending_allocations = 0;
$for_compliance_count = 0;
$approved_allocations = 0;
$disbursed_allocations = 0;

try {
    // Get total allocations
    $total_query = "SELECT SUM(amount) as total FROM budget_allocations WHERE status = 'approved'";
    $total_result = mysqli_query($conn, $total_query);
    $total_row = mysqli_fetch_assoc($total_result);
    $total_allocations = $total_row['total'] ?? 0;

    // Get monthly allocations
    $monthly_query = "SELECT SUM(amount) as monthly_total FROM budget_allocations 
                     WHERE status = 'approved' AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
                     AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $monthly_result = mysqli_query($conn, $monthly_query);
    $monthly_row = mysqli_fetch_assoc($monthly_result);
    $monthly_allocations = $monthly_row['monthly_total'] ?? 0;

    // Get departments count
    $dept_query = "SELECT COUNT(DISTINCT department) as dept_count FROM budget_allocations WHERE status = 'approved'";
    $dept_result = mysqli_query($conn, $dept_query);
    $dept_row = mysqli_fetch_assoc($dept_result);
    $departments_count = $dept_row['dept_count'] ?? 0;

    // Get pending allocations
    $pending_query = "SELECT COUNT(*) as pending_count FROM budget_allocations WHERE status = 'pending'";
    $pending_result = mysqli_query($conn, $pending_query);
    $pending_row = mysqli_fetch_assoc($pending_result);
    $pending_allocations = $pending_row['pending_count'] ?? 0;

    // Get for compliance count
    $compliance_query = "SELECT COUNT(*) as compliance_count FROM budget_allocations WHERE status = 'for_compliance'";
    $compliance_result = mysqli_query($conn, $compliance_query);
    $compliance_row = mysqli_fetch_assoc($compliance_result);
    $for_compliance_count = $compliance_row['compliance_count'] ?? 0;

    // Get approved allocations count
    $approved_query = "SELECT COUNT(*) as approved_count FROM budget_allocations WHERE status = 'approved'";
    $approved_result = mysqli_query($conn, $approved_query);
    $approved_row = mysqli_fetch_assoc($approved_result);
    $approved_allocations = $approved_row['approved_count'] ?? 0;

    // Get disbursed allocations count
    $disbursed_query = "SELECT COUNT(*) as disbursed_count FROM budget_allocations WHERE status = 'disbursed'";
    $disbursed_result = mysqli_query($conn, $disbursed_query);
    $disbursed_row = mysqli_fetch_assoc($disbursed_result);
    $disbursed_allocations = $disbursed_row['disbursed_count'] ?? 0;

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
}

// Fetch all allocations for the cards
$allocations = [];
try {
    $allocations_query = "SELECT * FROM budget_allocations ORDER BY 
                         CASE status 
                             WHEN 'approved' THEN 1
                             WHEN 'for_compliance' THEN 2
                             WHEN 'pending' THEN 3
                             WHEN 'disbursed' THEN 4
                             ELSE 5
                         END, created_at DESC";
    $allocations_result = mysqli_query($conn, $allocations_query);
    while ($row = mysqli_fetch_assoc($allocations_result)) {
        $allocations[] = $row;
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
}

// Helper function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Helper function to get status badge class
function getStatusBadge($status) {
    switch ($status) {
        case 'approved':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'pending':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'rejected':
            return 'bg-red-100 text-red-800 border-red-200';
        case 'for_compliance':
            return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'disbursed':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

// Helper function to get status icon
function getStatusIcon($status) {
    switch ($status) {
        case 'approved':
            return 'check-circle';
        case 'pending':
            return 'clock';
        case 'rejected':
            return 'x-circle';
        case 'for_compliance':
            return 'file-check';
        case 'disbursed':
            return 'dollar-sign';
        default:
            return 'help-circle';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disburse Allocation | System Name</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                    <h1 class="text-3xl font-bold text-gray-800">Disburse Allocation</h1>
                    <p class="text-gray-600 mt-2">Manage budget allocations and fund distributions to departments</p>
                </div>

                <!-- Allocation Stats Cards -->
                <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                            <span class="p-2 mr-3 rounded-lg bg-blue-100/50 text-blue-600">
                                <i data-lucide="dollar-sign" class="w-5 h-5"></i>
                            </span>
                            Allocation Overview
                        </h2>
                       
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 h-full">
                        
                        <!-- Total Allocations Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Total Allocations</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo formatCurrency($total_allocations); ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full" style="background:#F7B32B1A; color:#F7B32B;">
                                    <i data-lucide="wallet" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full" style="background:#F7B32B; width: <?php echo $monthly_allocations > 0 ? min(100, ($monthly_allocations / $total_allocations) * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>This month</span>
                                    <span class="font-medium"><?php echo formatCurrency($monthly_allocations); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Approved Allocations Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Approved</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $approved_allocations; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-green-100 text-green-600">
                                    <i data-lucide="check-circle" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $approved_allocations > 0 ? min(100, ($approved_allocations / count($allocations)) * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Ready to disburse</span>
                                    <span class="font-medium">Ready</span>
                                </div>
                            </div>
                        </div>

                        <!-- For Compliance Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">For Compliance</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $for_compliance_count; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                    <i data-lucide="file-check" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-purple-500 rounded-full" style="width: <?php echo $for_compliance_count > 0 ? min(100, ($for_compliance_count / count($allocations)) * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Needs compliance</span>
                                    <span class="font-medium">Review required</span>
                                </div>
                            </div>
                        </div>

                        <!-- Disbursed Allocations Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Disbursed</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo $disbursed_allocations; ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                    <i data-lucide="dollar-sign" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-500 rounded-full" style="width: <?php echo $disbursed_allocations > 0 ? min(100, ($disbursed_allocations / count($allocations)) * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Completed</span>
                                    <span class="font-medium">Finalized</span>
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
                            <a href="pending_disbursement.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Pending Disbursement
                            </a>
                            <a href="#" class="border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Disburse Allocation
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Allocation Cards Section -->
                <section class="glass-effect p-6 rounded-2xl shadow-sm">
                    <div class="mb-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Budget Allocations</h3>
                        <p class="text-gray-600">Manage and review budget allocations for different departments.</p>
                    </div>

                    <!-- Search and Filter -->
                    <div class="mb-6 flex flex-col sm:flex-row gap-4">
                        <div class="flex-1">
                            <div class="relative">
                                <input type="text" placeholder="Search allocations..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" onkeyup="filterAllocations()" id="searchInput">
                                <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                        </div>
                        <select class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="filterAllocations()" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="approved">Approved</option>
                            <option value="for_compliance">For Compliance</option>
                            <option value="pending">Pending</option>
                            <option value="disbursed">Disbursed</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>

                    <!-- Allocation Cards Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="allocationsGrid">
                        <?php if (empty($allocations)): ?>
                            <div class="col-span-3 text-center py-12 text-gray-500">
                                <i data-lucide="inbox" class="w-16 h-16 mx-auto mb-4 opacity-50"></i>
                                <p class="text-lg font-medium">No allocations found</p>
                                <p class="text-sm">Create your first allocation to get started</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($allocations as $allocation): ?>
                                <div class="allocation-card bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all duration-300" 
                                     data-status="<?php echo $allocation['status'] ?? ''; ?>"
                                     data-department="<?php echo htmlspecialchars($allocation['department'] ?? ''); ?>">
                                    <!-- Card Header -->
                                    <div class="flex justify-between items-start mb-4">
                                        <div>
                                            <h3 class="font-semibold text-gray-800 text-lg mb-1"><?php echo htmlspecialchars($allocation['department'] ?? 'N/A'); ?></h3>
                                            <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full border <?php echo getStatusBadge($allocation['status'] ?? ''); ?>">
                                                <i data-lucide="<?php echo getStatusIcon($allocation['status'] ?? ''); ?>" class="w-3 h-3 mr-1"></i>
                                                <?php echo ucfirst($allocation['status'] ?? 'pending'); ?>
                                            </span>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($allocation['amount'] ?? 0); ?></div>
                                            <div class="text-xs text-gray-500 font-mono"><?php echo htmlspecialchars($allocation['allocation_code'] ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Card Details -->
                                    <div class="space-y-3 mb-4">
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i data-lucide="calendar" class="w-4 h-4 mr-2 text-blue-600"></i>
                                            <span><?php echo htmlspecialchars($allocation['quarter'] ?? 'N/A'); ?> • <?php echo htmlspecialchars($allocation['fiscal_year'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i data-lucide="file-text" class="w-4 h-4 mr-2 text-green-600"></i>
                                            <span class="truncate"><?php echo htmlspecialchars($allocation['purpose'] ?? 'No purpose specified'); ?></span>
                                        </div>
                                    </div>

                                    <p class="text-sm text-gray-600 mb-4 line-clamp-2"><?php echo htmlspecialchars($allocation['purpose'] ?? 'No purpose specified'); ?></p>
                                    
                                    <!-- Card Footer -->
                                    <div class="flex justify-between items-center">
                                        <div class="text-xs text-gray-500">
                                            <?php echo date('M j, Y', strtotime($allocation['created_at'] ?? '')); ?>
                                        </div>
                                        <button onclick="viewAllocation(<?php echo $allocation['id']; ?>)" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Allocation Details Modal -->
    <div id="allocationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200 sticky top-0 bg-white">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Allocation Details</h3>
                    <button onclick="closeAllocationModal()" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div id="allocationDetails" class="space-y-6">
                    <!-- Allocation details will be loaded here -->
                </div>
            </div>
            <div class="p-6 border-t border-gray-200 bg-gray-50 sticky bottom-0">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-600" id="allocationStatus">
                        <!-- Status will be displayed here -->
                    </div>
                    <div class="flex gap-3" id="actionButtons">
                        <!-- Action buttons will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- For Compliance Modal -->
    <div id="complianceModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Mark for Compliance</h3>
                    <button onclick="closeComplianceModal()" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form id="complianceForm" onsubmit="submitCompliance(event)">
                    <input type="hidden" id="complianceAllocationId" name="allocation_id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Compliance Notes</label>
                        <textarea name="compliance_notes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="Explain why this allocation needs compliance review..." required></textarea>
                        <p class="text-xs text-gray-500 mt-1">Please provide detailed explanation for compliance review requirements</p>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeComplianceModal()" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center">
                            <i data-lucide="file-check" class="w-4 h-4 mr-2"></i>
                            Mark for Compliance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Disburse Modal -->
    <div id="disburseModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Disburse Allocation</h3>
                    <button onclick="closeDisburseModal()" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div class="text-center mb-6">
                    <i data-lucide="dollar-sign" class="w-12 h-12 text-green-600 mx-auto mb-3"></i>
                    <h4 class="text-lg font-semibold text-gray-800 mb-2">Confirm Disbursement</h4>
                    <p class="text-gray-600">Are you sure you want to disburse this allocation?</p>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-600">Amount:</span>
                        <span class="font-semibold" id="disburseAmount"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Department:</span>
                        <span class="font-semibold" id="disburseDepartment"></span>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeDisburseModal()" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="button" onclick="confirmDisburse()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center">
                        <i data-lucide="check" class="w-4 h-4 mr-2"></i>
                        Confirm Disburse
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        let currentAllocationId = null;
        let currentAllocationData = null;

        // Modal functions
        function openAllocationModal() {
            document.getElementById('allocationModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeAllocationModal() {
            document.getElementById('allocationModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            currentAllocationId = null;
            currentAllocationData = null;
        }

        function openComplianceModal(allocationId) {
            document.getElementById('complianceAllocationId').value = allocationId;
            document.getElementById('complianceModal').style.display = 'flex';
        }

        function closeComplianceModal() {
            document.getElementById('complianceModal').style.display = 'none';
            document.getElementById('complianceForm').reset();
        }

        function openDisburseModal(allocationData) {
            currentAllocationData = allocationData;
            document.getElementById('disburseAmount').textContent = '₱' + parseFloat(allocationData.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('disburseDepartment').textContent = allocationData.department || 'N/A';
            document.getElementById('disburseModal').style.display = 'flex';
        }

        function closeDisburseModal() {
            document.getElementById('disburseModal').style.display = 'none';
            currentAllocationData = null;
        }

        // View allocation details
        async function viewAllocation(allocationId) {
            currentAllocationId = allocationId;
            
            try {
                const response = await fetch(`../API/get_allocation.php?id=${allocationId}`);
                const result = await response.json();
                
                if (result.success) {
                    const allocation = result.allocation;
                    currentAllocationData = allocation;
                    displayAllocationDetails(allocation);
                    openAllocationModal();
                } else {
                    Swal.fire('Error!', result.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error!', 'Failed to load allocation details', 'error');
            }
        }

        // Display allocation details in modal
        function displayAllocationDetails(allocation) {
            const detailsContainer = document.getElementById('allocationDetails');
            const statusContainer = document.getElementById('allocationStatus');
            const actionButtons = document.getElementById('actionButtons');
            
            detailsContainer.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Allocation Code</label>
                            <p class="text-lg font-semibold text-gray-900">${allocation.allocation_code || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <p class="text-gray-900">${allocation.department || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                            <p class="text-2xl font-bold text-green-600">₱${parseFloat(allocation.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fiscal Year</label>
                            <p class="text-gray-900">${allocation.fiscal_year || 'N/A'}</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quarter</label>
                            <p class="text-gray-900">${allocation.quarter || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <span class="px-3 py-1 text-sm font-semibold rounded-full ${getStatusBadgeClass(allocation.status)}">
                                ${allocation.status ? allocation.status.charAt(0).toUpperCase() + allocation.status.slice(1).replace('_', ' ') : 'N/A'}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Created Date</label>
                            <p class="text-gray-900">${allocation.created_at ? new Date(allocation.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Updated</label>
                            <p class="text-gray-900">${allocation.updated_at ? new Date(allocation.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
                        </div>
                    </div>
                </div>
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Purpose</label>
                    <p class="text-gray-900 bg-gray-50 p-4 rounded-lg">${allocation.purpose || 'No purpose specified'}</p>
                </div>
                ${allocation.notes ? `
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <p class="text-gray-900 bg-gray-50 p-4 rounded-lg">${allocation.notes}</p>
                </div>
                ` : ''}
                ${allocation.compliance_notes ? `
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Compliance Notes</label>
                    <p class="text-gray-900 bg-purple-50 p-4 rounded-lg border border-purple-200">${allocation.compliance_notes}</p>
                </div>
                ` : ''}
            `;

            statusContainer.innerHTML = `Current Status: <span class="font-semibold">${allocation.status ? allocation.status.charAt(0).toUpperCase() + allocation.status.slice(1).replace('_', ' ') : 'N/A'}</span>`;

            // Set action buttons based on status with new logic
            let buttons = '';
            
            if (allocation.status === 'disbursed') {
                // If disbursed, no actions allowed
                buttons = `
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center" disabled>
                        <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i>
                        Already Disbursed
                    </button>
                `;
            } else if (allocation.status === 'approved') {
                // Approved allocations can be disbursed or marked for compliance
                buttons = `
                    <button onclick="openDisburseModal(currentAllocationData)" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center">
                        <i data-lucide="dollar-sign" class="w-4 h-4 mr-2"></i>
                        DISBURSE
                    </button>
                    <button onclick="openComplianceModal(${allocation.id})" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center">
                        <i data-lucide="file-check" class="w-4 h-4 mr-2"></i>
                        FOR COMPLIANCE
                    </button>
                `;
            } else if (allocation.status === 'for_compliance') {
                // For compliance allocations cannot be disbursed directly
                buttons = `
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center" disabled>
                        <i data-lucide="dollar-sign" class="w-4 h-4 mr-2"></i>
                        Cannot Disburse
                    </button>
                    <button onclick="approveFromCompliance(${allocation.id})" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                        <i data-lucide="check" class="w-4 h-4 mr-2"></i>
                        Approve
                    </button>
                `;
            } else if (allocation.status === 'pending') {
                // Pending allocations can be approved or rejected
                buttons = `
                    <button onclick="approveAllocation(${allocation.id})" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                        <i data-lucide="check" class="w-4 h-4 mr-2"></i>
                        Approve
                    </button>
                    <button onclick="rejectAllocation(${allocation.id})" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center">
                        <i data-lucide="x" class="w-4 h-4 mr-2"></i>
                        Reject
                    </button>
                `;
            } else if (allocation.status === 'rejected') {
                // Rejected allocations have no actions
                buttons = `
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center" disabled>
                        <i data-lucide="x-circle" class="w-4 h-4 mr-2"></i>
                        Rejected
                    </button>
                `;
            }

            actionButtons.innerHTML = buttons;
            lucide.createIcons();
        }

        // Helper function for status badge classes
        function getStatusBadgeClass(status) {
            switch (status) {
                case 'approved': return 'bg-green-100 text-green-800 border border-green-200';
                case 'pending': return 'bg-amber-100 text-amber-800 border border-amber-200';
                case 'rejected': return 'bg-red-100 text-red-800 border border-red-200';
                case 'for_compliance': return 'bg-purple-100 text-purple-800 border border-purple-200';
                case 'disbursed': return 'bg-blue-100 text-blue-800 border border-blue-200';
                default: return 'bg-gray-100 text-gray-800 border border-gray-200';
            }
        }

        // CRUD Actions
        async function approveAllocation(allocationId) {
            if (confirm('Are you sure you want to approve this allocation?')) {
                try {
                    const response = await fetch('API/update_allocation_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: allocationId,
                            status: 'approved'
                        })
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        Swal.fire('Success!', 'Allocation approved successfully', 'success');
                        closeAllocationModal();
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        Swal.fire('Error!', result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire('Error!', 'Failed to approve allocation', 'error');
                }
            }
        }

        async function approveFromCompliance(allocationId) {
            if (confirm('Are you sure you want to approve this allocation from compliance status?')) {
                try {
                    const response = await fetch('../API/update_allocation_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: allocationId,
                            status: 'approved'
                        })
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        Swal.fire('Success!', 'Allocation approved successfully', 'success');
                        closeAllocationModal();
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        Swal.fire('Error!', result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire('Error!', 'Failed to approve allocation', 'error');
                }
            }
        }

        async function rejectAllocation(allocationId) {
            const { value: reason } = await Swal.fire({
                title: 'Reject Allocation',
                input: 'textarea',
                inputLabel: 'Reason for rejection',
                inputPlaceholder: 'Please provide the reason for rejecting this allocation...',
                inputAttributes: {
                    'aria-label': 'Type your message here'
                },
                showCancelButton: true,
                confirmButtonText: 'Reject',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Please provide a reason for rejection';
                    }
                }
            });

            if (reason) {
                try {
                    const response = await fetch('../API/update_allocation_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: allocationId,
                            status: 'rejected',
                            notes: reason
                        })
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        Swal.fire('Success!', 'Allocation rejected successfully', 'success');
                        closeAllocationModal();
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        Swal.fire('Error!', result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire('Error!', 'Failed to reject allocation', 'error');
                }
            }
        }

        async function submitCompliance(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const allocationId = formData.get('allocation_id');
            const complianceNotes = formData.get('compliance_notes');

            try {
                const response = await fetch('API/update_allocation_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: allocationId,
                        status: 'for_compliance',
                        compliance_notes: complianceNotes
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    Swal.fire('Success!', 'Allocation marked for compliance', 'success');
                    closeComplianceModal();
                    closeAllocationModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    Swal.fire('Error!', result.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error!', 'Failed to mark for compliance', 'error');
            }
        }

        async function confirmDisburse() {
            try {
                const response = await fetch('../API/update_allocation_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: currentAllocationData.id,
                        status: 'disbursed'
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    Swal.fire('Success!', 'Allocation disbursed successfully', 'success');
                    closeDisburseModal();
                    closeAllocationModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    Swal.fire('Error!', result.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error!', 'Failed to disburse allocation', 'error');
            }
        }

        // Filter allocations
        function filterAllocations() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const cards = document.querySelectorAll('.allocation-card');

            cards.forEach(card => {
                const department = card.getAttribute('data-department').toLowerCase();
                const status = card.getAttribute('data-status');
                const textContent = card.textContent.toLowerCase();

                const matchesSearch = department.includes(searchTerm) || textContent.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;

                if (matchesSearch && matchesStatus) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAllocationModal();
                closeComplianceModal();
                closeDisburseModal();
            }
        });
    </script>
</body>
</html>