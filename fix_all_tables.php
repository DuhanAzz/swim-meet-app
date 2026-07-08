<?php
require_once __DIR__ . '/src/config/database.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = [];
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

foreach($tables as $table) {
    // Check columns
    $colStmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    $hasId = false;
    $hasPk = false;
    $hasAutoInc = false;
    
    while($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($col['Field'] == 'id') {
            $hasId = true;
            if ($col['Key'] == 'PRI') $hasPk = true;
            if (strpos($col['Extra'], 'auto_increment') !== false) $hasAutoInc = true;
        }
    }
    
    if ($hasId && (!$hasPk || !$hasAutoInc)) {
        echo "Fixing table $table...\n";
        try {
            if (!$hasPk) {
                $pdo->exec("ALTER TABLE `$table` ADD PRIMARY KEY (`id`)");
            }
            if (!$hasAutoInc) {
                $pdo->exec("ALTER TABLE `$table` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
            }
            echo "  [SUCCESS]\n";
        } catch (Exception $e) {
            echo "  [ERROR] " . $e->getMessage() . "\n";
        }
    }
}
echo "Done.\n";
