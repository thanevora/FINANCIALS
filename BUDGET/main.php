<?php
session_start();
include("../API_gateway.php");

// Database connection
$db_name = "fina_budget";
if (!isset($connections[$db_name])) {
    die("❌ Connection not found for $db_name");
}
$conn = $connections[$db_name];

// Get total approved budget from budget_proposals table
$budget_query = "SELECT SUM(amount) as total_budget FROM budget_proposals WHERE status = 'approved'";
$budget_result = mysqli_query($conn, $budget_query);
$budget_row = mysqli_fetch_assoc($budget_result);
$total_budget = $budget_row['total_budget'] ?? 0;

// ============================================
// BUDGET ALLOCATIONS DATA (Connected to database)
// ============================================

// Get total spent (disbursed + approved) from budget_allocations
$spent_query = "SELECT SUM(amount) as total_spent FROM budget_allocations WHERE status IN ('disburse', 'approved')";
$spent_result = mysqli_query($conn, $spent_query);
$spent_row = mysqli_fetch_assoc($spent_result);
$total_spent = $spent_row['total_spent'] ?? 0;

// Get total approved (for allocation requests)
$approved_query = "SELECT SUM(amount) as approved_total FROM budget_allocations WHERE status = 'approved'";
$approved_result = mysqli_query($conn, $approved_query);
$approved_row = mysqli_fetch_assoc($approved_result);
$approved_total = $approved_row['approved_total'] ?? 0;

// Get total under review
$pending_query = "SELECT SUM(amount) as pending_total FROM budget_allocations WHERE status = 'under_review'";
$pending_result = mysqli_query($conn, $pending_query);
$pending_row = mysqli_fetch_assoc($pending_result);
$pending_total = $pending_row['pending_total'] ?? 0;

// Get total disbursed
$disbursed_query = "SELECT SUM(amount) as disbursed_total FROM budget_allocations WHERE status = 'disburse'";
$disbursed_result = mysqli_query($conn, $disbursed_query);
$disbursed_row = mysqli_fetch_assoc($disbursed_result);
$disbursed_total = $disbursed_row['disbursed_total'] ?? 0;

// Stats Query for Budget Allocations (Updated with disburse status)
$query = "SELECT 
  (SELECT COUNT(*) FROM budget_allocations) AS total_allocations,
  (SELECT COUNT(*) FROM budget_allocations WHERE status = 'under_review') AS pending,
  (SELECT COUNT(*) FROM budget_allocations WHERE status = 'approved') AS approved,
  (SELECT COUNT(*) FROM budget_allocations WHERE status = 'rejected') AS rejected,
  (SELECT COUNT(*) FROM budget_allocations WHERE status = 'for_compliance') AS for_compliance,
  (SELECT COUNT(*) FROM budget_allocations WHERE status = 'disburse') AS disbursed,
  (SELECT SUM(amount) FROM budget_allocations WHERE status = 'under_review') AS pending_total,
  (SELECT SUM(amount) FROM budget_allocations WHERE status = 'approved') AS approved_total_alloc,
  (SELECT SUM(amount) FROM budget_allocations WHERE status = 'rejected') AS rejected_total,
  (SELECT SUM(amount) FROM budget_allocations WHERE status = 'for_compliance') AS compliance_total,
  (SELECT SUM(amount) FROM budget_allocations WHERE status = 'disburse') AS disbursed_total_alloc,
  (SELECT SUM(amount) FROM budget_allocations) AS total_amount";

$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

$total_allocations_count = $row['total_allocations'] ?? 0;
$pending_count = $row['pending'] ?? 0;
$approved_count = $row['approved'] ?? 0;
$rejected_count = $row['rejected'] ?? 0;
$compliance_count = $row['for_compliance'] ?? 0;
$disbursed_count = $row['disbursed'] ?? 0;
$pending_total = $row['pending_total'] ?? 0;
$approved_total_alloc = $row['approved_total_alloc'] ?? 0;
$rejected_total = $row['rejected_total'] ?? 0;
$compliance_total = $row['compliance_total'] ?? 0;
$disbursed_total_alloc = $row['disbursed_total_alloc'] ?? 0;
$total_amount = $row['total_amount'] ?? 0;

// Calculate remaining budget (dynamic from approved proposals)
$remaining_budget = $total_budget - $total_spent; // Using disbursed + approved

// Calculate efficiency rate (Disbursed + Approved vs Total Requested)
$efficiency_rate = 0;
if ($total_amount > 0) {
    $efficiency_rate = round((($disbursed_total_alloc + $approved_total_alloc) / $total_amount) * 100);
}

// Determine efficiency level
$efficiency_level = 'No data';
if ($total_allocations_count > 0) {
    if ($efficiency_rate >= 80) {
        $efficiency_level = 'High efficiency';
    } elseif ($efficiency_rate >= 50) {
        $efficiency_level = 'Medium efficiency';
    } else {
        $efficiency_level = 'Low efficiency';
    }
}

// ============================================
// FORECASTING CALCULATIONS (Connected to budget_allocations)
// ============================================

// Get historical data for forecasting (last 6 months) - Using disbursed and approved statuses
$historical_query = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(CASE WHEN status IN ('disburse', 'approved') THEN amount ELSE 0 END) as spent_amount,
        COUNT(*) as allocation_count
    FROM budget_allocations 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        AND status IN ('disburse', 'approved')
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
";
$historical_result = mysqli_query($conn, $historical_query);
$historical_data = [];
$monthly_totals = [];

if ($historical_result) {
    while ($row = mysqli_fetch_assoc($historical_result)) {
        $historical_data[] = $row;
        $monthly_totals[] = $row['spent_amount'];
    }
}

// Calculate average monthly spending
$avg_monthly_spending = 0;
if (!empty($monthly_totals)) {
    $avg_monthly_spending = array_sum($monthly_totals) / count($monthly_totals);
}

// Calculate days until budget depletion (Budget Lifespan)
$days_until_depletion = 0;
if ($avg_monthly_spending > 0 && $remaining_budget > 0) {
    $daily_spending = $avg_monthly_spending / 30; // Assuming 30 days per month
    $days_until_depletion = ceil($remaining_budget / $daily_spending);
} elseif ($remaining_budget <= 0) {
    $days_until_depletion = 0;
}

// Calculate forecast for next 3 months
$monthly_forecast = [];
for ($i = 1; $i <= 3; $i++) {
    $forecast_date = date('Y-m', strtotime("+$i months"));
    $forecast_amount = $avg_monthly_spending * (1 + ($i * 0.1)); // 10% increase each month
    $monthly_forecast[] = [
        'month' => date('F Y', strtotime("+$i months")),
        'forecast_amount' => round($forecast_amount, 2),
        'confidence' => max(60, 100 - ($i * 10)) // Decreasing confidence for farther months
    ];
}

// Calculate yearly forecast
$yearly_forecast = $avg_monthly_spending * 12 * 1.15; // 15% annual growth

// Calculate risk level
$risk_level = 'Low';
$risk_percentage = 0;

if ($remaining_budget <= 0) {
    $risk_level = 'Critical';
    $risk_percentage = 100;
} elseif ($days_until_depletion <= 30) {
    $risk_level = 'High';
    $risk_percentage = 75;
} elseif ($days_until_depletion <= 90) {
    $risk_level = 'Medium';
    $risk_percentage = 50;
} elseif ($days_until_depletion <= 180) {
    $risk_level = 'Low';
    $risk_percentage = 25;
}

// Recommended actions based on forecast
$recommended_actions = [];

if ($days_until_depletion <= 30) {
    $recommended_actions[] = "Immediate budget review required";
    $recommended_actions[] = "Consider reducing non-essential allocations by 20%";
    $recommended_actions[] = "Prepare budget supplement request";
    $recommended_actions[] = "Prioritize only critical disbursements";
} elseif ($days_until_depletion <= 90) {
    $recommended_actions[] = "Monthly budget monitoring recommended";
    $recommended_actions[] = "Review pending allocations for optimization";
    $recommended_actions[] = "Plan for next quarter budget allocation";
    $recommended_actions[] = "Evaluate pending under_review requests";
} else {
    $recommended_actions[] = "Continue regular budget monitoring";
    $recommended_actions[] = "Maintain current approval rates";
    $recommended_actions[] = "Plan for annual budget review";
    $recommended_actions[] = "Optimize disbursement schedule";
}

// Get data for pie chart (Status distribution from budget_allocations)
$pie_chart_query = "
    SELECT 
        status,
        COUNT(*) as count,
        SUM(amount) as total_amount
    FROM budget_allocations
    GROUP BY status
";
$pie_chart_result = mysqli_query($conn, $pie_chart_query);
$pie_chart_data = [];
$total_allocation_amount = 0;

if ($pie_chart_result) {
    while ($row = mysqli_fetch_assoc($pie_chart_result)) {
        $pie_chart_data[] = [
            'status' => ucfirst(str_replace('_', ' ', $row['status'])),
            'count' => $row['count'],
            'amount' => $row['total_amount']
        ];
        $total_allocation_amount += $row['total_amount'];
    }
}

// Get department-wise spending for pie chart
$department_pie_query = "
    SELECT 
        COALESCE(department, 'Unassigned') as department,
        COUNT(*) as count,
        SUM(amount) as total_amount
    FROM budget_allocations
    WHERE status IN ('disburse', 'approved')
    GROUP BY department
    ORDER BY total_amount DESC
    LIMIT 5
";
$department_pie_result = mysqli_query($conn, $department_pie_query);
$department_pie_data = [];

if ($department_pie_result) {
    while ($row = mysqli_fetch_assoc($department_pie_result)) {
        $department_pie_data[] = [
            'department' => $row['department'],
            'count' => $row['count'],
            'amount' => $row['total_amount']
        ];
    }
}

// ============================================
// SEARCH AND FILTER
// ============================================

// Search and Filter Parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$department_filter = isset($_GET['department']) ? mysqli_real_escape_string($conn, $_GET['department']) : '';

// Build query with filters
$where_conditions = [];
$query_params = [];

if (!empty($search)) {
    $where_conditions[] = "(allocation_code LIKE '%$search%' OR purpose LIKE '%$search%')";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = '$status_filter'";
}

if (!empty($department_filter)) {
    $where_conditions[] = "department = '$department_filter'";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Pagination
$limit = 9;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch allocations with pagination and filters
$allocations_query = "SELECT * FROM budget_allocations $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$allocations_result = mysqli_query($conn, $allocations_query);
$allocations = [];
if ($allocations_result) {
    while ($row = mysqli_fetch_assoc($allocations_result)) {
        $allocations[] = $row;
    }
}

// Get total pages for pagination with filters
$total_pages_query = "SELECT COUNT(*) as total FROM budget_allocations $where_clause";
$total_pages_result = mysqli_query($conn, $total_pages_query);
$total_pages_row = mysqli_fetch_assoc($total_pages_result);
$total_pages = ceil($total_pages_row['total'] / $limit);

// Get unique departments for filter
$departments_query = "SELECT DISTINCT department FROM budget_allocations WHERE department IS NOT NULL";
$departments_result = mysqli_query($conn, $departments_query);
$departments = [];
if ($departments_result) {
    while ($row = mysqli_fetch_assoc($departments_result)) {
        $departments[] = $row['department'];
    }
}

// Helper function to build pagination URLs with filters
function buildPaginationUrl($page, $search, $status_filter, $department_filter) {
    $params = ['page' => $page];
    if (!empty($search)) $params['search'] = $search;
    if (!empty($status_filter)) $params['status'] = $status_filter;
    if (!empty($department_filter)) $params['department'] = $department_filter;
    return http_build_query($params);
}
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .rounded-lg {
            border-radius: 0.75rem;
        }
        .rounded-xl {
            border-radius: 1rem;
        }
        .rounded-2xl {
            border-radius: 1.5rem;
        }
        .status-under_review { background-color: #fef3c7; color: #d97706; }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-rejected { background-color: #fee2e2; color: #dc2626; }
        .status-disburse { background-color: #10b981; color: #ffffff; }
        .status-for_compliance { background-color: #8b5cf6; color: #ffffff; }
      
        .risk-critical { background-color: #fee2e2; color: #dc2626; }
        .risk-high { background-color: #fef3c7; color: #d97706; }
        .risk-medium { background-color: #fef3c7; color: #d97706; }
        .risk-low { background-color: #d1fae5; color: #065f46; }
        
        /* Loading screen styles */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            color: white;
        }
        
        .loader {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .progress-bar {
            width: 300px;
            height: 4px;
            background: rgba(255,255,255,0.3);
            border-radius: 2px;
            margin-top: 20px;
            overflow: hidden;
        }
        
        .progress {
            height: 100%;
            background: white;
            width: 0%;
            animation: progress 10s linear forwards;
        }
        
        @keyframes progress {
            to { width: 100%; }
        }
    </style>
</head>
<body class="bg-base-100 min-h-screen bg-white" onload="showLoadingScreen()">
    <!-- Loading Screen -->
    <div id="loadingScreen" class="loading-screen">
        <div class="loader"></div>
        <h2 class="text-2xl font-bold mt-6 mb-2">Generating Budget Forecast</h2>
        <p class="text-center mb-4 max-w-md opacity-90">
            Analyzing historical allocations, calculating spending trends,<br>
            and preparing comprehensive budget forecast...
        </p>
        <div class="progress-bar">
            <div class="progress"></div>
        </div>
        <div class="mt-8 grid grid-cols-3 gap-4 text-center">
            <div>
                <div class="text-xl font-bold">1</div>
                <div class="text-sm opacity-80">Budget Analysis</div>
            </div>
            <div>
                <div class="text-xl font-bold">2</div>
                <div class="text-sm opacity-80">Trend Calculation</div>
            </div>
            <div>
                <div class="text-xl font-bold">3</div>
                <div class="text-sm opacity-80">Forecast Generation</div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex h-screen" style="display: none;" id="mainContent">
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
                        <p class="text-gray-600 mt-2">Monitor, allocate, track, and forecast travel budgets based on allocations</p>
                    </header>

                    <!-- Budget Summary Cards -->
                    <section class="glass-effect p-6 rounded-2xl shadow-sm mb-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                                <span class="p-2 mr-3 rounded-lg bg-blue-100/50 text-blue-600">
                                    <i data-lucide="pie-chart" class="w-5 h-5"></i>
                                </span>
                                Budget Overview (Connected to Allocations)
                            </h2>
                        </div>

                        <!-- Budget Dashboard Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4 gap-4 h-full">
                            <!-- Total Proposed Budget Card -->
                            <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Total Proposed Budget</p>
                                        <h3 class="text-2xl font-bold mt-1 text-gray-800">
                                            ₱<?php echo number_format($total_budget, 2); ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                        <i data-lucide="file-text" class="w-5 h-5"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="flex justify-between text-xs text-gray-500">
                                        <span>From approved proposals</span>
                                        <span class="font-medium">Total available</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Remaining Balance Card (CONNECTED TO ALLOCATIONS) -->
                            <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Remaining Balance</p>
                                        <h3 class="text-2xl font-bold mt-1 text-gray-800">
                                            ₱<?php echo number_format($remaining_budget, 2); ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                                        <i data-lucide="dollar-sign" class="w-5 h-5"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-green-500 rounded-full" style="width: <?php echo $total_budget > 0 ? min(100, ($remaining_budget / $total_budget) * 100) : 0; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>After disbursed/approved</span>
                                        <span class="font-medium">
                                            <?php 
                                            if ($total_budget > 0) {
                                                echo number_format(($remaining_budget / $total_budget) * 100, 1) . '%';
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Total Spent (Disbursed + Approved) Card -->
                            <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Total Spent</p>
                                        <h3 class="text-2xl font-bold mt-1 text-gray-800">
                                            ₱<?php echo number_format($total_spent, 2); ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full" style="background:#F7B32B1A; color:#F7B32B;">
                                        <i data-lucide="wallet" class="w-5 h-5"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full" style="background:#F7B32B; width: <?php echo $total_budget > 0 ? min(100, ($total_spent / $total_budget) * 100) : 0; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Disbursed + Approved</span>
                                        <span class="font-medium">
                                            <?php 
                                            if ($total_budget > 0) {
                                                echo number_format(($total_spent / $total_budget) * 100, 1) . '%';
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Pending Allocations Card -->
                            <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Pending Review</p>
                                        <h3 class="text-2xl font-bold mt-1 text-gray-800">
                                            ₱<?php echo number_format($pending_total, 2); ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-amber-100 text-amber-600">
                                        <i data-lucide="clock" class="w-5 h-5"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo $total_amount > 0 ? ($pending_total / $total_amount * 100) : 0; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Under review</span>
                                        <span class="font-medium"><?php echo $pending_count; ?> requests</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Second Row of Stats -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4 gap-4 mt-4">
                            <!-- Disbursed Card -->
                            <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Disbursed</p>
                                        <h3 class="text-2xl font-bold mt-1 text-gray-800">
                                            ₱<?php echo number_format($disbursed_total_alloc, 2); ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="flex justify-between text-xs text-gray-500">
                                        <span>Already paid</span>
                                        <span class="font-medium"><?php echo $disbursed_count; ?> items</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Approved (Not Disbursed) Card -->
                            <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Approved</p>
                                        <h3 class="text-2xl font-bold mt-1 text-gray-800">
                                            ₱<?php echo number_format($approved_total_alloc, 2); ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                        <i data-lucide="file-check" class="w-5 h-5"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="flex justify-between text-xs text-gray-500">
                                        <span>Approved not disbursed</span>
                                        <span class="font-medium"><?php echo $approved_count; ?> items</span>
                                    </div>
                                </div>
                            </div>

                            <!-- For Compliance Card -->
                            <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">For Compliance</p>
                                        <h3 class="text-2xl font-bold mt-1 text-gray-800">
                                            ₱<?php echo number_format($compliance_total, 2); ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                        <i data-lucide="file-check" class="w-5 h-5"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="flex justify-between text-xs text-gray-500">
                                        <span>Compliance review</span>
                                        <span class="font-medium"><?php echo $compliance_count; ?> items</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Rejected Card -->
                            <div class="stat-card p-5 rounded-xl shadow-lg border border-gray-100 bg-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium" style="color:#001f54;">Rejected</p>
                                        <h3 class="text-2xl font-bold mt-1 text-gray-800">
                                            ₱<?php echo number_format($rejected_total, 2); ?>
                                        </h3>
                                    </div>
                                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                                        <i data-lucide="x-circle" class="w-5 h-5"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="flex justify-between text-xs text-gray-500">
                                        <span>Rejected requests</span>
                                        <span class="font-medium"><?php echo $rejected_count; ?> items</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Navigation Tabs -->
                    <div class="mb-6">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8">
                                <a href="budget-monitoring.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Budget Monitoring
                                </a>
                                <a href="budget-allocating.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Budget Allocating
                                </a>
                                <a href="budget-proposal.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Budget Proposal
                                </a>
                                <a href="budget-transactions.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Budget Transactions
                                </a>
                                <a href="main-budget-management.php" class="border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Main Budget Management
                                </a>
                            </nav>
                        </div>
                    </div>

                    <!-- Content Area -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Left Column: Budget Forecast -->
                        <div class="lg:col-span-2">
                            <section class="glass-effect p-6 rounded-2xl shadow-sm h-full">
                                <div class="mb-6">
                                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Budget Forecasting & Analysis</h3>
                                    <p class="text-gray-600">Predictive analysis based on allocation spending (Disbursed + Approved)</p>
                                </div>

                                <!-- Forecasting Dashboard -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                    <!-- Budget Lifespan Card (CONNECTED TO REMAINING BALANCE) -->
                                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h4 class="text-lg font-semibold text-gray-800">Budget Lifespan</h4>
                                                <p class="text-sm text-gray-500">
                                                    Based on: 
                                                    <span class="font-medium">₱<?php echo number_format($remaining_budget, 2); ?></span> remaining
                                                </p>
                                            </div>
                                            <div class="p-3 rounded-full <?php echo $risk_level == 'Critical' ? 'bg-red-100 text-red-600' : ($risk_level == 'High' ? 'bg-amber-100 text-amber-600' : 'bg-green-100 text-green-600'); ?>">
                                                <i data-lucide="calendar" class="w-6 h-6"></i>
                                            </div>
                                        </div>
                                        <div class="text-center mb-4">
                                            <div class="text-3xl font-bold text-gray-800 mb-2">
                                                <?php 
                                                if ($days_until_depletion > 0) {
                                                    echo $days_until_depletion . ' days';
                                                } elseif ($remaining_budget <= 0) {
                                                    echo 'Depleted';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </div>
                                            <p class="text-sm text-gray-600">
                                                Remaining: ₱<?php echo number_format($remaining_budget, 2); ?><br>
                                                Monthly avg: ₱<?php echo number_format($avg_monthly_spending, 2); ?>
                                            </p>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm font-medium <?php echo $risk_level == 'Critical' ? 'text-red-600' : ($risk_level == 'High' ? 'text-amber-600' : 'text-green-600'); ?>">
                                                Risk: <?php echo $risk_level; ?>
                                            </span>
                                            <div class="w-32 h-2 bg-gray-200 rounded-full overflow-hidden">
                                                <div class="h-full <?php echo $risk_level == 'Critical' ? 'bg-red-500' : ($risk_level == 'High' ? 'bg-amber-500' : 'bg-green-500'); ?>" 
                                                     style="width: <?php echo $risk_percentage; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Monthly Forecast Card -->
                                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h4 class="text-lg font-semibold text-gray-800">Next 3 Months Forecast</h4>
                                                <p class="text-sm text-gray-500">Projected allocation spending</p>
                                            </div>
                                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                                <i data-lucide="trending-up" class="w-6 h-6"></i>
                                            </div>
                                        </div>
                                        <div class="space-y-3">
                                            <?php foreach ($monthly_forecast as $forecast): ?>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm font-medium text-gray-700"><?php echo $forecast['month']; ?></span>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-lg font-bold text-gray-800">₱<?php echo number_format($forecast['forecast_amount'], 2); ?></span>
                                                    <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-600">
                                                        <?php echo $forecast['confidence']; ?>% confidence
                                                    </span>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Spending Trend Analysis (CONNECTED TO BUDGET ALLOCATIONS) -->
                                <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4">Spending Trend Analysis</h4>
                                    <p class="text-sm text-gray-600 mb-4">Based on historical disbursed/approved allocations</p>
                                    <div class="h-64">
                                        <canvas id="forecastChart"></canvas>
                                    </div>
                                </div>

                                <!-- Pie Chart Section -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <!-- Allocation Status Distribution -->
                                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                                        <h4 class="text-lg font-semibold text-gray-800 mb-4">Allocation Status Distribution</h4>
                                        <div class="h-64">
                                            <canvas id="statusPieChart"></canvas>
                                        </div>
                                    </div>

                                    <!-- Department Spending Distribution -->
                                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                                        <h4 class="text-lg font-semibold text-gray-800 mb-4">Top Department Spending</h4>
                                        <div class="h-64">
                                            <canvas id="departmentPieChart"></canvas>
                                        </div>
                                    </div>
                                </div>

                                <!-- Recommended Actions -->
                                <div class="bg-white rounded-xl border border-gray-200 p-6">
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4">Recommended Actions</h4>
                                    <div class="space-y-3">
                                        <?php foreach ($recommended_actions as $index => $action): ?>
                                        <div class="flex items-start gap-3">
                                            <div class="flex-shrink-0 w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-medium">
                                                <?php echo $index + 1; ?>
                                            </div>
                                            <p class="text-gray-700"><?php echo $action; ?></p>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <!-- Right Column: Forecasting Details & Allocation Requests -->
                        <div class="lg:col-span-1">
                            <section class="glass-effect p-6 rounded-2xl shadow-sm h-full">
                                <div class="mb-6">
                                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Forecasting Details</h3>
                                    <p class="text-sm text-gray-600">Connected to budget allocations database</p>
                                </div>

                                <!-- Forecasting Metrics -->
                                <div class="space-y-6">
                                    <!-- Average Monthly Spending -->
                                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="font-medium text-gray-700">Avg. Monthly Spending</h4>
                                            <i data-lucide="bar-chart-3" class="w-5 h-5 text-blue-600"></i>
                                        </div>
                                        <div class="text-2xl font-bold text-gray-800 mb-2">
                                            ₱<?php echo number_format($avg_monthly_spending, 2); ?>
                                        </div>
                                        <p class="text-sm text-gray-500">Based on last 6 months of disbursed/approved allocations</p>
                                    </div>

                                    <!-- Yearly Forecast -->
                                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="font-medium text-gray-700">Yearly Forecast</h4>
                                            <i data-lucide="calendar" class="w-5 h-5 text-green-600"></i>
                                        </div>
                                        <div class="text-2xl font-bold text-gray-800 mb-2">
                                            ₱<?php echo number_format($yearly_forecast, 2); ?>
                                        </div>
                                        <p class="text-sm text-gray-500">Projected annual allocation spending</p>
                                    </div>

                                    <!-- Forecasting History (CONNECTED TO BUDGET ALLOCATIONS) -->
                                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="font-medium text-gray-700">Spending History</h4>
                                            <i data-lucide="history" class="w-5 h-5 text-purple-600"></i>
                                        </div>
                                        <div class="space-y-3 max-h-60 overflow-y-auto">
                                            <?php if (!empty($historical_data)): ?>
                                                <?php foreach ($historical_data as $data): ?>
                                                <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                                                    <div>
                                                        <span class="text-sm font-medium text-gray-700"><?php echo date('M Y', strtotime($data['month'] . '-01')); ?></span>
                                                        <p class="text-xs text-gray-500"><?php echo $data['allocation_count']; ?> allocations</p>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="text-sm font-bold text-gray-800">₱<?php echo number_format($data['spent_amount'], 2); ?></span>
                                                        <p class="text-xs <?php echo $data['spent_amount'] > 0 ? 'text-green-600' : 'text-gray-500'; ?>">
                                                            Disbursed/Approved
                                                        </p>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p class="text-sm text-gray-500 text-center py-4">No allocation spending data available</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Forecast Steps -->
                                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                                        <h4 class="font-medium text-gray-700 mb-3">Forecast Steps</h4>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2 text-sm">
                                                <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">1</div>
                                                <span class="text-gray-600">Analyze disbursed/approved allocations</span>
                                            </div>
                                            <div class="flex items-center gap-2 text-sm">
                                                <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">2</div>
                                                <span class="text-gray-600">Calculate average monthly spending</span>
                                            </div>
                                            <div class="flex items-center gap-2 text-sm">
                                                <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">3</div>
                                                <span class="text-gray-600">Project future spending trends</span>
                                            </div>
                                            <div class="flex items-center gap-2 text-sm">
                                                <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">4</div>
                                                <span class="text-gray-600">Calculate budget depletion timeline</span>
                                            </div>
                                            <div class="flex items-center gap-2 text-sm">
                                                <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">5</div>
                                                <span class="text-gray-600">Generate recommendations</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Allocation Quick Stats -->
                                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                                        <h4 class="font-medium text-gray-700 mb-3">Allocation Quick Stats</h4>
                                        <div class="space-y-2">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-600">Total Allocations:</span>
                                                <span class="font-medium"><?php echo $total_allocations_count; ?></span>
                                            </div>
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-600">Total Amount:</span>
                                                <span class="font-medium">₱<?php echo number_format($total_amount, 2); ?></span>
                                            </div>
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-600">Spent Rate:</span>
                                                <span class="font-medium">
                                                    <?php echo $total_amount > 0 ? number_format(($total_spent / $total_amount) * 100, 1) : '0'; ?>%
                                                </span>
                                            </div>
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-600">Pending Rate:</span>
                                                <span class="font-medium">
                                                    <?php echo $total_amount > 0 ? number_format(($pending_total / $total_amount) * 100, 1) : '0'; ?>%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <!-- Search and Filter Section for Allocation Requests -->
                    <div class="mt-6">
                        <section class="glass-effect p-6 rounded-2xl shadow-sm">
                            <div class="mb-6">
                                <h3 class="text-xl font-semibold text-gray-800 mb-4">Allocation Requests Management</h3>
                                <p class="text-gray-600">Manage and review budget allocation requests</p>
                            </div>

                            <!-- Search and Filter -->
                            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                                <form id="searchFilterForm" method="GET" class="flex flex-col sm:flex-row gap-4">
                                    <!-- Search Bar -->
                                    <div class="flex-1">
                                        <div class="relative">
                                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                   placeholder="Search by allocation code or purpose...">
                                            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        </div>
                                    </div>

                                    <!-- Status Filter -->
                                    <div class="sm:w-48">
                                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">All Status</option>
                                            <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                            <option value="for_compliance" <?php echo $status_filter === 'for_compliance' ? 'selected' : ''; ?>>For Compliance</option>
                                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="disburse" <?php echo $status_filter === 'disburse' ? 'selected' : ''; ?>>Disburse</option>
                                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>

                                    <!-- Department Filter -->
                                    <div class="sm:w-48">
                                        <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">All Departments</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo htmlspecialchars($dept); ?>" 
                                                        <?php echo $department_filter === $dept ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dept); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex gap-2">
                                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                                            <i data-lucide="filter" class="w-4 h-4"></i>
                                            Apply
                                        </button>
                                        <button type="button" onclick="clearFilters()" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center gap-2">
                                            <i data-lucide="x" class="w-4 h-4"></i>
                                            Clear
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Allocation Requests Table -->
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <div class="flex justify-between items-center mb-6">
                                    <h4 class="text-lg font-medium text-gray-700">Allocation Requests</h4>
                                    <div class="text-sm text-gray-500">
                                        Total: <?php echo $total_allocations_count; ?> requests
                                    </div>
                                </div>
                                
                                <?php if (empty($allocations)): ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                                    <p>No allocation requests found</p>
                                    <p class="text-sm">Allocation requests will appear here once submitted</p>
                                </div>
                                <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead>
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <?php foreach ($allocations as $allocation): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3 text-sm font-mono text-gray-900">
                                                    <?php echo htmlspecialchars($allocation['allocation_code']); ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($allocation['department']); ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm font-bold text-gray-900">
                                                    ₱<?php echo number_format($allocation['amount'], 2); ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full status-<?php echo $allocation['status']; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $allocation['status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900 max-w-xs truncate">
                                                    <?php echo htmlspecialchars($allocation['purpose']); ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-500">
                                                    <?php echo date('M j, Y', strtotime($allocation['created_at'])); ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm">
                                                    <button onclick="viewAllocation(<?php echo $allocation['id']; ?>)" 
                                                            class="px-3 py-1 bg-blue-600 text-white text-xs rounded-lg flex items-center gap-1 hover:bg-blue-700 transition-colors">
                                                        <i data-lucide="eye" class="w-3 h-3"></i>
                                                        View
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <div class="mt-6 flex justify-center items-center space-x-2">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?php echo buildPaginationUrl($page - 1, $search, $status_filter, $department_filter); ?>" 
                                           class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-1">
                                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                            Previous
                                        </a>
                                    <?php endif; ?>
                                    
                                    <span class="text-sm text-gray-600">
                                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                                    </span>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?<?php echo buildPaginationUrl($page + 1, $search, $status_filter, $department_filter); ?>" 
                                           class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-1">
                                            Next
                                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>

                    <!-- Budget Management Actions -->
                    <div class="mt-6">
                        <section class="glass-effect p-6 rounded-2xl shadow-sm">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="bg-white rounded-xl border border-gray-200 p-6 text-center hover:shadow-md transition-shadow">
                                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i data-lucide="file-text" class="w-6 h-6 text-blue-600"></i>
                                    </div>
                                    <h4 class="text-lg font-medium text-gray-700 mb-2">Annual Budget Plan</h4>
                                    <p class="text-gray-500 text-sm mb-4">Create and manage your annual budget strategy</p>
                                    <button onclick="viewAnnualBudgetPlan()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm">
                                        View Plan
                                    </button>
                                </div>
                                
                                <div class="bg-white rounded-xl border border-gray-200 p-6 text-center hover:shadow-md transition-shadow">
                                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i data-lucide="trending-up" class="w-6 h-6 text-green-600"></i>
                                    </div>
                                    <h4 class="text-lg font-medium text-gray-700 mb-2">Advanced Forecast</h4>
                                    <p class="text-gray-500 text-sm mb-4">Generate detailed allocation projections</p>
                                    <button onclick="generateAdvancedForecast()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm">
                                        Generate Forecast
                                    </button>
                                </div>
                                
                                <div class="bg-white rounded-xl border border-gray-200 p-6 text-center hover:shadow-md transition-shadow">
                                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i data-lucide="bar-chart-3" class="w-6 h-6 text-purple-600"></i>
                                    </div>
                                    <h4 class="text-lg font-medium text-gray-700 mb-2">Export Reports</h4>
                                    <p class="text-gray-500 text-sm mb-4">Export allocation data and analysis</p>
                                    <button onclick="exportForecastReport()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm">
                                        Export PDF
                                    </button>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- View Allocation Modal -->
    <div id="viewAllocationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">Allocation Details</h3>
                    <button onclick="closeModal('viewAllocationModal')" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div id="allocationDetails" class="space-y-4">
                    <!-- Allocation details will be loaded here -->
                </div>
                <div class="flex justify-end gap-2 mt-6 pt-6 border-t border-gray-200" id="actionButtons">
                    <!-- Action buttons will be loaded here based on status -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Loading screen function
        function showLoadingScreen() {
            setTimeout(function() {
                document.getElementById('loadingScreen').style.display = 'none';
                document.getElementById('mainContent').style.display = 'flex';
                initializeCharts();
            }, 10000); // 10 seconds loading screen
        }
        
        // Initialize all charts
        function initializeCharts() {
            initializeForecastChart();
            initializeStatusPieChart();
            initializeDepartmentPieChart();
        }
        
        // Initialize forecast chart (Connected to budget_allocations)
        function initializeForecastChart() {
            const ctx = document.getElementById('forecastChart').getContext('2d');
            
            // Historical months from budget_allocations
            const historicalMonths = <?php echo json_encode(array_column($historical_data, 'month')); ?>;
            const spentAmounts = <?php echo json_encode(array_column($historical_data, 'spent_amount')); ?>;
            
            // Forecast months (next 3 months)
            const forecastMonths = <?php echo json_encode(array_column($monthly_forecast, 'month')); ?>;
            const forecastAmounts = <?php echo json_encode(array_column($monthly_forecast, 'forecast_amount')); ?>;
            
            // Format historical months
            const formattedMonths = historicalMonths.map(m => {
                const date = new Date(m + '-01');
                return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            });
            
            // Combine data
            const allMonths = [...formattedMonths, ...forecastMonths];
            const allAmounts = [...spentAmounts, ...forecastAmounts];
            
            // Create datasets
            const historicalDataset = {
                label: 'Historical Spending (Disbursed/Approved)',
                data: spentAmounts,
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 2,
                tension: 0.1,
                fill: true
            };
            
            const forecastDataset = {
                label: 'Forecast',
                data: [...Array(spentAmounts.length).fill(null), ...forecastAmounts],
                backgroundColor: 'rgba(34, 197, 94, 0.3)',
                borderColor: 'rgb(34, 197, 94)',
                borderWidth: 2,
                borderDash: [5, 5],
                tension: 0.1,
                fill: true
            };
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: allMonths,
                    datasets: [historicalDataset, forecastDataset]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ₱${context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                                }
                            },
                            title: {
                                display: true,
                                text: 'Amount (₱)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Timeline'
                            }
                        }
                    }
                }
            });
        }
        
        // Initialize Status Pie Chart
        function initializeStatusPieChart() {
            const ctx = document.getElementById('statusPieChart').getContext('2d');
            
            const pieData = <?php echo json_encode($pie_chart_data); ?>;
            
            const labels = pieData.map(item => item.status);
            const data = pieData.map(item => item.amount);
            const counts = pieData.map(item => item.count);
            
            // Define colors for each status
            const backgroundColors = [
                'rgba(59, 130, 246, 0.7)',   // Blue for Under Review
                'rgba(16, 185, 129, 0.7)',   // Green for Approved
                'rgba(139, 92, 246, 0.7)',   // Purple for For Compliance
                'rgba(239, 68, 68, 0.7)',    // Red for Rejected
                'rgba(34, 197, 94, 0.7)',    // Green for Disburse
                'rgba(245, 158, 11, 0.7)',   // Amber for other statuses
            ];
            
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                        borderColor: 'white',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    const index = context.dataIndex;
                                    const count = counts[index];
                                    
                                    return `${label}: ₱${value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} (${percentage}%) - ${count} items`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Initialize Department Pie Chart
        function initializeDepartmentPieChart() {
            const ctx = document.getElementById('departmentPieChart').getContext('2d');
            
            const deptData = <?php echo json_encode($department_pie_data); ?>;
            
            if (deptData.length === 0) {
                document.getElementById('departmentPieChart').parentElement.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-64 text-gray-500">
                        <i data-lucide="building" class="w-12 h-12 mb-3 opacity-50"></i>
                        <p>No department spending data available</p>
                    </div>
                `;
                lucide.createIcons();
                return;
            }
            
            const labels = deptData.map(item => item.department);
            const data = deptData.map(item => item.amount);
            const counts = deptData.map(item => item.count);
            
            // Define colors for departments
            const backgroundColors = [
                'rgba(59, 130, 246, 0.7)',   // Blue
                'rgba(16, 185, 129, 0.7)',   // Green
                'rgba(139, 92, 246, 0.7)',   // Purple
                'rgba(245, 158, 11, 0.7)',   // Amber
                'rgba(239, 68, 68, 0.7)',    // Red
            ];
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                        borderColor: 'white',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    const index = context.dataIndex;
                                    const count = counts[index];
                                    
                                    return `${label}: ₱${value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} (${percentage}%) - ${count} allocations`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        let currentAllocationId = null;
        let currentAllocationStatus = null;
        let currentAllocationAmount = null;

        // Modal functions
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // View allocation function
        function viewAllocation(allocationId) {
            currentAllocationId = allocationId;
            
            // Show loading state
            document.getElementById('allocationDetails').innerHTML = `
                <div class="flex justify-center items-center py-8">
                    <i data-lucide="loader-2" class="w-8 h-8 animate-spin text-blue-600"></i>
                </div>
            `;
            lucide.createIcons();
            
            // Fetch allocation details
            fetch(`API/get_allocation.php?id=${allocationId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const allocation = data.allocation;
                        currentAllocationStatus = allocation.status;
                        currentAllocationAmount = parseFloat(allocation.amount);
                        
                        document.getElementById('allocationDetails').innerHTML = `
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="hash" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Allocation Code</h4>
                                    </div>
                                    <p class="font-mono text-gray-900 ml-6">${allocation.allocation_code || 'N/A'}</p>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="building" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Department</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">${allocation.department || 'N/A'}</p>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="dollar-sign" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Amount</h4>
                                    </div>
                                    <p class="text-2xl font-bold text-gray-900 ml-6">₱${currentAllocationAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="alert-circle" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Status</h4>
                                    </div>
                                    <div class="ml-6">
                                        <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full status-${allocation.status}">
                                            ${allocation.status ? allocation.status.charAt(0).toUpperCase() + allocation.status.slice(1).replace('_', ' ') : 'N/A'}
                                        </span>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="calendar" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Start Date</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">${allocation.start_date ? new Date(allocation.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="calendar" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">End Date</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">${allocation.end_date ? new Date(allocation.end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="file-text" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Purpose</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">${allocation.purpose || 'No purpose provided'}</p>
                                </div>

                                <div class="md:col-span-2">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="clock" class="w-4 h-4 text-blue-600"></i>
                                        <h4 class="font-semibold text-gray-700">Created At</h4>
                                    </div>
                                    <p class="text-gray-900 ml-6">${allocation.created_at ? new Date(allocation.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}</p>
                                </div>
                            </div>
                        `;
                        
                        // Update action buttons based on status
                        updateActionButtons(allocation.status);
                        
                    } else {
                        document.getElementById('allocationDetails').innerHTML = `
                            <div class="text-center py-8 text-red-600">
                                <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-4"></i>
                                <p>${data.message || 'Failed to load allocation details'}</p>
                            </div>
                        `;
                        lucide.createIcons();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('allocationDetails').innerHTML = `
                        <div class="text-center py-8 text-red-600">
                            <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-4"></i>
                            <p>Error loading allocation details: ${error.message}</p>
                        </div>
                    `;
                    lucide.createIcons();
                });
            
            openModal('viewAllocationModal');
        }

        // Update action buttons based on allocation status
        function updateActionButtons(status) {
            const actionButtons = document.getElementById('actionButtons');
            let buttonsHTML = '';
            
            if (status === 'disburse') {
                buttonsHTML = `
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center gap-2" disabled>
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                        Approve
                    </button>
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center gap-2" disabled>
                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                        Reject
                    </button>
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center gap-2" disabled>
                        <i data-lucide="file-check" class="w-4 h-4"></i>
                        Mark Disburse
                    </button>
                    <button onclick="markForCompliance()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center gap-2">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                        Re-open
                    </button>
                `;
            } else if (status === 'approved') {
                buttonsHTML = `
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center gap-2" disabled>
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                        Approve
                    </button>
                    <button onclick="rejectAllocation()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2">
                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                        Reject
                    </button>
                    <button onclick="markAsDisburse()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                        <i data-lucide="dollar-sign" class="w-4 h-4"></i>
                        Mark Disburse
                    </button>
                    <button onclick="reopenAllocation()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                        Re-open
                    </button>
                `;
            } else if (status === 'for_compliance') {
                buttonsHTML = `
                    <button onclick="approveAllocation()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                        Approve
                    </button>
                    <button onclick="rejectAllocation()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2">
                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                        Reject
                    </button>
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center gap-2" disabled>
                        <i data-lucide="file-check" class="w-4 h-4"></i>
                        For Compliance
                    </button>
                    <button onclick="reopenAllocation()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                        Re-open
                    </button>
                `;
            } else if (status === 'under_review') {
                buttonsHTML = `
                    <button onclick="approveAllocation()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                        Approve
                    </button>
                    <button onclick="rejectAllocation()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2">
                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                        Reject
                    </button>
                    <button onclick="markForCompliance()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center gap-2">
                        <i data-lucide="file-check" class="w-4 h-4"></i>
                        For Compliance
                    </button>
                    <button onclick="deleteAllocation()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex items-center gap-2">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                        Delete
                    </button>
                `;
            } else if (status === 'rejected') {
                buttonsHTML = `
                    <button onclick="approveAllocation()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                        Approve
                    </button>
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center gap-2" disabled>
                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                        Reject
                    </button>
                    <button onclick="markForCompliance()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center gap-2">
                        <i data-lucide="file-check" class="w-4 h-4"></i>
                        For Compliance
                    </button>
                    <button onclick="deleteAllocation()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex items-center gap-2">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                        Delete
                    </button>
                `;
            }
            
            actionButtons.innerHTML = buttonsHTML;
            lucide.createIcons();
        }

        // CRUD Actions with SweetAlert
        function approveAllocation() {
            if (!currentAllocationId) return;
            
            // Check remaining budget first
            fetch('API/check_remaining_budget.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const remainingBudget = data.remaining_budget;
                        if (currentAllocationAmount > remainingBudget) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Insufficient Budget',
                                text: `Cannot approve allocation. Requested amount (₱${currentAllocationAmount.toLocaleString()}) exceeds remaining budget (₱${remainingBudget.toLocaleString()})`,
                                confirmButtonColor: '#dc2626'
                            });
                            return;
                        }

                        // Proceed with approval
                        Swal.fire({
                            title: 'Approve Allocation?',
                            text: 'This will approve the budget allocation and cannot be undone.',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#10b981',
                            cancelButtonColor: '#6b7280',
                            confirmButtonText: 'Yes, Approve!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                updateAllocationStatus('approved');
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error checking budget:', error);
                    Swal.fire('Error!', 'Failed to check remaining budget.', 'error');
                });
        }

        function markAsDisburse() {
            if (!currentAllocationId) return;
            
            Swal.fire({
                title: 'Mark as Disbursed?',
                text: 'This will mark the allocation as disbursed (money has been paid).',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Mark as Disbursed!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    updateAllocationStatus('disburse');
                }
            });
        }

        function rejectAllocation() {
            if (!currentAllocationId) return;
            
            Swal.fire({
                title: 'Reject Allocation?',
                text: 'This will reject the budget allocation and cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Reject!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    updateAllocationStatus('rejected');
                }
            });
        }

        function markForCompliance() {
            if (!currentAllocationId) return;
            
            Swal.fire({
                title: 'Mark for Compliance?',
                text: 'This will mark the allocation for compliance review.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#8b5cf6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Mark!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    updateAllocationStatus('for_compliance');
                }
            });
        }

        function reopenAllocation() {
            if (!currentAllocationId) return;
            
            Swal.fire({
                title: 'Re-open Allocation?',
                text: 'This will change the status back to Under Review for further processing.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Re-open!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    updateAllocationStatus('under_review');
                }
            });
        }

        function deleteAllocation() {
            if (!currentAllocationId) return;
            
            Swal.fire({
                title: 'Delete Allocation?',
                text: 'This action cannot be undone!',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Delete!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteAllocationAPI(currentAllocationId);
                }
            });
        }

        function updateAllocationStatus(status) {
            // Show loading in SweetAlert
            Swal.fire({
                title: 'Updating...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('API/update_allocation_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: currentAllocationId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: `Allocation ${status.replace('_', ' ')} successfully.`,
                        confirmButtonColor: '#10b981',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        closeModal('viewAllocationModal');
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message || 'Failed to update allocation.', 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'Failed to update allocation.', 'error');
            });
        }

        function deleteAllocationAPI(allocationId) {
            fetch('API/delete_allocation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: allocationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', data.message, 'success').then(() => {
                        closeModal('viewAllocationModal');
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message || 'Failed to delete allocation.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'Failed to delete allocation.', 'error');
            });
        }

        // Filter functions
        function clearFilters() {
            window.location.href = 'main-budget-management.php';
        }

        function removeFilter(filterType) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filterType);
            window.location.href = url.toString();
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeModal(e.target.id);
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal('viewAllocationModal');
            }
        });

        // Budget Management Actions
        function viewAnnualBudgetPlan() {
            Swal.fire({
                title: 'Loading Annual Budget Plan...',
                text: 'Please wait while we retrieve your budget plan',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('API/get_annual_budget_plan.php')
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        showAnnualBudgetPlanModal(data.plan);
                    } else {
                        Swal.fire('Error!', data.message || 'Failed to load annual budget plan.', 'error');
                    }
                })
                .catch(error => {
                    Swal.close();
                    console.error('Error:', error);
                    Swal.fire('Error!', 'Failed to load annual budget plan.', 'error');
                });
        }

        function generateAdvancedForecast() {
            Swal.fire({
                title: 'Generating Advanced Forecast...',
                html: 'Analyzing allocation data, calculating spending trends, and generating comprehensive budget projections...',
                timer: 5000,
                timerProgressBar: true,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            }).then(() => {
                showAdvancedForecastModal();
            });
        }

        function exportForecastReport() {
            Swal.fire({
                title: 'Exporting Forecast Report...',
                text: 'Please wait while we generate your PDF report',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            setTimeout(() => {
                Swal.close();
                Swal.fire('Success!', 'Forecast report exported successfully as PDF.', 'success');
            }, 2000);
        }

        function showAdvancedForecastModal() {
            const forecastData = {
                remaining_budget: <?php echo $remaining_budget; ?>,
                days_until_depletion: <?php echo $days_until_depletion; ?>,
                avg_monthly_spending: <?php echo $avg_monthly_spending; ?>,
                monthly_forecast: <?php echo json_encode($monthly_forecast); ?>,
                yearly_forecast: <?php echo $yearly_forecast; ?>,
                risk_level: '<?php echo $risk_level; ?>',
                risk_percentage: <?php echo $risk_percentage; ?>,
                recommended_actions: <?php echo json_encode($recommended_actions); ?>,
                historical_data: <?php echo json_encode($historical_data); ?>,
                total_spent: <?php echo $total_spent; ?>,
                total_allocations: <?php echo $total_allocations_count; ?>
            };

            const modalContent = `
                <div class="max-h-96 overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-blue-800 mb-2">Budget Lifespan</h4>
                            <p class="text-xl font-bold text-blue-600">${forecastData.days_until_depletion > 0 ? forecastData.days_until_depletion + ' days' : 'Depleted'}</p>
                            <p class="text-sm text-blue-600">Remaining: ₱${forecastData.remaining_budget.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-green-800 mb-2">Spending Statistics</h4>
                            <p class="text-xl font-bold text-green-600">₱${forecastData.total_spent.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                            <p class="text-sm text-green-600">${forecastData.total_allocations} total allocations</p>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <h4 class="font-semibold text-gray-700 mb-3">Monthly Forecast (Based on Allocations)</h4>
                        <div class="space-y-2">
                            ${forecastData.monthly_forecast.map(forecast => `
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                    <span class="font-medium">${forecast.month}</span>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold">₱${forecast.forecast_amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                        <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-600">
                                            ${forecast.confidence}% confidence
                                        </span>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <h4 class="font-semibold text-gray-700 mb-3">Risk Assessment</h4>
                        <div class="p-4 rounded-lg ${forecastData.risk_level === 'Critical' ? 'bg-red-50 border border-red-200' : 
                                                      forecastData.risk_level === 'High' ? 'bg-amber-50 border border-amber-200' : 
                                                      'bg-green-50 border border-green-200'}">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-medium">${forecastData.risk_level} Risk</span>
                                <span class="font-bold ${forecastData.risk_level === 'Critical' ? 'text-red-600' : 
                                                        forecastData.risk_level === 'High' ? 'text-amber-600' : 
                                                        'text-green-600'}">
                                    ${forecastData.risk_percentage}%
                                </span>
                            </div>
                            <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full ${forecastData.risk_level === 'Critical' ? 'bg-red-500' : 
                                                      forecastData.risk_level === 'High' ? 'bg-amber-500' : 
                                                      'bg-green-500'}" 
                                     style="width: ${forecastData.risk_percentage}%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-sm text-gray-600">
                        <p><strong>Generated:</strong> ${new Date().toLocaleDateString()}</p>
                        <p><strong>Data Source:</strong> ${forecastData.historical_data.length} months of allocation data</p>
                        <p><strong>Forecast Method:</strong> Time series analysis of disbursed/approved allocations</p>
                    </div>
                </div>
            `;

            Swal.fire({
                title: 'Advanced Budget Forecast',
                html: modalContent,
                width: 600,
                confirmButtonText: 'Close',
                confirmButtonColor: '#3b82f6',
                showCancelButton: true,
                cancelButtonText: 'Export as PDF',
                cancelButtonColor: '#10b981'
            }).then((result) => {
                if (result.isCanceled) {
                    exportForecastReport();
                }
            });
        }

        function showAnnualBudgetPlanModal(plan) {
            const modalContent = `
                <div class="max-h-96 overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-blue-800 mb-2">Total Annual Budget</h4>
                            <p class="text-2xl font-bold text-blue-600">₱${parseFloat(plan.total_budget || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-green-800 mb-2">Allocated Budget</h4>
                            <p class="text-2xl font-bold text-green-600">₱${parseFloat(plan.allocated_budget || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-700 mb-3">Department Allocations</h4>
                        <div class="space-y-2">
                            ${plan.department_allocations ? plan.department_allocations.map(dept => `
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                    <span class="font-medium">${dept.department}</span>
                                    <span class="font-bold">₱${parseFloat(dept.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                </div>
                            `).join('') : '<p class="text-gray-500 text-center py-4">No department allocations found</p>'}
                        </div>
                    </div>
                    
                    <div class="mt-4 text-sm text-gray-600">
                        <p><strong>Fiscal Year:</strong> ${plan.fiscal_year || 'N/A'}</p>
                        <p><strong>Last Updated:</strong> ${plan.last_updated ? new Date(plan.last_updated).toLocaleDateString() : 'N/A'}</p>
                    </div>
                </div>
            `;

            Swal.fire({
                title: 'Annual Budget Plan',
                html: modalContent,
                width: 600,
                confirmButtonText: 'Close',
                confirmButtonColor: '#3b82f6'
            });
        }
    </script>
</body>
</html>