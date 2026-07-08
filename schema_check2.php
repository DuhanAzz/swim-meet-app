<?php
require 'src/config/database.php';

function dump_table($pdo, $table) {
    echo "--- $table ---\n";
    $stmt = $pdo->query("SHOW CREATE TABLE $table");
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
}

dump_table($pdo, 'events');
dump_table($pdo, 'event_numbers');
dump_table($pdo, 'clubs');
dump_table($pdo, 'swimmers');
