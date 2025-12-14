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
if (!isset($input['id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'ID and status are required']);
    exit;
}

$allocation_id = intval($input['id']);
$status = mysqli_real_escape_string($conn, $input['status']);

// Validate status
$allowed_statuses = ['under_review', 'for_compliance', 'approved', 'rejected', 'disbursed'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// First, check if the allocation is already disbursed
$check_query = "SELECT status FROM budget_allocations WHERE id = ?";
$check_stmt = mysqli_prepare($conn, $check_query);

if ($check_stmt) {
    mysqli_stmt_bind_param($check_stmt, "i", $allocation_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $current_allocation = mysqli_fetch_assoc($result);
    mysqli_stmt_close($check_stmt);
    
    // Prevent modification if already disbursed
    if ($current_allocation && $current_allocation['status'] === 'disbursed') {
        echo json_encode(['success' => false, 'message' => 'Cannot modify a disbursed allocation']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database query failed: ' . mysqli_error($conn)]);
    exit;
}

// Prepare update query with additional fields
$update_query = "UPDATE budget_allocations SET status = ?, updated_at = NOW()";
$params = [$status];
$types = "s";

// Add compliance notes if provided and status is for_compliance
if ($status === 'for_compliance' && isset($input['compliance_notes']) && !empty($input['compliance_notes'])) {
    $compliance_notes = mysqli_real_escape_string($conn, $input['compliance_notes']);
    $update_query .= ", compliance_notes = ?";
    $params[] = $compliance_notes;
    $types .= "s";
}

// Add general notes if provided
if (isset($input['notes']) && !empty($input['notes'])) {
    $notes = mysqli_real_escape_string($conn, $input['notes']);
    $update_query .= ", notes = ?";
    $params[] = $notes;
    $types .= "s";
}

$update_query .= " WHERE id = ?";
$params[] = $allocation_id;
$types .= "i";

// Update allocation status
$stmt = mysqli_prepare($conn, $update_query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Allocation status updated successfully',
            'new_status' => $status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update allocation status: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Database query failed: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>