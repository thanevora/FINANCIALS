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

// Get and validate form data
$required_fields = ['department', 'amount', 'purpose', 'start_date', 'end_date'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        echo json_encode(["success" => false, "message" => "All fields are required. Missing: $field"]);
        exit;
    }
}

$department = trim($_POST['department']);
$amount = floatval($_POST['amount']);
$purpose = trim($_POST['purpose']);
$start_date = trim($_POST['start_date']);
$end_date = trim($_POST['end_date']);
$status = 'under_review';

// Validate amount
if ($amount <= 0 || $amount > 3000000) {
    echo json_encode(["success" => false, "message" => "Invalid amount. Maximum allocation is ₱3,000,000"]);
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

// Prevent past dates
if (strtotime($start_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(["success" => false, "message" => "Start date cannot be in the past"]);
    exit;
}

try {
    // Generate unique allocation code
    $allocation_code = generateAllocationCode($conn);
    
    // Prepare SQL statement
    $sql = "INSERT INTO budget_allocations 
            (allocation_code, department, amount, purpose, start_date, end_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Database preparation failed: " . $conn->error);
    }
    
    // Bind parameters
    $stmt->bind_param("ssdssss", 
        $allocation_code,
        $department, 
        $amount, 
        $purpose, 
        $start_date, 
        $end_date,
        $status
    );
    
    // Execute query
    if ($stmt->execute()) {
        $allocation_id = $stmt->insert_id;
        
        echo json_encode([
            "success" => true, 
            "message" => "Budget allocation submitted successfully and is now under review!",
            "allocation_code" => $allocation_code,
            "allocation_id" => $allocation_id,
            "status" => $status
        ]);
    } else {
        throw new Exception("Failed to submit allocation: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Budget Allocation Error: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "An error occurred while submitting the allocation. Please try again."
    ]);
}

$conn->close();

// Function to generate unique allocation code
function generateAllocationCode($conn) {
    $prefix = "BA";
    $year = date('Y');
    $month = date('m');
    
    // Get the latest allocation number for this month
    $sql = "SELECT allocation_code FROM budget_allocations 
            WHERE allocation_code LIKE ? 
            ORDER BY id DESC LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $search_pattern = $prefix . $year . $month . '%';
    $stmt->bind_param("s", $search_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_code = $row['allocation_code'];
        $last_number = intval(substr($last_code, -4));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    $stmt->close();
    
    return $prefix . $year . $month . str_pad($new_number, 4, '0', STR_PAD_LEFT);
}
?>