<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $event_id = $_POST['event_id'];
    
    try {
        // A. RESET DATA LAMA
        $stmtFind = $pdo->prepare("SELECT id FROM heats WHERE event_id = ?");
        $stmtFind->execute([$event_id]);
        $oldHeats = $stmtFind->fetchAll(PDO::FETCH_COLUMN);
        
        if ($oldHeats) {
            $placeholders = implode(',', array_fill(0, count($oldHeats), '?'));
            $pdo->prepare("DELETE FROM heat_entries WHERE heat_id IN ($placeholders)")->execute($oldHeats);
            $pdo->prepare("DELETE FROM heats WHERE event_id = ?")->execute([$event_id]);
        }

        // B. AMBIL PENDAFTAR (Urutkan dari Tercepat)
        $sql = "SELECT * FROM entries 
                WHERE event_id = ? 
                ORDER BY CASE WHEN seed_time = '00:00.00' THEN 1 ELSE 0 END, seed_time ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$event_id]);
        $swimmers = $stmt->fetchAll();

        if (count($swimmers) == 0) {
            die("Belum ada pendaftar di event ini.");
        }

        // C. KONFIGURASI KOLAM
        $lanes_per_heat = 8; 
        $lane_order = [4, 5, 3, 6, 2, 7, 1, 8]; // Pola Zig-Zag

        // Bagi perenang ke dalam kelompok heat
        $heats_distribution = array_chunk($swimmers, $lanes_per_heat);
        $heats_distribution = array_reverse($heats_distribution); // Balik agar seri akhir isinya yang cepat

        foreach ($heats_distribution as $index => $heat_swimmers) {
            $heat_number = $index + 1;

            // Buat Heat Baru
            $sqlHeat = "INSERT INTO heats (event_id, heat_number) VALUES (?, ?)";
            $stmtHeat = $pdo->prepare($sqlHeat);
            $stmtHeat->execute([$event_id, $heat_number]);
            $heat_id = $pdo->lastInsertId();

            // Masukkan Perenang ke Lintasan
            foreach ($heat_swimmers as $key => $entry) {
                if (isset($lane_order[$key])) {
                    $lane = $lane_order[$key];
                    $sqlEntry = "INSERT INTO heat_entries (heat_id, swimmer_id, lane_number) VALUES (?, ?, ?)";
                    $pdo->prepare($sqlEntry)->execute([$heat_id, $entry['swimmer_id'], $lane]);
                }
            }
        }

        header("Location: view_startlist.php?event_id=" . $event_id);
        exit();

    } catch (PDOException $e) {
        die("Error Seeding: " . $e->getMessage());
    }
}
?>
