<?php
session_start();
include("../../API_gateway.php");

$db_name = "fina_budget";
$conn = $connections[$db_name] ?? die("❌ Connection not found for $db_name");

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'update_user':
        updateUser($conn);
        break;
    case 'change_password_admin':
        changePasswordAdmin($conn);
        break;
    case 'create_user':
        createUser($conn);
        break;
    case 'get_user_logs':
        getUserLogs($conn);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function updateUser($conn) {
    $user_id = $_POST['user_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? '';
    $department = $_POST['department'] ?? '';
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }
    
    // Handle profile picture upload
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'Profile_images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($_FILES['profile_picture']['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.']);
            return;
        }
        
        // Validate file size (max 5MB)
        if ($_FILES['profile_picture']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit.']);
            return;
        }
        
        $fileName = time() . '_' . uniqid() . '_' . basename($_FILES['profile_picture']['name']);
        $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName); // Sanitize filename
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
            $profile_picture = $fileName;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
            return;
        }
    }
    
    try {
        if ($profile_picture) {
            $stmt = $conn->prepare("UPDATE department_accounts SET employee_name = ?, email = ?, role = ?, status = ?, dept_name = ?, profile_picture = ? WHERE employee_id = ?");
            $stmt->bind_param("sssssss", $name, $email, $role, $status, $department, $profile_picture, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE department_accounts SET employee_name = ?, email = ?, role = ?, status = ?, dept_name = ? WHERE employee_id = ?");
            $stmt->bind_param("ssssss", $name, $email, $role, $status, $department, $user_id);
        }
        
        if ($stmt->execute()) {
            // Get current admin info from session
            $admin_name = $_SESSION['employee_name'] ?? 'Admin';
            $admin_dept = $_SESSION['dept_name'] ?? 'Administration';
            
            // Log the update action
            $logStmt = $conn->prepare("INSERT INTO dept_audit_transc (employee_id, employee_name, department, action, module, details, date) VALUES (?, ?, ?, 'update', 'user_management', ?, NOW())");
            $details = "Updated user: $name ($user_id) - Role: $role, Status: $status, Department: $department";
            $admin_id = $_SESSION['employee_id'] ?? 'admin';
            $logStmt->bind_param("ssss", $admin_id, $admin_name, $admin_dept, $details);
            $logStmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user: ' . $stmt->error]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function changePasswordAdmin($conn) {
    $user_id = $_POST['user_id'] ?? '';
    $admin_password = $_POST['admin_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if (!$user_id || !$admin_password || !$new_password) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    // Verify admin credentials from session
    $admin_id = $_SESSION['employee_id'] ?? '';
    $admin_name = $_SESSION['employee_name'] ?? '';
    $admin_dept = $_SESSION['dept_name'] ?? '';
    
    if (!$admin_id) {
        echo json_encode(['success' => false, 'message' => 'Admin authentication required. Please log in again.']);
        return;
    }
    
    // Verify admin password and role from database
    $adminCheck = $conn->prepare("SELECT password, role FROM department_accounts WHERE employee_id = ?");
    $adminCheck->bind_param("s", $admin_id);
    $adminCheck->execute();
    $adminResult = $adminCheck->get_result();
    
    if ($adminResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Admin account not found']);
        return;
    }
    
    $adminData = $adminResult->fetch_assoc();
    
    // Check if admin password is correct
    if (!password_verify($admin_password, $adminData['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid admin password']);
        return;
    }
    
    // Check if admin has permission to change passwords (supervisor, admin, or manager)
    $allowedRoles = ['supervisor', 'admin', 'manager'];
    $adminRole = strtolower(trim($adminData['role']));
    
    if (!in_array($adminRole, $allowedRoles)) {
        echo json_encode(['success' => false, 'message' => 'Permission denied. Only supervisor, admin, or manager can change passwords.']);
        return;
    }
    
    // Get user details
    $stmt = $conn->prepare("SELECT employee_name, dept_name FROM department_accounts WHERE employee_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    $user = $result->fetch_assoc();
    $employee_name = $user['employee_name'];
    $department = $user['dept_name'];
    
    // Validate new password strength
    if (strlen($new_password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
        return;
    }
    
    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $updateStmt = $conn->prepare("UPDATE department_accounts SET password = ? WHERE employee_id = ?");
    $updateStmt->bind_param("ss", $hashed_password, $user_id);
    
    if ($updateStmt->execute()) {
        // Log the password change
        $logStmt = $conn->prepare("INSERT INTO dept_audit_transc (employee_id, employee_name, department, action, module, details, date) VALUES (?, ?, ?, 'update', 'security', ?, NOW())");
        $details = "Password changed by admin ($adminRole) for user: $employee_name ($user_id)";
        $logStmt->bind_param("ssss", $admin_id, $admin_name, $admin_dept, $details);
        $logStmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to change password: ' . $updateStmt->error]);
    }
}

function createUser($conn) {
    // Check if user has permission to create users (supervisor, admin, or manager)
    $admin_id = $_SESSION['employee_id'] ?? '';
    
    if ($admin_id) {
        $roleCheck = $conn->prepare("SELECT role FROM department_accounts WHERE employee_id = ?");
        $roleCheck->bind_param("s", $admin_id);
        $roleCheck->execute();
        $roleResult = $roleCheck->get_result();
        
        if ($roleResult->num_rows > 0) {
            $adminData = $roleResult->fetch_assoc();
            $allowedRoles = ['supervisor', 'admin', 'manager'];
            $adminRole = strtolower(trim($adminData['role']));
            
            if (!in_array($adminRole, $allowedRoles)) {
                echo json_encode(['success' => false, 'message' => 'Permission denied. Only supervisor, admin, or manager can create users.']);
                return;
            }
        }
    }
    
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $employee_id = $_POST['employee_id'] ?? '';
    $role = $_POST['role'] ?? '';
    $department = $_POST['department'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!$name || !$email || !$employee_id || !$role || !$password) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        return;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    // Check if user already exists
    $checkStmt = $conn->prepare("SELECT employee_id FROM department_accounts WHERE employee_id = ? OR email = ?");
    $checkStmt->bind_param("ss", $employee_id, $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'User with this ID or email already exists']);
        return;
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
        return;
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO department_accounts (employee_id, employee_name, email, password, role, dept_name, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
    $stmt->bind_param("ssssss", $employee_id, $name, $email, $hashed_password, $role, $department);
    
    if ($stmt->execute()) {
        // Get admin info from session
        $admin_id = $_SESSION['employee_id'] ?? 'admin';
        $admin_name = $_SESSION['employee_name'] ?? 'Admin';
        $admin_dept = $_SESSION['dept_name'] ?? 'Administration';
        
        // Log the user creation
        $logStmt = $conn->prepare("INSERT INTO dept_audit_transc (employee_id, employee_name, department, action, module, details, date) VALUES (?, ?, ?, 'create', 'user_management', ?, NOW())");
        $details = "Created new user: $name ($employee_id) - Role: $role, Department: $department";
        $logStmt->bind_param("ssss", $admin_id, $admin_name, $admin_dept, $details);
        $logStmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'User created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . $stmt->error]);
    }
}

function getUserLogs($conn) {
    $user_id = $_POST['employee_id'] ?? ''; // Changed from user_id to employee_id to match form
    $type = $_POST['type'] ?? 'all';
    $period = $_POST['period'] ?? 'all';
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }
    
    // Get user details
    $userStmt = $conn->prepare("SELECT employee_name, dept_name FROM department_accounts WHERE employee_id = ?");
    $userStmt->bind_param("s", $user_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    if ($userResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    $user = $userResult->fetch_assoc();
    $employee_name = $user['employee_name'];
    $department = $user['dept_name'];
    
    $logs = [];
    
    // Fetch login logs if type is 'all' or 'department'
    if ($type === 'all' || $type === 'department') {
        $loginWhereClauses = ["employee_id = ?"];
        $loginParams = [$user_id];
        $loginParamTypes = "s";
        
        // Date period filter for login logs
        if ($period !== 'all') {
            $dateCondition = '';
            switch ($period) {
                case 'today':
                    $dateCondition = "DATE(date) = CURDATE()";
                    break;
                case 'week':
                    $dateCondition = "YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)";
                    break;
                case 'month':
                    $dateCondition = "YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())";
                    break;
            }
            if ($dateCondition) {
                $loginWhereClauses[] = $dateCondition;
            }
        }
        
        $loginWhereSQL = implode(" AND ", $loginWhereClauses);
        $loginQuery = "SELECT * FROM department_logs WHERE $loginWhereSQL ORDER BY date DESC LIMIT 50";
        
        $loginStmt = $conn->prepare($loginQuery);
        if ($loginParamTypes) {
            $loginStmt->bind_param($loginParamTypes, ...$loginParams);
        }
        $loginStmt->execute();
        $loginResult = $loginStmt->get_result();
        
        while ($row = $loginResult->fetch_assoc()) {
            $row['type'] = 'login';
            $row['action'] = $row['log_type'] ?? 'login';
            $row['details'] = $row['log_details'] ?? 'Login activity';
            $logs[] = $row;
        }
    }
    
    // Fetch audit trails if type is 'all' or 'audit'
    if ($type === 'all' || $type === 'audit') {
        $auditWhereClauses = ["employee_id = ?"];
        $auditParams = [$user_id];
        $auditParamTypes = "s";
        
        // Date period filter for audit trails
        if ($period !== 'all') {
            $dateCondition = '';
            switch ($period) {
                case 'today':
                    $dateCondition = "DATE(date) = CURDATE()";
                    break;
                case 'week':
                    $dateCondition = "YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)";
                    break;
                case 'month':
                    $dateCondition = "YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())";
                    break;
            }
            if ($dateCondition) {
                $auditWhereClauses[] = $dateCondition;
            }
        }
        
        $auditWhereSQL = implode(" AND ", $auditWhereClauses);
        $auditQuery = "SELECT * FROM dept_audit_transc WHERE $auditWhereSQL ORDER BY date DESC LIMIT 50";
        
        $auditStmt = $conn->prepare($auditQuery);
        if ($auditParamTypes) {
            $auditStmt->bind_param($auditParamTypes, ...$auditParams);
        }
        $auditStmt->execute();
        $auditResult = $auditStmt->get_result();
        
        while ($row = $auditResult->fetch_assoc()) {
            $row['type'] = 'activity';
            $logs[] = $row;
        }
    }
    
    // Sort by date
    usort($logs, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    echo json_encode([
        'success' => true,
        'logs' => array_slice($logs, 0, 50) // Limit to 50 most recent
    ]);
}
?>