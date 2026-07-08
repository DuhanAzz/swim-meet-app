<?php
require_once __DIR__ . '/src/config/database.php';
try {
    $pdo->exec("ALTER TABLE events ADD PRIMARY KEY (id)");
    $pdo->exec("ALTER TABLE events MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
    echo "Success";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
