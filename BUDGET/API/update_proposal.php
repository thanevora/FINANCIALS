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

// Check if required fields are provided
$required_fields = ['id', 'budget_title', 'amount', 'start_date', 'end_date', 'description'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
}

$proposal_id = intval($_POST['id']);
$budget_title = mysqli_real_escape_string($conn, trim($_POST['budget_title']));
$amount = floatval($_POST['amount']);
$start_date = mysqli_real_escape_string($conn, trim($_POST['start_date']));
$end_date = mysqli_real_escape_string($conn, trim($_POST['end_date']));
$description = mysqli_real_escape_string($conn, trim($_POST['description']));

// Validate inputs
if (empty($budget_title) || empty($start_date) || empty($end_date) || empty($description)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate amount
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
    exit;
}

if ($amount > 1000000) {
    echo json_encode(['success' => false, 'message' => 'Amount cannot exceed ₱1,000,000']);
    exit;
}

// Validate dates
if (!strtotime($start_date) || !strtotime($end_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

if (strtotime($start_date) > strtotime($end_date)) {
    echo json_encode(['success' => false, 'message' => 'End date must be after start date']);
    exit;
}

// Check if proposal exists
$check_query = "SELECT id, status FROM budget_proposals WHERE id = ?";
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

// Get current status
mysqli_stmt_bind_result($check_stmt, $id, $current_status);
mysqli_stmt_fetch($check_stmt);
mysqli_stmt_close($check_stmt);

// Prevent editing of approved/rejected proposals
if ($current_status !== 'under_review') {
    echo json_encode(['success' => false, 'message' => 'Cannot edit proposal that is already ' . $current_status]);
    exit;
}

// Update proposal
$update_query = "UPDATE budget_proposals SET budget_title = ?, amount = ?, start_date = ?, end_date = ?, description = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $update_query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'sdsssi', $budget_title, $amount, $start_date, $end_date, $description, $proposal_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Proposal updated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update proposal: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Database query failed: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>