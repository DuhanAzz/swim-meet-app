<?php
// FILE: public/fix_database_times.php
require_once __DIR__ . '/../src/config/database.php';

echo "<h1>🛠️ MEMPERBAIKI FORMAT WAKTU DATABASE...</h1>";

// Fungsi Konversi: "00:01:05.50" -> 65500 ms
function timeToMs($timeStr) {
    if (empty($timeStr) || $timeStr == 'NT' || $timeStr == '00:00.00') return 999999999; // NT ditaruh paling bawah
    
    // Pecah string (Menit:Detik.Milidetik) atau (Menit:Detik)
    // Format database bapak: 00:04:58 (Sepertinya Menit:Detik:Mili atau Menit:Detik?)
    // Asumsi standar renang: MM:SS.MS
    
    // Cek format: Apakah ada titik (.) atau titik dua (:)
    $parts = preg_split('/[:.]/', $timeStr);
    
    $menit = isset($parts[0]) ? (int)$parts[0] : 0;
    $detik = isset($parts[1]) ? (int)$parts[1] : 0;
    $mili  = isset($parts[2]) ? (int)$parts[2] : 0;

    // Hitung total milidetik
    return ($menit * 60 * 1000) + ($detik * 1000) + ($mili * 10); 
}

try {
    // 1. Ambil semua data
    $stmt = $pdo->query("SELECT id, entry_time, time_prelim FROM event_entries");
    $entries = $stmt->fetchAll();
    
    $count = 0;
    
    // 2. Loop dan Update
    $sql = "UPDATE event_entries SET entry_time_ms = :ems, time_prelim_ms = :pms WHERE id = :id";
    $updateStmt = $pdo->prepare($sql);

    foreach ($entries as $row) {
        $ems = timeToMs($row['entry_time']);
        $pms = ($row['time_prelim']) ? timeToMs($row['time_prelim']) : NULL;

        $updateStmt->execute([
            ':ems' => $ems,
            ':pms' => $pms,
            ':id'  => $row['id']
        ]);
        $count++;
    }

    echo "<h3 style='color:green'>✅ SUKSES! $count data berhasil diperbarui ke format Milidetik.</h3>";
    echo "<p>Sekarang sorting dan ranking akan berjalan SUPER CEPAT.</p>";
    echo "<a href='index.php'>Kembali ke Home</a>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>❌ ERROR: " . $e->getMessage() . "</h3>";
}
?>      <a href="/src/admin/results/index.php" class="<?= (strpos($req,"results/index")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-xl mr-3 text-center opacity-80">📊</span>
            <span class="font-bold text-[11px] tracking-widest uppercase">Hasil Lomba</span>
         </a>
         <a href="/src/admin/awards/index.php" class="<?= (strpos($req,"awards/index")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-xl mr-3 text-center opacity-80">🏅</span>
            <span class="font-bold text-[11px] tracking-widest uppercase">Penghargaan</span>
         </a>
      <?php endif; ?>

      <?php if($role == 'master'): ?>
         <div class="px-8 mt-8 mb-2 text-[10px] font-black text-slate-600 uppercase tracking-widest">Master Data</div>
         <a href="/src/master/clubs/index.php" class="<?= (strpos($req,"clubs")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-center mr-3 text-lg">🏊‍♂️</span> 
            <span class="font-bold text-[11px] tracking-widest uppercase">Data Klub</span>
         </a>
         <a href="/src/master/swimmers/index.php" class="<?= (strpos($req,"swimmers")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-center mr-3 text-lg">🤽‍♀️</span> 
            <span class="font-bold text-[11px] tracking-widest uppercase">Data Atlet</span>
         </a>
         <a href="/src/master/club_mutations/index.php" class="<?= (strpos($req,"club_mutations")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-center mr-3 text-lg">🔄</span>