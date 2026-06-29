<?php
require_once __DIR__ . '/src/config/database.php';

$tablesToImport = ['record_packages', 'system_logs', 'users'];

$sqlFile = file_get_contents(__DIR__ . '/databasehosting.sql');
if (!$sqlFile) die("Could not read SQL file\n");

// We need to disable foreign key checks
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");

foreach ($tablesToImport as $table) {
    echo "Processing $table...\n";
    
    // Extract CREATE TABLE
    preg_match("/CREATE TABLE `$table` \((.*?)\) ENGINE=InnoDB/s", $sqlFile, $matchesCreate);
    if (!empty($matchesCreate[0])) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        $pdo->exec($matchesCreate[0] . " DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
        echo "- Created table $table\n";
    }
    
    // Extract INSERT INTO
    preg_match("/INSERT INTO `$table` .*?;/s", $sqlFile, $matchesInsert);
    if (!empty($matchesInsert[0])) {
        $pdo->exec($matchesInsert[0]);
        echo "- Inserted data for $table\n";
    }
    
    // Extract ALTER TABLE constraints (Primary Keys & Auto Increments)
    // We look for ALTER TABLE `table_name`
    // Wait, the dump format separates Primary keys and auto increments.
    // Let's just find the ALTER TABLE block for this table.
    
    // In phpMyAdmin dump, it's usually like:
    // ALTER TABLE `table_name`
    //   ADD PRIMARY KEY (`id`);
    preg_match_all("/ALTER TABLE `$table`\s+ADD.*?;/s", $sqlFile, $matchesAlter);
    if (!empty($matchesAlter[0])) {
        foreach ($matchesAlter[0] as $alterStmt) {
            $pdo->exec($alterStmt);
            echo "- Applied ALTER TABLE constraint for $table\n";
        }
    }
    
    // Extract AUTO_INCREMENT for this table
    preg_match("/ALTER TABLE `$table`\s+MODIFY `.*?` int\(11\) NOT NULL AUTO_INCREMENT.*?;/s", $sqlFile, $matchesAi);
    if (!empty($matchesAi[0])) {
        $pdo->exec($matchesAi[0]);
        echo "- Applied AUTO_INCREMENT for $table\n";
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS=1");
echo "Done importing missing tables!\n";
