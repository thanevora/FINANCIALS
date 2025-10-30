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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-800">General Ledger</h1>
                                <p class="text-gray-600 mt-2">Complete financial overview and transaction records across all modules</p>
                            </div>
                            <div class="flex gap-2">
                                <!-- Date Range Filter -->
                                <div class="flex items-center gap-2 bg-white border border-gray-300 rounded-lg px-3 py-2">
                                    <i data-lucide="calendar" class="w-4 h-4 text-gray-500"></i>
                                    <select class="border-none focus:outline-none focus:ring-0 text-sm">
                                        <option>Last 7 days</option>
                                        <option>Last 30 days</option>
                                        <option>Last 90 days</option>
                                        <option>This Month</option>
                                        <option>This Quarter</option>
                                        <option>This Year</option>
                                        <option>Custom Range</option>
                                    </select>
                                </div>

                                <!-- Export Dropdown -->
                                <div class="relative group">
                                    <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                        <i data-lucide="download" class="w-4 h-4"></i>
                                        Export Data
                                        <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                    </button>
                                    <div class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-10 hidden group-hover:block">
                                        <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Export Modules</div>
                                        <div class="space-y-1">
                                            <label class="flex items-center px-4 py-2 hover:bg-gray-50 cursor-pointer">
                                                <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3" data-module="disbursements">
                                                <span class="text-sm text-gray-700">Disbursements</span>
                                            </label>
                                            <label class="flex items-center px-4 py-2 hover:bg-gray-50 cursor-pointer">
                                                <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3" data-module="collections">
                                                <span class="text-sm text-gray-700">Collections</span>
                                            </label>
                                            <label class="flex items-center px-4 py-2 hover:bg-gray-50 cursor-pointer">
                                                <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3" data-module="budget">
                                                <span class="text-sm text-gray-700">Budget Management</span>
                                            </label>
                                            <label class="flex items-center px-4 py-2 hover:bg-gray-50 cursor-pointer">
                                                <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3" data-module="receivable">
                                                <span class="text-sm text-gray-700">Accounts Receivable</span>
                                            </label>
                                            <label class="flex items-center px-4 py-2 hover:bg-gray-50 cursor-pointer">
                                                <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3" data-module="payable">
                                                <span class="text-sm text-gray-700">Accounts Payable</span>
                                            </label>
                                        </div>
                                        <div class="border-t border-gray-200 mt-2 pt-2 px-4">
                                            <div class="flex gap-2">
                                                <button onclick="exportData('pdf')" class="flex-1 px-3 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition-colors">
                                                    PDF
                                                </button>
                                                <button onclick="exportData('excel')" class="flex-1 px-3 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition-colors">
                                                    Excel
                                                </button>
                                                <button onclick="exportData('csv')" class="flex-1 px-3 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors">
                                                    CSV
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Overview Cards -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 flex items-center mb-6">
                            <span class="p-2 mr-3 rounded-lg bg-blue-100/50 text-blue-600">
                                <i data-lucide="trending-up" class="w-5 h-5"></i>
                            </span>
                            Financial Overview
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 h-full">
                            
                            <!-- Total Revenue Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Total Revenue</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800" id="totalRevenue">
                                            ₱0
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                                        <i data-lucide="trending-up" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-green-500 rounded-full" id="revenueProgress" style="width: 0%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Collections</span>
                                        <span class="font-medium" id="revenueCount">0 transactions</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Total Expenses Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Total Expenses</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800" id="totalExpenses">
                                            ₱0
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                                        <i data-lucide="trending-down" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-red-500 rounded-full" id="expensesProgress" style="width: 0%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Disbursements</span>
                                        <span class="font-medium" id="expensesCount">0 transactions</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Net Income Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Net Income</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800" id="netIncome">
                                            ₱0
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                        <i data-lucide="dollar-sign" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-blue-500 rounded-full" id="incomeProgress" style="width: 0%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Profit/Loss</span>
                                        <span class="font-medium" id="incomeStatus">Calculating...</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Active Accounts Card -->
                            <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Active Accounts</p>
                                        <h3 class="text-3xl font-bold mt-1 text-gray-800" id="activeAccounts">
                                            0
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                        <i data-lucide="users" class="w-6 h-6"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-purple-500 rounded-full" id="accountsProgress" style="width: 0%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>AR & AP Accounts</span>
                                        <span class="font-medium" id="accountsStatus">0 active</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Charts Section -->
                    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Revenue vs Expenses Chart -->
                        <div class="glass-effect p-6 rounded-2xl shadow-sm">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-xl font-semibold text-gray-800">Revenue vs Expenses</h3>
                                <select class="text-sm border border-gray-300 rounded-lg px-3 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="updateChartData('revenueExpensesChart', this.value)">
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            <div class="h-80">
                                <canvas id="revenueExpensesChart"></canvas>
                            </div>
                        </div>

                        <!-- Module Distribution Chart -->
                        <div class="glass-effect p-6 rounded-2xl shadow-sm">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-xl font-semibold text-gray-800">Transaction Distribution</h3>
                                <select class="text-sm border border-gray-300 rounded-lg px-3 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="updateChartData('moduleDistributionChart', this.value)">
                                    <option value="count">By Count</option>
                                    <option value="amount">By Amount</option>
                                </select>
                            </div>
                            <div class="h-80">
                                <canvas id="moduleDistributionChart"></canvas>
                            </div>
                        </div>
                    </section>

                    <!-- Account Balances Section -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-semibold text-gray-800">Account Balances</h3>
                            <div class="flex gap-2">
                                <button onclick="refreshAccountData()" class="flex items-center gap-2 px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                    Refresh
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <!-- Accounts Receivable -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                        <i data-lucide="trending-up" class="w-5 h-5 mr-2 text-green-600"></i>
                                        Accounts Receivable
                                    </h4>
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full" id="arStatus">
                                        0 Active
                                    </span>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Total Outstanding</span>
                                            <span id="arTotal">₱0</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-green-500 h-2 rounded-full" id="arProgress" style="width: 0%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Overdue</span>
                                            <span id="arOverdue">₱0</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-red-500 h-2 rounded-full" id="arOverdueProgress" style="width: 0%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Accounts Payable -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                        <i data-lucide="trending-down" class="w-5 h-5 mr-2 text-red-600"></i>
                                        Accounts Payable
                                    </h4>
                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full" id="apStatus">
                                        0 Pending
                                    </span>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Total Payable</span>
                                            <span id="apTotal">₱0</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-red-500 h-2 rounded-full" id="apProgress" style="width: 0%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Due This Month</span>
                                            <span id="apDue">₱0</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-amber-500 h-2 rounded-full" id="apDueProgress" style="width: 0%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Budget Overview -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                        <i data-lucide="pie-chart" class="w-5 h-5 mr-2 text-blue-600"></i>
                                        Budget Overview
                                    </h4>
                                    <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full" id="budgetStatus">
                                        0 Allocated
                                    </span>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Total Allocated</span>
                                            <span id="budgetAllocated">₱0</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-500 h-2 rounded-full" id="budgetAllocatedProgress" style="width: 0%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Total Utilized</span>
                                            <span id="budgetUtilized">₱0</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-green-500 h-2 rounded-full" id="budgetUtilizedProgress" style="width: 0%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Recent Transactions -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-semibold text-gray-800">Recent Transactions</h3>
                            <button onclick="loadAllTransactions()" class="flex items-center gap-2 px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                <i data-lucide="list" class="w-4 h-4"></i>
                                View All Transactions
                            </button>
                        </div>
                        
                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Module</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Debit</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Credit</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200" id="transactionsTable">
                                        <!-- Transactions will be loaded here dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Chart instances
        let revenueExpensesChart;
        let moduleDistributionChart;

        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            loadFinancialData();
            loadRecentTransactions();
        });

        function initializeCharts() {
            // Revenue vs Expenses Chart
            const revExpCtx = document.getElementById('revenueExpensesChart').getContext('2d');
            revenueExpensesChart = new Chart(revExpCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Revenue',
                            data: [],
                            backgroundColor: 'rgba(34, 197, 94, 0.8)',
                            borderColor: 'rgb(34, 197, 94)',
                            borderWidth: 1
                        },
                        {
                            label: 'Expenses',
                            data: [],
                            backgroundColor: 'rgba(239, 68, 68, 0.8)',
                            borderColor: 'rgb(239, 68, 68)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ₱' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Module Distribution Chart
            const modDistCtx = document.getElementById('moduleDistributionChart').getContext('2d');
            moduleDistributionChart = new Chart(modDistCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Disbursements', 'Collections', 'Budget', 'Accounts Receivable', 'Accounts Payable'],
                    datasets: [{
                        data: [0, 0, 0, 0, 0],
                        backgroundColor: [
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(168, 85, 247, 0.8)',
                            'rgba(245, 158, 11, 0.8)'
                        ],
                        borderColor: [
                            'rgb(239, 68, 68)',
                            'rgb(34, 197, 94)',
                            'rgb(59, 130, 246)',
                            'rgb(168, 85, 247)',
                            'rgb(245, 158, 11)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        function loadFinancialData() {
            // Simulate API call to load financial data
            setTimeout(() => {
                // Update overview cards
                document.getElementById('totalRevenue').textContent = '₱0';
                document.getElementById('totalExpenses').textContent = '₱0';
                document.getElementById('netIncome').textContent = '₱0';
                document.getElementById('activeAccounts').textContent = '0';

                // Update account balances
                document.getElementById('arTotal').textContent = '₱0';
                document.getElementById('arOverdue').textContent = '₱0';
                document.getElementById('apTotal').textContent = '₱0';
                document.getElementById('apDue').textContent = '₱0';
                document.getElementById('budgetAllocated').textContent = '₱0';
                document.getElementById('budgetUtilized').textContent = '₱0';

                // Update chart data
                updateChartData('revenueExpensesChart', 'monthly');
                updateChartData('moduleDistributionChart', 'count');
            }, 1000);
        }

        function loadRecentTransactions() {
            const tableBody = document.getElementById('transactionsTable');
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                        <i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-2 text-gray-400"></i>
                        <p>Loading transactions...</p>
                    </td>
                </tr>
            `;
            lucide.createIcons();

            // Simulate API call
            setTimeout(() => {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                            <i data-lucide="database" class="w-8 h-8 mx-auto mb-2 text-gray-400"></i>
                            <p>No transactions found for the selected period</p>
                            <p class="text-sm mt-1">Transactions will appear here when data is available</p>
                        </td>
                    </tr>
                `;
                lucide.createIcons();
            }, 1500);
        }

        function updateChartData(chartId, period) {
            // This function would typically fetch data from an API
            // For now, it will show empty/placeholder data
            if (chartId === 'revenueExpensesChart') {
                revenueExpensesChart.data.labels = ['No Data'];
                revenueExpensesChart.data.datasets[0].data = [0];
                revenueExpensesChart.data.datasets[1].data = [0];
                revenueExpensesChart.update();
            } else if (chartId === 'moduleDistributionChart') {
                moduleDistributionChart.data.datasets[0].data = [0, 0, 0, 0, 0];
                moduleDistributionChart.update();
            }
        }

        function refreshAccountData() {
            // Show loading state
            const statusElements = document.querySelectorAll('[id$="Status"]');
            statusElements.forEach(el => {
                el.innerHTML = '<i data-lucide="loader-2" class="w-3 h-3 animate-spin inline"></i> Loading...';
            });
            lucide.createIcons();

            // Simulate API refresh
            setTimeout(() => {
                loadFinancialData();
            }, 1000);
        }

        function loadAllTransactions() {
            // This would typically open a modal or navigate to full transactions page
            alert('This would open the full transactions view with all modules data.');
        }

        function exportData(format) {
            const selectedModules = [];
            document.querySelectorAll('input[data-module]:checked').forEach(checkbox => {
                selectedModules.push(checkbox.getAttribute('data-module'));
            });

            if (selectedModules.length === 0) {
                alert('Please select at least one module to export.');
                return;
            }

            // Show export in progress
            alert(`Exporting ${selectedModules.join(', ')} data as ${format.toUpperCase()}...\n\nThis would typically download the selected data in the chosen format.`);
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