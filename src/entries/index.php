<?php
session_start();
// PERBAIKAN: Hanya mundur satu langkah (..) karena config ada di dalam src
require_once __DIR__ . '/../config/database.php';

try {
    // JOIN 3 Tabel: Entries -> Swimmers -> Events
    $sql = "SELECT entries.id, entries.seed_time, 
                   swimmers.nama_atlet, clubs.nama_klub,
                   events.nama_event, events.jarak, events.gaya, events.jenis_kelamin
            FROM entries
            JOIN swimmers ON entries.swimmer_id = swimmers.id
            JOIN clubs ON swimmers.club_id = clubs.id
            JOIN events ON entries.event_id = events.id
            ORDER BY events.id ASC, entries.seed_time ASC";
            
    $entries = $pdo->query($sql)->fetchAll();
    
    // View ada di luar src, jadi mundur 2 langkah (../../) itu benar untuk View
    require_once __DIR__ . '/../../views/entries/index.php';
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
