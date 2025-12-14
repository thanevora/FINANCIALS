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

// Check if ID parameter is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Proposal ID is required']);
    exit;
}

$proposal_id = intval($_GET['id']);

// Fetch proposal details
$query = "SELECT * FROM budget_proposals WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $proposal_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $proposal = mysqli_fetch_assoc($result);
        
        // Debug: Return the actual raw data to see the field names
        $response = [
            'success' => true,
            'proposal' => $proposal, // Return raw data
            'debug_fields' => array_keys($proposal) // Show available fields for debugging
        ];
        
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Proposal not found']);
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Database query failed: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>