<?php
if (!defined('BOOM')) { die(); }

// Add the score field only once so a retry cannot fail with duplicate-column SQL.
$quizScoreColumn = $mysqli->query("SHOW COLUMNS FROM `boom_users` LIKE 'quiz_score'");
if (!$quizScoreColumn) {
    die('Quizbot: unable to inspect boom_users schema.');
}
if ($quizScoreColumn->num_rows === 0) {
    if (!$mysqli->query("ALTER TABLE `boom_users` ADD `quiz_score` int(11) NOT NULL DEFAULT '0'")) {
        die('Quizbot: unable to add quiz_score field.');
    }
}

$ad = array(
    'name' => 'quizbot',
    'bot_name' => 'Quizbot',
    'bot_type' => 2,
    'custom1' => 0,
    'custom2' => 0,
    'custom3' => 1,
    'custom4' => 'English1.txt',
    'custom5' => 'Scramble_english.txt'
);
?>
