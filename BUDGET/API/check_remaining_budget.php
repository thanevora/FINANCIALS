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

// Get total approved budget from budget_proposals table
$query = "SELECT SUM(amount) as total_budget FROM budget_proposals WHERE status = 'approved'";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

$total_budget = $row['total_budget'] ?? 0;

// Get total approved allocations from budget_allocations table
$query = "SELECT SUM(amount) as total_approved FROM budget_allocations WHERE status = 'approved'";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

$total_approved = $row['total_approved'] ?? 0;
$remaining_budget = $total_budget - $total_approved;

echo json_encode([
    'success' => true,
    'total_budget' => $total_budget,
    'total_approved' => $total_approved,
    'remaining_budget' => $remaining_budget
]);

mysqli_close($conn);
?>