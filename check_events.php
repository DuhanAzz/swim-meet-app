<?php
require_once __DIR__ . '/src/config/database.php';
$stmt = $pdo->query("DESCRIBE events");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $col) {
    echo $col['Field'] . " - " . $col['Type'] . " - " . $col['Null'] . " - " . $col['Default'] . "\n";
}
