<?php
session_start();
include("../../API_gateway.php");

// Database connection
$db_name = "fina_budget";
if (!isset($connections[$db_name])) {
    die(json_encode(["success" => false, "message" => "❌ Connection not found for $db_name"]));
}
$conn = $connections[$db_name];

header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

// Debug: Log all received POST data
error_log("Received POST data: " . print_r($_POST, true));

// Get and validate form data - UPDATED FIELDS
$required_fields = ['budget_title', 'department', 'amount', 'start_date', 'end_date', 'description'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        error_log("Missing field: $field");
        echo json_encode(["success" => false, "message" => "All fields are required. Missing: $field"]);
        exit;
    }
}

$budget_title = trim($_POST['budget_title']);
$department = trim($_POST['department']);
$amount = floatval($_POST['amount']);
$start_date = trim($_POST['start_date']);
$end_date = trim($_POST['end_date']);
$description = trim($_POST['description']);
$status = 'under_review'; // Automatically set to "under_review"

// Validate amount
if ($amount <= 0 || $amount > 1000000) {
    echo json_encode(["success" => false, "message" => "Invalid amount. Maximum budget is ₱1,000,000"]);
    exit;
}

// Validate department
$valid_departments = ['HR', 'LOGISTIC', 'ADMINISTRATIVE', 'FINANCIALS'];
if (!in_array($department, $valid_departments)) {
    echo json_encode(["success" => false, "message" => "Invalid department selected"]);
    exit;
}

// Validate dates
if (!strtotime($start_date) || !strtotime($end_date)) {
    echo json_encode(["success" => false, "message" => "Invalid date format"]);
    exit;
}

if (strtotime($start_date) > strtotime($end_date)) {
    echo json_encode(["success" => false, "message" => "End date must be after start date"]);
    exit;
}

try {
    // Generate unique proposal code
    $proposal_code = generateProposalCode($conn);
    
    // Convert amount to string for VARCHAR storage
    $amount_str = (string)$amount;
    
    // Prepare SQL statement - UPDATED FIELDS
    $sql = "INSERT INTO budget_proposals 
            (proposal_code, budget_title, department, amount, start_date, end_date, description, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Database preparation failed: " . $conn->error);
    }
    
    // Bind parameters - UPDATED FIELDS
    $stmt->bind_param("ssssssss", 
        $proposal_code,
        $budget_title, 
        $department, 
        $amount_str, 
        $start_date, 
        $end_date, 
        $description,
        $status
    );
    
    // Execute query
    if ($stmt->execute()) {
        $proposal_id = $stmt->insert_id;
        
        echo json_encode([
            "success" => true, 
            "message" => "Budget proposal submitted successfully and is now under review!",
            "proposal_code" => $proposal_code,
            "proposal_id" => $proposal_id,
            "status" => $status
        ]);
    } else {
        throw new Exception("Failed to submit proposal: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Budget Proposal Error: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "An error occurred while submitting the proposal. Please try again."
    ]);
}

$conn->close();

// Function to generate unique proposal code
function generateProposalCode($conn) {
    $prefix = "BP";
    $year = date('Y');
    $month = date('m');
    
    // Get the latest proposal number for this month
    $sql = "SELECT proposal_code FROM budget_proposals 
            WHERE proposal_code LIKE ? 
            ORDER BY id DESC LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $search_pattern = $prefix . $year . $month . '%';
    $stmt->bind_param("s", $search_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_code = $row['proposal_code'];
        $last_number = intval(substr($last_code, -4));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    $stmt->close();
    
    return $prefix . $year . $month . str_pad($new_number, 4, '0', STR_PAD_LEFT);
}
?>