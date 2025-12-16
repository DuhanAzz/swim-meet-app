<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$stage = $_GET['stage'] ?? 'Prelims'; 

// 1. AMBIL USER & LOGO
$stmtU = $pdo->prepare("SELECT nama_lengkap, venue_name, location, logo_left, logo_right, lane_count FROM users WHERE id = ?");
$stmtU->execute([$uid]);
$user = $stmtU->fetch();

// 2. AMBIL SEMUA KATEGORI
$stmtCats = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY event_no ASC");
$stmtCats->execute([$uid]);
$categories = $stmtCats->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    /* CSS SERAGAM */
    .report-container { background-color: #525659; min-height: 100vh; padding: 40px 0; display: flex; flex-direction: column; align-items: center; gap: 30px; }
    .report-paper { background: white; width: 210mm; min-height: 297mm; padding: 15mm 20mm; box-shadow: 0 0 15px rgba(0,0,0,0.3); font-family: 'Courier New', Courier, monospace; color: #000; position: relative; page-break-after: always; }
    .double-line { border-top: 4px double #000; margin: 10px 0 20px 0; }
    .table-list { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 25px; }
    .table-list th { border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 8px 4px; text-align: left; text-transform: uppercase; }
    .table-list td { padding: 6px 4px; border-bottom: 1px solid #ddd; vertical-align: middle; font-weight: bold; }
    .table-list tr:last-child td { border-bottom: 2px solid #000; }
    
    @media print {
        nav, aside, .no-print, .pt-24, .sm\:ml-64 { display: none !important; }
        .report-container { background: white; padding: 0; display: block; height: auto; gap: 0; }
        .report-paper { width: 100%; box-shadow: none; margin: 0; padding: 0; border: none; min-height: auto; }
        @page { size: A4 portrait; margin: 15mm; }
    }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen">
    <div class="max-w-7xl mx-auto mb-6 no-print flex justify-between items-center bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl font-bold">FULL BOOK PREVIEW</h1>
            <p class="text-xs font-bold text-slate-500 uppercase">SEMUA NOMOR LOMBA - <?= strtoupper($stage) ?></p>
        </div>
        <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-bold text-xs uppercase shadow hover:bg-blue-700">🖨️ Cetak Semua</button>
    </div>

    <div class="report-container">
        <?php foreach($categories as $cat): 
            // Ambil Heat utk kategori ini
            $stmtH = $pdo->prepare("SELECT * FROM race_heats WHERE category_id = ? AND stage = ? ORDER BY heat_number ASC");
            $stmtH->execute([$cat['id'], $stage]);
            $heats = $stmtH->fetchAll();
            
            // Loop heats utk ambil lanes
            foreach ($heats as &$h) {
                $stmtL = $pdo->prepare("SELECT rl.*, s.nama_atlet, u.nama_lengkap as nama_klub, u.location as kab 
                                        FROM race_lines rl JOIN swimmers s ON rl.swimmer_id = s.id 
                                        JOIN users u ON s.user_id = u.id WHERE rl.heat_id = ? ORDER BY rl.lane_number ASC");
                $stmtL->execute([$h['id']]);
                $h['lanes'] = $stmtL->fetchAll();
            }
        ?>
        <div class="report-paper">
            <div class="flex justify-between items-center mb-6">
                <div class="w-20 h-20 flex items-center justify-center">
                    <?php if($user['logo_left']): ?><img src="../../../public/<?= $user['logo_left'] ?>" class="max-h-full"><?php endif; ?>
                </div>
                <div class="text-center flex-1 mx-4">
                    <h2 class="text-lg font-black uppercase leading-tight"><?= strtoupper($user['nama_lengkap']) ?></h2>
                    <p class="text-xs font-bold mt-1 uppercase"><?= $user['location'] ?></p>
                    <div class="h-1 w-24 bg-black mx-auto my-2"></div>
                    <h1 class="text-3xl font-black uppercase tracking-tighter">START LIST</h1>
                </div>
                <div class="w-20 h-20 flex items-center justify-center">
                    <?php if($user['logo_right']): ?><img src="../../../public/<?= $user['logo_right'] ?>" class="max-h-full"><?php endif; ?>
                </div>
            </div>
            <div class="double-line"></div>

            <div class="flex justify-between items-end mb-6 font-bold uppercase text-xs border-b pb-2">
                <div><span class="block text-slate-500 text-[9px]">Nomor Acara</span><span class="text-xl">#<?= $cat['event_no'] ?></span></div>
                <div class="text-center"><span class="block text-slate-500 text-[9px]">Kategori</span><span class="text-base"><?= $cat['distance'] ?>M <?= $cat['style'] ?> <?= $cat['gender']=='Male'?'PUTRA':'PUTRI' ?></span></div>
                <div class="text-right"><span class="block text-slate-500 text-[9px]">Babak</span><span class="text-base bg-black text-white px-2 py-0.5"><?= strtoupper($stage) ?></span></div>
            </div>

            <?php if(!$heats): ?>
                <div class="text-center italic py-10 border border-dashed">Belum ada seeding untuk nomor ini.</div>
            <?php else: foreach($heats as $h): ?>
                <div class="mb-6 break-inside-avoid">
                    <div class="font-bold text-xs uppercase bg-slate-100 p-1 border border-slate-300 mb-1">SERI <?= $h['heat_number'] ?></div>
                    <table class="table-list">
                        <thead><tr><th width="5%">LN</th><th width="35%">NAMA ATLET</th><th width="25%">DAERAH</th><th width="25%">KLUB</th><th width="10%" class="text-center">TIME</th></tr></thead>
                        <tbody>
                            <?php 
                            $map = []; foreach($h['lanes'] as $l) $map[$l['lane_number']] = $l;
                            for($i=1; $i<=$user['lane_count']; $i++): $sw=$map[$i]??null; ?>
                            <tr>
                                <td class="text-center"><?= $i ?></td>
                                <td><?= $sw ? strtoupper($sw['nama_atlet']) : '<span class="text-slate-300 italic">--</span>' ?></td>
                                <td><?= $sw ? strtoupper($sw['kab']??'') : '' ?></td>
                                <td><?= $sw ? strtoupper($sw['nama_klub']??'') : '' ?></td>
                                <td class="text-center"><?= $sw ? ($sw['entry_time']=='999999999'?'NT':$sw['entry_time']) : '' ?></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; endif; ?>
            
            <div class="absolute bottom-10 left-0 w-full px-12 text-[9px] flex justify-between uppercase text-slate-400 font-bold">
                <span>SwimMeet System</span><span>Event #<?= $cat['event_no'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>