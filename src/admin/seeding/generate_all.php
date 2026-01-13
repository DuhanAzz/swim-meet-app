<?php
// src/admin/seeding/generate_all.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$admin_id = $_SESSION['user_id'];

// 1. AMBIL DATA DARI TABEL YANG BENAR (event_numbers)
// Kita hanya butuh ID dan Nama Eventnya
try {
    $sql = "SELECT id, event_number, event_name, distance, stroke, age_group 
            FROM event_numbers 
            WHERE organizer_id = ? 
            ORDER BY CAST(event_number AS UNSIGNED) ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$admin_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Jika tidak ada event
if (empty($events)) {
    echo "<script>alert('Belum ada nomor lomba untuk digenerate!'); window.location='index.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Seeding...</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center">

    <div class="bg-white p-10 rounded-[2rem] shadow-2xl max-w-md w-full text-center border border-slate-100">
        
        <div class="mb-6 relative">
            <div class="w-20 h-20 border-8 border-indigo-100 border-t-indigo-600 rounded-full animate-spin mx-auto"></div>
            <div class="absolute inset-0 flex items-center justify-center font-black text-indigo-600 text-xl" id="progressText">0%</div>
        </div>

        <h1 class="text-2xl font-black uppercase italic text-slate-800 mb-2">Auto Seeding</h1>
        <p class="text-slate-500 text-sm font-bold mb-6">Sedang menyusun lintasan...</p>

        <div class="bg-slate-900 text-left p-4 rounded-xl h-32 overflow-hidden relative mb-6">
            <div id="logContainer" class="text-[10px] font-mono text-emerald-400 space-y-1">
                <p>> Initializing system...</p>
            </div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-900 to-transparent pointer-events-none"></div>
        </div>

        <iframe id="processorFrame" style="display:none;"></iframe>

        <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">JANGAN TUTUP HALAMAN INI</p>
    </div>

<script>
    // Data Event dari PHP dikirim ke JS
    const events = <?= json_encode($events) ?>;
    let currentIndex = 0;
    const total = events.length;
    const logContainer = document.getElementById('logContainer');
    const progressText = document.getElementById('progressText');
    const processorFrame = document.getElementById('processorFrame');

    function addLog(msg) {
        const p = document.createElement('p');
        p.innerText = "> " + msg;
        logContainer.prepend(p);
    }

    function processNext() {
        if (currentIndex >= total) {
            addLog("SELESAI! Mengalihkan...");
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1000);
            return;
        }

        const ev = events[currentIndex];
        const percent = Math.round(((currentIndex) / total) * 100);
        
        progressText.innerText = percent + "%";
        addLog("Processing: #" + ev.event_number + " " + ev.event_name + "...");

        // Panggil file logic.php untuk event ini via IFRAME
        // Kita tambahkan parameter 'redirect=0' (opsional, jaga-jaga kalau logic.php support)
        processorFrame.src = "logic.php?category_id=" + ev.id + "&auto_mode=1";

        // Beri waktu jeda atau tunggu load (disini kita pakai timer sederhana agar aman)
        // Jika logic.php anda melakukan redirect otomatis, iframe akan menangkapnya jadi halaman utama aman.
        processorFrame.onload = function() {
            setTimeout(() => {
                currentIndex++;
                processNext();
            }, 500); // Jeda 0.5 detik per event agar database tidak choke
        };
    }

    // Mulai Proses
    window.onload = processNext;
</script>

</body>
</html>