<?php
require 'src/config/database.php';
$stmt = $pdo->query("SHOW COLUMNS FROM events");
echo "EVENTS TABLE:\n";
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));

$stmt = $pdo->query("SHOW COLUMNS FROM event_numbers");
echo "\nEVENT_NUMBERS TABLE:\n";
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
