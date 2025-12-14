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

$proposal_id = intval($input['id']);
$status = mysqli_real_escape_string($conn, $input['status']);

// Validate status
$allowed_statuses = ['under_review', 'approved', 'rejected'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Check if proposal exists and get current status
$check_query = "SELECT id, status, budget_title, proposal_code FROM budget_proposals WHERE id = ?";
$check_stmt = mysqli_prepare($conn, $check_query);
if (!$check_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database preparation failed']);
    exit;
}

mysqli_stmt_bind_param($check_stmt, 'i', $proposal_id);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) === 0) {
    echo json_encode(['success' => false, 'message' => 'Proposal not found']);
    mysqli_stmt_close($check_stmt);
    exit;
}

// Get current proposal data
mysqli_stmt_bind_result($check_stmt, $id, $current_status, $budget_title, $proposal_code);
mysqli_stmt_fetch($check_stmt);
mysqli_stmt_close($check_stmt);

// Prevent status change if already approved/rejected
if (in_array($current_status, ['approved', 'rejected']) && $current_status !== $status) {
    echo json_encode(['success' => false, 'message' => 'Cannot change status of a ' . $current_status . ' proposal']);
    exit;
}

// Update proposal status
$update_query = "UPDATE budget_proposals SET status = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $update_query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'si', $status, $proposal_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Log the action
        $log_message = "Proposal " . ($proposal_code ?? 'ID: ' . $proposal_id) . " (" . $budget_title . ") status updated from " . $current_status . " to " . $status;
        error_log("Budget Proposal Update: " . $log_message);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Proposal status updated successfully',
            'new_status' => $status,
            'previous_status' => $current_status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update proposal status: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Database query failed: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>