<?php
session_start();

// Set JSON header first
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get the correct path for main_connection.php
if (file_exists("../../main_connection.php")) {
    include("../../main_connection.php");
} elseif (file_exists("../main_connection.php")) {
    include("../main_connection.php");
} else {
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit;
}

$db_name = "rest_core_2_usm";
if (!isset($connections[$db_name])) {
    echo json_encode(['success' => false, 'message' => 'Database connection not found']);
    exit;
}

$conn = $connections[$db_name];

// Get current logged-in user
$user_id = $_SESSION['employee_id'] ?? null;
$user_name = $_SESSION['employee_name'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Debug: Log what's being received
error_log("=== FILE UPLOAD DEBUG ===");
error_log("FILES keys: " . implode(', ', array_keys($_FILES)));
error_log("POST keys: " . implode(', ', array_keys($_POST)));

// Check if dept_audit_transc table exists
$check_audit_table = "SHOW TABLES LIKE 'dept_audit_transc'";
$table_result = $conn->query($check_audit_table);
$has_audit_table = $table_result->num_rows > 0;

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check for file upload with name "image_url"
        if (isset($_FILES['image_url'])) {
            error_log("File detected with name 'image_url'. File error: " . $_FILES['image_url']['error']);
            
            // Check if file was actually uploaded
            if ($_FILES['image_url']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new Exception('No file was selected. Please choose an image to upload.');
            }
            
            $file = $_FILES['image_url'];
            error_log("File details - Name: {$file['name']}, Size: {$file['size']}, Type: {$file['type']}, Error: {$file['error']}");
            
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error_messages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive (max 5MB)',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                ];
                $message = $error_messages[$file['error']] ?? 'Unknown upload error (Code: ' . $file['error'] . ')';
                throw new Exception($message);
            }
            
            // Handle profile picture upload
            $uploadDir = '../Profile_images/';
            
            // Check if directory exists
            if (!is_dir($uploadDir)) {
                error_log("Creating directory: $uploadDir");
                if (!mkdir($uploadDir, 0777, true)) {
                    throw new Exception('Failed to create upload directory. Please check permissions.');
                }
            }
            
            // Validate file type
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.');
            }
            
            // Max 5MB
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('File too large (max 5MB)');
            }
            
            // Generate unique filename
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
            $filePath = $uploadDir . $fileName;
            
            error_log("Saving file to: $filePath");
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                error_log("File moved successfully");
                
                // Get old image_url to delete old file
                $old_pic_query = "SELECT image_url FROM department_accounts WHERE employee_id = ?";
                $old_pic_stmt = $conn->prepare($old_pic_query);
                $old_pic_stmt->bind_param("s", $user_id);
                $old_pic_stmt->execute();
                $old_pic_result = $old_pic_stmt->get_result();
                
                // Delete old picture if exists
                if ($old_pic_result->num_rows > 0) {
                    $old_data = $old_pic_result->fetch_assoc();
                    $old_picture = $old_data['image_url'] ?? '';
                    
                    if (!empty($old_picture) && file_exists($uploadDir . $old_picture)) {
                        error_log("Deleting old file: " . $uploadDir . $old_picture);
                        @unlink($uploadDir . $old_picture);
                    }
                }
                
                // Update database with image_url (filename)
                $query = "UPDATE department_accounts SET image_url = ? WHERE employee_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $fileName, $user_id);
                
                if ($stmt->execute()) {
                    error_log("Database updated successfully with image_url: $fileName");
                    
                    // Log the activity to dept_audit_transc if table exists
                    if ($has_audit_table) {
                        try {
                            $log_query = "INSERT INTO dept_audit_transc (employee_id, employee_name, activity, action, date) 
                                          VALUES (?, ?, 'Updated profile picture', 'UPDATE', NOW())";
                            $log_stmt = $conn->prepare($log_query);
                            $log_stmt->bind_param("ss", $user_id, $user_name);
                            $log_stmt->execute();
                            error_log("Audit log created");
                        } catch (Exception $logError) {
                            error_log("Failed to create audit log: " . $logError->getMessage());
                        }
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Profile picture updated successfully',
                        'image_url' => $fileName,
                        'debug' => [
                            'file_received' => true,
                            'file_name' => $fileName,
                            'file_size' => $file['size'],
                            'file_type' => $fileExtension
                        ]
                    ]);
                } else {
                    throw new Exception('Database update failed: ' . $conn->error);
                }
            } else {
                error_log("Failed to move uploaded file");
                throw new Exception('Failed to save uploaded file. Please check directory permissions.');
            }
            
        } 
        // Check for password change action
        elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
            error_log("Processing password change...");
            // Handle password change
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validate inputs
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('All password fields are required');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('New passwords do not match');
            }
            
            if (strlen($new_password) < 8) {
                throw new Exception('Password must be at least 8 characters');
            }
            
            // Verify current password
            $query = "SELECT password FROM department_accounts WHERE employee_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Compare passwords
                if ($current_password === $user['password']) {
                    // Update password
                    $update_query = "UPDATE department_accounts SET password = ? WHERE employee_id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("ss", $new_password, $user_id);
                    
                    if ($update_stmt->execute()) {
                        // Log password change
                        if ($has_audit_table) {
                            try {
                                $log_query = "INSERT INTO dept_audit_transc (employee_id, employee_name, activity, action, date) 
                                              VALUES (?, ?, 'Changed password', 'UPDATE', NOW())";
                                $log_stmt = $conn->prepare($log_query);
                                $log_stmt->bind_param("ss", $user_id, $user_name);
                                $log_stmt->execute();
                            } catch (Exception $logError) {
                                // Silently fail on audit log
                            }
                        }
                        
                        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
                    } else {
                        throw new Exception('Password update failed: ' . $conn->error);
                    }
                } else {
                    throw new Exception('Current password is incorrect');
                }
            } else {
                throw new Exception('User not found in database');
            }
            
        } 
        // Check for email update
        elseif (isset($_POST['email'])) {
            error_log("Processing email update...");
            // Handle email update
            $email = trim($_POST['email']);
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }
            
            // Check if email already exists (excluding current user)
            $check_query = "SELECT employee_id FROM department_accounts WHERE email = ? AND employee_id != ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("ss", $email, $user_id);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                throw new Exception('Email already in use by another account');
            }
            
            // Update email
            $query = "UPDATE department_accounts SET email = ? WHERE employee_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $email, $user_id);
            
            if ($stmt->execute()) {
                // Log email change
                if ($has_audit_table) {
                    try {
                        $log_query = "INSERT INTO dept_audit_transc (employee_id, employee_name, activity, action, date) 
                                      VALUES (?, ?, 'Updated email to: $email', 'UPDATE', NOW())";
                        $log_stmt = $conn->prepare($log_query);
                        $log_stmt->bind_param("ss", $user_id, $user_name);
                        $log_stmt->execute();
                    } catch (Exception $logError) {
                        // Silently fail on audit log
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Email updated successfully']);
            } else {
                throw new Exception('Email update failed: ' . $conn->error);
            }
            
        } else {
            // No valid action detected
            error_log("No valid action detected. Full request dump:");
            error_log("FILES: " . print_r($_FILES, true));
            error_log("POST: " . print_r($_POST, true));
            
            echo json_encode([
                'success' => false, 
                'message' => 'No valid action detected. File upload failed or no data received.',
                'debug' => [
                    'files_received' => !empty($_FILES),
                    'files_keys' => array_keys($_FILES),
                    'post_received' => !empty($_POST),
                    'post_keys' => array_keys($_POST),
                    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Not set'
                ]
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Profile API Exception: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Use POST.']);
}

// Close connection
if (isset($conn)) {
    $conn->close();
}