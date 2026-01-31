<?php
session_start();
require '../main_connection.php'; // 🔹 adjust path to your DB connection

// Capture session details before destroying
$employee_id   = $_SESSION['employee_id'] ?? null;
$employee_name = $_SESSION['employee_name'] ?? null;
$dept_id       = $_SESSION['Dept_id'] ?? null;
$role          = $_SESSION['role'] ?? null;

// Insert logout log into department logs DB
if ($employee_id && $employee_name) {
    try {
        // Switch to rest_core_2_usm DB
        $dbName = "rest_core_2_usm";
        $db = $connections[$dbName] ?? null;

        if ($db) {
            $stmt = $db->prepare("
                INSERT INTO department_logs 
                (dept_id, employee_id, employee_name, log_status, attempt_count, failure_reason, date, role, log_type) 
                VALUES 
                (:dept_id, :employee_id, :employee_name, :log_status, :attempt_count, :failure_reason, NOW(), :role, :log_type)
            ");

            $stmt->execute([
                ':dept_id'        => $dept_id,
                ':employee_id'    => $employee_id,
                ':employee_name'  => $employee_name,
                ':log_status'     => 'Success',
                ':attempt_count'  => 1,
                ':failure_reason' => null,
                ':role'           => $role,
                ':log_type'       => 'Logout'
            ]);
        }
    } catch (Exception $e) {
        error_log("Logout log failed: " . $e->getMessage());
    }
}

// Destroy the session
$_SESSION = [];
session_unset();
session_destroy();

// Optional: clear session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header("Location: index.php");
exit;
