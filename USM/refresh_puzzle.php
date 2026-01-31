<?php
// refresh_puzzle.php
session_start();
include("puzzle_functions.php");

// Generate new puzzle
$_SESSION['puzzle_question'] = generatePuzzle();

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'question' => $_SESSION['puzzle_question']
]);
exit();
?>