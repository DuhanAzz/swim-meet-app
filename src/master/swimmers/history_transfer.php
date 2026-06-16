<?php
// FILE: src/master/swimmers/history_transfer.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// PROTEKSI HALAMAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

$error_msg = "";
$transfers = [];

try {
    // QUERY: AMBIL DATA TRANSFER
    // KITA GUNAKAN LOGIKA: Jika transfer_date NULL, gunakan created_at untuk pengurutan
    $sql = "SELECT t.*, 
                   s.nama_atlet, s.uid,
                   c_old.nama_klub as old_club,
                   c_new.nama_klub as new_club,
                   u.nama_lengkap as admin_name
            FROM swimmer_transfers t
            JOIN swimmers s ON t.swimmer_id = s.id
            LEFT JOIN clubs c_old ON t.old_club_id = c_old.id
            LEFT JOIN clubs c_new ON t.new_club_id = c_new.id
            LEFT JOIN users u ON t.processed_by = u.id
            ORDER BY COALESCE(t.transfer_date, t.created_at) DESC"; // <-- Sortir pakai tanggal yang ada

    $transfers = $pdo->query($sql)->fetchAll();

} catch (PDOException $e) {
    $error_msg = "Gagal memuat data: " . $e->getMessage();
}

include __DIR__ . '/../../../views/layout/sidebar.php';
include __DIR__ . '/../../../views/layout/topbar.php';
?>

<div class="p-4 sm:ml-64">
    <div class="p-4 mt-14">

        <div class="mb-6">
            <nav class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">
                <a href="index.php" class="hover:text-blue-600">Database Atlet</a> / Mutasi
            </nav>
            <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">
                Riwayat Mutasi Klub
            </h1>
            <p class="text-xs text-slate-500 font-medium mt-1">
                Rekam jejak perpindahan atlet antar klub.
            </p>
        </div>

        <?php if($error_msg): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-4 text-xs font-bold border border-red-200">
                ⚠️ <?= $error_msg ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase text-slate-500 tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4">Atlet</th>
                            <th class="px-6 py-4 text-right">Dari Klub</th>
                            <th class="px-6 py-4 text-center"></th> 
                            <th class="px-6 py-4">Ke Klub</th>
                            <th class="px-6 py-4">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if(empty($transfers)): ?>
                            <tr><td colspan="6" class="p-8 text-center text-slate-400 italic">Belum ada riwayat perpindahan.</td></tr>
                        <?php else: foreach($transfers as $t): ?>
                            
                        <?php 
                            // LOGIKA TANGGAL: Prioritaskan transfer_date, kalau NULL pakai created_at
                            $raw_date = $t['transfer_date'] ? $t['transfer_date'] : $t['created_at'];
                        ?>
                        
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-bold text-slate-700 text-xs">
                                    <?= date('d M Y', strtotime($raw_date)) ?>
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    <?= date('H:i', strtotime($raw_date)) . ' WIB' ?>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div class="font-black text-slate-800 uppercase text-xs">
                                    <?= htmlspecialchars($t['nama_atlet']) ?>
                                </div>
                                <div class="text-[10px] font-mono text-blue-600">
                                    UID: <?= htmlspecialchars($t['uid'] ?? '-') ?>
                                </div>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <?php if($t['old_club']): ?>
                                    <span class="text-[10px] font-bold text-red-600 bg-red-50 px-2 py-1 rounded border border-red-100 inline-block">
                                        <?= htmlspecialchars($t['old_club']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-[10px] italic text-slate-400">Unattached</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <svg class="w-4 h-4 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </td>

                            <td class="px-6 py-4">
                                <?php if($t['new_club']): ?>
                                    <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded border border-emerald-100 inline-block">
                                        <?= htmlspecialchars($t['new_club']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-[10px] italic text-slate-400">Unattached</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4">
                                <div class="text-[10px] text-slate-600 italic">
                                    "<?= htmlspecialchars($t['notes'] ?? '-') ?>"
                                </div>
                                <div class="text-[9px] text-slate-400 mt-1 uppercase font-bold">
                                    Oleh: <?= htmlspecialchars($t['admin_name'] ?? 'System') ?>
                                </div>
                            </td>

                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>