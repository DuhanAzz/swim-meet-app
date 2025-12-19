<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../../public/login.php"); exit; 
}

$adminId = $_SESSION['user_id']; // ID Admin yang sedang login

// --- LOGIC HAPUS EVENT ---
if (isset($_POST['delete_id'])) {
    try {
        // UPDATE PENTING: Tambahkan WHERE organizer_id = ? 
        // Agar admin tidak bisa menghapus event milik admin lain (keamanan)
        $stmt = $pdo->prepare("DELETE FROM event_numbers WHERE id = ? AND organizer_id = ?");
        $stmt->execute([$_POST['delete_id'], $adminId]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['swal_type'] = 'success'; 
            $_SESSION['swal_msg'] = 'Nomor lomba berhasil dihapus.';
        } else {
            $_SESSION['swal_type'] = 'error'; 
            $_SESSION['swal_msg'] = 'Gagal menghapus atau data tidak ditemukan.';
        }
        
    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error'; 
        $_SESSION['swal_msg'] = 'Gagal menghapus: ' . $e->getMessage();
    }
    header("Location: index.php"); exit;
}

// --- AMBIL DATA ---
// UPDATE PENTING: Filter berdasarkan organizer_id
$stmt = $pdo->prepare("SELECT * FROM event_numbers WHERE organizer_id = ? ORDER BY event_number ASC");
$stmt->execute([$adminId]);
$events = $stmt->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="max-w-6xl mx-auto mb-10 flex flex-col md:flex-row justify-between items-end gap-4">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Database Nomor</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Daftar Event Perlombaan Saya</p>
        </div>
        
        <a href="create.php" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-[2rem] shadow-xl shadow-blue-200 hover:-translate-y-1 transition transform flex items-center gap-3 group">
            <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center group-hover:bg-white group-hover:text-blue-600 transition">
                <span class="font-black text-lg leading-none">+</span>
            </div>
            <span class="font-black text-xs uppercase tracking-[0.15em]">Buat Nomor Baru</span>
        </a>
    </div>

    <div class="max-w-6xl mx-auto bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden pb-10">
        
        <div class="p-10 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-black uppercase text-sm flex items-center gap-3 text-slate-800 italic">
                <span class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center text-xl">📋</span> 
                List Kategori Lomba (<?= count($events) ?>)
            </h3>
            
            <div class="hidden md:flex gap-3">
                <span class="flex items-center gap-1 text-[9px] font-bold uppercase text-slate-400"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Putra</span>
                <span class="flex items-center gap-1 text-[9px] font-bold uppercase text-slate-400"><span class="w-2 h-2 rounded-full bg-pink-500"></span> Putri</span>
                <span class="flex items-center gap-1 text-[9px] font-bold uppercase text-slate-400"><span class="w-2 h-2 rounded-full bg-purple-500"></span> Mixed</span>
            </div>
        </div>

        <div class="px-6">
            <?php if(empty($events)): ?>
                <div class="flex flex-col items-center justify-center py-20 text-center opacity-50">
                    <div class="text-6xl mb-4 grayscale">🏊</div>
                    <h4 class="font-black text-slate-400 uppercase tracking-widest text-lg">Data Kosong</h4>
                    <p class="text-xs font-bold text-slate-300 mt-1">Anda belum membuat nomor lomba.</p>
                </div>
            <?php else: ?>
                <div class="space-y-2 mt-4">
                    <?php foreach($events as $ev): 
                        // Logic Style Gender
                        if($ev['jenis_kelamin'] == 'L') {
                            $bgBadge = 'bg-blue-50 text-blue-600 border-blue-100';
                            $icon = '👨';
                            $labelGender = 'PUTRA';
                        } elseif($ev['jenis_kelamin'] == 'P') {
                            $bgBadge = 'bg-pink-50 text-pink-600 border-pink-100';
                            $icon = '👩';
                            $labelGender = 'PUTRI';
                        } else {
                            $bgBadge = 'bg-purple-50 text-purple-600 border-purple-100';
                            $icon = '👫';
                            $labelGender = 'MIXED';
                        }
                    ?>
                    
                    <div class="group flex flex-col md:flex-row items-center p-4 rounded-3xl border border-transparent hover:border-slate-200 hover:bg-slate-50 transition-all duration-300 gap-6">
                        
                        <div class="w-20 h-20 bg-slate-100 rounded-[1.5rem] flex flex-col items-center justify-center shrink-0 group-hover:bg-white group-hover:shadow-md transition">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Event</span>
                            <span class="text-3xl font-black text-slate-800 italic"><?= $ev['event_number'] ?></span>
                        </div>

                        <div class="flex-1 text-center md:text-left">
                            <h4 class="text-lg font-black text-slate-800 uppercase italic tracking-tight mb-1 group-hover:text-blue-600 transition">
                                <?= htmlspecialchars($ev['event_name']) ?>
                            </h4>
                            <div class="flex items-center justify-center md:justify-start gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
                                <span><?= $ev['age_group'] ?></span>
                                <span>•</span>
                                <span><?= $ev['distance'] ?> Meter</span>
                            </div>
                        </div>

                        <div class="flex flex-col items-center justify-center w-24">
                            <div class="w-full py-2 rounded-xl border <?= $bgBadge ?> flex flex-col items-center justify-center">
                                <span class="text-lg leading-none mb-1"><?= $icon ?></span>
                                <span class="text-[8px] font-black uppercase tracking-widest"><?= $labelGender ?></span>
                            </div>
                        </div>

                        <div class="pl-0 md:pl-4 border-l-0 md:border-l border-slate-200">
                            <form method="POST" onsubmit="return confirm('Yakin ingin menghapus Nomor <?= $ev['event_number'] ?>? Data seeding terkait akan hilang.')">
                                <input type="hidden" name="delete_id" value="<?= $ev['id'] ?>">
                                <button type="submit" class="w-12 h-12 rounded-2xl bg-white border border-slate-200 text-slate-300 hover:bg-red-50 hover:border-red-200 hover:text-red-500 flex items-center justify-center transition shadow-sm group/btn" title="Hapus">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover/btn:scale-110 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="p-6 bg-slate-50 border-t border-slate-100 text-center">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total <?= count($events) ?> Nomor Lomba Terdaftar</p>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../../views/layout/notification.php'; ?>