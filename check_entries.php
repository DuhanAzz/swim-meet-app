<?php
require 'src/config/database.php';
$stmt = $pdo->query("SHOW COLUMNS FROM event_entries");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
