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

// Get role from session
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
    // BUDGET DEPARTMENT MODULES
    'budget_management' => '../Budget/main.php',
    'budget_preparation' => '../Budget/budget_preparation.php',
    'budget_monitoring' => '../Budget/budget_monitoring.php',
    'budget_reporting' => '../Budget/budget_reports.php',
    'user_management' => 'department_accounts.php',
    'analytics' => '../Analytics/Analytics_metabase.php',
    
    // FINANCE MODULES
    'general_ledger' => '../Finance/general_ledger.php',
    'accounts_payable' => '../Finance/accounts_payable.php',
    'accounts_receivable' => '../Finance/accounts_receivable.php',
    'disbursement' => '../Finance/disbursement.php',
    'collection' => '../Finance/collection.php',
    
    // ADMINISTRATION
    'profile' => 'profile.php',
    'settings' => 'settings.php',
];

// For supervisors/admins, redirect to profile
if ($session_role === 'superviser' || $session_role === 'admin') {
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

// Fallback for all other cases - redirect to Budget/main.php
header("Location: ../Budget/main.php");
exit;
?>