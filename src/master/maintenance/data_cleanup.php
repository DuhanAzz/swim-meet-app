<?php
// FILE: src/master/maintenance/data_cleanup.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// CEK AKSES MASTER
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

// --- HELPER LOGGING (Jika belum ada di global) ---
if (!function_exists('writeLog')) {
    function writeLog($pdo, $userId, $action, $targetId, $desc) {
        try {
            $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action_type, target_id, description, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $action, $targetId, $desc, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
        } catch (Exception $e) {}
    }
}

$msg = '';
$msgType = '';

// ==========================================
// 1. HANDLE ACTION: MERGE CLUB
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['merge_clubs'])) {
    $sourceId = $_POST['source_club_id'];
    $targetId = $_POST['target_club_id'];

    if ($sourceId == $targetId) {
        $msg = "Klub asal dan tujuan tidak boleh sama!";
        $msgType = "error";
    } elseif (empty($sourceId) || empty($targetId)) {
        $msg = "Pilih kedua klub terlebih dahulu.";
        $msgType = "error";
    } else {
        try {
            $pdo->beginTransaction();

            // A. Pindahkan Atlet
            $stmtUpdate = $pdo->prepare("UPDATE swimmers SET club_id = ? WHERE club_id = ?");
            $stmtUpdate->execute([$targetId, $sourceId]);
            $countMoved = $stmtUpdate->rowCount();

            // B. Ambil Nama Klub untuk Log
            $clubName = $pdo->query("SELECT nama_klub FROM clubs WHERE id = $sourceId")->fetchColumn();
            $targetName = $pdo->query("SELECT nama_klub FROM clubs WHERE id = $targetId")->fetchColumn();

            // C. Hapus Klub Lama
            $pdo->prepare("DELETE FROM clubs WHERE id = ?")->execute([$sourceId]);

            // D. Catat Log
            writeLog($pdo, $_SESSION['user_id'], 'MERGE_CLUB', $targetId, "Menggabungkan '$clubName' ke '$targetName'. $countMoved atlet dipindahkan.");

            $pdo->commit();
            $msg = "Berhasil menggabungkan klub! $countMoved atlet telah dipindahkan.";
            $msgType = "success";

        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Gagal merge: " . $e->getMessage();
            $msgType = "error";
        }
    }
}

// ==========================================
// 2. HANDLE ACTION: CLEANUP EVENT
// ==========================================
if (isset($_POST['cleanup_events'])) {
    try {
        // Hapus event status 'Draft' yang dibuat lebih dari 30 hari lalu
        $sql = "DELETE FROM events WHERE event_status = 'Draft' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $deleted = $stmt->rowCount();

        if ($deleted > 0) {
            writeLog($pdo, $_SESSION['user_id'], 'CLEANUP_EVENT', 0, "Menghapus $deleted event draft usang.");
            $msg = "Berhasil menghapus $deleted event draft yang usang.";
            $msgType = "success";
        } else {
            $msg = "Tidak ada event draft usang yang ditemukan.";
            $msgType = "info";
        }
    } catch (Exception $e) {
        $msg = "Error cleanup: " . $e->getMessage();
        $msgType = "error";
    }
}

// --- DATA UNTUK VIEW ---
// 1. List Klub untuk Dropdown
$clubs = $pdo->query("SELECT id, nama_klub, city FROM clubs ORDER BY nama_klub ASC")->fetchAll();

// 2. List Event Draft Usang (Preview)
$oldDrafts = $pdo->query("SELECT * FROM events WHERE event_status = 'Draft' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchAll();

include __DIR__ . '/../../../views/layout/sidebar.php';
include __DIR__ . '/../../../views/layout/topbar.php';
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">
            Maintenance & Kebersihan Data
        </h1>
        <p class="text-sm text-slate-500 font-medium">Tools untuk merapikan database dan menghapus data sampah.</p>
    </div>

    <?php if($msg): ?>
        <div class="p-4 mb-6 rounded-xl text-sm font-bold border flex items-center gap-3 shadow-sm 
            <?= $msgType == 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 
               ($msgType == 'info' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-red-50 text-red-700 border-red-200') ?>">
            <span><?= $msgType == 'success' ? '✅' : ($msgType == 'info' ? 'ℹ️' : '⚠️') ?></span>
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">

        <div class="bg-white rounded-[2rem] shadow-xl border border-slate-200 overflow-hidden">
            <div class="bg-blue-600 p-6 border-b border-blue-500">
                <h2 class="text-white font-black text-lg uppercase tracking-widest flex items-center gap-2">
                    🔄 Gabungkan Klub Ganda
                </h2>
                <p class="text-blue-100 text-xs mt-1">Pindahkan semua atlet dari klub salah ke klub benar, lalu hapus klub salah.</p>
            </div>
            
            <form method="POST" class="p-8" onsubmit="return confirm('PERINGATAN: Klub Asal akan DIHAPUS permanen setelah atlet dipindahkan. Lanjutkan?');">
                <input type="hidden" name="merge_clubs" value="1">
                
                <div class="space-y-6">
                    <div class="bg-red-50 p-4 rounded-xl border border-red-100">
                        <label class="block text-[10px] font-black uppercase text-red-500 tracking-widest mb-2">1. Pilih Klub Asal (Akan Dihapus)</label>
                        <select name="source_club_id" class="w-full px-4 py-3 rounded-xl border border-red-200 focus:border-red-500 focus:ring-0 font-bold text-slate-700 text-sm bg-white" required>
                            <option value="">-- Pilih Klub Salah --</option>
                            <?php foreach($clubs as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nama_klub']) ?> (<?= htmlspecialchars($c['city']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex justify-center -my-3 relative z-10">
                        <div class="bg-white p-2 rounded-full shadow border border-slate-100 text-slate-400">⬇️ Pindahkan ke ⬇️</div>
                    </div>

                    <div class="bg-emerald-50 p-4 rounded-xl border border-emerald-100">
                        <label class="block text-[10px] font-black uppercase text-emerald-600 tracking-widest mb-2">2. Pilih Klub Tujuan (Penyimpanan)</label>
                        <select name="target_club_id" class="w-full px-4 py-3 rounded-xl border border-emerald-200 focus:border-emerald-500 focus:ring-0 font-bold text-slate-700 text-sm bg-white" required>
                            <option value="">-- Pilih Klub Benar --</option>
                            <?php foreach($clubs as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nama_klub']) ?> (<?= htmlspecialchars($c['city']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="mt-8 w-full bg-slate-900 hover:bg-blue-700 text-white py-4 rounded-xl font-black uppercase tracking-widest text-xs shadow-lg transition transform hover:scale-[1.02]">
                    Eksekusi Penggabungan
                </button>
            </form>
        </div>

        <div class="bg-white rounded-[2rem] shadow-xl border border-slate-200 overflow-hidden flex flex-col">
            <div class="bg-slate-800 p-6 border-b border-slate-700">
                <h2 class="text-white font-black text-lg uppercase tracking-widest flex items-center gap-2">
                    🧹 Bersihkan Event Sampah
                </h2>
                <p class="text-slate-400 text-xs mt-1">Hapus event berstatus 'Draft' yang dibuat lebih dari 30 hari lalu.</p>
            </div>
            
            <div class="p-8 flex-1 flex flex-col">
                <div class="bg-slate-50 rounded-xl border border-slate-200 p-4 mb-6 flex-1 overflow-y-auto max-h-64">
                    <h3 class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-3 sticky top-0 bg-slate-50 pb-2">
                        Preview Data yang akan dihapus:
                    </h3>
                    <?php if(empty($oldDrafts)): ?>
                        <div class="text-center py-8 text-slate-400 italic text-sm">
                            Hore! Tidak ada file sampah. Database bersih. ✨
                        </div>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach($oldDrafts as $d): ?>
                                <li class="flex justify-between items-center text-xs border-b border-slate-100 pb-2">
                                    <span class="font-bold text-slate-700"><?= htmlspecialchars($d['event_name']) ?></span>
                                    <span class="text-slate-400 font-mono"><?= date('d M Y', strtotime($d['created_at'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <form method="POST" onsubmit="return confirm('Yakin hapus semua event draft usang?');">
                    <input type="hidden" name="cleanup_events" value="1">
                    <?php if(empty($oldDrafts)): ?>
                        <button type="button" class="w-full bg-slate-200 text-slate-400 py-4 rounded-xl font-black uppercase tracking-widest text-xs cursor-not-allowed" disabled>
                            Tidak Ada Sampah
                        </button>
                    <?php else: ?>
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-4 rounded-xl font-black uppercase tracking-widest text-xs shadow-lg transition transform hover:scale-[1.02]">
                            Hapus <?= count($oldDrafts) ?> Event Usang
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    </div>
</div>