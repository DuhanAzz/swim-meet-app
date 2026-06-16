<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $event_id = $_POST['event_id'];
    $results = $_POST['results']; // Waktu
    $statuses = $_POST['status']; // Status (OK, DQ, DNS)

    try {
        $pdo->beginTransaction();

        // 1. UPDATE DATA (Waktu & Status)
        $stmtUpdate = $pdo->prepare("UPDATE heat_entries SET final_time = ?, status = ? WHERE id = ?");
        
        foreach ($results as $id => $time) {
            $status = $statuses[$id];
            $timeVal = empty(trim($time)) ? null : trim($time);
            
            // Jika status bukan OK, waktu tidak wajib (tapi bisa disimpan sebagai catatan)
            $stmtUpdate->execute([$timeVal, $status, $id]);
        }

        // 2. HITUNG RANKING (HANYA YANG STATUS 'OK')
        // Reset dulu semua ranking jadi NULL biar bersih
        $pdo->prepare("UPDATE heat_entries 
                       JOIN heats ON heat_entries.heat_id = heats.id 
                       SET rank = NULL 
                       WHERE heats.event_id = ?")->execute([$event_id]);

        // Ambil atlet yang SAH (Status OK) dan punya waktu
        $sqlRank = "SELECT heat_entries.id 
                    FROM heat_entries 
                    JOIN heats ON heat_entries.heat_id = heats.id
                    WHERE heats.event_id = ? 
                      AND heat_entries.status = 'OK' 
                      AND heat_entries.final_time IS NOT NULL 
                      AND heat_entries.final_time != ''
                    ORDER BY heat_entries.final_time ASC";
        
        $stmtGet = $pdo->prepare($sqlRank);
        $stmtGet->execute([$event_id]);
        $sahFinisher = $stmtGet->fetchAll();

        // Berikan Ranking
        $rank = 1;
        $stmtUpdateRank = $pdo->prepare("UPDATE heat_entries SET rank = ? WHERE id = ?");
        foreach ($sahFinisher as $finisher) {
            $stmtUpdateRank->execute([$rank, $finisher['id']]);
            $rank++;
        }

        $pdo->commit();
        $_SESSION['toast_type'] = 'success';
        $_SESSION['toast_message'] = 'Hasil & Status Diskualifikasi tersimpan!';
        header("Location: input.php?event_id=" . $event_id);
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_message'] = 'Error: ' . $e->getMessage();
        header("Location: input.php?event_id=$event_id");
        exit();
    }
}
?>
