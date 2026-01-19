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

// Get data from each table separately
$tables_data = [];
$error_message = "";

try {
    $tables = [
        'budget_proposals' => [
            'name' => 'Budget Proposals',
            'icon' => 'file-text',
            'color' => 'blue'
        ],
        'budget_allocations' => [
            'name' => 'Budget Allocations', 
            'icon' => 'wallet',
            'color' => 'purple'
        ],
        'budget_request_disbursement' => [
            'name' => 'Disbursements',
            'icon' => 'dollar-sign',
            'color' => 'orange'
        ]
    ];
    
    foreach ($tables as $table_name => $table_info) {
        // Get all data from the table without ordering by created_at
        $query = "SELECT * FROM $table_name";
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            throw new Exception("Query failed for $table_name: " . mysqli_error($conn));
        }
        
        $table_data = [
            'info' => $table_info,
            'rows' => [],
            'total_amount' => 0,
            'row_count' => 0,
            'status_counts' => [],
            'department_counts' => [],
            'pending_amount' => 0,
            'approved_amount' => 0,
            'completed_amount' => 0,
            'monthly_data' => [],
            'amount_ranges' => []
        ];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $table_data['rows'][] = $row;
            $table_data['row_count']++;
            
            // Calculate total amount
            $amount = 0;
            foreach ($row as $key => $value) {
                if (strpos(strtolower($key), 'amount') !== false && is_numeric($value)) {
                    $amount = $value;
                    $table_data['total_amount'] += $value;
                    break;
                }
            }
            
            // Count by status
            $status = getDisplayValue($row, 'status');
            if (!isset($table_data['status_counts'][$status])) {
                $table_data['status_counts'][$status] = 0;
            }
            $table_data['status_counts'][$status]++;
            
            // Track amounts by status
            if ($status === 'pending') {
                $table_data['pending_amount'] += $amount;
            } elseif ($status === 'approved') {
                $table_data['approved_amount'] += $amount;
            } elseif ($status === 'completed') {
                $table_data['completed_amount'] += $amount;
            }
            
            // Count by department
            $department = getDisplayValue($row, 'department');
            if (!isset($table_data['department_counts'][$department])) {
                $table_data['department_counts'][$department] = 0;
            }
            $table_data['department_counts'][$department]++;
            
            // Monthly data for line charts
            $date = getDisplayValue($row, 'date');
            if ($date !== 'N/A') {
                $month = date('M Y', strtotime($date));
                if (!isset($table_data['monthly_data'][$month])) {
                    $table_data['monthly_data'][$month] = 0;
                }
                $table_data['monthly_data'][$month] += $amount;
            }
            
            // Amount ranges for histogram
            if ($amount > 0) {
                if ($amount <= 10000) $range = '0-10K';
                elseif ($amount <= 50000) $range = '10K-50K';
                elseif ($amount <= 100000) $range = '50K-100K';
                elseif ($amount <= 500000) $range = '100K-500K';
                else $range = '500K+';
                
                if (!isset($table_data['amount_ranges'][$range])) {
                    $table_data['amount_ranges'][$range] = 0;
                }
                $table_data['amount_ranges'][$range]++;
            }
        }
        
        $tables_data[$table_name] = $table_data;
        mysqli_free_result($result);
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Calculate overall statistics
$total_transactions = 0;
$total_amount = 0;
$total_pending_amount = 0;
$total_approved_amount = 0;
$total_completed_amount = 0;
$all_status_counts = [];
$all_department_counts = [];

foreach ($tables_data as $table_data) {
    $total_transactions += $table_data['row_count'];
    $total_amount += $table_data['total_amount'];
    $total_pending_amount += $table_data['pending_amount'];
    $total_approved_amount += $table_data['approved_amount'];
    $total_completed_amount += $table_data['completed_amount'];
    
    // Aggregate status counts
    foreach ($table_data['status_counts'] as $status => $count) {
        if (!isset($all_status_counts[$status])) {
            $all_status_counts[$status] = 0;
        }
        $all_status_counts[$status] += $count;
    }
    
    // Aggregate department counts
    foreach ($table_data['department_counts'] as $department => $count) {
        if (!isset($all_department_counts[$department])) {
            $all_department_counts[$department] = 0;
        }
        $all_department_counts[$department] += $count;
    }
}

// Calculate additional statistics
$average_transaction_amount = $total_transactions > 0 ? $total_amount / $total_transactions : 0;
$approval_rate = $total_amount > 0 ? ($total_approved_amount / $total_amount) * 100 : 0;
$completion_rate = $total_amount > 0 ? ($total_completed_amount / $total_amount) * 100 : 0;
$pending_rate = $total_amount > 0 ? ($total_pending_amount / $total_amount) * 100 : 0;

// Get top departments
arsort($all_department_counts);
$top_departments = array_slice($all_department_counts, 0, 3, true);

// Helper function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Helper function to format percentage
function formatPercentage($value) {
    return number_format($value, 1) . '%';
}

// Helper function for status badge classes
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'completed':
        case 'approved':
        case 'disbursed': 
            return 'bg-green-100 text-green-800 border border-green-200';
        case 'pending': 
            return 'bg-yellow-100 text-yellow-800 border border-yellow-200';
        case 'rejected': 
            return 'bg-red-100 text-red-800 border border-red-200';
        default: 
            return 'bg-gray-100 text-gray-800 border border-gray-200';
    }
}

// Helper function for color classes
function getColorClasses($color) {
    $classes = [
        'blue' => 'bg-blue-50 text-blue-600 border-blue-200',
        'purple' => 'bg-purple-50 text-purple-600 border-purple-200', 
        'orange' => 'bg-orange-50 text-orange-600 border-orange-200',
        'green' => 'bg-green-50 text-green-600 border-green-200',
        'red' => 'bg-red-50 text-red-600 border-red-200',
        'yellow' => 'bg-yellow-50 text-yellow-600 border-yellow-200',
        'indigo' => 'bg-indigo-50 text-indigo-600 border-indigo-200',
        'pink' => 'bg-pink-50 text-pink-600 border-pink-200'
    ];
    return $classes[$color] ?? 'bg-gray-50 text-gray-600 border-gray-200';
}

// Find display value for a field
function getDisplayValue($row, $field) {
    $possible_names = [
        'description' => ['budget_title', 'purpose', 'description', 'title'],
        'reference' => ['proposal_code', 'allocation_code', 'request_code', 'reference_number', 'code'],
        'amount' => ['amount', 'total_amount', 'value'],
        'status' => ['status', 'state'],
        'department' => ['department', 'dept', 'division'],
        'date' => ['created_at', 'submitted_at', 'date_created', 'timestamp']
    ];
    
    if (isset($possible_names[$field])) {
        foreach ($possible_names[$field] as $column) {
            if (isset($row[$column]) && !empty($row[$column])) {
                return $row[$column];
            }
        }
    }
    
    return 'N/A';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Ledger | Financial System</title>
        <?php include '../COMPONENTS/header.php'; ?>

    <style>
        .hover-lift {
            transition: all 0.3s ease;
        }
        .hover-lift:hover {
            transform: translateY(-2px);
        }
        .table-container {
            max-height: 400px;
            overflow-y: auto;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen font-inter">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include '../COMPONENTS/sidebar.php'; ?>

        <!-- Content Area -->
        <div class="flex flex-col flex-1 overflow-auto">
            <!-- Navbar -->
            <?php include '../COMPONENTS/navbar.php'; ?>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Header -->
                    <div class="mb-8">
                        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900">General Ledger</h1>
                                <p class="text-gray-600 mt-2">Financial transactions organized by table</p>
                            </div>
                            <div class="flex gap-3">
                                <button onclick="refreshPage()" class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                    Refresh
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Error Message -->
                    <?php if (!empty($error_message)): ?>
                    <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <i data-lucide="alert-triangle" class="w-5 h-5 text-red-600"></i>
                            <div>
                                <h4 class="text-sm font-medium text-red-900">Database Error</h4>
                                <p class="text-xs text-red-700"><?php echo htmlspecialchars($error_message); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-2 mb-8">
                        
                        <!-- Total Transactions Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Total Transactions</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo number_format($total_transactions); ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full" style="background:#F7B32B1A; color:#F7B32B;">
                                    <i data-lucide="database" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full" style="background:#F7B32B; width: 100%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Across all tables</span>
                                    <span class="font-medium">Complete</span>
                                </div>
                            </div>
                        </div>

                        <!-- Total Amount Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Total Amount</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo formatCurrency($total_amount); ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-green-100 text-green-600">
                                    <i data-lucide="dollar-sign" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-green-500 rounded-full" style="width: 100%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Combined value</span>
                                    <span class="font-medium">All funds</span>
                                </div>
                            </div>
                        </div>

                        <!-- Average Transaction Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Avg. Transaction</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo formatCurrency($average_transaction_amount); ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                    <i data-lucide="trending-up" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-purple-500 rounded-full" style="width: <?php echo min(100, ($average_transaction_amount / max($total_amount, 1)) * 1000); ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Per transaction</span>
                                    <span class="font-medium">Mean value</span>
                                </div>
                            </div>
                        </div>

                        <!-- Approved Amount Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Approved</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo formatCurrency($total_approved_amount); ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-green-100 text-green-600">
                                    <i data-lucide="check-circle" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $approval_rate; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Approval rate</span>
                                    <span class="font-medium"><?php echo formatPercentage($approval_rate); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Amount Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Pending</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo formatCurrency($total_pending_amount); ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                    <i data-lucide="clock" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-yellow-500 rounded-full" style="width: <?php echo $pending_rate; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Pending rate</span>
                                    <span class="font-medium"><?php echo formatPercentage($pending_rate); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Completed Amount Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Completed</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo formatCurrency($total_completed_amount); ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                    <i data-lucide="check-square" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-500 rounded-full" style="width: <?php echo $completion_rate; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Completion rate</span>
                                    <span class="font-medium"><?php echo formatPercentage($completion_rate); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Database Tables Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Database Tables</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        3
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                                    <i data-lucide="layers" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500 rounded-full" style="width: 100%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Connected tables</span>
                                    <span class="font-medium">Active</span>
                                </div>
                            </div>
                        </div>

                        <!-- Status Types Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Status Types</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo count($all_status_counts); ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-pink-100 text-pink-600">
                                    <i data-lucide="tag" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-pink-500 rounded-full" style="width: <?php echo min(100, (count($all_status_counts) / 10) * 100); ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Different statuses</span>
                                    <span class="font-medium">Active</span>
                                </div>
                            </div>
                        </div>

                        <!-- Departments Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Departments</p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo count($all_department_counts); ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                                    <i data-lucide="building" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-orange-500 rounded-full" style="width: <?php echo min(100, (count($all_department_counts) / 20) * 100); ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Active departments</span>
                                    <span class="font-medium">Engaged</span>
                                </div>
                            </div>
                        </div>

                        <!-- Top Department Card -->
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;">Top Department</p>
                                    <h3 class="text-2xl font-bold mt-1 text-gray-800 truncate">
                                        <?php 
                                        if (!empty($top_departments)) {
                                            $top_dept = array_key_first($top_departments);
                                            echo $top_dept !== 'N/A' ? $top_dept : 'N/A';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full bg-red-100 text-red-600">
                                    <i data-lucide="award" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-red-500 rounded-full" style="width: <?php echo !empty($top_departments) ? min(100, (reset($top_departments) / $total_transactions) * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Transactions</span>
                                    <span class="font-medium"><?php echo !empty($top_departments) ? reset($top_departments) : 0; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Breakdown Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-2 mb-8">
                        <?php foreach ($all_status_counts as $status => $count): ?>
                        <div class="p-5 rounded-xl shadow-lg border border-gray-100 bg-white hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium" style="color:#001f54;"><?php echo ucfirst($status); ?></p>
                                    <h3 class="text-3xl font-bold mt-1 text-gray-800">
                                        <?php echo number_format($count); ?>
                                    </h3>
                                </div>
                                <div class="p-3 rounded-full <?php echo $status === 'approved' ? 'bg-green-100 text-green-600' : ($status === 'pending' ? 'bg-yellow-100 text-yellow-600' : ($status === 'completed' ? 'bg-blue-100 text-blue-600' : 'bg-red-100 text-red-600')); ?>">
                                    <i data-lucide="<?php echo $status === 'approved' ? 'check-circle' : ($status === 'pending' ? 'clock' : ($status === 'completed' ? 'check-square' : 'x-circle')); ?>" class="w-6 h-6"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full <?php echo $status === 'approved' ? 'bg-green-500' : ($status === 'pending' ? 'bg-yellow-500' : ($status === 'completed' ? 'bg-blue-500' : 'bg-red-500')); ?>" style="width: <?php echo ($count / $total_transactions) * 100; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Of total</span>
                                    <span class="font-medium"><?php echo formatPercentage(($count / $total_transactions) * 100); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Charts Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                        <!-- Overall Analysis Chart -->
                        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Overall Financial Analysis</h3>
                            <div class="chart-container">
                                <canvas id="overallAnalysisChart"></canvas>
                            </div>
                        </div>

                        <!-- Status Distribution Chart -->
                        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Status Distribution</h3>
                            <div class="chart-container">
                                <canvas id="statusDistributionChart"></canvas>
                            </div>
                        </div>

                        <!-- Department Distribution Chart -->
                        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Department Distribution</h3>
                            <div class="chart-container">
                                <canvas id="departmentDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Individual Tables Section with Charts -->
                    <div class="space-y-8">
                        <?php foreach ($tables_data as $table_name => $table_data): ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                            <!-- Table Header -->
                            <div class="px-6 py-4 border-b border-gray-200">
                                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 rounded-lg <?php echo getColorClasses($table_data['info']['color']); ?>">
                                            <i data-lucide="<?php echo $table_data['info']['icon']; ?>" class="w-5 h-5"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">
                                                <?php echo $table_data['info']['name']; ?>
                                            </h3>
                                            <p class="text-sm text-gray-500">
                                                <?php echo number_format($table_data['row_count']); ?> records • 
                                                Total: <?php echo formatCurrency($table_data['total_amount']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button onclick="toggleTable('<?php echo $table_name; ?>')" 
                                                class="flex items-center gap-2 px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                            Toggle View
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Charts for this table -->
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 p-6 border-b border-gray-200">
                                <!-- Histogram -->
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h4 class="text-sm font-medium text-gray-900 mb-3">Amount Distribution</h4>
                                    <div class="chart-container" style="height: 200px;">
                                        <canvas id="<?php echo $table_name; ?>Histogram"></canvas>
                                    </div>
                                </div>

                                <!-- Monthly Analysis -->
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h4 class="text-sm font-medium text-gray-900 mb-3">Monthly Trend</h4>
                                    <div class="chart-container" style="height: 200px;">
                                        <canvas id="<?php echo $table_name; ?>Analysis"></canvas>
                                    </div>
                                </div>

                                <!-- Pie Chart -->
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h4 class="text-sm font-medium text-gray-900 mb-3">Status Breakdown</h4>
                                    <div class="chart-container" style="height: 200px;">
                                        <canvas id="<?php echo $table_name; ?>Pie"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Table Content -->
                            <div id="<?php echo $table_name; ?>-table" class="table-container">
                                <?php if ($table_data['row_count'] === 0): ?>
                                    <div class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-400">
                                            <i data-lucide="file-text" class="w-12 h-12 mb-3"></i>
                                            <p class="text-lg font-medium text-gray-500">No records found</p>
                                            <p class="text-sm text-gray-400 mt-1">No data available in this table</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="w-full">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($table_data['rows'] as $row): ?>
                                                    <tr class="hover:bg-gray-50 transition-colors">
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                            <?php 
                                                            $date = getDisplayValue($row, 'date');
                                                            echo $date !== 'N/A' ? date('M j, Y', strtotime($date)) : 'N/A';
                                                            ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                                                            <?php echo htmlspecialchars(getDisplayValue($row, 'reference')); ?>
                                                        </td>
                                                        <td class="px-6 py-4 text-sm text-gray-900">
                                                            <div class="max-w-xs">
                                                                <?php echo htmlspecialchars(getDisplayValue($row, 'description')); ?>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                            <?php echo htmlspecialchars(getDisplayValue($row, 'department')); ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getStatusBadgeClass(getDisplayValue($row, 'status')); ?>">
                                                                <?php echo ucfirst(getDisplayValue($row, 'status')); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900">
                                                            <?php 
                                                            $amount = getDisplayValue($row, 'amount');
                                                            echo $amount !== 'N/A' ? formatCurrency($amount) : 'N/A';
                                                            ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Database Info -->
                    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <i data-lucide="database" class="w-5 h-5 text-blue-600"></i>
                            <div>
                                <h4 class="text-sm font-medium text-blue-900">Database Information</h4>
                                <p class="text-xs text-blue-700">
                                    Connected to: budget_proposals, budget_allocations, budget_request_disbursement
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Chart data from PHP
        const tablesData = <?php echo json_encode($tables_data); ?>;
        const overallData = {
            totalAmount: <?php echo $total_amount; ?>,
            approvedAmount: <?php echo $total_approved_amount; ?>,
            pendingAmount: <?php echo $total_pending_amount; ?>,
            completedAmount: <?php echo $total_completed_amount; ?>,
            statusCounts: <?php echo json_encode($all_status_counts); ?>,
            departmentCounts: <?php echo json_encode(array_slice($all_department_counts, 0, 10)); ?>
        };

        // Initialize charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeOverallCharts();
            initializeTableCharts();
            setupFilters();
        });

        function initializeOverallCharts() {
            // Overall Analysis Chart (Bar)
            const overallCtx = document.getElementById('overallAnalysisChart').getContext('2d');
            new Chart(overallCtx, {
                type: 'bar',
                data: {
                    labels: ['Total', 'Approved', 'Pending', 'Completed'],
                    datasets: [{
                        label: 'Amount (₱)',
                        data: [
                            overallData.totalAmount,
                            overallData.approvedAmount,
                            overallData.pendingAmount,
                            overallData.completedAmount
                        ],
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(139, 92, 246, 0.8)'
                        ],
                        borderColor: [
                            'rgb(59, 130, 246)',
                            'rgb(34, 197, 94)',
                            'rgb(245, 158, 11)',
                            'rgb(139, 92, 246)'
                        ],
                        borderWidth: 1
                    }]
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

            // Status Distribution Chart (Pie)
            const statusCtx = document.getElementById('statusDistributionChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'pie',
                data: {
                    labels: Object.keys(overallData.statusCounts),
                    datasets: [{
                        data: Object.values(overallData.statusCounts),
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(139, 92, 246, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(99, 102, 241, 0.8)'
                        ],
                        borderColor: [
                            'rgb(34, 197, 94)',
                            'rgb(245, 158, 11)',
                            'rgb(139, 92, 246)',
                            'rgb(239, 68, 68)',
                            'rgb(99, 102, 241)'
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
                        }
                    }
                }
            });

            // Department Distribution Chart (Doughnut)
            const deptCtx = document.getElementById('departmentDistributionChart').getContext('2d');
            new Chart(deptCtx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(overallData.departmentCounts),
                    datasets: [{
                        data: Object.values(overallData.departmentCounts),
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(139, 92, 246, 0.8)',
                            'rgba(14, 165, 233, 0.8)',
                            'rgba(20, 184, 166, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(249, 115, 22, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(236, 72, 153, 0.8)',
                            'rgba(168, 85, 247, 0.8)',
                            'rgba(6, 182, 212, 0.8)'
                        ],
                        borderColor: 'white',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function initializeTableCharts() {
            Object.keys(tablesData).forEach(tableName => {
                const tableData = tablesData[tableName];
                
                // Histogram for amount ranges
                const histCtx = document.getElementById(tableName + 'Histogram').getContext('2d');
                if (histCtx) {
                    new Chart(histCtx, {
                        type: 'bar',
                        data: {
                            labels: Object.keys(tableData.amount_ranges || {}),
                            datasets: [{
                                label: 'Transactions',
                                data: Object.values(tableData.amount_ranges || {}),
                                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                                borderColor: 'rgb(59, 130, 246)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                }

                // Analysis chart (monthly trend)
                const analysisCtx = document.getElementById(tableName + 'Analysis').getContext('2d');
                if (analysisCtx) {
                    const monthlyData = tableData.monthly_data || {};
                    const sortedMonths = Object.keys(monthlyData).sort((a, b) => new Date(a) - new Date(b));
                    
                    new Chart(analysisCtx, {
                        type: 'line',
                        data: {
                            labels: sortedMonths,
                            datasets: [{
                                label: 'Monthly Amount',
                                data: sortedMonths.map(month => monthlyData[month]),
                                borderColor: 'rgb(139, 92, 246)',
                                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4
                            }]
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
                            }
                        }
                    });
                }

                // Pie chart for status distribution
                const pieCtx = document.getElementById(tableName + 'Pie').getContext('2d');
                if (pieCtx) {
                    new Chart(pieCtx, {
                        type: 'pie',
                        data: {
                            labels: Object.keys(tableData.status_counts || {}),
                            datasets: [{
                                data: Object.values(tableData.status_counts || {}),
                                backgroundColor: [
                                    'rgba(34, 197, 94, 0.8)',
                                    'rgba(245, 158, 11, 0.8)',
                                    'rgba(139, 92, 246, 0.8)',
                                    'rgba(239, 68, 68, 0.8)'
                                ],
                                borderColor: 'white',
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
            });
        }

        // Toggle table visibility
        function toggleTable(tableName) {
            const tableElement = document.getElementById(tableName + '-table');
            if (tableElement) {
                if (tableElement.style.display === 'none') {
                    tableElement.style.display = 'block';
                } else {
                    tableElement.style.display = 'none';
                }
            }
        }

        function refreshPage() {
            window.location.reload();
        }

        function setupFilters() {
            // Add hover effects to cards
            document.querySelectorAll('.hover-lift').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        }
    </script>
</body>
</html>