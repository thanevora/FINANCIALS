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
</head>
<body class="bg-base-100 min-h-screen bg-white">
  <div class="flex h-screen">
    <!-- Sidebar -->
    <?php include '../COMPONENTS/sidebar.php'; ?>

    <!-- Content Area -->
    <div class="flex flex-col flex-1 overflow-auto">
        <!-- Navbar -->
        <?php include '../COMPONENTS/navbar.php'; ?>
            <!-- Navbar is included above -->
            
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

                        <!-- Collections Dashboard Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 h-full">
                            
                            <!-- Total Collections Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Total Collections</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            ₱1,847,500
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full" style="background:#F7B32B1A; color:#F7B32B;">
                                        <i data-lucide="credit-card" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full" style="background:#F7B32B; width: 92%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>This month</span>
                                        <span class="font-medium">₱325,000</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Pending Collections Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Pending Collections</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            8
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                                        <i data-lucide="clock" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-amber-500 rounded-full" style="width: 40%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Awaiting payment</span>
                                        <span class="font-medium">₱85,000 total</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Successful This Month Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Successful This Month</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            ₱325,000
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                                        <i data-lucide="check-circle" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-green-500 rounded-full" style="width: 85%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Processed payments</span>
                                        <span class="font-medium">45 transactions</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Collection Rate Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Collection Rate</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            94.5%
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                        <i data-lucide="trending-up" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-purple-500 rounded-full" style="width: 94.5%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Success rate</span>
                                        <span class="font-medium">+2.3% from last month</span>
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
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">COL-001</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">John Travel Agency</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱45,000</td>
                                            <td class="px-6 py-4 text-sm text-gray-500">Bohol Tour Package</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2024-01-20</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Paid</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                                                <button class="text-green-600 hover:text-green-900">Receipt</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">COL-002</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Sarah Corporation</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱28,500</td>
                                            <td class="px-6 py-4 text-sm text-gray-500">Team Building Event</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2024-01-18</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800">Pending</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                                                <button class="text-green-600 hover:text-green-900">Remind</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">COL-003</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Mike Enterprises</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱67,000</td>
                                            <td class="px-6 py-4 text-sm text-gray-500">Corporate Retreat</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2024-01-15</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Overdue</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                                                <button class="text-red-600 hover:text-red-900">Escalate</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <!-- Transaction Status Overview -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm mt-6">
                        <div class="mb-6">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4">Collection Transactions Status</h3>
                            <p class="text-gray-600">Overview of all collection transactions by status.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                            <!-- Success Transactions -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-800 mb-1">Success</h4>
                                <p class="text-2xl font-bold text-green-600 mb-2">156</p>
                                <p class="text-sm text-gray-500">₱1,245,000</p>
                                <div class="mt-3">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: 78%"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Failed Transactions -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
                                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i data-lucide="x-circle" class="w-6 h-6 text-red-600"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-800 mb-1">Failed</h4>
                                <p class="text-2xl font-bold text-red-600 mb-2">8</p>
                                <p class="text-sm text-gray-500">₱42,000</p>
                                <div class="mt-3">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-red-500 h-2 rounded-full" style="width: 4%"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Refund Transactions -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i data-lucide="rotate-ccw" class="w-6 h-6 text-blue-600"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-800 mb-1">Refund</h4>
                                <p class="text-2xl font-bold text-blue-600 mb-2">12</p>
                                <p class="text-sm text-gray-500">₱65,000</p>
                                <div class="mt-3">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full" style="width: 6%"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pending Transactions -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
                                <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i data-lucide="clock" class="w-6 h-6 text-amber-600"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-800 mb-1">Pending</h4>
                                <p class="text-2xl font-bold text-amber-600 mb-2">15</p>
                                <p class="text-sm text-gray-500">₱85,000</p>
                                <div class="mt-3">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-amber-500 h-2 rounded-full" style="width: 7.5%"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- For Approval Transactions -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
                                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i data-lucide="shield-question" class="w-6 h-6 text-purple-600"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-800 mb-1">For Approval</h4>
                                <p class="text-2xl font-bold text-purple-600 mb-2">5</p>
                                <p class="text-sm text-gray-500">₱28,500</p>
                                <div class="mt-3">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-purple-500 h-2 rounded-full" style="width: 2.5%"></div>
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
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <!-- Credit Card -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                        <i data-lucide="credit-card" class="w-5 h-5 mr-2 text-blue-600"></i>
                                        Credit Card
                                    </h4>
                                    <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                        45%
                                    </span>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Total Amount</span>
                                            <span>₱562,500</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-500 h-2 rounded-full" style="width: 45%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Transactions</span>
                                            <span>89</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bank Transfer -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                        <i data-lucide="building" class="w-5 h-5 mr-2 text-green-600"></i>
                                        Bank Transfer
                                    </h4>
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                        35%
                                    </span>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Total Amount</span>
                                            <span>₱437,500</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-green-500 h-2 rounded-full" style="width: 35%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Transactions</span>
                                            <span>67</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Digital Wallet -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                        <i data-lucide="smartphone" class="w-5 h-5 mr-2 text-purple-600"></i>
                                        Digital Wallet
                                    </h4>
                                    <span class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">
                                        15%
                                    </span>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Total Amount</span>
                                            <span>₱187,500</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-purple-500 h-2 rounded-full" style="width: 15%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Transactions</span>
                                            <span>45</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cash -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                        <i data-lucide="dollar-sign" class="w-5 h-5 mr-2 text-amber-600"></i>
                                        Cash
                                    </h4>
                                    <span class="px-2 py-1 text-xs font-medium bg-amber-100 text-amber-800 rounded-full">
                                        5%
                                    </span>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Total Amount</span>
                                            <span>₱62,500</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-amber-500 h-2 rounded-full" style="width: 5%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Transactions</span>
                                            <span>23</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                <form>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                                <option>Select Customer</option>
                                <option>John Travel Agency</option>
                                <option>Sarah Corporation</option>
                                <option>Mike Enterprises</option>
                                <option>Travel Partners Co.</option>
                                <option>Adventure Seekers Ltd.</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₱)</label>
                            <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="0.00">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option>Tour Package</option>
                            <option>Hotel Booking</option>
                            <option>Transportation</option>
                            <option>Event Reservation</option>
                            <option>Custom Package</option>
                            <option>Other Service</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                        <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option>Credit Card</option>
                            <option>Bank Transfer</option>
                            <option>Digital Wallet</option>
                            <option>Cash</option>
                            <option>Check</option>
                        </select>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Additional details about this collection request..."></textarea>
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