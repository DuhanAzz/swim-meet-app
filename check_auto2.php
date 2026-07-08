<?php
require_once __DIR__ . '/src/config/database.php';
$stmt = $pdo->query("SHOW CREATE TABLE events");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
