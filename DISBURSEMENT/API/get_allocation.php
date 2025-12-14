<?php
session_start();
include("../../API_gateway.php");

$db_name = "fina_budget";
if (!isset($connections[$db_name])) {
    echo json_encode(['success' => false, 'message' => 'Database connection not found']);
    exit;
}

$conn = $connections[$db_name];
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $allocation_id = intval($_GET['id']);
    
    $query = "SELECT * FROM budget_allocations WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $allocation_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($allocation = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'allocation' => $allocation]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Allocation not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Allocation ID is required']);
}
?>