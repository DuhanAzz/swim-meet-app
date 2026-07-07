<?php
require 'src/config/database.php';
$stmt = $pdo->query("SELECT * FROM event_historical_records LIMIT 50");
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('dump.json', json_encode($data, JSON_PRETTY_PRINT));
