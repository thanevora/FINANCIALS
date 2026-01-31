<?php
// toggle-sidebar.php
session_start();

// Check if request is for toggling sidebar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['collapsed'])) {
        // Set sidebar state from POST data
        $_SESSION['sidebar_collapsed'] = $_POST['collapsed'] === 'true';
    } else {
        // Toggle sidebar state if no specific value provided
        $_SESSION['sidebar_collapsed'] = !isset($_SESSION['sidebar_collapsed']) || !$_SESSION['sidebar_collapsed'];
    }
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'collapsed' => $_SESSION['sidebar_collapsed']]);
    exit;
}

// If not POST request, redirect to home
header('Location: /FINANCIALS/');
exit;