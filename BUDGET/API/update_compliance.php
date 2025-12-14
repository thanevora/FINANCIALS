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
if (!isset($_POST['id']) || !isset($_POST['compliance_title']) || !isset($_POST['department']) || !isset($_POST['amount_involved']) || !isset($_POST['status']) || !isset($_POST['review_date'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

$compliance_id = intval($_POST['id']);
$compliance_title = mysqli_real_escape_string($conn, trim($_POST['compliance_title']));
$department = mysqli_real_escape_string($conn, trim($_POST['department']));
$amount_involved = floatval($_POST['amount_involved']);
$status = mysqli_real_escape_string($conn, trim($_POST['status']));
$review_date = mysqli_real_escape_string($conn, trim($_POST['review_date']));
$notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, trim($_POST['notes'])) : '';

// Validate inputs
if (empty($compliance_title) || empty($department) || empty($review_date)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate department
$valid_departments = ['HR', 'LOGISTIC', 'ADMINISTRATIVE', 'FINANCIALS'];
if (!in_array($department, $valid_departments)) {
    echo json_encode(['success' => false, 'message' => 'Invalid department selected']);
    exit;
}

// Validate status
$valid_statuses = ['compliant', 'non_compliant', 'pending_review'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status selected']);
    exit;
}

// Update compliance
$update_query = "UPDATE budget_compliance SET compliance_title = ?, department = ?, amount_involved = ?, status = ?, review_date = ?, notes = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $update_query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ssdsssi', $compliance_title, $department, $amount_involved, $status, $review_date, $notes, $compliance_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Compliance record updated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update compliance record: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Database query failed: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>