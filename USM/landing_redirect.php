<?php
session_start();

function dd($data) {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    die;
}

// Check if user is logged in
if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit;
}

// Get role from session (no need to normalize if your role_permissions uses exact role names)
$session_role = $_SESSION['role'];
$permissions = include 'role_permissions.php';
$allowed_modules = $permissions[$session_role] ?? [];

// Debugging (uncomment when needed)
// dd([
//     'session_role' => $session_role,
//     'allowed_modules' => $allowed_modules
// ]);

// Mapping of modules to their landing pages
$module_to_landing = [
    // CORE 2 MODULES
    'analytics' => '../Analytics/Analytics_metabase.php',
    'table_reservation' => '../M1/calendar.php',
    'user_management' => 'department_accounts.php',
    'kitchen_orders' => '../M5/main.php',
    'inventory' => '../M2/main.php',
    'menu_management' => '../M3/main.php',
    'event_management' => '../M6/main_reservation.php', // Fixed typo: resevation -> reservation
    'table_turnover' => '../M8/turnover_main.php',
    'pos_system' => '../M4/employee_main.php',
    'billing' => '../M7/main.php',
    'staff_management' => '../M9/wait_main.php',
    'customer_feedback' => '../M10/comments_main.php', // Fixed: was pointing to wait_main.php
    'USM' => 'profile.php', // Fixed: was pointing to wait_main.php

];

// For supervisors/admins, redirect to analytics dashboard
if ($session_role === 'supervisor' || $session_role === 'admin') {
    header("Location: profile.php");
    exit;
}

// Find the first allowed module with a defined landing page
foreach ($allowed_modules as $module) {
    if (isset($module_to_landing[$module])) {
        header("Location: " . $module_to_landing[$module]);
        exit;
    }
}

// Fallback for all other cases - redirect to a default page based on role
switch($session_role) {
    case 'cashier':
        header("Location: ../M4/employee_main.php");
        break;
    case 'security':
        header("Location: department_accounts.php");
        break;
    case 'reservation':
        header("Location: ../M1/calendar.php");
        break;
    case 'inventory':
        header("Location: ../M2/main.php");
        break;
    case 'waiter':
    case 'waitress':
        header("Location: ../M9/wait_main.php");
        break;
    case 'head':
        header("Location: ../M5/main.php");
        break;
    default:
        header("Location: index.php");
}
exit;