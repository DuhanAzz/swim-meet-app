<?php
require_once __DIR__ . '/src/config/database.php';
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $table = $row[0];
    $createStmt = $pdo->query("SHOW CREATE TABLE $table");
    $create = $createStmt->fetch(PDO::FETCH_ASSOC)['Create Table'];
    if (strpos($create, 'AUTO_INCREMENT') === false && strpos($create, 'id`') !== false) {
        echo "Table $table has NO AUTO_INCREMENT!\n";
    }
}
