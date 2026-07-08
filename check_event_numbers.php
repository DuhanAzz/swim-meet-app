<?php
require_once __DIR__ . '/src/config/database.php';
$stmt = $pdo->query("SHOW CREATE TABLE event_numbers");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
