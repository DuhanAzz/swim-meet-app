<?php
// FILE: src/admin/seeding/generate_all.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

// 1. AMBIL NOMOR LOMBA (Gunakan nama kolom 'jenis_kelamin')
try {
    $sql = "SELECT id, distance, stroke, age_group, jenis_kelamin 
            FROM event_numbers 
            ORDER BY id ASC"; 
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
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
            <div class="w-20 h-20 border-8 border-indigo-100 border-t-indigo-600 rounded-full animate-spin mx-auto"></div>
            <div class="absolute inset-0 flex items-center justify-center font-black text-indigo-600 text-xl" id="progressText">0%</div>
        </div>

        <h1 class="text-2xl font-black uppercase italic text-slate-800 mb-2">Auto Seeding</h1>
        <p class="text-slate-500 text-sm font-bold mb-6">Sedang menyusun lintasan & menghitung waktu...</p>

        <div class="bg-slate-900 text-left p-4 rounded-xl h-48 overflow-y-auto relative mb-6">
            <div id="logContainer" class="text-[10px] font-mono text-emerald-400 space-y-1">
                <p>> System Ready.</p>
            </div>
        </div>

        <iframe id="processorFrame" style="display:none;"></iframe>
    </div>

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
                window.location.href = 'index.php'; 
            }, 1500);
            return;
        }

        const ev = events[currentIndex];
        const percent = Math.round(((currentIndex + 1) / total) * 100);
        progressText.innerText = percent + "%";
        
        // PERBAIKAN: Gunakan ev.jenis_kelamin
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
            processNext();
        } else {
            addLog("Tidak ada data lomba.");
        }
    };
</script>
</body>
</html>