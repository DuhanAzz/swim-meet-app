<?php
// FILE: src/master/settings/global_config.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// CEK AKSES
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

// --- HANDLE UPDATE CONFIG (Hanya App Name & Maintenance) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    try {
        $appName   = $_POST['app_name'];
        $maintMode = isset($_POST['maintenance_mode']) ? 1 : 0;

        // Kita hanya update identitas dan maintenance. Data Bank TIDAK disentuh.
        $sql = "UPDATE site_settings SET 
                app_name = ?, 
                maintenance_mode = ? 
                WHERE id = 1";
        
        $pdo->prepare($sql)->execute([$appName, $maintMode]);

        $_SESSION['msg'] = "Konfigurasi sistem berhasil diperbarui.";
        $_SESSION['msg_type'] = "success";
    } catch (Exception $e) {
        $_SESSION['msg'] = "Gagal simpan: " . $e->getMessage();
        $_SESSION['msg_type'] = "error";
    }
    header("Location: global_config.php"); exit;
}

// AMBIL DATA SETTINGS
$config = $pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();

include __DIR__ . '/../../../views/layout/sidebar.php';
include __DIR__ . '/../../../views/layout/topbar.php';
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">
            Global Configuration
        </h1>
        <p class="text-sm text-slate-500 font-medium">Pengaturan inti sistem & Monitoring Rekening.</p>
    </div>

    <?php if(isset($_SESSION['msg'])): ?>
        <div class="p-4 mb-6 rounded-xl text-sm font-bold border flex items-center gap-3 shadow-sm 
            <?= $_SESSION['msg_type'] == 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
            <?= htmlspecialchars($_SESSION['msg']) ?>
        </div>
        <?php unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <form method="POST" class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        <input type="hidden" name="save_config" value="1">

        <div class="xl:col-span-2 space-y-8">
            
            <div class="bg-white rounded-[2rem] shadow-xl border border-slate-200 overflow-hidden">
                <div class="bg-slate-800 px-8 py-5 border-b border-slate-700">
                    <h3 class="text-white font-black text-sm uppercase tracking-wider flex items-center gap-2">
                        <span>🏷️</span> Identitas Sistem
                    </h3>
                </div>
                <div class="p-8">
                    <label class="block text-[10px] font-black uppercase text-slate-500 tracking-widest mb-2">Nama Aplikasi (Brand)</label>
                    <input type="text" name="app_name" value="<?= htmlspecialchars($config['app_name'] ?? 'SwimMeet App') ?>" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-slate-500 font-bold text-slate-700">
                    <p class="text-[10px] text-slate-400 mt-2 italic">
                        Nama ini akan muncul di Title Bar browser.
                    </p>
                </div>
            </div>

            <div class="bg-slate-100 rounded-[2rem] border border-slate-200 overflow-hidden opacity-90">
                <div class="bg-slate-200 px-8 py-4 border-b border-slate-300 flex justify-between items-center">
                    <h3 class="text-slate-600 font-black text-sm uppercase tracking-wider flex items-center gap-2">
                        <span>💳</span> Rekening Utama (Read Only)
                    </h3>
                    <span class="bg-slate-300 text-slate-600 text-[9px] font-bold px-2 py-1 rounded uppercase">Dikelola Admin</span>
                </div>
                <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-6 relative">
                    <div class="absolute inset-0 z-10 cursor-not-allowed" title="Data ini dikelola oleh Admin Event"></div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">Bank</label>
                        <input type="text" value="<?= htmlspecialchars($config['bank_name'] ?? '-') ?>" class="w-full px-4 py-2 rounded-xl border border-slate-300 bg-white text-slate-500 font-bold" disabled>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">No. Rekening</label>
                        <input type="text" value="<?= htmlspecialchars($config['bank_account'] ?? '-') ?>" class="w-full px-4 py-2 rounded-xl border border-slate-300 bg-white text-slate-500 font-mono font-bold" disabled>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">Atas Nama</label>
                        <input type="text" value="<?= htmlspecialchars($config['bank_holder'] ?? '-') ?>" class="w-full px-4 py-2 rounded-xl border border-slate-300 bg-white text-slate-500 font-bold" disabled>
                    </div>
                </div>
            </div>

        </div>

        <div class="xl:col-span-1">
            <div class="bg-white rounded-[2rem] shadow-xl border border-slate-200 overflow-hidden h-full flex flex-col">
                <div class="bg-red-600 px-8 py-5 border-b border-red-500">
                    <h3 class="text-white font-black text-sm uppercase tracking-wider flex items-center gap-2">
                        <span>🚨</span> Emergency Zone
                    </h3>
                </div>
                <div class="p-8 flex-1 flex flex-col justify-between">
                    <div>
                        <h4 class="font-bold text-slate-800 mb-2">Mode Perbaikan (Maintenance)</h4>
                        <p class="text-xs text-slate-500 mb-6 leading-relaxed">
                            Jika diaktifkan, halaman depan (Public) akan dikunci dengan layar "Under Maintenance".
                        </p>

                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="maintenance_mode" value="1" class="sr-only peer" <?= ($config['maintenance_mode'] ?? 0) == 1 ? 'checked' : '' ?>>
                            <div class="w-14 h-7 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-red-600"></div>
                            <span class="ml-3 text-sm font-bold text-slate-700">Aktifkan</span>
                        </label>
                    </div>

                    <div class="mt-8 bg-orange-50 p-4 rounded-xl border border-orange-100 text-orange-700 text-xs font-medium">
                        ⚠️ Pastikan Anda login sebagai Master sebelum mengaktifkan ini.
                    </div>
                </div>
            </div>
        </div>

        <div class="xl:col-span-3">
            <button type="submit" class="w-full bg-slate-900 hover:bg-indigo-700 text-white py-5 rounded-2xl font-black uppercase tracking-[0.2em] text-sm shadow-2xl transition transform hover:scale-[1.01]">
                Simpan Konfigurasi Sistem
            </button>
        </div>

    </form>
</div>