<?php
require 'src/config/database.php';
try {
    echo "<h1>DEBUG DATABASE - EVENT HISTORICAL RECORDS</h1>";
    echo "<h2>Tabel event_historical_records</h2>";
    $stmt = $pdo->query("SELECT * FROM event_historical_records ORDER BY id DESC LIMIT 20");
    echo "<pre>"; print_r($stmt->fetchAll(PDO::FETCH_ASSOC)); echo "</pre>";

    echo "<h2>Tabel event_numbers (Event 26)</h2>";
    $stmt = $pdo->query("SELECT id, event_number, distance, stroke, jenis_kelamin, age_group FROM event_numbers WHERE event_id = 26 LIMIT 10");
    echo "<pre>"; print_r($stmt->fetchAll(PDO::FETCH_ASSOC)); echo "</pre>";
    
    echo "<h2>Tabel events (Event 26)</h2>";
    $stmt = $pdo->query("SELECT id, event_name, record_package_id FROM events WHERE id = 26");
    echo "<pre>"; print_r($stmt->fetchAll(PDO::FETCH_ASSOC)); echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
