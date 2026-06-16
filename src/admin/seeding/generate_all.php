<?php
// FILE: src/admin/seeding/generate_all.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$admin_id = $_SESSION['user_id'];

// --- 1. AMBIL ID EVENT TARGET (YANG SEDANG DIBUKA) ---
$targetEventId = $_GET['event_id'] ?? 0;
if ($targetEventId == 0) {
    // Jika tidak ada di URL, ambil event terakhir milik admin ini
    $stmtLastEvt = $pdo->prepare("SELECT id FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtLastEvt->execute([$admin_id]);
    $targetEventId = $stmtLastEvt->fetchColumn() ?: 0;
}

if ($targetEventId == 0) {
    die("Error: Tidak ada event yang aktif atau ditemukan.");
}

// --- 2. AMBIL NOMOR LOMBA HANYA UNTUK EVENT INI ---
try {
    $sql = "SELECT id, distance, stroke, age_group, jenis_kelamin 
            FROM event_numbers 
            WHERE event_id = ?
            ORDER BY CAST(event_number AS UNSIGNED) ASC"; 
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$targetEventId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Processing Seeding...</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center">

    <div class="bg-white p-10 rounded-[2rem] shadow-2xl max-w-md w-full text-center border border-slate-100">
        
        <div class="mb-6 relative">
            <div class="w-20 h-20 border-8 border-indigo-50 border-t-indigo-600 rounded-full animate-spin mx-auto"></div>
            <div class="absolute inset-0 flex items-center justify-center">
                <span class="text-indigo-600 font-black text-sm" id="progressText">0%</span>
            </div>
        </div>

        <h2 class="text-2xl font-black text-slate-800 uppercase italic mb-2">Memproses Seeding</h2>
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-8">Mohon jangan tutup halaman ini</p>

        <div class="bg-slate-900 rounded-2xl p-4 h-48 overflow-y-auto text-left flex flex-col-reverse custom-scrollbar" id="logContainer">
            <p class="text-[10px] font-mono text-emerald-400 opacity-50">> Inisialisasi engine seeding...</p>
        </div>

    </div>

    <iframe id="processorFrame" class="hidden"></iframe>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #334155; border-radius: 10px; }
        #logContainer p { margin-bottom: 4px; font-size: 10px; font-family: monospace; color: #4ade80; }
    </style>

    <script>
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
            addLog("SELESAI! Semua nomor telah di-seeding.");
            setTimeout(() => {
                // Redirect kembali ke event yang tepat
                window.location.href = 'index.php?event_id=<?= $targetEventId ?>'; 
            }, 1500);
            return;
        }

        const ev = events[currentIndex];
        const percent = Math.round(((currentIndex + 1) / total) * 100);
        progressText.innerText = percent + "%";
        
        let eventName = ev.distance + "M " + ev.stroke + " " + ev.jenis_kelamin + " (" + ev.age_group + ")";
        addLog("Processing: " + eventName + "...");

        processorFrame.src = "logic.php?category_id=" + ev.id;

        processorFrame.onload = function() {
            setTimeout(() => {
                currentIndex++;
                processNext();
            }, 300);
        };
    }

    window.onload = function() {
        if(total > 0) {
            addLog("Ditemukan " + total + " nomor lomba untuk di-seeding.");
            processNext();
        } else {
            addLog("TIDAK ADA NOMOR LOMBA UNTUK EVENT INI!");
            progressText.innerText = "100%";
            setTimeout(() => {
                window.location.href = 'index.php?event_id=<?= $targetEventId ?>'; 
            }, 2000);
        }
    };
    </script>
</body>
</html>