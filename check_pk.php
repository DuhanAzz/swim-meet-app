<?php
require_once __DIR__ . '/src/config/database.php';
$tables = ['event_numbers', 'clubs', 'event_age_groups', 'relay_entries'];
foreach($tables as $table) {
    echo "\n=== $table ===\n";
    $stmt = $pdo->query("SHOW CREATE TABLE $table");
    print_r($stmt->fetch(PDO::FETCH_ASSOC)['Create Table']);
}
