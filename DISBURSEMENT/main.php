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

            
            <!-- Main Content -->
            <main class="p-6">
                <div class="container mx-auto">
                    <!-- Header -->
                    <div class="mb-8">
                        <h1 class="text-3xl font-bold text-gray-800">Disbursement Management</h1>
                        <p class="text-gray-600 mt-2">Manage and track all outgoing payments and fund allocations</p>
                    </div>

                    <!-- Disbursement Summary Cards -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                                <span class="p-2 mr-3 rounded-lg bg-blue-100/50 text-blue-600">
                                    <i data-lucide="wallet" class="w-5 h-5"></i>
                                </span>
                                Disbursement Overview
                            </h2>
                            <div class="flex gap-2">
                                <button onclick="openModal('disburseAllocationModal')" class="px-4 py-2 bg-blue-600 text-white rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors">
                                    <i data-lucide="dollar-sign" class="w-4 h-4"></i>
                                    Disburse Allocation
                                </button>
                                <button onclick="openModal('disbursementRequestModal')" class="px-4 py-2 bg-green-600 text-white rounded-lg flex items-center gap-2 hover:bg-green-700 transition-colors">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                    Disbursement Request
                                </button>
                                <button onclick="window.location.href='pending_disbursement.php'" class="px-4 py-2 bg-amber-500 text-white rounded-lg flex items-center gap-2 hover:bg-amber-600 transition-colors">
                                    <i data-lucide="clock" class="w-4 h-4"></i>
                                    Pending Disbursement
                                </button>
                            </div>
                        </div>

                        <!-- Disbursement Dashboard Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 h-full">
                            
                            <!-- Total Disbursements Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Total Disbursements</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            ₱2,847,500
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full" style="background:#F7B32B1A; color:#F7B32B;">
                                        <i data-lucide="wallet" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full" style="background:#F7B32B; width: 85%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>This month</span>
                                        <span class="font-medium">₱425,000</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Pending Requests Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Pending Requests</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            12
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                                        <i data-lucide="clock" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-amber-500 rounded-full" style="width: 60%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Awaiting approval</span>
                                        <span class="font-medium">₱185,000 total</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Approved This Month Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Approved This Month</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            ₱425,000
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
                                        <span class="font-medium">24 transactions</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Departments Allocation Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Departments</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                            10
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                        <i data-lucide="building" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-purple-500 rounded-full" style="width: 100%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Active departments</span>
                                        <span class="font-medium">All allocated</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Navigation Tabs -->
                    <div class="mb-6">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8">
                                <a href="#" class="border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Disbursement Request
                                </a>
                                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Pending Disbursement
                                </a>
                                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Disbursement Transactions
                                </a>
                                <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Disbursement Allocation
                                </a>
                            </nav>
                        </div>
                    </div>

                    <!-- Content Area -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm">
                        <div class="mb-6">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4">Disbursement Requests</h3>
                            <p class="text-gray-600">Manage and review all disbursement requests from various departments.</p>
                        </div>
                        
                        <!-- Disbursement Requests Table -->
                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">DIS-001</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">HR1</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱25,000</td>
                                            <td class="px-6 py-4 text-sm text-gray-500">Employee bonuses</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2024-01-15</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Approved</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                                                <button class="text-green-600 hover:text-green-900">Process</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">DIS-002</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Logistics 1</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱45,000</td>
                                            <td class="px-6 py-4 text-sm text-gray-500">Vehicle maintenance</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2024-01-14</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800">Pending</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                                                <button class="text-green-600 hover:text-green-900">Approve</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">DIS-003</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Financials</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱15,000</td>
                                            <td class="px-6 py-4 text-sm text-gray-500">Software subscription</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2024-01-13</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Rejected</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                                                <button class="text-gray-600 hover:text-gray-900">Review</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <!-- Department Allocation Overview -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm mt-6">
                        <div class="mb-6">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4">Department Allocation Overview</h3>
                            <p class="text-gray-600">Current budget allocation and utilization across departments.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <!-- HR Departments -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                    <i data-lucide="users" class="w-5 h-5 mr-2 text-blue-600"></i>
                                    HR Departments
                                </h4>
                                <div class="space-y-4">
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>HR1</span>
                                            <span>₱125,000 / ₱150,000</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: 83%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>HR2</span>
                                            <span>₱95,000 / ₱120,000</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: 79%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>HR3</span>
                                            <span>₱110,000 / ₱140,000</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: 78%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>HR4</span>
                                            <span>₱85,000 / ₱100,000</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: 85%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Logistics Departments -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                    <i data-lucide="truck" class="w-5 h-5 mr-2 text-green-600"></i>
                                    Logistics Departments
                                </h4>
                                <div class="space-y-4">
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Logistics 1</span>
                                            <span>₱200,000 / ₱250,000</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-green-600 h-2 rounded-full" style="width: 80%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Logistics 2</span>
                                            <span>₱175,000 / ₱220,000</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-green-600 h-2 rounded-full" style="width: 79%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Other Departments -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                    <i data-lucide="building" class="w-5 h-5 mr-2 text-purple-600"></i>
                                    Other Departments
                                </h4>
                                <div class="space-y-4">
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Administrative</span>
                                            <span>₱90,000 / ₱120,000</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-purple-600 h-2 rounded-full" style="width: 75%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Financials</span>
                                            <span>₱65,000 / ₱80,000</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-purple-600 h-2 rounded-full" style="width: 81%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Core 1</span>
                                            <span>₱150,000 / ₱180,000</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-purple-600 h-2 rounded-full" style="width: 83%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Core 2</span>
                                            <span>₱130,000 / ₱160,000</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-purple-600 h-2 rounded-full" style="width: 81%"></div>
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

    <!-- Disburse Allocation Modal -->
    <div id="disburseAllocationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Disburse Allocation</h3>
                    <button onclick="closeModal('disburseAllocationModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Department</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Choose department...</option>
                            <option>HR1</option>
                            <option>HR2</option>
                            <option>HR3</option>
                            <option>HR4</option>
                            <option>Logistics 1</option>
                            <option>Logistics 2</option>
                            <option>Administrative</option>
                            <option>Financials</option>
                            <option>Core 1</option>
                            <option>Core 2</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Allocation Amount (₱)</label>
                        <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="0.00">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quarter</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option>Q1 2024</option>
                            <option>Q2 2024</option>
                            <option>Q3 2024</option>
                            <option>Q4 2024</option>
                        </select>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                        <textarea rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Purpose of this allocation..."></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeModal('disburseAllocationModal')" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Allocate Funds
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Disbursement Request Modal -->
    <div id="disbursementRequestModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">New Disbursement Request</h3>
                    <button onclick="closeModal('disbursementRequestModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Requesting Department</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option>HR1</option>
                                <option>HR2</option>
                                <option>HR3</option>
                                <option>HR4</option>
                                <option>Logistics 1</option>
                                <option>Logistics 2</option>
                                <option>Administrative</option>
                                <option>Financials</option>
                                <option>Core 1</option>
                                <option>Core 2</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₱)</label>
                            <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="0.00">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option>Salaries & Benefits</option>
                            <option>Operations</option>
                            <option>Equipment</option>
                            <option>Maintenance</option>
                            <option>Travel & Accommodation</option>
                            <option>Supplies</option>
                            <option>Training & Development</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                        <textarea rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Detailed purpose of this disbursement request..."></textarea>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Supporting Documents</label>
                        <input type="file" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Upload supporting documents (PDF, JPG, PNG)</p>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeModal('disbursementRequestModal')" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            Submit Request
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