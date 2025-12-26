<?php
// src/user/atlet/index.php
session_start();

// 1. CONFIG DATABASE (Mundur 2 langkah ke folder src/config)
require_once __DIR__ . '/../../config/database.php';

// 2. CEK LOGIN (Mundur 3 langkah ke folder public)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$id_user = $_SESSION['user_id'];

// 3. AMBIL DATA ATLET
try {
    $stmt = $pdo->prepare("SELECT * FROM swimmers WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$id_user]);
    $atlet = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error mengambil data atlet: " . $e->getMessage());
}

// 4. INCLUDE LAYOUT (Mundur 3 langkah ke folder views)
include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <?php if(isset($_GET['msg'])): ?>
        <div id="alert-msg" class="mb-6 px-4 py-3 rounded-xl text-sm font-bold shadow-sm 
            <?= $_GET['msg']=='deleted' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?>">
            <?php 
                if($_GET['msg'] == 'updated') echo "✅ Data berhasil diperbarui.";
                elseif($_GET['msg'] == 'deleted') echo "🗑️ Data berhasil dihapus.";
                else echo "✅ Aksi berhasil.";
            ?>
        </div>
        <script>setTimeout(() => { document.getElementById('alert-msg').style.display = 'none'; }, 3000);</script>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black uppercase italic tracking-tighter text-slate-900">Database Atlet</h1>
            <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1">Kelola data profil dan best time.</p>
        </div>
        <a href="create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl shadow-lg transition transform hover:-translate-y-1 text-sm uppercase tracking-wider flex items-center gap-2">
            <span>+</span> Tambah Atlet
        </a>
    </div>

    <div class="bg-white border border-slate-200 rounded-[1.5rem] shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-slate-100 text-[10px] font-black uppercase tracking-widest text-slate-400">
                    <tr>
                        <th class="p-5">Nama & Sekolah</th>
                        <th class="p-5 text-center">Gender & Usia</th>
                        <th class="p-5 text-center">Best Time</th>
                        <th class="p-5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($atlet)): ?>
                        <tr>
                            <td colspan="4" class="p-12 text-center">
                                <div class="text-4xl mb-2">🏊</div>
                                <div class="text-slate-400 font-bold italic">Belum ada data atlet.</div>
                                <a href="create.php" class="text-blue-600 text-xs font-bold underline mt-2 block">Tambah Atlet Sekarang</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($atlet as $a): 
                            // LOGIC HITUNG UMUR
                            $umur = '-';
                            if (!empty($a['tanggal_lahir']) && $a['tanggal_lahir'] != '0000-00-00') {
                                $lahir = new DateTime($a['tanggal_lahir']);
                                $today = new DateTime();
                                $umur = $today->diff($lahir)->y . ' TH';
                            }

                            // LOGIC GENDER (FIXING TAMPILAN)
                            $jkCode = strtoupper($a['jenis_kelamin']);
                            // Cek apakah kodenya L, M, MALE, atau LAKI-LAKI
                            $isMale = in_array($jkCode, ['L', 'M', 'MALE', 'LAKI-LAKI', 'LAKI']); 
                            
                            $genderLabel = $isMale ? 'PUTRA' : 'PUTRI';
                            $genderClass = $isMale ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-pink-50 text-pink-600 border-pink-100';
                            
                            // LOGIC HITUNG JUMLAH RECORD (Best Time)
                            $recordCount = 0;
                            try {
                                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM swimmer_records WHERE swimmer_id = ?");
                                $stmtCount->execute([$a['id']]);
                                $recordCount = $stmtCount->fetchColumn();
                            } catch (PDOException $e) {
                                // Jika tabel belum ada, abaikan error (anggap 0)
                                $recordCount = 0; 
                            }
                        ?>
                        <tr class="hover:bg-slate-50 transition group">
                            <td class="p-5 align-middle">
                                <div class="font-black text-slate-800 text-sm uppercase"><?= htmlspecialchars($a['nama_atlet']) ?></div>
                                <div class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-wider flex items-center gap-1">
                                    <span>🏫</span> <?= htmlspecialchars($a['asal_sekolah'] ?? '-') ?>
                                </div>
                            </td>

                            <td class="p-5 text-center align-middle">
                                <div class="inline-flex items-center gap-2">
                                    <span class="px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-widest border <?= $genderClass ?>">
                                        <?= $genderLabel ?>
                                    </span>
                                    <span class="text-xs font-bold text-slate-600"><?= $umur ?></span>
                                </div>
                                <div class="text-[9px] font-mono text-slate-400 mt-1">
                                    <?= date('d/m/Y', strtotime($a['tanggal_lahir'])) ?>
                                </div>
                            </td>

                            <td class="p-5 text-center align-middle">
                                <a href="records.php?id=<?= $a['id'] ?>" class="group/btn relative inline-flex items-center gap-2 bg-emerald-50 hover:bg-emerald-500 hover:text-white text-emerald-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-wider transition border border-emerald-100 shadow-sm">
                                    <span>⏱</span>
                                    <span>Kelola Waktu</span>
                                    <?php if($recordCount > 0): ?>
                                        <span class="ml-1 bg-emerald-200 text-emerald-800 group-hover:bg-white group-hover:text-emerald-600 px-1.5 py-0.5 rounded-md text-[9px] font-mono"><?= $recordCount ?></span>
                                    <?php endif; ?>
                                </a>
                            </td>

                            <td class="p-5 text-center align-middle">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="edit.php?id=<?= $a['id'] ?>" class="text-blue-600 bg-blue-50 hover:bg-blue-600 hover:text-white px-3 py-2 rounded-lg text-xs font-bold transition border border-blue-100">
                                        Edit
                                    </a>
                                    <a href="delete.php?id=<?= $a['id'] ?>" onclick="return confirm('Yakin ingin menghapus atlet ini beserta seluruh record waktunya?')" class="text-red-500 bg-red-50 hover:bg-red-500 hover:text-white px-3 py-2 rounded-lg text-xs font-bold transition border border-red-100">
                                        Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>