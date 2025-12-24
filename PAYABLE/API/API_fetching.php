<?php
session_start();
include("../../API_gateway.php");

// Database configuration
$db_name = "fina_budget";

// Check database connection
if (!isset($connections[$db_name])) {
    die(json_encode(['success' => false, 'message' => "❌ Connection not found for $db_name"]));
}

$conn = $connections[$db_name];

// Main API Gateway Logic
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get action from POST request
$action = $_POST['action'] ?? '';

// Route to appropriate handler
switch($action) {
    case 'fetch_payables':
        fetchPayables($conn);
        break;
    case 'process_payment':
        processPayment($conn);
        break;
    case 'decline_payable':
        declinePayable($conn);
        break;
    case 'compliance_review':
        complianceReview($conn);
        break;
    case 'add_payable':
        addPayable($conn);
        break;
    case 'get_payable_details':
        getPayableDetails($conn);
        break;
    case 'get_dashboard_stats':
        getDashboardStats($conn);
        break;
    case 'get_vendors':
        getVendors($conn);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
        exit;
}

// Fetch payables with pagination and filtering
function fetchPayables($conn) {
    try {
        $page = max(1, intval($_POST['page'] ?? 1));
        $limit = 15;
        $offset = ($page - 1) * $limit;
        
        // Sanitize input
        $search = htmlspecialchars(trim($_POST['search'] ?? ''));
        $status = htmlspecialchars(trim($_POST['status'] ?? ''));
        $vendor = htmlspecialchars(trim($_POST['vendor'] ?? ''));
        
        // Build query with security in mind
        $query = "SELECT SQL_CALC_FOUND_ROWS 
                    id, vendor_name, invoice_number, invoice_date, due_date, 
                    amount, description, status, payment_method, bank_account, 
                    check_number, compliance_notes, declined_reason, payment_date, 
                    created_by, created_at, updated_at 
                  FROM accounts_payable WHERE 1=1";
        
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $query .= " AND (vendor_name LIKE ? OR invoice_number LIKE ? OR description LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "sss";
        }
        
        if (!empty($status) && $status !== 'overdue') {
            $query .= " AND status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if ($status === 'overdue') {
            $query .= " AND status IN ('pending', 'approved') AND due_date < CURDATE()";
        }
        
        if (!empty($vendor)) {
            $query .= " AND vendor_name = ?";
            $params[] = $vendor;
            $types .= "s";
        }
        
        $query .= " ORDER BY due_date ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Query execution failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $results = [];
        
        while ($row = $result->fetch_assoc()) {
            // Format dates for display
            $row['invoice_date_formatted'] = date('M d, Y', strtotime($row['invoice_date']));
            $row['due_date_formatted'] = date('M d, Y', strtotime($row['due_date']));
            $row['created_at_formatted'] = date('M d, Y H:i', strtotime($row['created_at']));
            $row['updated_at_formatted'] = date('M d, Y H:i', strtotime($row['updated_at']));
            
            if ($row['payment_date']) {
                $row['payment_date_formatted'] = date('M d, Y', strtotime($row['payment_date']));
            }
            
            $results[] = $row;
        }
        
        // Get total count
        $totalResult = $conn->query("SELECT FOUND_ROWS()");
        $total = $totalResult->fetch_row()[0];
        
        echo json_encode([
            'success' => true,
            'data' => $results,
            'total' => $total,
            'page' => $page,
            'total_pages' => ceil($total / $limit)
        ]);
        
    } catch (Exception $e) {
        error_log("API Error in fetchPayables: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching payables. Please try again.'
        ]);
    }
}

// Process payment for a payable
function processPayment($conn) {
    try {
        $id = intval($_POST['id']);
        $payment_method = htmlspecialchars(trim($_POST['payment_method']));
        $payment_date = htmlspecialchars(trim($_POST['payment_date']));
        $bank_account = htmlspecialchars(trim($_POST['bank_account'] ?? ''));
        $check_number = htmlspecialchars(trim($_POST['check_number'] ?? ''));
        
        $conn->begin_transaction();
        
        // Update payable status and payment details
        $stmt = $conn->prepare("UPDATE accounts_payable SET 
            status = 'paid', 
            payment_method = ?,
            payment_date = ?,
            bank_account = ?,
            check_number = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND status IN ('pending', 'approved', 'for_compliance')");
        
        $stmt->bind_param("ssssi", $payment_method, $payment_date, $bank_account, $check_number, $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Payment update failed: " . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Payable not found or cannot be paid");
        }
        
        // Log the transaction
        logTransaction($conn, $id, 'payment_processed', $_SESSION['username'] ?? 'system', 
            "Payment processed via $payment_method on $payment_date");
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);
    } catch(Exception $e) {
        $conn->rollback();
        error_log("Payment processing error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// Decline a payable
function declinePayable($conn) {
    try {
        $id = intval($_POST['id']);
        $reason = htmlspecialchars(trim($_POST['reason']));
        
        $stmt = $conn->prepare("UPDATE accounts_payable SET 
            status = 'declined',
            declined_reason = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND status IN ('pending', 'approved', 'for_compliance')");
        
        $stmt->bind_param("si", $reason, $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Decline update failed: " . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Payable not found or cannot be declined");
        }
        
        logTransaction($conn, $id, 'declined', $_SESSION['username'] ?? 'system', $reason);
        
        echo json_encode(['success' => true, 'message' => 'Payable declined successfully']);
    } catch(Exception $e) {
        error_log("Decline payable error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// Send for compliance review
function complianceReview($conn) {
    try {
        $id = intval($_POST['id']);
        $notes = htmlspecialchars(trim($_POST['notes']));
        
        $stmt = $conn->prepare("UPDATE accounts_payable SET 
            status = 'for_compliance',
            compliance_notes = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND status IN ('pending', 'approved')");
        
        $stmt->bind_param("si", $notes, $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Compliance review update failed: " . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Payable not found or cannot be sent for compliance review");
        }
        
        logTransaction($conn, $id, 'sent_for_compliance', $_SESSION['username'] ?? 'system', $notes);
        
        echo json_encode(['success' => true, 'message' => 'Sent for compliance review successfully']);
    } catch(Exception $e) {
        error_log("Compliance review error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// Add new payable
function addPayable($conn) {
    try {
        $vendor_name = htmlspecialchars(trim($_POST['vendor_name']));
        $invoice_number = htmlspecialchars(trim($_POST['invoice_number']));
        $invoice_date = htmlspecialchars(trim($_POST['invoice_date']));
        $due_date = htmlspecialchars(trim($_POST['due_date']));
        $amount = floatval($_POST['amount']);
        $description = htmlspecialchars(trim($_POST['description'] ?? ''));
        $created_by = $_SESSION['username'] ?? 'system';
        
        // Check if invoice number already exists
        $checkStmt = $conn->prepare("SELECT id FROM accounts_payable WHERE invoice_number = ?");
        $checkStmt->bind_param("s", $invoice_number);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            throw new Exception("Invoice number already exists");
        }
        
        // Insert new payable
        $stmt = $conn->prepare("INSERT INTO accounts_payable 
            (vendor_name, invoice_number, invoice_date, due_date, amount, 
             description, status, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        
        $stmt->bind_param("ssssdss", $vendor_name, $invoice_number, $invoice_date, $due_date, $amount, $description, $created_by);
        
        if (!$stmt->execute()) {
            throw new Exception("Insert failed: " . $stmt->error);
        }
        
        $payable_id = $stmt->insert_id;
        
        logTransaction($conn, $payable_id, 'created', $created_by, 'New payable created');
        
        echo json_encode([
            'success' => true, 
            'message' => 'Payable added successfully', 
            'id' => $payable_id
        ]);
        
    } catch(Exception $e) {
        error_log("Add payable error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// Get payable details with transaction logs
function getPayableDetails($conn) {
    try {
        $id = intval($_POST['id']);
        
        // Get payable details
        $stmt = $conn->prepare("SELECT 
                id, vendor_name, invoice_number, invoice_date, due_date, 
                amount, description, status, payment_method, bank_account, 
                check_number, compliance_notes, declined_reason, payment_date, 
                created_by, created_at, updated_at 
            FROM accounts_payable WHERE id = ?");
        
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Payable not found");
        }
        
        $payable = $result->fetch_assoc();
        
        // Format dates
        $payable['invoice_date_formatted'] = date('M d, Y', strtotime($payable['invoice_date']));
        $payable['due_date_formatted'] = date('M d, Y', strtotime($payable['due_date']));
        $payable['created_at_formatted'] = date('M d, Y H:i', strtotime($payable['created_at']));
        $payable['updated_at_formatted'] = date('M d, Y H:i', strtotime($payable['updated_at']));
        
        if ($payable['payment_date']) {
            $payable['payment_date_formatted'] = date('M d, Y', strtotime($payable['payment_date']));
        }
        
        // Get transaction logs
        $logStmt = $conn->prepare("SELECT * FROM transaction_logs WHERE payable_id = ? ORDER BY created_at DESC");
        $logStmt->bind_param("i", $id);
        $logStmt->execute();
        $logResult = $logStmt->get_result();
        $logs = [];
        
        while ($log = $logResult->fetch_assoc()) {
            $log['created_at_formatted'] = date('M d, Y H:i', strtotime($log['created_at']));
            $logs[] = $log;
        }
        
        echo json_encode([
            'success' => true,
            'payable' => $payable,
            'logs' => $logs
        ]);
        
    } catch(Exception $e) {
        error_log("Get payable details error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// Get dashboard statistics
function getDashboardStats($conn) {
    try {
        $stats = [];
        
        // Total Payables Amount
        $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM accounts_payable WHERE status IN ('pending', 'approved', 'for_compliance')");
        $stats['total_payables'] = floatval($result->fetch_assoc()['total']);
        
        // Count active bills
        $result = $conn->query("SELECT COUNT(*) as count FROM accounts_payable WHERE status IN ('pending', 'approved', 'for_compliance')");
        $stats['active_bills'] = intval($result->fetch_assoc()['count']);
        
        // Due this week amount
        $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM accounts_payable WHERE status IN ('pending', 'approved') AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
        $stats['due_this_week'] = floatval($result->fetch_assoc()['total']);
        
        // Due bills count
        $result = $conn->query("SELECT COUNT(*) as count FROM accounts_payable WHERE status IN ('pending', 'approved') AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
        $stats['due_bills_count'] = intval($result->fetch_assoc()['count']);
        
        // Overdue amount
        $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM accounts_payable WHERE status IN ('pending', 'approved') AND due_date < CURDATE()");
        $stats['overdue_amount'] = floatval($result->fetch_assoc()['total']);
        
        // Overdue bills count
        $result = $conn->query("SELECT COUNT(*) as count FROM accounts_payable WHERE status IN ('pending', 'approved') AND due_date < CURDATE()");
        $stats['overdue_bills'] = intval($result->fetch_assoc()['count']);
        
        // Paid this month
        $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM accounts_payable WHERE status = 'paid' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())");
        $stats['paid_this_month'] = floatval($result->fetch_assoc()['total']);
        
        // Processed payments count
        $result = $conn->query("SELECT COUNT(*) as count FROM accounts_payable WHERE status = 'paid' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())");
        $stats['processed_payments'] = intval($result->fetch_assoc()['count']);
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
    } catch(Exception $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error fetching dashboard statistics']);
    }
}

// Get unique vendors
function getVendors($conn) {
    try {
        $result = $conn->query("SELECT DISTINCT vendor_name FROM accounts_payable ORDER BY vendor_name");
        $vendors = [];
        
        while ($row = $result->fetch_assoc()) {
            $vendors[] = htmlspecialchars($row['vendor_name']);
        }
        
        echo json_encode([
            'success' => true,
            'vendors' => $vendors
        ]);
        
    } catch(Exception $e) {
        error_log("Get vendors error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error fetching vendors']);
    }
}

// Log transaction to database
function logTransaction($conn, $payable_id, $action, $performed_by, $notes) {
    try {
        $stmt = $conn->prepare("INSERT INTO transaction_logs 
            (payable_id, action, performed_by, notes, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->bind_param("isssss", $payable_id, $action, $performed_by, $notes, $ip_address, $user_agent);
        $stmt->execute();
        
    } catch(Exception $e) {
        // Log to error log if database logging fails
        error_log("Transaction logging failed: " . $e->getMessage());
    }
}