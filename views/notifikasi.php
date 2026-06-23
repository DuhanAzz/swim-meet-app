<?php
// Cek jika session belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['flash_message'])):
    // Ambil data
    $msg = $_SESSION['flash_message'];
    $type = $_SESSION['flash_type'] ?? 'success'; // Default ke success jika tidak di-set
    
    // Hapus session agar flash message hanya muncul sekali
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
    
    // Tentukan warna berdasarkan tipe
    $bgColor = ($type === 'error') ? 'bg-red-500' : (($type === 'warning') ? 'bg-amber-500' : 'bg-green-500');
    $icon = ($type === 'error') ? '✖' : (($type === 'warning') ? '⚠' : '✔');
?>

<!-- UI Notifikasi -->
<div id="flash-notification" class="fixed top-24 right-5 z-[100] flex items-center p-4 mb-4 text-white rounded-lg shadow-xl <?= $bgColor ?> transform transition-all duration-500 translate-y-0 opacity-100" role="alert">
    <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-lg bg-white/20">
        <span class="text-lg font-bold"><?= $icon ?></span>
    </div>
    <div class="ms-3 text-sm font-bold pr-8">
        <?= htmlspecialchars($msg) ?>
    </div>
    <button type="button" onclick="closeFlashNotification()" class="ms-auto -mx-1.5 -my-1.5 bg-transparent text-white/70 hover:text-white rounded-lg p-1.5 inline-flex items-center justify-center h-8 w-8 transition">
        <span class="sr-only">Tutup</span>
        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
        </svg>
    </button>
</div>

<!-- Script untuk menghilangkan notifikasi secara otomatis dalam 3 detik -->
<script>
    function closeFlashNotification() {
        const el = document.getElementById('flash-notification');
        if (el) {
            el.classList.remove('translate-y-0', 'opacity-100');
            el.classList.add('-translate-y-4', 'opacity-0');
            setTimeout(() => el.remove(), 500); // Hapus dari DOM setelah animasi selesai
        }
    }

    // Hilang otomatis dalam 3 detik
    setTimeout(() => {
        closeFlashNotification();
    }, 3000);
</script>

<?php endif; ?>
