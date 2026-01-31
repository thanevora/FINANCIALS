<?php
session_start();
include("../main_connection.php");

$db_name = "rest_core_2_usm";
$conn = $connections[$db_name] ?? die("❌ Connection not found for $db_name");

// ================== PAGINATION SETTINGS ==================
$perPage = 6;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// ================== SEARCH & FILTER PARAMETERS ==================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// ================== BUILD QUERY WITH FILTERS ==================
$whereClauses = [];
$params = [];
$param_types = "";

if (!empty($search)) {
    $whereClauses[] = "(employee_name LIKE ? OR email LIKE ? OR dept_name LIKE ? OR employee_id LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "ssss";
}

if (!empty($role_filter)) {
    $whereClauses[] = "role = ?";
    $params[] = $role_filter;
    $param_types .= "s";
}

if (!empty($status_filter)) {
    $whereClauses[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

$whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// ================== GET TOTAL RECORDS ==================
$totalQuery = "SELECT COUNT(*) as total FROM department_accounts $whereSQL";
$totalStmt = $conn->prepare($totalQuery);

if (!empty($params)) {
    $totalStmt->bind_param($param_types, ...$params);
}

$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalRecords = $totalRow['total'];
$totalPages = ceil($totalRecords / $perPage);

// ================== FETCH USERS WITH PAGINATION ==================
$query = "SELECT * FROM department_accounts $whereSQL ORDER BY employee_name ASC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $employee_id = $row['employee_id'];
    
    // Get user's login stats using employee_id
    $login_query = "SELECT COUNT(*) as total_logins FROM department_logs WHERE employee_id = ? AND log_status = 'success'";
    $login_stmt = $conn->prepare($login_query);
    $login_stmt->bind_param("s", $employee_id);
    $login_stmt->execute();
    $login_result = $login_stmt->get_result();
    $login_data = $login_result->fetch_assoc();
    $row['total_logins'] = $login_data['total_logins'] ?? 0;
    
    // Get last login using employee_id
    $last_login_query = "SELECT date FROM department_logs WHERE employee_id = ? AND log_status = 'success' ORDER BY date DESC LIMIT 1";
    $last_login_stmt = $conn->prepare($last_login_query);
    $last_login_stmt->bind_param("s", $employee_id);
    $last_login_stmt->execute();
    $last_login_result = $last_login_stmt->get_result();
    $last_login_data = $last_login_result->fetch_assoc();
    $row['last_login'] = $last_login_data['date'] ?? 'Never';
    
    // Get activity count from dept_audit_transc using employee_id
    $activity_query = "SELECT COUNT(*) as total_activities FROM dept_audit_transc WHERE employee_id = ?";
    $activity_stmt = $conn->prepare($activity_query);
    $activity_stmt->bind_param("s", $employee_id);
    $activity_stmt->execute();
    $activity_result = $activity_stmt->get_result();
    $activity_data = $activity_result->fetch_assoc();
    $row['total_activities'] = $activity_data['total_activities'] ?? 0;
    
    // Get recent department logs for this user
    $dept_logs_query = "SELECT * FROM department_logs WHERE employee_id = ? ORDER BY date DESC LIMIT 5";
    $dept_logs_stmt = $conn->prepare($dept_logs_query);
    $dept_logs_stmt->bind_param("s", $employee_id);
    $dept_logs_stmt->execute();
    $dept_logs_result = $dept_logs_stmt->get_result();
    $row['recent_logs'] = [];
    while($log = $dept_logs_result->fetch_assoc()) {
        $row['recent_logs'][] = $log;
    }
    
    // Get recent audit trails for this user
    $audit_logs_query = "SELECT * FROM dept_audit_transc WHERE employee_id = ? ORDER BY date DESC LIMIT 5";
    $audit_logs_stmt = $conn->prepare($audit_logs_query);
    $audit_logs_stmt->bind_param("s", $employee_id);
    $audit_logs_stmt->execute();
    $audit_logs_result = $audit_logs_stmt->get_result();
    $row['recent_audit_logs'] = [];
    while($audit = $audit_logs_result->fetch_assoc()) {
        $row['recent_audit_logs'][] = $audit;
    }
    
    $users[] = $row;
}

// ================== GET STATISTICS ==================
// Total Users
$total_users = $totalRecords;

// Active Users
$active_query = "SELECT COUNT(*) as active FROM department_accounts WHERE status = 'active'";
$active_result = $conn->query($active_query);
$active_row = $active_result->fetch_assoc();
$active_users = $active_row['active'] ?? 0;

// Total Logins Today
$today = date('Y-m-d');
$logins_today_query = "SELECT COUNT(*) as today_logins FROM department_logs WHERE DATE(date) = ? AND log_status = 'success'";
$logins_today_stmt = $conn->prepare($logins_today_query);
$logins_today_stmt->bind_param("s", $today);
$logins_today_stmt->execute();
$logins_today_result = $logins_today_stmt->get_result();
$logins_today_row = $logins_today_result->fetch_assoc();
$today_logins = $logins_today_row['today_logins'] ?? 0;

// Failed Logins Today
$failed_logins_query = "SELECT COUNT(*) as failed_logins FROM department_logs WHERE DATE(date) = ? AND log_status = 'failed'";
$failed_logins_stmt = $conn->prepare($failed_logins_query);
$failed_logins_stmt->bind_param("s", $today);
$failed_logins_stmt->execute();
$failed_logins_result = $failed_logins_stmt->get_result();
$failed_logins_row = $failed_logins_result->fetch_assoc();
$failed_logins = $failed_logins_row['failed_logins'] ?? 0;

// Total Activities Today
$activities_today_query = "SELECT COUNT(*) as total_activities FROM dept_audit_transc WHERE DATE(date) = ?";
$activities_today_stmt = $conn->prepare($activities_today_query);
$activities_today_stmt->bind_param("s", $today);
$activities_today_stmt->execute();
$activities_today_result = $activities_today_stmt->get_result();
$activities_today_row = $activities_today_result->fetch_assoc();
$today_activities = $activities_today_row['total_activities'] ?? 0;

// Pending Users
$pending_users_query = "SELECT COUNT(*) as pending FROM department_accounts WHERE status = 'pending'";
$pending_result = $conn->query($pending_users_query);
$pending_row = $pending_result->fetch_assoc();
$pending_users = $pending_row['pending'] ?? 0;

// Total Departments
$total_departments_query = "SELECT COUNT(DISTINCT dept_name) as total_depts FROM department_accounts WHERE dept_name IS NOT NULL AND dept_name != ''";
$total_depts_result = $conn->query($total_departments_query);
$total_depts_row = $total_depts_result->fetch_assoc();
$total_departments = $total_depts_row['total_depts'] ?? 0;

// Average Logins per User
$avg_logins_query = "SELECT AVG(login_count) as avg_logins FROM (SELECT COUNT(*) as login_count FROM department_logs WHERE log_status = 'success' GROUP BY employee_id) as login_counts";
$avg_logins_result = $conn->query($avg_logins_query);
$avg_logins_row = $avg_logins_result->fetch_assoc();
$avg_logins = round($avg_logins_row['avg_logins'] ?? 0, 1);

// Total Department Logs
$total_department_logs_query = "SELECT COUNT(*) as total_logs FROM department_logs";
$total_logs_result = $conn->query($total_department_logs_query);
$total_logs_row = $total_logs_result->fetch_assoc();
$total_department_logs = $total_logs_row['total_logs'] ?? 0;

// Total Audit Trails
$total_audit_trails_query = "SELECT COUNT(*) as total_audit FROM dept_audit_transc";
$total_audit_result = $conn->query($total_audit_trails_query);
$total_audit_row = $total_audit_result->fetch_assoc();
$total_audit_trails = $total_audit_row['total_audit'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>User Management | Soliera Restaurant</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- UI Libraries -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom Styles -->
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-hover: #4f46e5;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        
        .card {
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }
        
        .user-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border: 3px solid white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-active {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .status-inactive {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .status-pending {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .stat-card {
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            background: white;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .filter-select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 1200px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .tab-button {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            color: #64748b;
            transition: all 0.2s ease;
            border-bottom: 3px solid transparent;
        }
        
        .tab-button.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .password-strength {
            height: 6px;
            border-radius: 9999px;
            transition: all 0.3s ease;
            margin-top: 4px;
        }
        
        .pagination-btn {
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            color: #4b5563;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .pagination-btn:hover:not(.disabled) {
            background: #f3f4f6;
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .pagination-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-primary {
            background-color: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
        }
        
        .badge-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .badge-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }
        
        .badge-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .action-btn {
            transition: all 0.2s ease;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }
        
        .table-wrapper {
            min-width: 100%;
        }
        
        .table-header {
            background-color: #f9fafb;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        
        .table-row {
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.2s;
        }
        
        .table-row:hover {
            background-color: #f9fafb;
        }
        
        .table-cell {
            padding: 1rem;
            vertical-align: middle;
        }
        
        .log-status-success {
            color: #10b981;
            background-color: rgba(16, 185, 129, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .log-status-failed {
            color: #ef4444;
            background-color: rgba(239, 68, 68, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .log-type {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .log-type-login {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .log-type-logout {
            background-color: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }
        
        .log-type-activity {
            background-color: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        /* Scrollbar styling */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,.1);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include '../sidebarr.php'; ?>

        <!-- Content Area -->
        <div class="flex flex-col flex-1 overflow-auto bg-gray-50">
            <!-- Navbar -->
            <?php include '../navbar.php'; ?>

            <!-- Main Content -->
            <main class="p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Header -->
                    <div class="mb-8">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900 mb-2">User Management</h1>
                                <p class="text-gray-500">Manage all user accounts and permissions</p>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="viewDepartmentLogs()" 
                                        class="btn btn-outline border-gray-300 px-6 py-3 rounded-lg flex items-center gap-2">
                                    <i data-lucide="file-text" class="w-5 h-5"></i>
                                    View Logs
                                </button>
                                <button onclick="viewAuditTrails()" 
                                        class="btn btn-outline border-gray-300 px-6 py-3 rounded-lg flex items-center gap-2">
                                    <i data-lucide="shield" class="w-5 h-5"></i>
                                    Audit Trails
                                </button>
                                <button onclick="openAddUserModal()" 
                                        class="btn btn-primary px-6 py-3 rounded-lg flex items-center gap-2">
                                    <i data-lucide="user-plus" class="w-5 h-5"></i>
                                    Add User
                                </button>
                            </div>
                        </div>
                    </div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Users -->
    <div class="stat-card bg-white text-black shadow-xl p-5 rounded-lg">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-[#F7B32B]">Total Users</p>
                <h3 class="text-3xl font-bold mt-1"><?php echo $total_users; ?></h3>
                <p class="text-xs opacity-70 mt-1">All registered accounts</p>
            </div>
            <div class="p-3 rounded-lg bg-[#001f54] text-[#F7B32B]">
                <i data-lucide="users" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- Active Users -->
    <div class="stat-card bg-white text-black shadow-xl p-5 rounded-lg">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-[#F7B32B]">Active Users</p>
                <h3 class="text-3xl font-bold mt-1"><?php echo $active_users; ?></h3>
                <p class="text-xs opacity-70 mt-1">Currently active</p>
            </div>
            <div class="p-3 rounded-lg bg-[#001f54] text-[#F7B32B]">
                <i data-lucide="user-check" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- Today's Logins -->
    <div class="stat-card bg-white text-black shadow-xl p-5 rounded-lg">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-[#F7B32B]">Today's Logins</p>
                <h3 class="text-3xl font-bold mt-1"><?php echo $today_logins; ?></h3>
                <p class="text-xs opacity-70 mt-1">Successful logins today</p>
            </div>
            <div class="p-3 rounded-lg bg-[#001f54] text-[#F7B32B]">
                <i data-lucide="log-in" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- Failed Logins -->
    <div class="stat-card bg-white text-black shadow-xl p-5 rounded-lg">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-[#F7B32B]">Failed Logins</p>
                <h3 class="text-3xl font-bold mt-1"><?php echo $failed_logins; ?></h3>
                <p class="text-xs opacity-70 mt-1">Today's failed attempts</p>
            </div>
            <div class="p-3 rounded-lg bg-[#001f54] text-[#F7B32B]">
                <i data-lucide="alert-circle" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- Today's Activities -->
    <div class="stat-card bg-white text-black shadow-xl p-5 rounded-lg">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-[#F7B32B]">Today's Activities</p>
                <h3 class="text-3xl font-bold mt-1"><?php echo $today_activities; ?></h3>
                <p class="text-xs opacity-70 mt-1">Activities performed today</p>
            </div>
            <div class="p-3 rounded-lg bg-[#001f54] text-[#F7B32B]">
                <i data-lucide="activity" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- Pending Users -->
    <div class="stat-card bg-white text-black shadow-xl p-5 rounded-lg">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-[#F7B32B]">Pending Users</p>
                <h3 class="text-3xl font-bold mt-1"><?php echo $pending_users; ?></h3>
                <p class="text-xs opacity-70 mt-1">Awaiting activation</p>
            </div>
            <div class="p-3 rounded-lg bg-[#001f54] text-[#F7B32B]">
                <i data-lucide="clock" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- Total Department Logs -->
    <div class="stat-card bg-white text-black shadow-xl p-5 rounded-lg">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-[#F7B32B]">Department Logs</p>
                <h3 class="text-3xl font-bold mt-1"><?php echo $total_department_logs; ?></h3>
                <p class="text-xs opacity-70 mt-1">Total login records</p>
            </div>
            <div class="p-3 rounded-lg bg-[#001f54] text-[#F7B32B]">
                <i data-lucide="file-text" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- Total Audit Trails -->
    <div class="stat-card bg-white text-black shadow-xl p-5 rounded-lg">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-[#F7B32B]">Audit Trails</p>
                <h3 class="text-3xl font-bold mt-1"><?php echo $total_audit_trails; ?></h3>
                <p class="text-xs opacity-70 mt-1">Total activity records</p>
            </div>
            <div class="p-3 rounded-lg bg-[#001f54] text-[#F7B32B]">
                <i data-lucide="shield" class="w-6 h-6"></i>
            </div>
        </div>
    </div>
</div>

                    <!-- Search & Filter Section -->
                    <div class="card bg-white p-6 mb-8">
                        <div class="flex flex-col lg:flex-row gap-4">
                            <!-- Search Bar -->
                            <div class="flex-1">
                                <div class="relative">
                                    <i data-lucide="search" class="absolute left-3 top-3 w-5 h-5 text-gray-400"></i>
                                    <input type="text" 
                                           id="search-input"
                                           placeholder="Search users by name, email, department, or ID..." 
                                           class="search-input w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white"
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>

                            <!-- Role Filter -->
                            <div class="w-full lg:w-48">
                                <select id="role-filter" class="filter-select w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                                    <option value="">All Roles</option>
                                    <?php
                                    $roles_query = "SELECT DISTINCT role FROM department_accounts WHERE role IS NOT NULL AND role != '' ORDER BY role";
                                    $roles_result = $conn->query($roles_query);
                                    while ($role_row = $roles_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($role_row['role']); ?>" 
                                            <?php echo $role_filter == $role_row['role'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role_row['role']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Status Filter -->
                            <div class="w-full lg:w-48">
                                <select id="status-filter" class="filter-select w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                </select>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-2">
                                <button onclick="applyFilters()" 
                                        class="btn btn-primary px-6 py-3 rounded-lg flex items-center gap-2">
                                    <i data-lucide="filter" class="w-5 h-5"></i>
                                    Filter
                                </button>
                                <button onclick="resetFilters()" 
                                        class="btn btn-outline border-gray-300 px-6 py-3 rounded-lg flex items-center gap-2">
                                    <i data-lucide="refresh-ccw" class="w-5 h-5"></i>
                                    Reset
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Users Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6 mb-8">
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <div class="user-card card p-6">
                                    <div class="flex flex-col">
                                        <!-- User Header -->
                                        <div class="flex items-start justify-between mb-4">
                                            <div class="flex items-center gap-4">
                                                <!-- Profile Picture -->
                                                <div class="user-avatar rounded-full overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200">
                                                    <?php 
                                                    $image_path = '';
                                                    if (!empty($user['image_url'])) {
                                                        $image_path = $user['image_url'];
                                                    } elseif (!empty($user['profile_picture'])) {
                                                        $image_path = $user['profile_picture'];
                                                    }
                                                    
                                                    if (!empty($image_path) && file_exists("Profile_images/" . $image_path)): 
                                                    ?>
                                                        <img src="Profile_images/<?php echo htmlspecialchars($image_path); ?>" 
                                                             alt="<?php echo htmlspecialchars($user['employee_name']); ?>" 
                                                             class="w-full h-full object-cover"
                                                             onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzk5OSI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+='">
                                                    <?php else: ?>
                                                        <div class="w-full h-full flex items-center justify-center">
                                                            <i data-lucide="user" class="w-10 h-10 text-gray-400"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div>
                                                    <h3 class="font-bold text-lg text-gray-900"><?php echo htmlspecialchars($user['employee_name']); ?></h3>
                                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email'] ?? 'No email'); ?></p>
                                                </div>
                                            </div>
                                            
                                            <!-- Status Badge -->
                                            <?php 
                                            $status = $user['status'] ?? 'inactive';
                                            $statusClass = 'status-' . $status;
                                            ?>
                                            <span class="<?php echo $statusClass; ?> status-badge">
                                                <i data-lucide="<?php 
                                                    echo $status === 'active' ? 'check-circle' : 
                                                         ($status === 'inactive' ? 'x-circle' : 'clock'); 
                                                ?>" class="w-4 h-4"></i>
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </div>

                                        <!-- User Details -->
                                        <div class="space-y-3 mb-6">
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="id-card" class="w-4 h-4 text-gray-400"></i>
                                                <span class="text-sm text-gray-600">ID: <?php echo htmlspecialchars($user['employee_id']); ?></span>
                                            </div>
                                            
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="shield" class="w-4 h-4 text-gray-400"></i>
                                                <span class="text-sm text-gray-600">Role: <?php echo htmlspecialchars($user['role']); ?></span>
                                            </div>
                                            
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="building" class="w-4 h-4 text-gray-400"></i>
                                                <span class="text-sm text-gray-600"><?php echo htmlspecialchars($user['dept_name']); ?></span>
                                            </div>
                                            
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="log-in" class="w-4 h-4 text-gray-400"></i>
                                                <span class="text-sm text-gray-600">
                                                    Last login: <?php 
                                                        if ($user['last_login'] !== 'Never') {
                                                            try {
                                                                $date = new DateTime($user['last_login']);
                                                                echo $date->format('M j, Y H:i');
                                                            } catch (Exception $e) {
                                                                echo 'Recently';
                                                            }
                                                        } else {
                                                            echo 'Never';
                                                        }
                                                    ?>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- User Stats -->
                                        <div class="grid grid-cols-2 gap-3 mb-6">
                                            <div class="bg-gray-50 p-3 rounded-lg text-center">
                                                <div class="text-lg font-bold text-gray-900"><?php echo $user['total_logins']; ?></div>
                                                <div class="text-xs text-gray-500">Logins</div>
                                            </div>
                                            <div class="bg-gray-50 p-3 rounded-lg text-center">
                                                <div class="text-lg font-bold text-gray-900"><?php echo $user['total_activities']; ?></div>
                                                <div class="text-xs text-gray-500">Activities</div>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flex gap-2">
                                            <button onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                                    class="action-btn flex-1 btn btn-outline border-gray-300 py-2 rounded-lg flex items-center justify-center gap-2">
                                                <i data-lucide="edit" class="w-4 h-4"></i>
                                                Edit
                                            </button>
                                            <button onclick="viewUserLogs('<?php echo htmlspecialchars($user['employee_id']); ?>', '<?php echo htmlspecialchars($user['employee_name']); ?>')" 
                                                    class="action-btn flex-1 btn btn-outline border-gray-300 py-2 rounded-lg flex items-center justify-center gap-2">
                                                <i data-lucide="history" class="w-4 h-4"></i>
                                                Logs
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-span-3">
                                <div class="empty-state bg-white rounded-xl p-8">
                                    <div class="empty-state-icon">
                                        <i data-lucide="users" class="w-16 h-16 mx-auto"></i>
                                    </div>
                                    <h3 class="text-xl font-semibold text-gray-900 mb-2">No Users Found</h3>
                                    <p class="text-gray-500 mb-6">Try adjusting your search or filter to find what you're looking for.</p>
                                    <button onclick="resetFilters()" class="btn btn-primary">
                                        <i data-lucide="refresh-ccw" class="w-4 h-4 mr-2"></i>
                                        Reset Filters
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="card bg-white p-6">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                <div class="text-sm text-gray-500">
                                    Showing <span class="font-medium text-gray-700"><?php echo min($offset + 1, $totalRecords); ?></span> to 
                                    <span class="font-medium text-gray-700"><?php echo min($offset + $perPage, $totalRecords); ?></span> of 
                                    <span class="font-medium text-gray-700"><?php echo $totalRecords; ?></span> users
                                </div>
                                
                                <div class="flex items-center gap-1">
                                    <!-- First Page -->
                                    <button onclick="changePage(1)" 
                                            class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <i data-lucide="chevrons-left" class="w-4 h-4"></i>
                                    </button>
                                    
                                    <!-- Previous Page -->
                                    <button onclick="changePage(<?php echo $page - 1; ?>)" 
                                            class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                    </button>
                                    
                                    <!-- Page Numbers -->
                                    <?php
                                    $startPage = max(1, $page - 1);
                                    $endPage = min($totalPages, $page + 1);
                                    
                                    if ($startPage > 1) {
                                        echo '<button onclick="changePage(1)" class="pagination-btn">1</button>';
                                        if ($startPage > 2) echo '<span class="px-2 text-gray-400">...</span>';
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $activeClass = $i == $page ? 'active' : '';
                                        echo '<button onclick="changePage('.$i.')" class="pagination-btn '.$activeClass.'">'.$i.'</button>';
                                    }
                                    
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) echo '<span class="px-2 text-gray-400">...</span>';
                                        echo '<button onclick="changePage('.$totalPages.')" class="pagination-btn">'.$totalPages.'</button>';
                                    }
                                    ?>
                                    
                                    <!-- Next Page -->
                                    <button onclick="changePage(<?php echo $page + 1; ?>)" 
                                            class="pagination-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                    </button>
                                    
                                    <!-- Last Page -->
                                    <button onclick="changePage(<?php echo $totalPages; ?>)" 
                                            class="pagination-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <i data-lucide="chevrons-right" class="w-4 h-4"></i>
                                    </button>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500">Items per page:</span>
                                    <select id="per-page-select" class="select select-bordered select-sm bg-white" onchange="changePerPage(this.value)">
                                        <option value="5" <?php echo $perPage == 5 ? 'selected' : ''; ?>>5</option>
                                        <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                                        <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                                        <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="edit-user-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="p-6">
                <!-- Modal Header -->
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Edit User</h3>
                    <button onclick="closeEditUserModal()" class="btn btn-ghost btn-sm btn-circle">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-gray-200 mb-6">
                    <button onclick="switchEditTab('basic')" 
                            class="tab-button active" id="tab-basic">
                        Basic Info
                    </button>
                    <button onclick="switchEditTab('security')" 
                            class="tab-button" id="tab-security">
                        Security
                    </button>
                    <button onclick="switchEditTab('logs')" 
                            class="tab-button" id="tab-logs">
                        User Logs
                    </button>
                </div>

                <!-- Basic Info Tab -->
                <div id="edit-basic-tab" class="edit-tab">
                    <form id="edit-user-form" class="space-y-4">
                        <input type="hidden" id="edit-user-id" name="user_id">
                        
                        <!-- Profile Picture -->
                        <div class="flex flex-col items-center mb-6">
                            <div class="relative mb-4">
                                <div id="edit-user-avatar" class="user-avatar rounded-full overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200">
                                    <!-- Profile picture will be loaded here -->
                                </div>
                                <button type="button" 
                                        onclick="document.getElementById('edit-profile-picture').click()" 
                                        class="absolute bottom-0 right-0 btn btn-sm btn-primary btn-circle shadow-lg">
                                    <i data-lucide="camera" class="w-4 h-4"></i>
                                </button>
                                <input type="file" 
                                       id="edit-profile-picture" 
                                       name="profile_picture" 
                                       accept="image/*" 
                                       class="hidden" 
                                       onchange="previewEditImage(event)">
                            </div>
                        </div>

                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                            <input type="text" 
                                   id="edit-name" 
                                   name="name" 
                                   required
                                   class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                            <input type="email" 
                                   id="edit-email" 
                                   name="email" 
                                   required
                                   class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                        </div>

                        <!-- Role -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                            <select id="edit-role" 
                                    name="role" 
                                    required
                                    class="filter-select w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                                <option value="">Select Role</option>
                                <?php
                                $roles_result = $conn->query("SELECT DISTINCT role FROM department_accounts WHERE role IS NOT NULL AND role != '' ORDER BY role");
                                while ($role_row = $roles_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($role_row['role']); ?>">
                                        <?php echo htmlspecialchars($role_row['role']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                            <select id="edit-status" 
                                    name="status" 
                                    required
                                    class="filter-select w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>

                        <!-- Department -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                            <input type="text" 
                                   id="edit-department" 
                                   name="department" 
                                   class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                        </div>

                        <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                            <button type="button" 
                                    onclick="closeEditUserModal()" 
                                    class="btn btn-ghost text-gray-600">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="btn btn-primary">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security Tab -->
                <div id="edit-security-tab" class="edit-tab hidden">
                    <form id="change-password-form" class="space-y-4">
                        <input type="hidden" id="password-user-id" name="user_id">
                        
                        <!-- Current Password (for admin verification) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Your Admin Password *</label>
                            <div class="relative">
                                <input type="password" 
                                       id="admin-password" 
                                       name="admin_password" 
                                       required
                                       class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                                <button type="button" 
                                        onclick="togglePasswordVisibility('admin-password', this)" 
                                        class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                    <i data-lucide="eye" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>

                        <!-- New Password -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Password *</label>
                            <div class="relative">
                                <input type="password" 
                                       id="new-password" 
                                       name="new_password" 
                                       required
                                       oninput="checkPasswordStrength(this.value)"
                                       class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                                <button type="button" 
                                        onclick="togglePasswordVisibility('new-password', this)" 
                                        class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                    <i data-lucide="eye" class="w-5 h-5"></i>
                                </button>
                            </div>
                            <div class="mt-2 space-y-2">
                                <div class="flex items-center gap-2">
                                    <div id="password-strength" class="password-strength w-full bg-gray-200"></div>
                                    <span id="strength-text" class="text-xs font-medium"></span>
                                </div>
                                <p class="text-xs text-gray-500">Password must be at least 8 characters with uppercase, lowercase, number, and special character.</p>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                            <div class="relative">
                                <input type="password" 
                                       id="confirm-password" 
                                       name="confirm_password" 
                                       required
                                       oninput="checkPasswordMatch()"
                                       class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                                <button type="button" 
                                        onclick="togglePasswordVisibility('confirm-password', this)" 
                                        class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                    <i data-lucide="eye" class="w-5 h-5"></i>
                                </button>
                            </div>
                            <p id="password-match-message" class="text-xs mt-2"></p>
                        </div>

                        <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                            <button type="button" 
                                    onclick="switchEditTab('basic')" 
                                    class="btn btn-ghost text-gray-600">
                                Back
                            </button>
                            <button type="submit" 
                                    class="btn btn-primary">
                                Change Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- User Logs Tab -->
                <div id="edit-logs-tab" class="edit-tab hidden">
                    <div class="space-y-4">
                        <div id="user-logs-info" class="mb-4 p-4 bg-gray-50 rounded-lg">
                            <h4 class="font-medium text-gray-900" id="logs-user-name"></h4>
                            <p class="text-sm text-gray-500" id="logs-user-id"></p>
                        </div>
                        
                        <!-- Logs Filter -->
                        <div class="flex gap-2 mb-4">
                            <select id="logs-type-filter" class="filter-select flex-1 bg-white">
                                <option value="all">All Logs</option>
                                <option value="department">Department Logs</option>
                                <option value="audit">Audit Trails</option>
                            </select>
                            <select id="logs-period-filter" class="filter-select flex-1 bg-white">
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="all">All Time</option>
                            </select>
                        </div>

                        <!-- Logs Container -->
                        <div id="user-logs-container" class="space-y-3 max-h-96 overflow-y-auto custom-scrollbar">
                            <!-- Logs will be loaded here -->
                        </div>

                        <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                            <button type="button" 
                                    onclick="switchEditTab('security')" 
                                    class="btn btn-ghost text-gray-600">
                                Back
                            </button>
                            <button type="button" 
                                    onclick="exportUserLogs()" 
                                    class="btn btn-outline border-gray-300">
                                <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                                Export Logs
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="add-user-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Add New User</h3>
                    <button onclick="closeAddUserModal()" class="btn btn-ghost btn-sm btn-circle">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form id="add-user-form" class="space-y-4">
                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                        <input type="text" 
                               name="name" 
                               required
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                        <input type="email" 
                               name="email" 
                               required
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                    </div>

                    <!-- Employee ID -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Employee ID *</label>
                        <input type="text" 
                               name="employee_id" 
                               required
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                    </div>

                    <!-- Role -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                        <select name="role" 
                                required
                                class="filter-select w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                            <option value="">Select Role</option>
                            <?php
                            $roles_result = $conn->query("SELECT DISTINCT role FROM department_accounts WHERE role IS NOT NULL AND role != '' ORDER BY role");
                            while ($role_row = $roles_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($role_row['role']); ?>">
                                    <?php echo htmlspecialchars($role_row['role']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Department -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <input type="text" 
                               name="department" 
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                    </div>

                    <!-- Initial Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Initial Password *</label>
                        <div class="relative">
                            <input type="password" 
                                   name="password" 
                                   required
                                   class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
                            <button type="button" 
                                    onclick="togglePasswordVisibility(this)" 
                                    class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                <i data-lucide="eye" class="w-5 h-5"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">User will be prompted to change password on first login</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                        <button type="button" 
                                onclick="closeAddUserModal()" 
                                class="btn btn-ghost text-gray-600">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="btn btn-primary">
                            Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Department Logs Modal -->
    <div id="department-logs-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Department Logs</h3>
                    <button onclick="closeDepartmentLogsModal()" class="btn btn-ghost btn-sm btn-circle">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="mb-6">
                    <div class="flex flex-col lg:flex-row gap-4">
                        <div class="flex-1">
                            <div class="relative">
                                <i data-lucide="search" class="absolute left-3 top-3 w-5 h-5 text-gray-400"></i>
                                <input type="text" 
                                       id="logs-search" 
                                       placeholder="Search logs by employee ID, name, or department..." 
                                       class="search-input w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white"
                                       onkeyup="filterLogs()">
                            </div>
                        </div>
                        <div class="w-full lg:w-48">
                            <select id="logs-status-filter" class="filter-select w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white" onchange="filterLogs()">
                                <option value="all">All Status</option>
                                <option value="success">Success</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <div class="table-wrapper">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Log ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody id="logs-table-body">
                                <!-- Logs will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination for logs -->
                <div id="logs-pagination" class="flex justify-between items-center mt-6 pt-6 border-t border-gray-200">
                    <!-- Pagination will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Trails Modal -->
    <div id="audit-trails-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Audit Trails</h3>
                    <button onclick="closeAuditTrailsModal()" class="btn btn-ghost btn-sm btn-circle">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="mb-6">
                    <div class="flex flex-col lg:flex-row gap-4">
                        <div class="flex-1">
                            <div class="relative">
                                <i data-lucide="search" class="absolute left-3 top-3 w-5 h-5 text-gray-400"></i>
                                <input type="text" 
                                       id="audit-search" 
                                       placeholder="Search audit trails by employee ID, name, or department..." 
                                       class="search-input w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white"
                                       onkeyup="filterAuditTrails()">
                            </div>
                        </div>
                        <div class="w-full lg:w-48">
                            <select id="audit-type-filter" class="filter-select w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white" onchange="filterAuditTrails()">
                                <option value="all">All Types</option>
                                <option value="login">Login</option>
                                <option value="logout">Logout</option>
                                <option value="create">Create</option>
                                <option value="update">Update</option>
                                <option value="delete">Delete</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <div class="table-wrapper">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Module</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody id="audit-table-body">
                                <!-- Audit trails will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination for audit trails -->
                <div id="audit-pagination" class="flex justify-between items-center mt-6 pt-6 border-t border-gray-200">
                    <!-- Pagination will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="../JavaScript/sidebar.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // ================== FILTER FUNCTIONS ==================
        function applyFilters() {
            const url = new URL(window.location.href);
            const search = document.getElementById('search-input').value;
            const role = document.getElementById('role-filter').value;
            const status = document.getElementById('status-filter').value;

            url.searchParams.set('search', search);
            url.searchParams.set('role_filter', role);
            url.searchParams.set('status_filter', status);
            url.searchParams.set('page', 1);

            window.location.href = url.toString();
        }

        function resetFilters() {
            const url = new URL(window.location.href);
            url.searchParams.delete('search');
            url.searchParams.delete('role_filter');
            url.searchParams.delete('status_filter');
            url.searchParams.delete('page');

            window.location.href = url.toString();
        }

        // ================== PAGINATION FUNCTIONS ==================
        function changePage(page) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            window.location.href = url.toString();
        }

        function changePerPage(perPage) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', perPage);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }

        // ================== MODAL FUNCTIONS ==================
        function openEditUserModal(userData) {
            // Populate form fields
            document.getElementById('edit-user-id').value = userData.employee_id;
            document.getElementById('edit-name').value = userData.employee_name;
            document.getElementById('edit-email').value = userData.email || '';
            document.getElementById('edit-role').value = userData.role || '';
            document.getElementById('edit-status').value = userData.status || 'active';
            document.getElementById('edit-department').value = userData.dept_name || '';
            
            // Set password form user ID
            document.getElementById('password-user-id').value = userData.employee_id;
            
            // Load profile picture
            const avatarDiv = document.getElementById('edit-user-avatar');
            avatarDiv.innerHTML = '';
            
            let imagePath = '';
            if (userData.image_url) {
                imagePath = userData.image_url;
            } else if (userData.profile_picture) {
                imagePath = userData.profile_picture;
            }
            
            if (imagePath) {
                avatarDiv.innerHTML = `
                    <img src="Profile_images/${imagePath}" 
                         alt="${userData.employee_name}" 
                         class="w-full h-full object-cover"
                         onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzk5OSI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+='">
                `;
            } else {
                avatarDiv.innerHTML = `
                    <div class="w-full h-full flex items-center justify-center">
                        <i data-lucide="user" class="w-10 h-10 text-gray-400"></i>
                    </div>
                `;
            }
            
            // Switch to basic info tab
            switchEditTab('basic');
            
            // Show modal
            document.getElementById('edit-user-modal').style.display = 'flex';
        }

        function closeEditUserModal() {
            document.getElementById('edit-user-modal').style.display = 'none';
            document.getElementById('edit-user-form').reset();
            document.getElementById('change-password-form').reset();
        }

        function openAddUserModal() {
            document.getElementById('add-user-modal').style.display = 'flex';
        }

        function closeAddUserModal() {
            document.getElementById('add-user-modal').style.display = 'none';
            document.getElementById('add-user-form').reset();
        }

        function viewUserLogs(userId, userName) {
            // Set user info in logs tab
            document.getElementById('logs-user-name').textContent = `Logs for: ${userName}`;
            document.getElementById('logs-user-id').textContent = `Employee ID: ${userId}`;
            
            // Find user in the users array
            const user = users.find(u => u.employee_id === userId);
            if (user) {
                openEditUserModal(user);
                switchEditTab('logs');
                loadUserLogs(userId);
            }
        }

        // ================== DEPARTMENT LOGS FUNCTIONS ==================
        async function viewDepartmentLogs(page = 1) {
            try {
                const response = await fetch(`sub-modules/fetch_logs.php?type=department&page=${page}`);
                const data = await response.json();
                
                if (data.success) {
                    displayDepartmentLogs(data.logs, data.totalPages, page);
                    document.getElementById('department-logs-modal').style.display = 'flex';
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to load department logs'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while loading logs'
                });
            }
        }

        function closeDepartmentLogsModal() {
            document.getElementById('department-logs-modal').style.display = 'none';
        }

        function displayDepartmentLogs(logs, totalPages, currentPage) {
            const tbody = document.getElementById('logs-table-body');
            tbody.innerHTML = '';
            
            if (logs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                            <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 text-gray-300"></i>
                            <p>No logs found</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            logs.forEach(log => {
                const date = new Date(log.date);
                const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                
                const row = document.createElement('tr');
                row.className = 'table-row';
                row.innerHTML = `
                    <td class="table-cell">${log.dept_logs_id || log.id}</td>
                    <td class="table-cell">${log.dept_name || 'N/A'}</td>
                    <td class="table-cell">${log.employee_id || 'N/A'}</td>
                    <td class="table-cell">${log.employee_name || 'N/A'}</td>
                    <td class="table-cell">
                        <span class="log-type log-type-${log.log_type || 'activity'}">
                            ${log.log_type || 'Activity'}
                        </span>
                    </td>
                    <td class="table-cell">
                        <span class="${log.log_status === 'success' ? 'log-status-success' : 'log-status-failed'}">
                            ${log.log_status || 'Unknown'}
                        </span>
                    </td>
                    <td class="table-cell">${log.details || log.log_details || 'No details'}</td>
                    <td class="table-cell">${formattedDate}</td>
                `;
                tbody.appendChild(row);
            });
            
            // Update pagination
            updateLogsPagination(totalPages, currentPage);
        }

        function updateLogsPagination(totalPages, currentPage) {
            const paginationDiv = document.getElementById('logs-pagination');
            if (totalPages <= 1) {
                paginationDiv.innerHTML = '';
                return;
            }
            
            let html = `
                <div class="text-sm text-gray-500">
                    Page ${currentPage} of ${totalPages}
                </div>
                <div class="flex gap-1">
            `;
            
            if (currentPage > 1) {
                html += `
                    <button onclick="viewDepartmentLogs(${currentPage - 1})" class="pagination-btn">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </button>
                `;
            }
            
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <button onclick="viewDepartmentLogs(${i})" 
                            class="pagination-btn ${i === currentPage ? 'active' : ''}">
                        ${i}
                    </button>
                `;
            }
            
            if (currentPage < totalPages) {
                html += `
                    <button onclick="viewDepartmentLogs(${currentPage + 1})" class="pagination-btn">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </button>
                `;
            }
            
            html += '</div>';
            paginationDiv.innerHTML = html;
            lucide.createIcons();
        }

        function filterLogs() {
            const searchTerm = document.getElementById('logs-search').value.toLowerCase();
            const statusFilter = document.getElementById('logs-status-filter').value;
            const rows = document.querySelectorAll('#logs-table-body tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 8) return;
                
                const employeeId = cells[2].textContent.toLowerCase();
                const employee = cells[3].textContent.toLowerCase();
                const department = cells[1].textContent.toLowerCase();
                const status = cells[5].textContent.toLowerCase();
                
                const matchesSearch = employeeId.includes(searchTerm) || 
                                     employee.includes(searchTerm) || 
                                     department.includes(searchTerm);
                const matchesStatus = statusFilter === 'all' || status.includes(statusFilter);
                
                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            });
        }

        // ================== AUDIT TRAILS FUNCTIONS ==================
        async function viewAuditTrails(page = 1) {
            try {
                const response = await fetch(`sub-modules/fetch_logs.php?type=audit&page=${page}`);
                const data = await response.json();
                
                if (data.success) {
                    displayAuditTrails(data.trails, data.totalPages, page);
                    document.getElementById('audit-trails-modal').style.display = 'flex';
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to load audit trails'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while loading audit trails'
                });
            }
        }

        function closeAuditTrailsModal() {
            document.getElementById('audit-trails-modal').style.display = 'none';
        }

        function displayAuditTrails(trails, totalPages, currentPage) {
            const tbody = document.getElementById('audit-table-body');
            tbody.innerHTML = '';
            
            if (trails.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                            <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 text-gray-300"></i>
                            <p>No audit trails found</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            trails.forEach(trail => {
                const date = new Date(trail.date);
                const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                
                const row = document.createElement('tr');
                row.className = 'table-row';
                row.innerHTML = `
                    <td class="table-cell">${trail.audit_id || trail.id}</td>
                    <td class="table-cell">${trail.employee_id || 'N/A'}</td>
                    <td class="table-cell">${trail.employee_name || 'N/A'}</td>
                    <td class="table-cell">${trail.department || 'N/A'}</td>
                    <td class="table-cell">
                        <span class="badge ${getActionBadgeClass(trail.action)}">
                            ${trail.action || 'Unknown'}
                        </span>
                    </td>
                    <td class="table-cell">${trail.module || 'N/A'}</td>
                    <td class="table-cell">${trail.details || 'No details'}</td>
                    <td class="table-cell">${formattedDate}</td>
                `;
                tbody.appendChild(row);
            });
            
            // Update pagination
            updateAuditPagination(totalPages, currentPage);
        }

        function getActionBadgeClass(action) {
            switch(action?.toLowerCase()) {
                case 'create': return 'badge-success';
                case 'update': return 'badge-primary';
                case 'delete': return 'badge-danger';
                case 'login': return 'badge-success';
                case 'logout': return 'badge-warning';
                default: return 'badge-primary';
            }
        }

        function updateAuditPagination(totalPages, currentPage) {
            const paginationDiv = document.getElementById('audit-pagination');
            if (totalPages <= 1) {
                paginationDiv.innerHTML = '';
                return;
            }
            
            let html = `
                <div class="text-sm text-gray-500">
                    Page ${currentPage} of ${totalPages}
                </div>
                <div class="flex gap-1">
            `;
            
            if (currentPage > 1) {
                html += `
                    <button onclick="viewAuditTrails(${currentPage - 1})" class="pagination-btn">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </button>
                `;
            }
            
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <button onclick="viewAuditTrails(${i})" 
                            class="pagination-btn ${i === currentPage ? 'active' : ''}">
                        ${i}
                    </button>
                `;
            }
            
            if (currentPage < totalPages) {
                html += `
                    <button onclick="viewAuditTrails(${currentPage + 1})" class="pagination-btn">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </button>
                `;
            }
            
            html += '</div>';
            paginationDiv.innerHTML = html;
            lucide.createIcons();
        }

        function filterAuditTrails() {
            const searchTerm = document.getElementById('audit-search').value.toLowerCase();
            const typeFilter = document.getElementById('audit-type-filter').value;
            const rows = document.querySelectorAll('#audit-table-body tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 8) return;
                
                const employeeId = cells[1].textContent.toLowerCase();
                const employee = cells[2].textContent.toLowerCase();
                const action = cells[4].textContent.toLowerCase();
                
                const matchesSearch = employeeId.includes(searchTerm) || 
                                     employee.includes(searchTerm);
                const matchesType = typeFilter === 'all' || action.includes(typeFilter);
                
                row.style.display = matchesSearch && matchesType ? '' : 'none';
            });
        }

        // ================== EDIT TAB FUNCTIONS ==================
        function switchEditTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.edit-tab').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById('edit-' + tabName + '-tab').classList.remove('hidden');
            
            // Activate selected tab button
            document.getElementById('tab-' + tabName).classList.add('active');
            
            // Load logs if switching to logs tab
            if (tabName === 'logs') {
                const userId = document.getElementById('edit-user-id').value;
                loadUserLogs(userId);
            }
        }

        // ================== PASSWORD FUNCTIONS ==================
        function togglePasswordVisibility(fieldId, button) {
            const input = document.getElementById(fieldId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                input.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }

        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('password-strength');
            const strengthText = document.getElementById('strength-text');
            
            let strength = 0;
            let text = '';
            let color = '#ef4444';
            
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    text = 'Very Weak';
                    color = '#ef4444';
                    break;
                case 2:
                    text = 'Weak';
                    color = '#f97316';
                    break;
                case 3:
                    text = 'Fair';
                    color = '#eab308';
                    break;
                case 4:
                    text = 'Good';
                    color = '#3b82f6';
                    break;
                case 5:
                    text = 'Strong';
                    color = '#10b981';
                    break;
            }
            
            strengthBar.style.width = (strength * 20) + '%';
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = text;
            strengthText.style.color = color;
        }

        function checkPasswordMatch() {
            const password = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            const message = document.getElementById('password-match-message');
            
            if (!confirmPassword) {
                message.textContent = '';
                message.className = 'text-xs mt-2';
                return;
            }
            
            if (password === confirmPassword) {
                message.textContent = '✓ Passwords match';
                message.className = 'text-xs mt-2 text-green-600';
            } else {
                message.textContent = '✗ Passwords do not match';
                message.className = 'text-xs mt-2 text-red-600';
            }
        }

        // ================== FORM SUBMISSIONS ==================
    // ================== FORM SUBMISSIONS ==================
document.getElementById('edit-user-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Create FormData including files
    const formData = new FormData(this);
    formData.append('action', 'update_user');
    
    // Add the profile picture file if selected
    const profilePicInput = document.getElementById('edit-profile-picture');
    if (profilePicInput.files.length > 0) {
        formData.append('profile_picture', profilePicInput.files[0]);
    }
    
    try {
        const response = await fetch('sub-modules/update_user_api.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'User updated successfully!',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                closeEditUserModal();
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to update user'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while updating user'
        });
    }
});

document.getElementById('change-password-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;
    
    if (newPassword !== confirmPassword) {
        Swal.fire({
            icon: 'error',
            title: 'Password Mismatch',
            text: 'New password and confirmation do not match.'
        });
        return;
    }
    
    if (newPassword.length < 8) {
        Swal.fire({
            icon: 'error',
            title: 'Weak Password',
            text: 'Password must be at least 8 characters long.'
        });
        return;
    }
    
    const formData = new FormData(this);
    formData.append('action', 'change_password_admin');
    
    try {
        const response = await fetch('sub-modules/update_user_api.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Password changed successfully!',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                document.getElementById('change-password-form').reset();
                document.getElementById('password-strength').style.width = '0%';
                document.getElementById('strength-text').textContent = '';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to change password'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while changing password'
        });
    }
});

document.getElementById('add-user-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'create_user');
    
    try {
        const response = await fetch('sub-modules/update_user_api.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'User created successfully!',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                closeAddUserModal();
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to create user'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while creating user'
        });
    }
});

// Fix the loadUserLogs function - change the parameter name
async function loadUserLogs(userId) {
    const typeFilter = document.getElementById('logs-type-filter').value;
    const periodFilter = document.getElementById('logs-period-filter').value;
    const container = document.getElementById('user-logs-container');
    
    container.innerHTML = `
        <div class="flex justify-center py-8">
            <div class="loading-spinner"></div>
        </div>
    `;
    
    try {
        const formData = new FormData();
        formData.append('employee_id', userId); // Changed from 'user_id' to 'employee_id'
        formData.append('type', typeFilter);
        formData.append('period', periodFilter);
        formData.append('action', 'get_user_logs');
        
        const response = await fetch('sub-modules/update_user_api.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success && data.logs.length > 0) {
            // ... rest of your display code remains the same ...
        }
    } catch (error) {
        console.error('Error loading logs:', error);
    }
}

        document.getElementById('change-password-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (newPassword !== confirmPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Password Mismatch',
                    text: 'New password and confirmation do not match.'
                });
                return;
            }
            
            if (newPassword.length < 8) {
                Swal.fire({
                    icon: 'error',
                    title: 'Weak Password',
                    text: 'Password must be at least 8 characters long.'
                });
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'change_password_admin');
            
            try {
                const response = await fetch('sub-modules/update_user_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Password changed successfully!',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        document.getElementById('change-password-form').reset();
                        document.getElementById('password-strength').style.width = '0%';
                        document.getElementById('strength-text').textContent = '';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to change password'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while changing password'
                });
            }
        });

        document.getElementById('add-user-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create_user');
            
            try {
                const response = await fetch('sub-modules/update_user_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'User created successfully!',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        closeAddUserModal();
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to create user'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while creating user'
                });
            }
        });

        // ================== USER LOGS FUNCTIONS ==================
        async function loadUserLogs(userId) {
            const typeFilter = document.getElementById('logs-type-filter').value;
            const periodFilter = document.getElementById('logs-period-filter').value;
            const container = document.getElementById('user-logs-container');
            
            container.innerHTML = `
                <div class="flex justify-center py-8">
                    <div class="loading-spinner"></div>
                </div>
            `;
            
            try {
                const formData = new FormData();
                formData.append('employee_id', userId);
                formData.append('type', typeFilter);
                formData.append('period', periodFilter);
                formData.append('action', 'get_user_logs');
                
                const response = await fetch('sub-modules/update_user_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success && data.logs.length > 0) {
                    let html = '';
                    data.logs.forEach(log => {
                        const date = new Date(log.date || log.created_at);
                        const timeAgo = getTimeAgo(date);
                        const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                        
                        // Determine log type
                        let logType = 'activity';
                        let logIcon = 'activity';
                        let logColor = 'bg-orange-500';
                        
                        if (log.log_type) {
                            logType = log.log_type;
                            if (log.log_type === 'login') {
                                logIcon = 'log-in';
                                logColor = 'bg-green-500';
                            } else if (log.log_type === 'logout') {
                                logIcon = 'log-out';
                                logColor = 'bg-blue-500';
                            }
                        } else if (log.action) {
                            logType = log.action;
                            if (log.action === 'login') {
                                logIcon = 'log-in';
                                logColor = 'bg-green-500';
                            } else if (log.action === 'logout') {
                                logIcon = 'log-out';
                                logColor = 'bg-blue-500';
                            } else if (log.action === 'create') {
                                logIcon = 'plus';
                                logColor = 'bg-green-500';
                            } else if (log.action === 'update') {
                                logIcon = 'edit';
                                logColor = 'bg-blue-500';
                            } else if (log.action === 'delete') {
                                logIcon = 'trash';
                                logColor = 'bg-red-500';
                            }
                        }
                        
                        html += `
                            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                                <div class="flex-shrink-0 mt-1">
                                    <div class="w-8 h-8 rounded-full ${logColor} flex items-center justify-center">
                                        <i data-lucide="${logIcon}" class="w-4 h-4 text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm text-gray-900 font-medium">${logType.toUpperCase()}</p>
                                    <p class="text-sm text-gray-700 mt-1">${log.details || log.log_details || log.module || 'No details'}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs text-gray-500">${formattedDate}</span>
                                        ${log.log_status ? `<span class="text-xs ${getStatusColor(log.log_status)}">${log.log_status}</span>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                    lucide.createIcons();
                } else {
                    container.innerHTML = `
                        <div class="text-center py-8">
                            <i data-lucide="inbox" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                            <p class="text-gray-500">No logs found for this user</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading logs:', error);
                container.innerHTML = `
                    <div class="text-center py-8">
                        <i data-lucide="alert-circle" class="w-12 h-12 text-red-300 mx-auto mb-3"></i>
                        <p class="text-red-500">Failed to load logs</p>
                    </div>
                `;
            }
        }

        function getStatusColor(status) {
            switch(status.toLowerCase()) {
                case 'success': return 'text-green-600';
                case 'failed': return 'text-red-600';
                case 'warning': return 'text-yellow-600';
                default: return 'text-gray-600';
            }
        }

        function getTimeAgo(date) {
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins} minutes ago`;
            if (diffHours < 24) return `${diffHours} hours ago`;
            if (diffDays < 30) return `${diffDays} days ago`;
            return date.toLocaleDateString();
        }

        function exportUserLogs() {
            Swal.fire({
                icon: 'info',
                title: 'Export Feature',
                text: 'This feature is coming soon!'
            });
        }

        // ================== IMAGE PREVIEW ==================
        function previewEditImage(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Too Large',
                    text: 'Maximum file size is 5MB.'
                });
                event.target.value = '';
                return;
            }
            
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid File Type',
                    text: 'Only JPG, PNG, GIF, and WebP images are allowed.'
                });
                event.target.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const avatarDiv = document.getElementById('edit-user-avatar');
                avatarDiv.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
            }
            reader.readAsDataURL(file);
        }

        // ================== EVENT LISTENERS ==================
        document.getElementById('logs-type-filter').addEventListener('change', function() {
            const userId = document.getElementById('edit-user-id').value;
            if (userId) loadUserLogs(userId);
        });

        document.getElementById('logs-period-filter').addEventListener('change', function() {
            const userId = document.getElementById('edit-user-id').value;
            if (userId) loadUserLogs(userId);
        });

        // Close modals on outside click
        window.addEventListener('click', function(event) {
            const editModal = document.getElementById('edit-user-modal');
            const addModal = document.getElementById('add-user-modal');
            const logsModal = document.getElementById('department-logs-modal');
            const auditModal = document.getElementById('audit-trails-modal');
            
            if (event.target === editModal) closeEditUserModal();
            if (event.target === addModal) closeAddUserModal();
            if (event.target === logsModal) closeDepartmentLogsModal();
            if (event.target === auditModal) closeAuditTrailsModal();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditUserModal();
                closeAddUserModal();
                closeDepartmentLogsModal();
                closeAuditTrailsModal();
            }
        });

        // Global users array for reference
        const users = <?php echo json_encode($users); ?>;
    </script>
</body>
</html>