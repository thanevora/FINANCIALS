
<?php
// main_connection.php

$dbHost = "127.0.0.1";
$dbUser = "3206_CENTRALIZED_DATABASE";
$dbPass = "1234";

// ✅ List only the databases you want to connect to
$targetDatabases = [
    "fina_budget"
    
];

$connections = [];
$errors = [];

foreach ($targetDatabases as $dbName) {
    $conn = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

    if ($conn) {
        $connections[$dbName] = $conn;
    } else {
        $errors[] = "❌ Failed to connect to <strong>$dbName</strong>: " . mysqli_connect_error();
    }
}

// Optional: Show connection errors (for debugging only)
if (!empty($errors)) {
    echo "<h2 style='color:red;'>❌ Connection Errors:</h2><ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}
?>
