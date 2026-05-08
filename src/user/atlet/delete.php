<?php
// FILE: src/user/atlet/delete.php
session_start();

// 1. CONFIG DATABASE
require_once __DIR__ . '/../../config/database.php';

// 2. CEK LOGIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$id = $_GET['id'] ?? null;
$uid = $_SESSION['user_id'];

if ($id) {
    try {
        // Mulai mode Transaksi (Agar aman jika ada salah satu yang gagal dihapus)
        $pdo->beginTransaction();
        
        // A. Hapus data rekor waktu (Gunakan nama tabel yang benar: athlete_records)
        $stmtRec = $pdo->prepare("DELETE FROM athlete_records WHERE swimmer_id = ?");
        $stmtRec->execute([$id]);
        
        // B. Hapus riwayat pendaftaran lomba (Agar tidak ada data nyangkut/Error Constraint)
        $stmtEnt = $pdo->prepare("DELETE FROM event_entries WHERE swimmer_id = ? AND user_id = ?");
        $stmtEnt->execute([$id, $uid]);

        // C. Terakhir, hapus atlet utamanya
        $stmtSwim = $pdo->prepare("DELETE FROM swimmers WHERE id = ? AND user_id = ?");
        $stmtSwim->execute([$id, $uid]);
        
        // Simpan semua perubahan
        $pdo->commit();
        
        // Kembalikan ke halaman awal dengan notifikasi sukses
        header("Location: index.php?msg=deleted");
        exit;
        
    } catch (PDOException $e) {
        // Batalkan penghapusan jika ada error
        $pdo->rollBack();
        die("Fatal Error saat menghapus: " . $e->getMessage());
    }
} else {
    // Jika tidak ada ID di URL, kembalikan saja
    header("Location: index.php");
    exit;
}