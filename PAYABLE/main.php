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

// Get vendors for filter
$vendors = [];
$result = $conn->query("SELECT DISTINCT vendor_name FROM accounts_payable ORDER BY vendor_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $vendors[] = $row['vendor_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Accounts Payable'; ?> | System Name</title>
          <?php include '../COMPONENTS/header.php'; ?>

    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-declined { background: #fee2e2; color: #991b1b; }
        .status-paid { background: #dbeafe; color: #1e40af; }
        .status-for_compliance { background: #f5d0fe; color: #86198f; }
        
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
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
                        <h1 class="text-3xl font-bold text-gray-800">Accounts Payable</h1>
                        <p class="text-gray-600 mt-2">Manage and track all outgoing payments to vendors and suppliers</p>
                    </div>

                    <!-- AP Summary Cards -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                                <span class="p-2 mr-3 rounded-lg bg-red-100/50 text-red-600">
                                    <i data-lucide="trending-down" class="w-5 h-5"></i>
                                </span>
                                Payables Overview
                            </h2>
                            <div class="flex gap-2">
                                <button onclick="openNewBillModal()" class="px-4 py-2 bg-red-600 text-white rounded-lg flex items-center gap-2 hover:bg-red-700 transition-colors">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                    New Bill
                                </button>
                            </div>
                        </div>

                        <!-- AP Dashboard Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 h-full">
                            
                            <!-- Total Payables Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Total Payables</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            ₱<span id="totalPayables">0</span>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full" style="background:#F7B32B1A; color:#F7B32B;">
                                        <i data-lucide="dollar-sign" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full" style="background:#F7B32B; width: 75%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Active bills</span>
                                        <span class="font-medium"><span id="activeBills">0</span> bills</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Due This Week Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Due This Week</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            ₱<span id="dueThisWeek">0</span>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                                        <i data-lucide="calendar" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-amber-500 rounded-full" style="width: 55%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span><span id="dueBillsCount">0</span> bills due</span>
                                        <span class="font-medium">7 days</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Overdue Amount Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Overdue Amount</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            ₱<span id="overdueAmount">0</span>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                                        <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-red-500 rounded-full" style="width: 25%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span><span id="overdueBills">0</span> overdue bills</span>
                                        <span class="font-medium">10% of total</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Paid This Month Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Paid This Month</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            ₱<span id="paidThisMonth">0</span>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                                        <i data-lucide="check-circle" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-green-500 rounded-full" style="width: 65%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Processed payments</span>
                                        <span class="font-medium"><span id="processedPayments">0</span> payments</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Navigation Tabs -->
                    <div class="mb-6">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8">
                                <a href="#" class="border-red-500 text-red-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm tab-link active" data-status="">
                                    All Bills
                                </a>
                                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm tab-link" data-status="pending">
                                    Due Soon
                                </a>
                                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm tab-link" data-status="overdue">
                                    Overdue
                                </a>
                                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm tab-link" data-status="paid">
                                    Paid
                                </a>
                            </nav>
                        </div>
                    </div>

                    <!-- Filters and Search -->
                    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                        <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
                            <div class="flex-1 w-full md:w-auto">
                                <div class="relative">
                                    <i data-lucide="search" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                                    <input type="text" id="searchInput" placeholder="Search by vendor, invoice, or description..." 
                                           class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg w-full focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap gap-2 w-full md:w-auto">
                                <select id="statusFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="declined">Declined</option>
                                    <option value="paid">Paid</option>
                                    <option value="for_compliance">For Compliance</option>
                                </select>
                                
                                <select id="vendorFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                    <option value="">All Vendors</option>
                                    <?php foreach($vendors as $vendor): ?>
                                        <option value="<?php echo htmlspecialchars($vendor); ?>"><?php echo htmlspecialchars($vendor); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <button onclick="resetFilters()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2">
                                    <i data-lucide="filter-x" class="w-4 h-4"></i>
                                    Reset
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Payables Table -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="payablesTable" class="bg-white divide-y divide-gray-200">
                                    <!-- Data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Loading State -->
                        <div id="loadingState" class="p-8 text-center">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-red-600"></div>
                            <p class="mt-2 text-gray-500">Loading payables...</p>
                        </div>
                        
                        <!-- Empty State -->
                        <div id="emptyState" class="hidden p-8 text-center">
                            <i data-lucide="file-text" class="w-12 h-12 text-gray-300 mx-auto"></i>
                            <h3 class="mt-4 text-lg font-medium text-gray-900">No payables found</h3>
                            <p class="mt-1 text-gray-500">Get started by adding a new bill.</p>
                        </div>
                        
                        <!-- Pagination -->
                        <div id="pagination" class="hidden border-t border-gray-200 px-4 py-3 sm:px-6">
                            <!-- Pagination will be loaded here -->
                        </div>
                    </div>
                </div>
            </main>
    </div>
</div>

<!-- New Bill Modal -->
<div id="newBillModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-900">Add New Bill</h3>
                <button onclick="closeModal('newBillModal')" class="text-gray-400 hover:text-gray-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form id="newBillForm">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vendor Name</label>
                        <input type="text" name="vendor_name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Number</label>
                        <input type="text" name="invoice_number" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Date</label>
                            <input type="date" name="invoice_date" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                            <input type="date" name="due_date" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">₱</span>
                            <input type="number" name="amount" step="0.01" required 
                                   class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"></textarea>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('newBillModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        Add Bill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal with CRUD Actions in Footer -->
<div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">Payable Details</h3>
                <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-gray-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>
        
        <!-- Modal Content -->
        <div class="p-6 overflow-y-auto flex-1">
            <div id="viewModalContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
        
        <!-- Modal Footer with CRUD Actions -->
        <div class="p-6 border-t border-gray-200 bg-gray-50">
            <div class="flex flex-wrap gap-2 justify-end" id="viewModalActions">
                <!-- Action buttons will be loaded here based on payable status -->
            </div>
        </div>
    </div>
</div>

<!-- Process Payment Modal -->
<div id="processPaymentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-900">Process Payment</h3>
                <button onclick="closeModal('processPaymentModal')" class="text-gray-400 hover:text-gray-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form id="paymentForm">
                <input type="hidden" id="paymentPayableId" name="id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select name="payment_method" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="check">Check</option>
                            <option value="cash">Cash</option>
                            <option value="online">Online Payment</option>
                        </select>
                    </div>
                    
                    <div id="bankAccountField" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bank Account</label>
                        <input type="text" name="bank_account" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    </div>
                    
                    <div id="checkNumberField" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Check Number</label>
                        <input type="text" name="check_number" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                        <input type="date" name="payment_date" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('processPaymentModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        Process Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Decline Modal -->
<div id="declineModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-900">Decline Payable</h3>
                <button onclick="closeModal('declineModal')" class="text-gray-400 hover:text-gray-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form id="declineForm">
                <input type="hidden" id="declinePayableId" name="id">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Decline</label>
                    <textarea name="reason" rows="4" required 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                              placeholder="Provide detailed reason for declining this payable..."></textarea>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('declineModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        Decline Payable
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Compliance Review Modal -->
<div id="complianceModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-900">Send for Compliance Review</h3>
                <button onclick="closeModal('complianceModal')" class="text-gray-400 hover:text-gray-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form id="complianceForm">
                <input type="hidden" id="compliancePayableId" name="id">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Compliance Notes</label>
                    <textarea name="notes" rows="4" required 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                              placeholder="Add notes for compliance review..."></textarea>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('complianceModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                        Send for Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Initialize Lucide icons
    lucide.createIcons();
    
    // Global variables
    let currentPage = 1;
    let currentStatus = '';
    let currentSearch = '';
    let currentVendor = '';
    let currentPayableId = null;
    let currentPayableStatus = '';
    
    // Load payables on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadPayables();
        updateDashboard();
        
        // Setup event listeners
        setupEventListeners();
        
        // Set default dates
        const today = new Date().toISOString().split('T')[0];
        const invoiceDateInput = document.querySelector('[name="invoice_date"]');
        const dueDateInput = document.querySelector('[name="due_date"]');
        const paymentDateInput = document.querySelector('[name="payment_date"]');
        
        if (invoiceDateInput) invoiceDateInput.value = today;
        if (dueDateInput) dueDateInput.value = today;
        if (paymentDateInput) paymentDateInput.value = today;
    });
    
    function setupEventListeners() {
        // Search input
        document.getElementById('searchInput').addEventListener('input', debounce(function(e) {
            currentSearch = e.target.value;
            currentPage = 1;
            loadPayables();
        }, 500));
        
        // Status filter
        document.getElementById('statusFilter').addEventListener('change', function(e) {
            currentStatus = e.target.value;
            currentPage = 1;
            loadPayables();
        });
        
        // Vendor filter
        document.getElementById('vendorFilter').addEventListener('change', function(e) {
            currentVendor = e.target.value;
            currentPage = 1;
            loadPayables();
        });
        
        // Tab links
        document.querySelectorAll('.tab-link').forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.tab-link').forEach(t => {
                    t.classList.remove('active', 'border-red-500', 'text-red-600');
                    t.classList.add('border-transparent', 'text-gray-500');
                });
                
                this.classList.add('active', 'border-red-500', 'text-red-600');
                this.classList.remove('border-transparent', 'text-gray-500');
                
                currentStatus = this.dataset.status;
                currentPage = 1;
                loadPayables();
            });
        });
        
        // Payment method change
        const paymentMethodSelect = document.querySelector('[name="payment_method"]');
        if (paymentMethodSelect) {
            paymentMethodSelect.addEventListener('change', function(e) {
                const bankField = document.getElementById('bankAccountField');
                const checkField = document.getElementById('checkNumberField');
                
                if (bankField) bankField.classList.add('hidden');
                if (checkField) checkField.classList.add('hidden');
                
                if (e.target.value === 'bank_transfer' && bankField) {
                    bankField.classList.remove('hidden');
                } else if (e.target.value === 'check' && checkField) {
                    checkField.classList.remove('hidden');
                }
            });
        }
        
        // Form submissions
        const newBillForm = document.getElementById('newBillForm');
        if (newBillForm) {
            newBillForm.addEventListener('submit', function(e) {
                e.preventDefault();
                addNewBill();
            });
        }
        
        const paymentForm = document.getElementById('paymentForm');
        if (paymentForm) {
            paymentForm.addEventListener('submit', function(e) {
                e.preventDefault();
                processPayment();
            });
        }
        
        const declineForm = document.getElementById('declineForm');
        if (declineForm) {
            declineForm.addEventListener('submit', function(e) {
                e.preventDefault();
                declinePayable();
            });
        }
        
        const complianceForm = document.getElementById('complianceForm');
        if (complianceForm) {
            complianceForm.addEventListener('submit', function(e) {
                e.preventDefault();
                sendForCompliance();
            });
        }
    }
    
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    function loadPayables() {
        const tableBody = document.getElementById('payablesTable');
        const loading = document.getElementById('loadingState');
        const empty = document.getElementById('emptyState');
        const pagination = document.getElementById('pagination');
        
        tableBody.innerHTML = '';
        loading.classList.remove('hidden');
        empty.classList.add('hidden');
        pagination.classList.add('hidden');
        
        const formData = new FormData();
        formData.append('action', 'fetch_payables');
        formData.append('page', currentPage);
        formData.append('search', currentSearch);
        formData.append('status', currentStatus);
        formData.append('vendor', currentVendor);
        
        fetch('API/API_fetching.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            loading.classList.add('hidden');
            
            if (data.success && data.data.length > 0) {
                data.data.forEach(payable => {
                    const row = createPayableRow(payable);
                    tableBody.appendChild(row);
                });
                
                updatePagination(data.total_pages, data.total);
                pagination.classList.remove('hidden');
            } else {
                empty.classList.remove('hidden');
            }
            
            // Refresh icons
            lucide.createIcons();
        })
        .catch(error => {
            loading.classList.add('hidden');
            Swal.fire('Error', 'Failed to load payables', 'error');
            console.error('Load payables error:', error);
        });
    }
    
    function createPayableRow(payable) {
        const row = document.createElement('tr');
        
        // Format dates
        const invoiceDate = new Date(payable.invoice_date).toLocaleDateString();
        const dueDate = new Date(payable.due_date);
        const formattedDueDate = dueDate.toLocaleDateString();
        
        // Check if overdue
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const isOverdue = dueDate < today && payable.status !== 'paid';
        
        // Status badge
        let statusBadge = '';
        switch(payable.status) {
            case 'pending':
                statusBadge = '<span class="status-badge status-pending">Pending</span>';
                break;
            case 'approved':
                statusBadge = '<span class="status-badge status-approved">Approved</span>';
                break;
            case 'declined':
                statusBadge = '<span class="status-badge status-declined">Declined</span>';
                break;
            case 'paid':
                statusBadge = '<span class="status-badge status-paid">Paid</span>';
                break;
            case 'for_compliance':
                statusBadge = '<span class="status-badge status-for_compliance">Compliance</span>';
                break;
        }
        
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">${payable.invoice_number || ''}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${payable.vendor_name || ''}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-500">${invoiceDate}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-2">
                    <div class="text-sm ${isOverdue ? 'text-red-600 font-medium' : 'text-gray-500'}">
                        ${formattedDueDate}
                    </div>
                    ${isOverdue ? '<i data-lucide="alert-circle" class="w-4 h-4 text-red-500"></i>' : ''}
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">₱${parseFloat(payable.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                ${statusBadge}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex items-center gap-2">
                    <button onclick="viewPayable(${payable.id})" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors">
                        <i data-lucide="eye" class="w-4 h-4"></i> View
                    </button>
                </div>
            </td>
        `;
        
        return row;
    }
    
    function viewPayable(id) {
        currentPayableId = id;
        const formData = new FormData();
        formData.append('action', 'get_payable_details');
        formData.append('id', id);
        
        fetch('API/API_fetching.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const payable = data.payable;
                const logs = data.logs;
                currentPayableStatus = payable.status;
                
                // Build details content
                let content = `
                    <div class="space-y-6">
                        <!-- Header with Status -->
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">${payable.invoice_number || ''}</h4>
                                <p class="text-sm text-gray-500">${payable.vendor_name || ''}</p>
                            </div>
                            <div class="inline-block status-badge status-${payable.status}">
                                ${payable.status.replace('_', ' ').toUpperCase()}
                            </div>
                        </div>
                        
                        <!-- Main Details Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Left Column -->
                            <div class="space-y-4">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h5 class="text-sm font-medium text-gray-700 mb-2">Invoice Information</h5>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-600">Invoice Date:</span>
                                            <span class="text-sm font-medium">${new Date(payable.invoice_date).toLocaleDateString()}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-600">Due Date:</span>
                                            <span class="text-sm font-medium ${new Date(payable.due_date) < new Date() && payable.status !== 'paid' ? 'text-red-600' : ''}">
                                                ${new Date(payable.due_date).toLocaleDateString()}
                                                ${new Date(payable.due_date) < new Date() && payable.status !== 'paid' ? '<i data-lucide="alert-circle" class="w-4 h-4 inline ml-1"></i>' : ''}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-600">Amount:</span>
                                            <span class="text-lg font-bold text-gray-900">₱${formatCurrency(payable.amount)}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                ${payable.description ? `
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h5 class="text-sm font-medium text-gray-700 mb-2">Description</h5>
                                    <p class="text-sm text-gray-700">${payable.description}</p>
                                </div>
                                ` : ''}
                            </div>
                            
                            <!-- Right Column -->
                            <div class="space-y-4">
                                ${payable.payment_method || payable.bank_account || payable.check_number ? `
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h5 class="text-sm font-medium text-gray-700 mb-2">Payment Details</h5>
                                    <div class="space-y-2">
                                        ${payable.payment_method ? `
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-600">Method:</span>
                                            <span class="text-sm font-medium">${payable.payment_method.replace('_', ' ').toUpperCase()}</span>
                                        </div>
                                        ` : ''}
                                        ${payable.bank_account ? `
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-600">Bank Account:</span>
                                            <span class="text-sm">${payable.bank_account}</span>
                                        </div>
                                        ` : ''}
                                        ${payable.check_number ? `
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-600">Check #:</span>
                                            <span class="text-sm">${payable.check_number}</span>
                                        </div>
                                        ` : ''}
                                        ${payable.payment_date ? `
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-600">Payment Date:</span>
                                            <span class="text-sm">${new Date(payable.payment_date).toLocaleDateString()}</span>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                ` : ''}
                                
                                ${payable.declined_reason || payable.compliance_notes ? `
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h5 class="text-sm font-medium text-gray-700 mb-2">Notes</h5>
                                    <div class="space-y-2">
                                        ${payable.declined_reason ? `
                                        <div>
                                            <span class="text-xs font-medium text-red-600">Decline Reason:</span>
                                            <p class="text-sm text-gray-700 mt-1">${payable.declined_reason}</p>
                                        </div>
                                        ` : ''}
                                        ${payable.compliance_notes ? `
                                        <div>
                                            <span class="text-xs font-medium text-purple-600">Compliance Notes:</span>
                                            <p class="text-sm text-gray-700 mt-1">${payable.compliance_notes}</p>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <!-- Activity Log -->
                        <div class="border-t pt-6">
                            <h5 class="text-sm font-medium text-gray-700 mb-4">Activity Log</h5>
                            <div class="space-y-3 max-h-48 overflow-y-auto pr-2">
                `;
                
                if (logs && logs.length > 0) {
                    logs.forEach(log => {
                        const date = new Date(log.created_at).toLocaleString();
                        let icon = 'clock';
                        let color = 'bg-gray-100';
                        
                        if (log.action.includes('payment')) {
                            icon = 'credit-card';
                            color = 'bg-green-100 text-green-600';
                        } else if (log.action.includes('decline')) {
                            icon = 'x-circle';
                            color = 'bg-red-100 text-red-600';
                        } else if (log.action.includes('compliance')) {
                            icon = 'shield-alert';
                            color = 'bg-purple-100 text-purple-600';
                        }
                        
                        content += `
                            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                <div class="p-2 rounded-full ${color}">
                                    <i data-lucide="${icon}" class="w-4 h-4"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <span class="text-sm font-medium">${log.action ? log.action.replace('_', ' ').toUpperCase() : ''}</span>
                                            <p class="text-sm text-gray-600 mt-1">${log.notes || ''}</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-xs text-gray-500 whitespace-nowrap">${date}</span>
                                            <p class="text-xs text-gray-400 mt-1">By: ${log.performed_by || ''}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    content += `
                        <div class="text-center py-8 text-gray-500">
                            <i data-lucide="clock" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                            <p class="text-sm">No activity recorded</p>
                        </div>
                    `;
                }
                
                content += `
                            </div>
                        </div>
                    </div>
                `;
                
                document.getElementById('viewModalContent').innerHTML = content;
                updateActionButtons(payable);
                openModal('viewModal');
                lucide.createIcons();
            } else {
                Swal.fire('Error', data.message || 'Failed to load payable details', 'error');
            }
        })
        .catch(error => {
            Swal.fire('Error', 'Failed to load payable details', 'error');
            console.error('View payable error:', error);
        });
    }
    
    function updateActionButtons(payable) {
        const actionsContainer = document.getElementById('viewModalActions');
        if (!actionsContainer) return;
        
        let buttons = '';
        
        switch(payable.status) {
            case 'pending':
                buttons = `
                    <button onclick="openDeclineModal(${payable.id})" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2">
                        <i data-lucide="x-circle" class="w-4 h-4"></i> Decline
                    </button>
                    <button onclick="openComplianceModal(${payable.id})" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center gap-2">
                        <i data-lucide="shield-alert" class="w-4 h-4"></i> For Compliance
                    </button>
                    <button onclick="closeModal('viewModal');" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Close
                    </button>
                `;
                break;
                
            case 'approved':
                buttons = `
                    <button onclick="openPaymentModal(${payable.id})" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                        <i data-lucide="credit-card" class="w-4 h-4"></i> Process Payment
                    </button>
                    <button onclick="closeModal('viewModal');" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Close
                    </button>
                `;
                break;
                
            case 'for_compliance':
                buttons = `
                    <button onclick="openPaymentModal(${payable.id})" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                        <i data-lucide="credit-card" class="w-4 h-4"></i> Process Payment
                    </button>
                    <button onclick="openDeclineModal(${payable.id})" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2">
                        <i data-lucide="x-circle" class="w-4 h-4"></i> Decline
                    </button>
                    <button onclick="closeModal('viewModal');" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Close
                    </button>
                `;
                break;
                
            case 'declined':
            case 'paid':
                buttons = `
                    <button onclick="closeModal('viewModal');" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        Close
                    </button>
                `;
                break;
        }
        
        actionsContainer.innerHTML = buttons;
        lucide.createIcons();
    }
    
    function updateDashboard() {
        fetch('API/API_fetching.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_dashboard_stats'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const stats = data.stats;
                document.getElementById('totalPayables').textContent = formatCurrency(stats.total_payables);
                document.getElementById('activeBills').textContent = stats.active_bills;
                document.getElementById('dueThisWeek').textContent = formatCurrency(stats.due_this_week);
                document.getElementById('dueBillsCount').textContent = stats.due_bills_count;
                document.getElementById('overdueAmount').textContent = formatCurrency(stats.overdue_amount);
                document.getElementById('overdueBills').textContent = stats.overdue_bills;
                document.getElementById('paidThisMonth').textContent = formatCurrency(stats.paid_this_month);
                document.getElementById('processedPayments').textContent = stats.processed_payments;
            }
        })
        .catch(error => {
            console.error('Error loading dashboard stats:', error);
        });
    }
    
    function formatCurrency(amount) {
        return parseFloat(amount || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    function updatePagination(totalPages, totalItems) {
        const paginationDiv = document.getElementById('pagination');
        
        if (totalPages <= 1) {
            paginationDiv.classList.add('hidden');
            return;
        }
        
        let paginationHTML = `
            <div class="flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    <button onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''} 
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md ${currentPage === 1 ? 'bg-gray-100 text-gray-400' : 'text-gray-700 bg-white hover:bg-gray-50'}">
                        Previous
                    </button>
                    <button onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''} 
                            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md ${currentPage === totalPages ? 'bg-gray-100 text-gray-400' : 'text-gray-700 bg-white hover:bg-gray-50'}">
                        Next
                    </button>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium">${((currentPage - 1) * 15) + 1}</span> to 
                            <span class="font-medium">${Math.min(currentPage * 15, totalItems)}</span> of 
                            <span class="font-medium">${totalItems}</span> results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
        `;
        
        // Previous button
        paginationHTML += `
            <button onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}
                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium ${currentPage === 1 ? 'text-gray-300' : 'text-gray-500 hover:bg-gray-50'}">
                <span class="sr-only">Previous</span>
                <i data-lucide="chevron-left" class="w-5 h-5"></i>
            </button>
        `;
        
        // Page numbers
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        if (startPage > 1) {
            paginationHTML += `
                <button onclick="changePage(1)"
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    1
                </button>
            `;
            if (startPage > 2) {
                paginationHTML += `
                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                        ...
                    </span>
                `;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            paginationHTML += `
                <button onclick="changePage(${i})"
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium ${currentPage === i ? 'z-10 bg-red-50 border-red-500 text-red-600' : 'text-gray-500 hover:bg-gray-50'}">
                    ${i}
                </button>
            `;
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                paginationHTML += `
                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                        ...
                    </span>
                `;
            }
            paginationHTML += `
                <button onclick="changePage(${totalPages})"
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    ${totalPages}
                </button>
            `;
        }
        
        // Next button
        paginationHTML += `
            <button onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}
                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium ${currentPage === totalPages ? 'text-gray-300' : 'text-gray-500 hover:bg-gray-50'}">
                <span class="sr-only">Next</span>
                <i data-lucide="chevron-right" class="w-5 h-5"></i>
            </button>
        `;
        
        paginationHTML += `
                        </nav>
                    </div>
                </div>
            </div>
        `;
        
        paginationDiv.innerHTML = paginationHTML;
        lucide.createIcons();
    }
    
    function changePage(page) {
        if (page < 1 || page > currentPage * 100) return;
        currentPage = page;
        loadPayables();
    }
    
    function resetFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('vendorFilter').value = '';
        
        // Reset tabs
        document.querySelectorAll('.tab-link').forEach(t => {
            t.classList.remove('active', 'border-red-500', 'text-red-600');
            t.classList.add('border-transparent', 'text-gray-500');
        });
        const allBillsTab = document.querySelector('.tab-link[data-status=""]');
        if (allBillsTab) {
            allBillsTab.classList.add('active', 'border-red-500', 'text-red-600');
            allBillsTab.classList.remove('border-transparent', 'text-gray-500');
        }
        
        currentSearch = '';
        currentStatus = '';
        currentVendor = '';
        currentPage = 1;
        
        loadPayables();
    }
    
    function openNewBillModal() {
        const form = document.getElementById('newBillForm');
        if (form) form.reset();
        const today = new Date().toISOString().split('T')[0];
        const invoiceDateInput = document.querySelector('[name="invoice_date"]');
        const dueDateInput = document.querySelector('[name="due_date"]');
        if (invoiceDateInput) invoiceDateInput.value = today;
        if (dueDateInput) dueDateInput.value = today;
        openModal('newBillModal');
    }
    
    function openPaymentModal(id) {
        closeModal('viewModal');
        setTimeout(() => {
            const payableIdInput = document.getElementById('paymentPayableId');
            if (payableIdInput) payableIdInput.value = id;
            const paymentForm = document.getElementById('paymentForm');
            if (paymentForm) paymentForm.reset();
            const paymentDateInput = document.querySelector('[name="payment_date"]');
            if (paymentDateInput) paymentDateInput.valueAsDate = new Date();
            const bankField = document.getElementById('bankAccountField');
            const checkField = document.getElementById('checkNumberField');
            if (bankField) bankField.classList.add('hidden');
            if (checkField) checkField.classList.add('hidden');
            openModal('processPaymentModal');
        }, 300);
    }
    
    function openDeclineModal(id) {
        closeModal('viewModal');
        setTimeout(() => {
            const payableIdInput = document.getElementById('declinePayableId');
            if (payableIdInput) payableIdInput.value = id;
            const declineForm = document.getElementById('declineForm');
            if (declineForm) declineForm.reset();
            openModal('declineModal');
        }, 300);
    }
    
    function openComplianceModal(id) {
        closeModal('viewModal');
        setTimeout(() => {
            const payableIdInput = document.getElementById('compliancePayableId');
            if (payableIdInput) payableIdInput.value = id;
            const complianceForm = document.getElementById('complianceForm');
            if (complianceForm) complianceForm.reset();
            openModal('complianceModal');
        }, 300);
    }
    
    function addNewBill() {
        const form = document.getElementById('newBillForm');
        if (!form) return;
        
        const formData = new FormData(form);
        formData.append('action', 'add_payable');
        
        fetch('API/API_fetching.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                closeModal('newBillModal');
                loadPayables();
                updateDashboard();
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            Swal.fire('Error', 'Failed to add bill', 'error');
            console.error('Add bill error:', error);
        });
    }
    
    function processPayment() {
        const form = document.getElementById('paymentForm');
        if (!form) return;
        
        const formData = new FormData(form);
        formData.append('action', 'process_payment');
        
        fetch('API/API_fetching.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                closeModal('processPaymentModal');
                loadPayables();
                updateDashboard();
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            Swal.fire('Error', 'Failed to process payment', 'error');
            console.error('Process payment error:', error);
        });
    }
    
    function declinePayable() {
        const form = document.getElementById('declineForm');
        if (!form) return;
        
        const formData = new FormData(form);
        formData.append('action', 'decline_payable');
        
        fetch('API/API_fetching.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                closeModal('declineModal');
                loadPayables();
                updateDashboard();
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            Swal.fire('Error', 'Failed to decline payable', 'error');
            console.error('Decline payable error:', error);
        });
    }
    
    function sendForCompliance() {
        const form = document.getElementById('complianceForm');
        if (!form) return;
        
        const formData = new FormData(form);
        formData.append('action', 'compliance_review');
        
        fetch('API/API_fetching.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                closeModal('complianceModal');
                loadPayables();
                updateDashboard();
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            Swal.fire('Error', 'Failed to send for compliance', 'error');
            console.error('Compliance error:', error);
        });
    }
    
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
    }
    
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    }
</script>
</body>
</html>