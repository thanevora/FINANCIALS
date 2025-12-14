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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $allocation_id = intval($input['id'] ?? 0);
    $status = mysqli_real_escape_string($conn, $input['status'] ?? '');
    $compliance_notes = mysqli_real_escape_string($conn, $input['compliance_notes'] ?? '');
    $notes = mysqli_real_escape_string($conn, $input['notes'] ?? '');
    
    if ($allocation_id && $status) {
        // First, check if the allocation is already disbursed
        $check_query = "SELECT status FROM budget_allocations WHERE id = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "i", $allocation_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $current_allocation = mysqli_fetch_assoc($result);
        
        if ($current_allocation && $current_allocation['status'] === 'disbursed') {
            echo json_encode(['success' => false, 'message' => 'Cannot modify a disbursed allocation']);
            exit;
        }
        
        // Build the update query
        $query = "UPDATE budget_allocations SET status = ?, updated_at = NOW()";
        $params = [$status];
        $types = "s";
        
        if ($status === 'for_compliance' && $compliance_notes) {
            $query .= ", compliance_notes = ?";
            $params[] = $compliance_notes;
            $types .= "s";
        }
        
        if ($notes) {
            $query .= ", notes = ?";
            $params[] = $notes;
            $types .= "s";
        }
        
        $query .= " WHERE id = ?";
        $params[] = $allocation_id;
        $types .= "i";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Allocation status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update allocation status: ' . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>