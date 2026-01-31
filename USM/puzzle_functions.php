<?php
// puzzle_functions.php
function generatePuzzle() {
    $puzzles = [
        // Math puzzles
        ['question' => 'What is 15 + 9?', 'answer' => '24'],
        ['question' => 'Solve: 25 - 13 = ?', 'answer' => '12'],
        ['question' => 'Multiply: 7 × 6 = ?', 'answer' => '42'],
        ['question' => 'Divide: 48 ÷ 8 = ?', 'answer' => '6'],
        ['question' => 'What is 3³ (three cubed)?', 'answer' => '27'],
        // Word puzzles
        ['question' => 'Spell "DOG" backwards', 'answer' => 'god'],
        ['question' => 'What is the third letter of "SOLIERA"?', 'answer' => 'l'],
        ['question' => 'Complete: HOT__ (fill the blank with 2 letters)', 'answer' => 'el'],
        // Logic puzzles
        ['question' => 'How many months have 31 days? (Enter number)', 'answer' => '7'],
        ['question' => 'What comes next: 2, 4, 6, 8, ?', 'answer' => '10'],
        ['question' => 'What is the square root of 64?', 'answer' => '8'],
        ['question' => 'If today is Monday, what day comes after tomorrow?', 'answer' => 'wednesday'],
        // General knowledge
        ['question' => 'How many sides does a hexagon have?', 'answer' => '6'],
        ['question' => 'What is the capital of Philippines?', 'answer' => 'manila'],
        ['question' => 'In what year did the 21st century begin? (4 digits)', 'answer' => '2001'],
        // Mixed puzzles
        ['question' => 'What is half of 50?', 'answer' => '25'],
        ['question' => 'If 5 apples cost 25 pesos, how much is one apple?', 'answer' => '5'],
        ['question' => 'How many zeros are in one thousand?', 'answer' => '3'],
        ['question' => 'What is 100 ÷ 4?', 'answer' => '25'],
        ['question' => 'What is 12 × 12?', 'answer' => '144']
    ];
    
    $selectedPuzzle = $puzzles[array_rand($puzzles)];
    $_SESSION['puzzle_answer'] = strtolower(trim($selectedPuzzle['answer']));
    return $selectedPuzzle['question'];
}
?>