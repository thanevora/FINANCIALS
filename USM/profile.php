<?php
session_start();

include("../main_connection.php");

$db_name = "rest_core_2_usm";
$conn = $connections[$db_name] ?? die("❌ Connection not found for $db_name");

// Get current logged-in user
$user_id = $_SESSION['employee_id'] ?? null;
$user_name = $_SESSION['employee_name'] ?? null;

if (!$user_id) {
    die("❌ User not logged in");
}

// Fetch user data from department_accounts
$query = "SELECT * FROM department_accounts WHERE employee_id = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user_profile = $result->fetch_assoc();
    
    // Ensure employee_name is set in session if not already
    if (empty($user_name) && !empty($user_profile['employee_name'])) {
        $_SESSION['employee_name'] = $user_profile['employee_name'];
        $user_name = $user_profile['employee_name'];
    }
} else {
    die("❌ User profile not found");
}

// Fetch department ID and format as "Restaurant - Dept IT"
$dept_query = "SELECT dept_id FROM department_accounts WHERE employee_id = ?";
$dept_stmt = $conn->prepare($dept_query);
$dept_stmt->bind_param("s", $user_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();

if ($dept_result->num_rows > 0) {
    $dept_data = $dept_result->fetch_assoc();
    $formatted_dept = "Restaurant - Dept " . htmlspecialchars($dept_data['dept_id'] ?? 'IT');
} else {
    $formatted_dept = "Restaurant - Dept IT";
}

// ========== LOGIN LOGS WITH PAGINATION ==========
$login_page = isset($_GET['login_page']) ? max(1, intval($_GET['login_page'])) : 1;
$login_limit = 20;
$login_offset = ($login_page - 1) * $login_limit;

// Search and filter parameters for login logs
$login_search = isset($_GET['login_search']) ? trim($_GET['login_search']) : '';
$login_status_filter = isset($_GET['login_status']) ? $_GET['login_status'] : '';
$login_type_filter = isset($_GET['login_type']) ? $_GET['login_type'] : '';

// Build login logs query with filters
$login_where = "WHERE employee_name = ?";
$login_params = [$user_name];
$login_param_types = "s";

if ($login_search) {
    $login_where .= " AND (employee_name LIKE ? OR log_status LIKE ? OR failure_reason LIKE ?)";
    $search_term = "%$login_search%";
    $login_params[] = $search_term;
    $login_params[] = $search_term;
    $login_params[] = $search_term;
    $login_param_types .= "sss";
}

if ($login_status_filter) {
    $login_where .= " AND log_status = ?";
    $login_params[] = $login_status_filter;
    $login_param_types .= "s";
}

if ($login_type_filter) {
    $login_where .= " AND log_type = ?";
    $login_params[] = $login_type_filter;
    $login_param_types .= "s";
}

// Get total login logs for pagination
$total_logins_query = "SELECT COUNT(*) as total FROM department_logs $login_where";
$total_logins_stmt = $conn->prepare($total_logins_query);

// Fix: Create references for bind_param
$login_params_refs = [];
foreach ($login_params as $key => $value) {
    $login_params_refs[$key] = &$login_params[$key];
}
array_unshift($login_params_refs, $login_param_types);
call_user_func_array([$total_logins_stmt, 'bind_param'], $login_params_refs);

$total_logins_stmt->execute();
$total_logins_result = $total_logins_stmt->get_result();
$total_logins_data = $total_logins_result->fetch_assoc();
$total_login_logs = $total_logins_data['total'] ?? 0;
$total_login_pages = ceil($total_login_logs / $login_limit);

// Fetch login logs with pagination
$logs_query = "SELECT employee_id, employee_name, log_status, attempt_count, failure_reason, cooldown, date, role, log_type 
               FROM department_logs 
               $login_where 
               ORDER BY date DESC 
               LIMIT ? OFFSET ?";

// Add pagination parameters
$login_params[] = $login_limit;
$login_params[] = $login_offset;
$login_param_types .= "ii";

$logs_stmt = $conn->prepare($logs_query);

// Fix: Create new references array with all parameters including pagination
$login_params_paged_refs = [];
foreach ($login_params as $key => $value) {
    $login_params_paged_refs[$key] = &$login_params[$key];
}
array_unshift($login_params_paged_refs, $login_param_types);
call_user_func_array([$logs_stmt, 'bind_param'], $login_params_paged_refs);

$logs_stmt->execute();
$logs_result = $logs_stmt->get_result();
$recent_logins = [];
while ($log = $logs_result->fetch_assoc()) {
    $recent_logins[] = $log;
}

// ========== ACTIVITY LOGS WITH PAGINATION ==========
$activity_page = isset($_GET['activity_page']) ? max(1, intval($_GET['activity_page'])) : 1;
$activity_limit = 20;
$activity_offset = ($activity_page - 1) * $activity_limit;

// Search and filter parameters for activity logs
$activity_search = isset($_GET['activity_search']) ? trim($_GET['activity_search']) : '';
$activity_type_filter = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';

// Find activity table
$all_tables_query = "SHOW TABLES";
$all_tables_result = $conn->query($all_tables_query);
$all_tables = [];
while($row = $all_tables_result->fetch_array()) {
    $all_tables[] = $row[0];
}

$activity_table = null;
$possible_tables = [
    'dept_audit_transc',
    'dept_auth_transc', 
    'audit_logs',
    'activity_logs',
    'system_logs',
    'user_activity',
    'department_audit'
];

foreach ($possible_tables as $table_name) {
    if (in_array($table_name, $all_tables)) {
        $activity_table = $table_name;
        break;
    }
}

$recent_activities = [];
$total_activity_logs = 0;
$total_activity_pages = 1;

if ($activity_table) {
    // Get columns from the activity table
    $activity_columns_query = "SHOW COLUMNS FROM $activity_table";
    $activity_columns_result = $conn->query($activity_columns_query);
    $activity_columns = [];
    while($row = $activity_columns_result->fetch_assoc()) {
        $activity_columns[] = $row['Field'];
    }
    
    // Check what columns exist
    $has_employee_name = in_array('employee_name', $activity_columns);
    $has_activity = in_array('activity', $activity_columns);
    $has_action = in_array('action', $activity_columns);
    $has_date = in_array('date', $activity_columns);
    $has_created_at = in_array('created_at', $activity_columns);
    
    if ($has_employee_name) {
        // Build activity where clause
        $activity_where = "WHERE employee_name = ?";
        $activity_params = [$user_name];
        $activity_param_types = "s";
        
        if ($activity_search) {
            if ($has_activity) {
                $activity_where .= " AND (activity LIKE ?";
                $search_term = "%$activity_search%";
                $activity_params[] = $search_term;
                $activity_param_types .= "s";
                
                if ($has_action) {
                    $activity_where .= " OR action LIKE ?";
                    $activity_params[] = $search_term;
                    $activity_param_types .= "s";
                }
                
                $activity_where .= ")";
            }
        }
        
        if ($activity_type_filter) {
            if ($has_action) {
                $activity_where .= " AND action = ?";
                $activity_params[] = $activity_type_filter;
                $activity_param_types .= "s";
            }
        }
        
        // Build columns to select
        $select_columns = [];
        $desired_columns = [
            'dept_name', 'modules_cover', 'action', 'activity', 
            'employee_name', 'role', 'date', 'created_at'
        ];
        
        foreach ($desired_columns as $col) {
            if (in_array($col, $activity_columns)) {
                $select_columns[] = $col;
            }
        }
        
        if (!empty($select_columns)) {
            $columns_str = implode(', ', $select_columns);
            
            // Determine date column
            $date_column = 'date';
            if (!$has_date && $has_created_at) {
                $date_column = 'created_at';
            }
            
            // Get total activities for pagination
            $total_activities_query = "SELECT COUNT(*) as total FROM $activity_table $activity_where";
            $total_activities_stmt = $conn->prepare($total_activities_query);
            
            // Fix: Create references for bind_param
            $activity_params_refs = [];
            foreach ($activity_params as $key => $value) {
                $activity_params_refs[$key] = &$activity_params[$key];
            }
            array_unshift($activity_params_refs, $activity_param_types);
            call_user_func_array([$total_activities_stmt, 'bind_param'], $activity_params_refs);
            
            $total_activities_stmt->execute();
            $total_activities_result = $total_activities_stmt->get_result();
            $total_activities_data = $total_activities_result->fetch_assoc();
            $total_activity_logs = $total_activities_data['total'] ?? 0;
            $total_activity_pages = ceil($total_activity_logs / $activity_limit);
            
            // Fetch activities with pagination
            $activity_params_paged = $activity_params;
            $activity_params_paged[] = $activity_limit;
            $activity_params_paged[] = $activity_offset;
            $activity_param_types_paged = $activity_param_types . "ii";
            
            $activity_query = "SELECT $columns_str FROM $activity_table 
                              $activity_where 
                              ORDER BY $date_column DESC 
                              LIMIT ? OFFSET ?";
            
            $activity_stmt = $conn->prepare($activity_query);
            
            // Fix: Create new references array with all parameters including pagination
            $activity_params_paged_refs = [];
            foreach ($activity_params_paged as $key => $value) {
                $activity_params_paged_refs[$key] = &$activity_params_paged[$key];
            }
            array_unshift($activity_params_paged_refs, $activity_param_types_paged);
            call_user_func_array([$activity_stmt, 'bind_param'], $activity_params_paged_refs);
            
            $activity_stmt->execute();
            $activity_result = $activity_stmt->get_result();
            
            while ($activity = $activity_result->fetch_assoc()) {
                $recent_activities[] = $activity;
            }
        }
    }
}

// Statistics
$total_activities = $total_activity_logs;
$total_logins = $total_login_logs;

// Total password changes count - This query looks correct
$password_changes_query = "SELECT COUNT(*) as total_changes FROM department_logs WHERE employee_name = ? AND log_type = 'password_change'";
$password_stmt = $conn->prepare($password_changes_query);
$password_stmt->bind_param("s", $user_name);
$password_stmt->execute();
$password_result = $password_stmt->get_result();
$password_data = $password_result->fetch_assoc();
$total_password_changes = $password_data['total_changes'] ?? 0;

// Prepare user data for display
$user = [
    'employee_id' => $user_profile['employee_id'],
    'employee_name' => $user_profile['employee_name'] ?? 'Unknown User',
    'role' => $user_profile['role'] ?? 'User',
    'email' => $user_profile['email'] ?? '',
    'image_url' => $user_profile['image_url'] ?? '',
    'dept_name' => $formatted_dept,
    'last_login' => !empty($recent_logins) ? ($recent_logins[0]['date'] ?? 'Never') : 'Never',
    'recent_activity' => !empty($recent_activities) ? 
        ($recent_activities[0]['activity'] ?? 
         $recent_activities[0]['action'] ?? 
         'No recent activity') : 'No recent activity'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>My Profile | Soliera Restaurant</title>
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
    <link rel="stylesheet" href="../CSS/sidebar.css">
    
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-hover: #4f46e5;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        .profile-card {
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .profile-avatar {
            width: 160px;
            height: 160px;
            border: 4px solid white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .input-field {
            transition: all 0.2s ease;
        }
        .input-field:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .tab-btn {
            position: relative;
            padding: 1rem 1.5rem;
            font-weight: 500;
            color: #64748b;
            transition: all 0.2s ease;
        }
        .tab-btn.active {
            color: var(--primary-color);
        }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 9999px;
        }
        .stat-card {
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            transition: all 0.2s ease;
        }
        .upload-area:hover {
            border-color: var(--primary-color);
            background-color: rgba(99, 102, 241, 0.02);
        }
        .upload-area.dragover {
            border-color: var(--primary-color);
            background-color: rgba(99, 102, 241, 0.05);
        }
        .password-strength {
            height: 4px;
            border-radius: 9999px;
            transition: all 0.3s ease;
        }
        .activity-item {
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
        }
        .activity-item:hover {
            border-left-color: var(--primary-color);
            background-color: rgba(99, 102, 241, 0.05);
        }
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }
        .badge-primary {
            background-color: #6366f120;
            color: #6366f1;
        }
        .badge-success {
            background-color: #10b98120;
            color: #10b981;
        }
        .badge-warning {
            background-color: #f59e0b20;
            color: #f59e0b;
        }
        .badge-error {
            background-color: #ef444420;
            color: #ef4444;
        }
        .badge-info {
            background-color: #3b82f620;
            color: #3b82f6;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-open {
            display: flex;
        }
        .modal-box {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            max-width: 28rem;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-action {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .table-container {
            max-height: 500px;
            overflow-y: auto;
        }
        .user-avatar-sm {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .pagination-btn {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            border: 1px solid #e5e7eb;
            background: white;
            color: #4b5563;
            transition: all 0.2s;
        }
        .pagination-btn:hover:not(.disabled) {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        .pagination-btn.active {
            background: #6366f1;
            color: white;
            border-color: #6366f1;
        }
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
            <h6 class="text-3xl font-bold text-gray-900 mb-2">My Profile</h6>
            <p class="text-gray-500">Manage your personal information and account settings</p>
          </div>
          
          <!-- Statistics Cards -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Total Activities Card -->
            <div class="stat-card bg-white text-black shadow-2xl p-5 rounded-xl transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:bg-gray-50">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-sm font-medium text-[#001f54] hover:drop-shadow-md transition-all">Total Activities</p>
                  <h3 class="text-3xl font-bold mt-1"><?php echo $total_activities; ?></h3>
                  <p class="text-xs text-gray-500 mt-1">All recorded activities</p>
                </div>
                <div class="p-3 rounded-lg bg-[#001f54] flex items-center justify-center transition-all duration-300 hover:bg-[#002b70]">
                  <i class="fas fa-chart-line text-2xl text-[#F7B32B]"></i>
                </div>
              </div>
            </div>

            <!-- Total Logins Card -->
            <div class="stat-card bg-white text-black shadow-2xl p-5 rounded-xl transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:bg-gray-50">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-sm font-medium text-[#001f54] hover:drop-shadow-md transition-all">Total Logins</p>
                  <h3 class="text-3xl font-bold mt-1"><?php echo $total_logins; ?></h3>
                  <p class="text-xs text-gray-500 mt-1">Successful logins</p>
                </div>
                <div class="p-3 rounded-lg bg-[#001f54] flex items-center justify-center transition-all duration-300 hover:bg-[#002b70]">
                  <i class="fas fa-sign-in-alt text-2xl text-[#F7B32B]"></i>
                </div>
              </div>
            </div>

            
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Left Sidebar - Profile Card -->
            <div class="lg:col-span-1">
              <div class="profile-card bg-white p-6 sticky top-6">
                <!-- Profile Picture -->
                <div class="flex flex-col items-center mb-6">
                  <div class="relative mb-4">
                   <!-- In your profile page HTML, update the image source -->
<div class="profile-avatar rounded-full overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200">
    <?php 
    // Check both profile_picture and image_url fields
    $image_field = '';
    if (!empty($user['image_url'])) {
        $image_field = 'image_url';
        $image_value = $user['image_url'];
    } elseif (!empty($user['profile_picture'])) {
        $image_field = 'profile_picture';
        $image_value = $user['profile_picture'];
    }
    
    if (!empty($image_field) && !empty($image_value)): 
    ?>
        <img src="Profile_images/<?php echo htmlspecialchars($image_value); ?>" 
             alt="<?php echo htmlspecialchars($user['employee_name']); ?>" 
             class="w-full h-full object-cover"
             onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2YwZjBmMCIvPjx0ZXh0IHg9IjEwMCUiIHk9IjUwJSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjOTk5Ij5ObyBJbWFnZTwvdGV4dD48L3N2Zz4='">
    <?php else: ?>
        <div class="w-full h-full flex items-center justify-center">
            <i data-lucide="user" class="w-16 h-16 text-gray-400"></i>
        </div>
    <?php endif; ?>
</div>
                    <button onclick="openUploadModal()" 
                            class="absolute bottom-2 right-2 btn btn-sm btn-primary btn-circle shadow-lg">
                      <i data-lucide="camera" class="w-4 h-4"></i>
                    </button>
                  </div>
                  
                  <h3 class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($user['employee_name']); ?></h3>
                  <p class="text-gray-500 mb-2"><?php echo htmlspecialchars($user['role']); ?></p>
                  <p class="text-sm text-gray-400">Employee ID: <?php echo htmlspecialchars($user['employee_id']); ?></p>
                </div>

                <!-- Statistics -->
                <div class="space-y-3 mb-6">
                  <div class="stat-card bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100">
                    <div class="flex items-center justify-between">
                      <div>
                        <p class="text-sm text-gray-500">Department</p>
                        <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($user['dept_name']); ?></p>
                      </div>
                      <div class="p-2 bg-blue-100 rounded-lg">
                        <i data-lucide="building" class="w-5 h-5 text-blue-600"></i>
                      </div>
                    </div>
                  </div>
                  
                  <div class="stat-card bg-gradient-to-r from-green-50 to-emerald-50 border border-green-100">
                    <div class="flex items-center justify-between">
                      <div>
                        <p class="text-sm text-gray-500">Last Login</p>
                        <p class="text-lg font-semibold text-gray-900">
                          <?php 
                            if ($user['last_login'] !== 'Never') {
                              $date = new DateTime($user['last_login']);
                              echo $date->format('M j, Y H:i');
                            } else {
                              echo 'Never';
                            }
                          ?>
                        </p>
                      </div>
                      <div class="p-2 bg-green-100 rounded-lg">
                        <i data-lucide="log-in" class="w-5 h-5 text-green-600"></i>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex flex-col space-y-1">
                  <button onclick="switchTab('profile')" 
                          class="tab-btn text-left active" id="tab-profile">
                    <div class="flex items-center gap-3">
                      <i data-lucide="user" class="w-5 h-5"></i>
                      <span>Profile Information</span>
                    </div>
                  </button>
                  
                  <button onclick="switchTab('password')" 
                          class="tab-btn text-left" id="tab-password">
                    <div class="flex items-center gap-3">
                      <i data-lucide="lock" class="w-5 h-5"></i>
                      <span>Change Password</span>
                    </div>
                  </button>
                  
                  <button onclick="switchTab('activity')" 
                          class="tab-btn text-left" id="tab-activity">
                    <div class="flex items-center gap-3">
                      <i data-lucide="activity" class="w-5 h-5"></i>
                      <span>Recent Activity</span>
                    </div>
                  </button>
                  
                  <button onclick="switchTab('logs')" 
                          class="tab-btn text-left" id="tab-logs">
                    <div class="flex items-center gap-3">
                      <i data-lucide="history" class="w-5 h-5"></i>
                      <span>Login History</span>
                    </div>
                  </button>
                </div>
              </div>
            </div>

            <!-- Right Content -->
            <div class="lg:col-span-3">
              <!-- Profile Information Tab -->
              <div id="profile-tab" class="tab-content">
                <div class="profile-card bg-white p-8">
                  <div class="flex justify-between items-center mb-8">
                    <div>
                      <h4 class="text-2xl font-bold text-gray-900">Profile Information</h4>
                      <p class="text-gray-500">Update your personal details and contact information</p>
                    </div>
                    <button onclick="enableEditing()" 
                            class="btn btn-outline border-gray-300 hover:bg-gray-50">
                      <i data-lucide="edit" class="w-4 h-4 mr-2"></i>
                      Edit Profile
                    </button>
                  </div>

                  <form id="profile-form" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                        <input type="text" 
                               value="<?php echo htmlspecialchars($user['employee_name']); ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-500"
                               disabled>
                      </div>
                      
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Employee ID</label>
                        <input type="text" 
                               value="<?php echo htmlspecialchars($user['employee_id']); ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-500"
                               disabled>
                      </div>
                      
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" 
                               name="email" 
                               id="email-field"
                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 bg-gray-50 text-gray-500"
                               disabled>
                      </div>
                      
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <input type="text" 
                               value="<?php echo htmlspecialchars($user['dept_name']); ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-500"
                               disabled>
                      </div>
                      
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                        <input type="text" 
                               value="<?php echo htmlspecialchars($user['role']); ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-500"
                               disabled>
                      </div>
                      
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Last Login</label>
                        <input type="text" 
                               value="<?php 
                                 if ($user['last_login'] !== 'Never') {
                                   try {
                                     $date = new DateTime($user['last_login']);
                                     echo $date->format('M j, Y H:i:s');
                                   } catch (Exception $e) {
                                     echo htmlspecialchars($user['last_login']);
                                   }
                                 } else {
                                   echo 'Never';
                                 }
                               ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-500"
                               disabled>
                      </div>
                    </div>
                    
                    <!-- Form Actions (Hidden by default) -->
                    <div id="form-actions" class="flex justify-end gap-3 hidden pt-6 border-t border-gray-100">
                      <button type="button" 
                              onclick="cancelEditing()" 
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
              </div>

              <!-- Change Password Tab -->
              <div id="password-tab" class="tab-content hidden">
                <div class="profile-card bg-white p-8">
                  <h4 class="text-2xl font-bold text-gray-900 mb-2">Change Password</h4>
                  <p class="text-gray-500 mb-8">Update your password to keep your account secure</p>

                  <form id="password-form" class="space-y-6 max-w-md">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                      <div class="relative">
                        <input type="password" 
                               name="current_password" 
                               class="bg-white input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                               required>
                        <button type="button" 
                                onclick="togglePassword(this)" 
                                class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                          <i data-lucide="eye" class="w-5 h-5"></i>
                        </button>
                      </div>
                    </div>
                    
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                      <div class="relative">
                        <input type="password" 
                               name="new_password" 
                               id="new-password"
                               class="bg-white input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                               required
                               oninput="checkPasswordStrength(this.value)">
                        <button type="button" 
                                onclick="togglePassword(this)" 
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
                    
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                      <div class="relative">
                        <input type="password" 
                               name="confirm_password" 
                               class="bg-white input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                               required>
                        <button type="button" 
                                onclick="togglePassword(this)" 
                                class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                          <i data-lucide="eye" class="w-5 h-5"></i>
                        </button>
                      </div>
                      <p id="password-match" class="text-xs mt-2 hidden"></p>
                    </div>
                    
                    <div class="pt-4">
                      <button type="submit" 
                              class="btn btn-primary px-8">
                        Update Password
                      </button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- Recent Activity Tab -->
              <div id="activity-tab" class="tab-content hidden">
                <div class="profile-card bg-white p-8">
                  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                    <div>
                      <h4 class="text-2xl font-bold text-gray-900">Recent Activity</h4>
                    </div>
                    
                    <!-- Activity Filters -->
                    <div class="flex flex-wrap gap-3 mt-4 sm:mt-0">
                      <!-- Search -->
                      <div class="relative">
                        <i data-lucide="search" class="absolute left-3 top-3 w-4 h-4 text-gray-400"></i>
                        <input type="text" 
                               id="activity-search-input"
                               placeholder="Search activities..." 
                               class="bg-white pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                               value="<?php echo htmlspecialchars($activity_search); ?>">
                      </div>
                      
                      <!-- Type Filter -->
                      <select id="activity-type-filter" class="bg-white select select-bordered border-gray-300">
                        <option value="">All Types</option>
                        <?php if (!empty($recent_activities)): ?>
                          <?php 
                            $activity_types = [];
                            foreach ($recent_activities as $activity) {
                              if (isset($activity['action']) && !in_array($activity['action'], $activity_types)) {
                                $activity_types[] = $activity['action'];
                              }
                            }
                            sort($activity_types);
                          ?>
                          <?php foreach ($activity_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" 
                              <?php echo $activity_type_filter == $type ? 'selected' : ''; ?>>
                              <?php echo htmlspecialchars($type); ?>
                            </option>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </select>
                      
                      <!-- Search Button -->
                      <button onclick="applyActivityFilters()" 
                              class="btn btn-primary">
                        <i data-lucide="filter" class="w-4 h-4 mr-2"></i>
                        Apply
                      </button>
                    </div>
                  </div>

                  <!-- Activity Table -->
                  <?php if (!empty($recent_activities)): ?>
                    <div class="table-container">
                      <table class="table w-full">
                        <thead>
                          <tr class="bg-gray-50">
                            <th class="font-semibold text-gray-700 py-3 px-4">Activity</th>
                            <th class="font-semibold text-gray-700 py-3 px-4">Type</th>
                            <th class="font-semibold text-gray-700 py-3 px-4">Department</th>
                            <th class="font-semibold text-gray-700 py-3 px-4">Date</th>
                          </tr>
                        </thead>
                        <tbody>
                         <?php
// First, get the current user's image for reference
$current_user_image = '';
if (!empty($user['image_url'])) {
    $current_user_image = $user['image_url'];
} elseif (!empty($user['profile_picture'])) {
    $current_user_image = $user['profile_picture'];
}
?>

<?php foreach ($recent_activities as $activity): ?>
    <tr class="activity-item border-b border-gray-100 hover:bg-gray-50">
        <td class="py-4 px-4">
            <div class="flex items-center">
                <!-- User Profile Image -->
                <div class="flex-shrink-0 mr-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-200 border border-gray-300">
                        <?php 
                        // For profile page, all activities should be from the logged-in user
                        // So we can use the current user's image
                        $activity_user_name = $activity['employee_name'] ?? '';
                        
                        if (!empty($current_user_image)): 
                        ?>
                            <img src="Profile_images/<?php echo htmlspecialchars($current_user_image); ?>" 
                                 alt="<?php echo htmlspecialchars($activity_user_name); ?>" 
                                 class="w-full h-full object-cover"
                                 onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxMiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzk5OSI+VXNlcjwvdGV4dD48L3N2Zz4='">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-gray-100">
                                <i data-lucide="user" class="w-5 h-5 text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Activity Details -->
                <div class="flex-1">
                    <div class="font-medium text-gray-900 mb-1">
                        <?php 
                            $activity_text = '';
                            if (isset($activity['activity'])) {
                                $activity_text = $activity['activity'];
                            } elseif (isset($activity['modules_cover'])) {
                                $activity_text = $activity['modules_cover'];
                            }
                            echo htmlspecialchars(substr($activity_text, 0, 100)) . (strlen($activity_text) > 100 ? '...' : '');
                        ?>
                    </div>
                    <?php if (isset($activity['employee_name'])): ?>
                        <div class="text-sm text-gray-500 flex items-center">
                            <i data-lucide="user" class="w-3 h-3 mr-1"></i>
                            <?php echo htmlspecialchars($activity['employee_name']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </td>
        <td class="py-4 px-4">
            <?php if (isset($activity['action'])): ?>
                <?php 
                    $badge_class = 'badge-info';
                    $action_lower = strtolower($activity['action']);
                    if (strpos($action_lower, 'login') !== false) $badge_class = 'badge-success';
                    elseif (strpos($action_lower, 'error') !== false || strpos($action_lower, 'fail') !== false) $badge_class = 'badge-error';
                    elseif (strpos($action_lower, 'create') !== false) $badge_class = 'badge-primary';
                    elseif (strpos($action_lower, 'update') !== false) $badge_class = 'badge-warning';
                    elseif (strpos($action_lower, 'delete') !== false) $badge_class = 'badge-error';
                ?>
                <span class="badge <?php echo $badge_class; ?>">
                    <?php echo htmlspecialchars($activity['action']); ?>
                </span>
            <?php endif; ?>
        </td>
        <td class="py-4 px-4">
            <?php if (isset($activity['dept_name'])): ?>
                <div class="text-gray-700">
                    <?php echo htmlspecialchars($activity['dept_name']); ?>
                </div>
            <?php endif; ?>
        </td>
        <td class="py-4 px-4">
            <?php 
                $date_str = '';
                if (isset($activity['date'])) {
                    $date_str = $activity['date'];
                } elseif (isset($activity['created_at'])) {
                    $date_str = $activity['created_at'];
                }
                if (!empty($date_str)) {
                    try {
                        $date = new DateTime($date_str);
                        echo '<div class="text-gray-700">' . $date->format('M j, Y') . '</div>';
                        echo '<div class="text-sm text-gray-500">' . $date->format('H:i:s') . '</div>';
                    } catch (Exception $e) {
                        echo htmlspecialchars($date_str);
                    }
                }
            ?>
        </td>
    </tr>
<?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_activity_pages > 1): ?>
                      <div class="flex justify-center items-center gap-2 mt-8">
                        <button onclick="changeActivityPage(<?php echo max(1, $activity_page - 1); ?>)" 
                                class="pagination-btn <?php echo $activity_page <= 1 ? 'disabled' : ''; ?>">
                          <i data-lucide="chevron-left" class="w-4 h-4"></i>
                        </button>
                        
                        <?php
                          $start_page = max(1, $activity_page - 2);
                          $end_page = min($total_activity_pages, $activity_page + 2);
                          
                          if ($start_page > 1) {
                            echo '<button onclick="changeActivityPage(1)" class="pagination-btn">1</button>';
                            if ($start_page > 2) echo '<span class="px-3">...</span>';
                          }
                          
                          for ($i = $start_page; $i <= $end_page; $i++) {
                            $active_class = $i == $activity_page ? 'active' : '';
                            echo '<button onclick="changeActivityPage(' . $i . ')" class="pagination-btn ' . $active_class . '">' . $i . '</button>';
                          }
                          
                          if ($end_page < $total_activity_pages) {
                            if ($end_page < $total_activity_pages - 1) echo '<span class="px-3">...</span>';
                            echo '<button onclick="changeActivityPage(' . $total_activity_pages . ')" class="pagination-btn">' . $total_activity_pages . '</button>';
                          }
                        ?>
                        
                        <button onclick="changeActivityPage(<?php echo min($total_activity_pages, $activity_page + 1); ?>)" 
                                class="pagination-btn <?php echo $activity_page >= $total_activity_pages ? 'disabled' : ''; ?>">
                          <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </button>
                      </div>
                    <?php endif; ?>
                    
                  <?php else: ?>
                    <div class="text-center py-12">
                      <i data-lucide="activity" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                      <h5 class="text-lg font-medium text-gray-900 mb-2">No Activity Found</h5>
                      <p class="text-gray-500">Your recent activities will appear here.</p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Login History Tab -->
              <div id="logs-tab" class="tab-content hidden">
                <div class="profile-card bg-white p-8">
                  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                    <div>
                      <h4 class="text-2xl font-bold text-gray-900">Login History</h4>
                    </div>
                    
                    <!-- Login Filters -->
                    <div class="flex flex-wrap gap-3 mt-4 sm:mt-0">
                      <!-- Search -->
                      <div class="relative">
                        <i data-lucide="search" class="absolute left-3 top-3 w-4 h-4 text-gray-400"></i>
                        <input type="text" 
                               id="login-search-input"
                               placeholder="Search logs..." 
                               class="bg-white pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                               value="<?php echo htmlspecialchars($login_search); ?>">
                      </div>
                      
                      <!-- Status Filter -->
                      <select id="login-status-filter" class="bg-white select select-bordered border-gray-300">
                        <option value="">All Status</option>
                        <option value="success" <?php echo $login_status_filter == 'success' ? 'selected' : ''; ?>>Success</option>
                        <option value="failed" <?php echo $login_status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="locked" <?php echo $login_status_filter == 'locked' ? 'selected' : ''; ?>>Locked</option>
                      </select>
                      
                      <!-- Type Filter -->
                      <select id="login-type-filter" class="bg-white select select-bordered border-gray-300">
                        <option value="">All Types</option>
                        <option value="login" <?php echo $login_type_filter == 'login' ? 'selected' : ''; ?>>Login</option>
                        <option value="password_change" <?php echo $login_type_filter == 'password_change' ? 'selected' : ''; ?>>Password Change</option>
                        <option value="logout" <?php echo $login_type_filter == 'logout' ? 'selected' : ''; ?>>Logout</option>
                      </select>
                      
                      <!-- Search Button -->
                      <button onclick="applyLoginFilters()" 
                              class="btn btn-primary">
                        <i data-lucide="filter" class="w-4 h-4 mr-2"></i>
                        Apply
                      </button>
                    </div>
                  </div>

                  <!-- Login Logs Table -->
                  <?php if (!empty($recent_logins)): ?>
                    <div class="table-container">
                      <table class="table w-full">
                        <thead>
                          <tr class="bg-gray-50">
                            <th class="font-semibold text-gray-700 py-3 px-4">Status</th>
                            <th class="font-semibold text-gray-700 py-3 px-4">Type</th>
                            <th class="font-semibold text-gray-700 py-3 px-4">Attempts</th>
                            <th class="font-semibold text-gray-700 py-3 px-4">Details</th>
                            <th class="font-semibold text-gray-700 py-3 px-4">Date & Time</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php 
// First, get the current user's image for reference
$current_user_image = '';
if (!empty($user['image_url'])) {
    $current_user_image = $user['image_url'];
} elseif (!empty($user['profile_picture'])) {
    $current_user_image = $user['profile_picture'];
}
?>

<?php foreach ($recent_logins as $log): ?>
    <tr class="border-b border-gray-100 hover:bg-gray-50">
        <td class="py-4 px-4">
            <div class="flex items-center">
                <!-- User Profile Image -->
                <div class="flex-shrink-0 mr-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-200 border border-gray-300">
                        <?php 
                        // For profile page, all login activities should be from the logged-in user
                        // So we can use the current user's image
                        $user_name = $log['employee_name'] ?? $log['username'] ?? '';
                        
                        if (!empty($current_user_image)): 
                        ?>
                            <img src="Profile_images/<?php echo htmlspecialchars($current_user_image); ?>" 
                                 alt="<?php echo htmlspecialchars($user_name); ?>" 
                                 class="w-full h-full object-cover"
                                 onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxMiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzk5OSI+VXNlcjwvdGV4dD48L3N2Zz4='">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-gray-100">
                                <i data-lucide="user" class="w-5 h-5 text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- User Details -->
                <div class="flex-1">
                    <div class="font-medium text-gray-900 mb-1">
                        <?php 
                            $user_display_name = $user_name;
                            if (!empty($user_display_name)) {
                                echo htmlspecialchars($user_display_name);
                            } else {
                                echo htmlspecialchars($log['username'] ?? 'User');
                            }
                        ?>
                    </div>
                    <?php if (!empty($log['username'])): ?>
                        <div class="text-sm text-gray-500 flex items-center">
                            <i data-lucide="user" class="w-3 h-3 mr-1"></i>
                            <?php echo htmlspecialchars($log['username']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </td>
        <td class="py-4 px-4">
            <?php 
                $badge_class = 'badge-success';
                $status = $log['log_status'] ?? '';
                if ($status === 'failed') $badge_class = 'badge-error';
                elseif ($status === 'locked') $badge_class = 'badge-warning';
                elseif (empty($status)) $badge_class = 'badge-secondary';
            ?>
            <span class="badge <?php echo $badge_class; ?>">
                <?php echo ucfirst($status ?: 'unknown'); ?>
            </span>
        </td>
        <td class="py-4 px-4">
            <?php 
                $type_badge_class = 'badge-info';
                $log_type = $log['log_type'] ?? '';
                if ($log_type === 'password_change') $type_badge_class = 'badge-primary';
                elseif ($log_type === 'logout') $type_badge_class = 'badge-warning';
                elseif (empty($log_type)) $type_badge_class = 'badge-secondary';
            ?>
            <span class="badge <?php echo $type_badge_class; ?>">
                <?php 
                    $type_text = str_replace('_', ' ', $log_type);
                    echo ucwords($type_text ?: 'login activity');
                ?>
            </span>
        </td>
        <td class="py-4 px-4">
            <div class="font-medium text-center">
                <?php echo htmlspecialchars($log['attempt_count'] ?? '0'); ?>
            </div>
        </td>
        <td class="py-4 px-4">
            <?php if (!empty($log['failure_reason'])): ?>
                <div class="text-gray-700" title="<?php echo htmlspecialchars($log['failure_reason']); ?>">
                    <?php 
                        $reason = htmlspecialchars($log['failure_reason']);
                        echo strlen($reason) > 50 ? substr($reason, 0, 50) . '...' : $reason;
                    ?>
                </div>
            <?php elseif (!empty($log['cooldown'])): ?>
                <div class="text-gray-500 text-sm">
                    Cooldown: <?php echo htmlspecialchars($log['cooldown']); ?>
                </div>
            <?php else: ?>
                <div class="text-gray-400 text-sm">-</div>
            <?php endif; ?>
        </td>
        <td class="py-4 px-4">
            <?php 
                $date_str = $log['date'] ?? '';
                if (!empty($date_str)) {
                    try {
                        $date = new DateTime($date_str);
                        echo '<div class="text-gray-700">' . $date->format('M j, Y') . '</div>';
                        echo '<div class="text-sm text-gray-500">' . $date->format('H:i:s') . '</div>';
                    } catch (Exception $e) {
                        echo htmlspecialchars($date_str);
                    }
                }
            ?>
        </td>
    </tr>
<?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_login_pages > 1): ?>
                      <div class="flex justify-center items-center gap-2 mt-8">
                        <button onclick="changeLoginPage(<?php echo max(1, $login_page - 1); ?>)" 
                                class="pagination-btn <?php echo $login_page <= 1 ? 'disabled' : ''; ?>">
                          <i data-lucide="chevron-left" class="w-4 h-4"></i>
                        </button>
                        
                        <?php
                          $start_page = max(1, $login_page - 2);
                          $end_page = min($total_login_pages, $login_page + 2);
                          
                          if ($start_page > 1) {
                            echo '<button onclick="changeLoginPage(1)" class="pagination-btn">1</button>';
                            if ($start_page > 2) echo '<span class="px-3">...</span>';
                          }
                          
                          for ($i = $start_page; $i <= $end_page; $i++) {
                            $active_class = $i == $login_page ? 'active' : '';
                            echo '<button onclick="changeLoginPage(' . $i . ')" class="pagination-btn ' . $active_class . '">' . $i . '</button>';
                          }
                          
                          if ($end_page < $total_login_pages) {
                            if ($end_page < $total_login_pages - 1) echo '<span class="px-3">...</span>';
                            echo '<button onclick="changeLoginPage(' . $total_login_pages . ')" class="pagination-btn">' . $total_login_pages . '</button>';
                          }
                        ?>
                        
                        <button onclick="changeLoginPage(<?php echo min($total_login_pages, $login_page + 1); ?>)" 
                                class="pagination-btn <?php echo $login_page >= $total_login_pages ? 'disabled' : ''; ?>">
                          <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </button>
                      </div>
                    <?php endif; ?>
                    
                  <?php else: ?>
                    <div class="text-center py-12">
                      <i data-lucide="history" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                      <h5 class="text-lg font-medium text-gray-900 mb-2">No Login History</h5>
                      <p class="text-gray-500">Your login history will appear here.</p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Profile Picture Upload Modal -->
  <div id="upload-modal" class="modal">
    <div class="modal-box">
      <h3 class="text-xl font-bold text-gray-900 mb-2">Update Profile Picture</h3>
      <p class="text-gray-500 mb-6">Upload a new profile picture (JPG, PNG, or GIF)</p>
      
      <form id="upload-form" enctype="multipart/form-data">
        <div class="upload-area p-8 text-center mb-4" 
             id="drop-area"
             ondrop="dropHandler(event)"
             ondragover="dragOverHandler(event)">
          <i data-lucide="upload" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
          <p class="text-gray-700 mb-2">Drag & drop your image here</p>
          <p class="text-gray-400 text-sm mb-4">or</p>
          <input type="file" 
                 id="file-input" 
                 name="image_url" 
                 accept="image/*"
                 class="hidden"
                 onchange="previewImage()">
          <label for="file-input" class="btn btn-outline border-gray-300 cursor-pointer">
            Browse Files
          </label>
          <p class="text-gray-400 text-xs mt-4">Maximum file size: 5MB</p>
        </div>
        
        <!-- Image Preview -->
        <div id="image-preview" class="mb-6 hidden">
          <p class="text-sm font-medium text-gray-700 mb-2">Preview:</p>
          <div class="relative w-32 h-32 rounded-lg overflow-hidden border border-gray-300">
            <img id="preview-img" class="w-full h-full object-cover" />
          </div>
        </div>
        
        <div class="modal-action">
          <button type="button" 
                  onclick="closeUploadModal()" 
                  class="btn btn-ghost text-gray-600">
            Cancel
          </button>
          <button type="submit" 
                  class="btn btn-primary"
                  id="upload-btn">
            Upload Picture
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Include scripts -->
  <script src="../JS/sidebar.js"></script>
 <script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Tab switching functionality
    function switchTab(tabName) {
      // Hide all tab contents
      document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
      });
      
      // Remove active class from all tab buttons
      document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
      });
      
      // Show selected tab content
      document.getElementById(tabName + '-tab').classList.remove('hidden');
      
      // Activate selected tab button
      document.getElementById('tab-' + tabName).classList.add('active');
      
      // Update URL parameter
      const url = new URL(window.location.href);
      url.searchParams.set('tab', tabName);
      window.history.replaceState({}, '', url);
    }

    // Profile editing
    function enableEditing() {
      const emailField = document.getElementById('email-field');
      emailField.disabled = false;
      emailField.classList.remove('bg-gray-50', 'text-gray-500');
      emailField.classList.add('bg-white', 'text-gray-900');
      
      document.getElementById('form-actions').classList.remove('hidden');
    }

    function cancelEditing() {
      const emailField = document.getElementById('email-field');
      emailField.disabled = true;
      emailField.classList.remove('bg-white', 'text-gray-900');
      emailField.classList.add('bg-gray-50', 'text-gray-500');
      emailField.value = '<?php echo htmlspecialchars($user["email"]); ?>';
      
      document.getElementById('form-actions').classList.add('hidden');
    }

    // Profile form submission
    document.getElementById('profile-form').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const email = document.getElementById('email-field').value;
      
      // Validate email
      if (!email || !validateEmail(email)) {
        Swal.fire({
          icon: 'error',
          title: 'Invalid Email',
          text: 'Please enter a valid email address'
        });
        return;
      }
      
      try {
        const formData = new FormData();
        formData.append('email', email);
        
        const response = await fetch('sub-modules/update_profile.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await response.json();
        console.log('Email update response:', data);
        
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Profile Updated',
            text: data.message || 'Your email has been updated successfully.',
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            cancelEditing();
            location.reload();
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Update Failed',
            text: data.message || 'Failed to update profile.'
          });
        }
      } catch (error) {
        console.error('Profile update error:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'An error occurred while updating your profile.'
        });
      }
    });

    // Email validation helper
    function validateEmail(email) {
      const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
      return re.test(String(email).toLowerCase());
    }

    // Password strength checker
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

    // Password form submission
    document.getElementById('password-form').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const currentPassword = document.querySelector('input[name="current_password"]').value;
      const newPassword = document.getElementById('new-password').value;
      const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
      
      // Validate passwords
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
      
      try {
        const formData = new FormData();
        formData.append('action', 'change_password');
        formData.append('current_password', currentPassword);
        formData.append('new_password', newPassword);
        formData.append('confirm_password', confirmPassword);
        
        const response = await fetch('sub-modules/update_profile.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await response.json();
        console.log('Password update response:', data);
        
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Password Updated',
            text: data.message || 'Your password has been changed successfully.',
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            document.getElementById('password-form').reset();
            document.getElementById('password-strength').style.width = '0%';
            document.getElementById('strength-text').textContent = '';
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Update Failed',
            text: data.message || 'Failed to update password.'
          });
        }
      } catch (error) {
        console.error('Password update error:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'An error occurred while updating your password.'
        });
      }
    });

    // Toggle password visibility
    function togglePassword(button) {
      const input = button.parentElement.querySelector('input');
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

    // File upload functionality
    function openUploadModal() {
      document.getElementById('upload-modal').classList.add('modal-open');
    }

    function closeUploadModal() {
      document.getElementById('upload-modal').classList.remove('modal-open');
      document.getElementById('image-preview').classList.add('hidden');
      document.getElementById('file-input').value = '';
    }

    function previewImage() {
      const fileInput = document.getElementById('file-input');
      const previewImg = document.getElementById('preview-img');
      const previewDiv = document.getElementById('image-preview');
      
      if (fileInput.files && fileInput.files[0]) {
        const file = fileInput.files[0];
        
        // Validate file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
          Swal.fire({
            icon: 'error',
            title: 'File Too Large',
            text: 'Maximum file size is 5MB.'
          });
          fileInput.value = '';
          return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
          Swal.fire({
            icon: 'error',
            title: 'Invalid File Type',
            text: 'Only JPG, PNG, GIF, and WebP images are allowed.'
          });
          fileInput.value = '';
          return;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(e) {
          previewImg.src = e.target.result;
          previewDiv.classList.remove('hidden');
        }
        
        reader.readAsDataURL(fileInput.files[0]);
      }
    }

    // Drag and drop handlers
    function dragOverHandler(e) {
      e.preventDefault();
      document.getElementById('drop-area').classList.add('dragover');
    }

    function dropHandler(e) {
      e.preventDefault();
      document.getElementById('drop-area').classList.remove('dragover');
      
      if (e.dataTransfer.files.length) {
        document.getElementById('file-input').files = e.dataTransfer.files;
        previewImage();
      }
    }

    // Upload form submission
    document.getElementById('upload-form').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const fileInput = document.getElementById('file-input');
      if (!fileInput.files || !fileInput.files[0]) {
        Swal.fire({
          icon: 'error',
          title: 'No File Selected',
          text: 'Please select an image to upload.'
        });
        return;
      }
      
      const formData = new FormData(this);
      
      const uploadBtn = document.getElementById('upload-btn');
      uploadBtn.disabled = true;
      uploadBtn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin mr-2"></i> Uploading...';
      
      try {
        const response = await fetch('sub-modules/update_profile.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await response.json();
        console.log('Upload response:', data);
        
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Upload Successful',
            text: data.message || 'Your profile picture has been updated.',
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            closeUploadModal();
            location.reload();
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Upload Failed',
            text: data.message || 'Failed to upload picture.'
          });
        }
      } catch (error) {
        console.error('Upload error:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'An error occurred while uploading the picture.'
        });
      } finally {
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = 'Upload Picture';
      }
    });

    // Pagination functions
    function changeLoginPage(page) {
      const url = new URL(window.location.href);
      url.searchParams.set('login_page', page);
      window.location.href = url.toString();
    }

    function changeActivityPage(page) {
      const url = new URL(window.location.href);
      url.searchParams.set('activity_page', page);
      window.location.href = url.toString();
    }

    // Filter functions
    function applyLoginFilters() {
      const url = new URL(window.location.href);
      const search = document.getElementById('login-search-input').value;
      const status = document.getElementById('login-status-filter').value;
      const type = document.getElementById('login-type-filter').value;
      
      url.searchParams.set('login_search', search);
      url.searchParams.set('login_status', status);
      url.searchParams.set('login_type', type);
      url.searchParams.set('login_page', 1);
      
      window.location.href = url.toString();
    }

    function applyActivityFilters() {
      const url = new URL(window.location.href);
      const search = document.getElementById('activity-search-input').value;
      const type = document.getElementById('activity-type-filter').value;
      
      url.searchParams.set('activity_search', search);
      url.searchParams.set('activity_type', type);
      url.searchParams.set('activity_page', 1);
      
      window.location.href = url.toString();
    }

    // Initialize current tab
    <?php 
      $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
    ?>
    switchTab('<?php echo $current_tab; ?>');
    
    // Debug function to check API connectivity
    async function testApiConnection() {
      try {
        console.log('Testing API connection...');
        const response = await fetch('sub-modules/update_profile.php');
        const text = await response.text();
        console.log('API connection test:', text);
      } catch (error) {
        console.error('API connection test failed:', error);
      }
    }
    
    // Test API on page load (optional)
    // testApiConnection();
  </script>
</body>
</html>