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
    <title>Budget Management | Travel & Tour System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .modal {
            transition: opacity 0.25s ease;
        }
        body {
            font-family: 'Inter', sans-serif;
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
        <main class="flex-1 overflow-auto p-4 md:p-6">

    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Budget Management</h1>
            <p class="text-gray-600 mt-2">Monitor, allocate, and track your travel budgets</p>
        </header>

        <!-- Budget Summary Cards -->
        <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <span class="p-2 mr-3 rounded-lg bg-blue-100/50 text-blue-600">
                        <i data-lucide="pie-chart" class="w-5 h-5"></i>
                    </span>
                    Budget Overview
                </h2>
                <div class="flex gap-2">
                    <button onclick="openModal('proposeBudgetModal')" class="px-4 py-2 bg-blue-600 text-white rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Propose Budget
                    </button>
                    <button onclick="openModal('allocateBudgetModal')" class="px-4 py-2 bg-green-600 text-white rounded-lg flex items-center gap-2 hover:bg-green-700 transition-colors">
                        <i data-lucide="dollar-sign" class="w-4 h-4"></i>
                        Allocate Budget
                    </button>
                    <button onclick="openModal('remainingBudgetModal')" class="px-4 py-2 bg-amber-500 text-white rounded-lg flex items-center gap-2 hover:bg-amber-600 transition-colors">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                        Check Remaining
                    </button>
                </div>
            </div>

            <!-- Budget Dashboard Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 h-full">
                
                <!-- Total Budget Card -->
                <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color:#001f54;">Total Budget</p>
                            <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                ₱2,450,000
                            </h3>
                        </div>
                        <div class="p-3 rounded-full" style="background:#F7B32B1A; color:#F7B32B;">
                            <i data-lucide="wallet" class="w-6 h-6"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full" style="background:#F7B32B; width: 100%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>Annual allocation</span>
                            <span class="font-medium">2024 Budget</span>
                        </div>
                    </div>
                </div>

                <!-- Allocated Budget Card -->
                <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color:#001f54;">Allocated</p>
                            <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                ₱1,870,000
                            </h3>
                        </div>
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i data-lucide="check-circle" class="w-6 h-6"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-green-500 rounded-full" style="width: 76%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>Distributed to departments</span>
                            <span class="font-medium">76% of total</span>
                        </div>
                    </div>
                </div>

                <!-- Remaining Budget Card -->
                <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color:#001f54;">Remaining</p>
                            <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                ₱580,000
                            </h3>
                        </div>
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i data-lucide="trending-up" class="w-6 h-6"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width: 24%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>Available for allocation</span>
                            <span class="font-medium">24% of total</span>
                        </div>
                    </div>
                </div>

                <!-- Pending Proposals Card -->
                <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" style="color:#001f54;">Pending Proposals</p>
                            <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                5
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
                            <span class="font-medium">₱320,000 total</span>
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
                        Budget Monitoring
                    </a>
                    <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Budget Allocating
                    </a>
                    <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Budget Proposal
                    </a>
                    <a href="#" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Budget Transactions
                    </a>
                </nav>
            </div>
        </div>

        <!-- Content Area -->
        <section class="glass-effect p-6 rounded-2xl shadow-sm">
            <div class="mb-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Budget Monitoring Dashboard</h3>
                <p class="text-gray-600">Track your budget performance across departments and projects.</p>
            </div>
            
            <!-- Placeholder for budget monitoring content -->
            <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
                <i data-lucide="bar-chart-3" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                <h4 class="text-lg font-medium text-gray-700 mb-2">Budget Monitoring Content</h4>
                <p class="text-gray-500 max-w-md mx-auto">Charts, tables, and visualizations for budget tracking will appear here.</p>
            </div>
        </section>
    </div>

    <!-- Propose Budget Modal -->
    <div id="proposeBudgetModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Propose New Budget</h3>
                    <button onclick="closeModal('proposeBudgetModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Budget Title</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., Q3 Marketing Campaign">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option>Marketing</option>
                            <option>Operations</option>
                            <option>Sales</option>
                            <option>Customer Service</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₱)</label>
                        <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="0.00">
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Brief description of the budget proposal..."></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeModal('proposeBudgetModal')" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Submit Proposal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Allocate Budget Modal -->
    <div id="allocateBudgetModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Allocate Budget</h3>
                    <button onclick="closeModal('allocateBudgetModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Department</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option>Marketing</option>
                            <option>Operations</option>
                            <option>Sales</option>
                            <option>Customer Service</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Allocation Amount (₱)</label>
                        <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="0.00">
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                        <textarea rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Purpose of this budget allocation..."></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeModal('allocateBudgetModal')" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            Allocate Budget
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Check Remaining Budget Modal -->
    <div id="remainingBudgetModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Remaining Budget</h3>
                    <button onclick="closeModal('remainingBudgetModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Total Annual Budget</span>
                        <span class="text-lg font-bold text-gray-800">₱2,450,000</span>
                    </div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Allocated Budget</span>
                        <span class="text-lg font-bold text-gray-800">₱1,870,000</span>
                    </div>
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-sm font-medium text-gray-700">Pending Proposals</span>
                        <span class="text-lg font-bold text-amber-600">₱320,000</span>
                    </div>
                    <div class="border-t border-gray-200 pt-4">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-gray-800">Available Budget</span>
                            <span class="text-2xl font-bold text-blue-600">₱580,000</span>
                        </div>
                    </div>
                </div>
                
                <h4 class="text-md font-medium text-gray-700 mb-3">Breakdown by Department</h4>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Marketing</span>
                        <span class="text-sm font-medium text-gray-800">₱120,000</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Operations</span>
                        <span class="text-sm font-medium text-gray-800">₱250,000</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Sales</span>
                        <span class="text-sm font-medium text-gray-800">₱150,000</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Customer Service</span>
                        <span class="text-sm font-medium text-gray-800">₱60,000</span>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button onclick="closeModal('remainingBudgetModal')" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Close
                    </button>
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
    </script>
        <script>
    // Update main content margin based on sidebar state
    function updateMainContentMargin() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        
        if (window.innerWidth >= 768) { // Desktop
            if (sidebar.classList.contains('w-20')) {
                mainContent.classList.remove('md:ml-64');
                mainContent.classList.add('md:ml-20');
            } else {
                mainContent.classList.remove('md:ml-20');
                mainContent.classList.add('md:ml-64');
            }
        } else {
            // Mobile - reset margins
            mainContent.classList.remove('md:ml-20', 'md:ml-64');
        }
    }

    // Call this when sidebar state changes
    function toggleSidebar() {
        // ... existing toggleSidebar code ...
        updateMainContentMargin();
    }

    function handleResize() {
        // ... existing handleResize code ...
        updateMainContentMargin();
    }

    // Initial call
    document.addEventListener('DOMContentLoaded', function() {
        updateMainContentMargin();
    });
    </script>
</body>
</html>