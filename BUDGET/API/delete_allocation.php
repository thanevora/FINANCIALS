<?php
session_start();
include("../../API_gateway.php");

// Database connection
$db_name = "fina_budget";
if (!isset($connections[$db_name])) {
    die(json_encode(['success' => false, 'message' => 'Database connection not found']));
}
$conn = $connections[$db_name];

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID is required']);
    exit;
}

$allocation_id = intval($input['id']);

// Delete allocation
$delete_query = "DELETE FROM budget_allocations WHERE id = ?";
$stmt = mysqli_prepare($conn, $delete_query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $allocation_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Allocation deleted successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete allocation: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Database query failed: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>