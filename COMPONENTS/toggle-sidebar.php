<?php
// toggle-sidebar.php
session_start();

// Toggle sidebar state
if (isset($_POST['sidebar_collapsed'])) {
    $_SESSION['sidebar_collapsed'] = $_POST['sidebar_collapsed'] === '1';
}

// Redirect back to the previous page
$referer = $_SERVER['HTTP_REFERER'] ?? '/FINANCIALS/';
header("Location: $referer");
exit;