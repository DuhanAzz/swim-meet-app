<?php
/**
 * ==============================================================================
 * SISTEM TIMER RENANG - ZERO BASED (0-9)
 * Simpan file ini sebagai: index.php
 * ==============================================================================
 */

// BAGIAN PHP (API & BACKEND)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    ini_set('display_errors', 0);
    
    // GUNAKAN CONFIG DATABASE UTAMA (Otomatis mendeteksi Local/Hosting)
    require_once __DIR__ . '/../../src/config/database.php';
    
    try {
        // Objek $pdo sudah tersedia dari database.php

        if ($_GET['action'] == 'get_events') {
            $stmt = $pdo->query("SELECT id, event_name FROM events ORDER BY id DESC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($_GET['action'] == 'get_races') {
            $eventId = $_GET['event_id'];
            $sql = "SELECT id, event_number, event_name, jenis_kelamin, age_group 
                    FROM event_numbers 
                    WHERE event_id = :eid OR (event_id IS NULL AND organizer_id = :eid)
                    ORDER BY CAST(event_number AS UNSIGNED) ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['eid' => $eventId]);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($_GET['action'] == 'get_heats') {
            $raceId = $_GET['race_id'];
            $sql = "SELECT DISTINCT es.heat_prelim as heat 
                    FROM event_seeding es
                    INNER JOIN event_entries ee ON es.entry_id = ee.id
                    WHERE ee.category_id = ? AND es.heat_prelim IS NOT NULL 
                    ORDER BY es.heat_prelim ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$raceId]);
            $heats = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if(empty($heats)) { $heats = [1]; }
            echo json_encode(['status' => 'success', 'data' => $heats]);
            exit;
        }

        if ($_GET['action'] == 'get_participants') {
            $raceId = $_GET['race_id'];
            $heat   = $_GET['heat'];
            $sql = "SELECT es.lane_prelim as lane, s.nama_atlet as swimmer_name, ee.id as entry_id, es.time_final as final_time, es.id as seeding_id
                    FROM event_entries ee
                    INNER JOIN event_seeding es ON es.entry_id = ee.id
                    LEFT JOIN swimmers s ON ee.swimmer_id = s.id
                    WHERE ee.category_id = :rid AND es.heat_prelim = :heat
                    ORDER BY es.lane_prelim ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['rid' => $raceId, 'heat' => $heat]);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($_GET['action'] == 'update_results') {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            if (!$data) { throw new Exception("Data tidak valid"); }
            $sql = "UPDATE event_seeding SET time_final = :waktu, time_final_ms = :ms WHERE entry_id = :id";
            $stmt = $pdo->prepare($sql);
            $count = 0;
            foreach ($data as $row) {
                if (!empty($row['id']) && !empty($row['time'])) {
                    // Hitung MS
                    $timeStr = trim($row['time']);
                    $parts = explode(':', $timeStr);
                    if (count($parts) == 3) {
                        // format MM:SS:mm
                        $ms = ((int)$parts[0] * 60 * 1000) + ((int)$parts[1] * 1000) + ((int)$parts[2] * 10);
                    } else if (count($parts) == 2) {
                        $ms = ((int)$parts[0] * 1000) + ((int)$parts[1] * 10);
                    } else {
                        $ms = 99999999;
                    }

                    // Ubah format string agar standard MM:SS.mm
                    $standardTimeStr = str_replace(':', '.', $timeStr);
                    $firstColonPos = strpos($timeStr, ':');
                    if ($firstColonPos !== false) {
                        $standardTimeStr = substr_replace($standardTimeStr, ':', $firstColonPos, 1);
                    }

                    $stmt->execute(['waktu' => $standardTimeStr, 'ms' => $ms, 'id' => $row['id']]);
                    $count++;
                }
            }
            echo json_encode(['status' => 'success', 'updated' => $count]);
            exit;
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Timer Renang (Format 0-9)</title>
  <style>
    :root {
      --bg-gradient: linear-gradient(135deg, #1e1e2f, #252540, #1a1a2e);
      --panel-bg: rgba(255, 255, 255, 0.08);
      --text-color: #ffffff;
      --accent-color: #00e5ff;
      --timer-bg: #000000;
      --timer-text: #00ffcc;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
    body {
      min-height: 100vh; background: var(--bg-gradient); color: var(--text-color);
      display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding: 10px 20px;
    }
    .main-container { display: flex; width: 100%; max-width: 1400px; gap: 15px; height: auto; }
    
    .top-nav {
      width: 100%; max-width: 1400px; margin-bottom: 10px; 
      background: var(--panel-bg); border-radius: 15px; border: 1px solid rgba(255,255,255,0.1);
      overflow: hidden; flex-shrink: 0;
    }
    .top-nav-header {
      padding: 10px 20px; cursor: pointer; display: flex; justify-content: space-between;
      align-items: center; font-weight: bold; font-size: 1.1em; background: rgba(0,0,0,0.3);
      transition: background 0.2s; color: var(--accent-color);
    }
    .top-nav-header:hover { background: rgba(0,0,0,0.5); }
    .top-nav-content {
      max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out;
    }
    .top-nav-content.open { max-height: 500px; }
    .nav-cards-container {
      display: flex; gap: 15px; padding: 15px; align-items: flex-start;
    }
    .nav-cards-container .control-card { flex: 1; margin: 0; }
    .timer-section { flex: 3; background: var(--panel-bg); border-radius: 15px; padding: 15px; border: 1px solid rgba(255,255,255,0.1); }
    
    .stopwatch-row {
      display: flex; align-items: center; background: rgba(0, 0, 0, 0.3);
      padding: 4px 10px; border-radius: 8px; margin-bottom: 6px;
      border-left: 5px solid transparent; transition: 0.3s;
    }
    .stopwatch-row.active-lane { border-left-color: #00ffcc; background: rgba(0, 255, 204, 0.08); }
    .stopwatch-row.finished-lane { border-left-color: #f1c40f; background: rgba(241, 196, 15, 0.1); }
    .stopwatch-row.disabled-lane { opacity: 0.4; filter: grayscale(80%); }
    
    .check-col { margin-right: 10px; }
    .lane-checkbox { transform: scale(1.3); cursor: pointer; accent-color: var(--accent-color); }
    .lane-info { flex: 1; text-align: left; }
    .lane-number { font-weight: bold; font-size: 1em; color: var(--accent-color); }
    .swimmer-name { font-size: 0.8em; color: #ddd; font-style: italic; display: block; text-transform: uppercase;}
    
    .time-display {
      font-family: 'Courier New', monospace; font-size: 1.6em; background: var(--timer-bg);
      color: var(--timer-text); padding: 3px 15px; border-radius: 6px;
      margin: 0 10px; min-width: 140px; text-align: center; letter-spacing: 2px;
    }
    .finished-time { color: #f1c40f; }
    .btn-stop-small { background: #ff4d4d; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 0.8em; }

    .control-section { flex: 1; display: flex; flex-direction: column; gap: 10px; }
    .control-card { background: var(--panel-bg); padding: 15px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); display: flex; flex-direction: column; gap: 8px; }
    h2 { font-size: 1.1em; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 5px; margin-bottom: 2px; color: #fff; }
    label { font-size: 0.85em; color: #bbb; margin-top: 2px; }
    select, input { background: rgba(0,0,0,0.4); border: 1px solid #444; color: white; padding: 6px 10px; border-radius: 5px; font-size: 0.95em; width: 100%; }
    .btn { padding: 10px; font-size: 1em; font-weight: bold; border: none; border-radius: 8px; cursor: pointer; text-transform: uppercase; margin-top:3px; width: 100%; }
    .btn-start { background: #2ecc71; color: white; }
    .btn-reset { background: #f1c40f; color: black; }
    .btn-save { background: #17a2b8; color: white; }
    .btn-connect { background: #6c757d; color: white; }
    .btn-connect.connected { background: #2ecc71; }
    .heat-control { display: flex; gap: 5px; }
    .btn-next { background: var(--accent-color); border: none; border-radius: 5px; cursor: pointer; font-weight: bold; width: 60px; color:#000;}
  </style>
</head>
<body>
  <div class="top-nav">
    <div class="top-nav-header" onclick="toggleTopNav()">
      <span>⚙️ Pengaturan Lanjutan (Penyimpanan & Hardware)</span>
      <span id="navIcon">▼</span>
    </div>
    <div class="top-nav-content" id="topNavContent">
      <div class="nav-cards-container">
        
        <div class="control-card">
          <h2>💾 Penyimpanan Lokal (100% Offline)</h2>
          <button id="btnSelFolder" class="btn" style="background: #34495e; color: white; font-size: 0.9em; padding: 10px;" onclick="selectBackupFolder()">📁 Pilih Folder Backup Teks</button>
          <div id="folderStatus" style="font-size: 0.75em; color: #aaa; text-align: center; margin-top: 5px;">Folder belum dipilih</div>
          
          <div style="padding:8px; border-radius:5px; background: rgba(241, 196, 15, 0.1); border: 1px solid #f1c40f; color: #f1c40f; text-align:center; font-weight:bold; margin-top: 10px; font-size: 0.8em;">
              Data di Antrean Lokal: <span id="syncCount">0</span>
          </div>
          <a href="sync.php" target="_blank" class="btn" style="background: #e67e22; color: white; margin-top: 10px; text-decoration: none; text-align: center; display: block; font-size: 0.9em;">🚀 Buka Sync Dashboard</a>
        </div>

        <div class="control-card">
          <h2>🔌 Hardware</h2>
          <button id="connectBtn" class="btn btn-connect">Connect Arduino</button>
          <div style="font-size:0.8em; color:#aaa; margin-top:5px;">
              Mode 0-9: <br>
              <code>{"lane": 0, "time": 45.12}</code> = Lane 0
          </div>
        </div>

      </div>
    </div>
  </div>

  <div class="main-container">
    <div class="timer-section" id="stopwatchContainer"></div>

    <div class="control-section">
      <div class="control-card">
        <h2>🎛️ Setup Lomba</h2>
        <label>1. Pilih Kejuaraan:</label>
        <select id="eventSelect" onchange="onEventChange()"><option>Loading...</option></select>
        <label>2. Pilih Nomor Lomba:</label>
        <select id="raceSelect" onchange="onRaceChange()"><option>-- Pilih Kejuaraan --</option></select>
        <input type="text" id="eventNameDisplay" value="-" readonly style="color: #f1c40f; border:none; background:transparent;" />
        <label>3. Pilih Heat:</label>
        <div class="heat-control">
          <select id="heatSelect" style="flex:1" onchange="loadDatabaseData()"></select>
          <button class="btn-next" onclick="nextHeat()">Next</button>
        </div>
        <button class="btn" style="background: #e91e63; color:white; margin-top:15px;" onclick="loadDatabaseData()">📥 LOAD DATA ATLET</button>
      </div>

      <div class="control-card">
        <h2>⏱️ Kontrol Timer</h2>
        <button class="btn btn-start" onclick="startAll()">START (Spasi)</button>
        <button class="btn btn-reset" onclick="resetAll()">RESET (R)</button>
        <button class="btn btn-save" onclick="saveResults()">💾 SAVE & DB</button>
      </div>
    </div>
  </div>

  <script>
    const stopwatches = []; const intervals = [];
    const container = document.getElementById("stopwatchContainer");

    // ============================================
    // 1. GENERATE TAMPILAN (LINTASAN 0 - 9)
    // ============================================
    for (let i = 0; i < 10; i++) {
      const row = document.createElement("div"); 
      row.className = "stopwatch-row disabled-lane"; 
      row.id = "row" + i; 
      
      // === UBAH DISINI: Label Lintasan dimulai dari 0 ===
      const labelLane = i; 

      row.innerHTML = `
        <div class="check-col">
            <input type="checkbox" id="chk${i}" class="lane-checkbox" onchange="toggleLane(${i})">
        </div>
        <div class="lane-info">
            <div class="lane-number">LINTASAN ${labelLane}</div> 
            <span id="swimmer${i}" class="swimmer-name">- KOSONG -</span>
        </div>
        <div class="time-display" id="sw${i}">00:00:00</div>
        <button class="btn-stop-small" onclick="stopOne(${i})">STOP</button>
      `;
      container.appendChild(row);
      stopwatches.push({ id: i, startTime: null, elapsed: 0, running: false, db_entry_id: null });
      intervals.push(null);
    }

    function toggleTopNav() {
        const content = document.getElementById("topNavContent");
        const icon = document.getElementById("navIcon");
        content.classList.toggle("open");
        icon.textContent = content.classList.contains("open") ? "▲" : "▼";
    }

    function toggleLane(i) {
        const chk = document.getElementById("chk" + i);
        const row = document.getElementById("row" + i);
        if(chk.checked) row.classList.remove("disabled-lane");
        else row.classList.add("disabled-lane");
    }

    function formatTime(ms) {
      const min = Math.floor(ms / 60000);
      const sec = Math.floor((ms % 60000) / 1000);
      const msec = Math.floor((ms % 1000) / 10);
      return `${String(min).padStart(2,"0")}:${String(sec).padStart(2,"0")}:${String(msec).padStart(2,"0")}`;
    }

    function startAll() {
      stopwatches.forEach((sw, i) => {
        const isChecked = document.getElementById("chk"+i).checked;
        const isFinished = document.getElementById("sw"+i).classList.contains('finished-time');
        
        if (!sw.running && isChecked && !isFinished) {
          sw.startTime = Date.now() - sw.elapsed; 
          sw.running = true;
          intervals[i] = setInterval(() => {
            sw.elapsed = Date.now() - sw.startTime;
            document.getElementById("sw" + i).textContent = formatTime(sw.elapsed);
          }, 50);
        }
      });
    }

    function stopOne(i) {
      if(i < 0 || i > 9) return;
      const sw = stopwatches[i];
      if (sw.running) { 
          clearInterval(intervals[i]); 
          sw.running = false; 
          sw.elapsed = Date.now() - sw.startTime; 
          document.getElementById("sw" + i).textContent = formatTime(sw.elapsed);
          document.getElementById("sw" + i).classList.add('finished-time');
          document.getElementById("row" + i).classList.add('finished-lane');
      }
    }

    function resetAll() {
      if(!confirm("Yakin Reset Timer?")) return;
      stopwatches.forEach((sw, i) => {
        clearInterval(intervals[i]); sw.running = false; sw.elapsed = 0;
        document.getElementById("sw" + i).textContent = "00:00:00";
        document.getElementById("sw" + i).classList.remove('finished-time');
        document.getElementById("row" + i).classList.remove('finished-lane');
      });
    }

    // ============================================
    // OFFLINE QUEUE (PRODUCER) & FILE SYSTEM
    // ============================================
    let backupDirHandle = null;

    function getOfflineQueue() {
        const q = localStorage.getItem('stopwatch_sync_queue');
        return q ? JSON.parse(q) : [];
    }

    function saveOfflineQueue(q) {
        localStorage.setItem('stopwatch_sync_queue', JSON.stringify(q));
        updateQueueUI();
    }

    function updateQueueUI() {
        const q = getOfflineQueue();
        const countEl = document.getElementById('syncCount');
        if(countEl) countEl.textContent = q.length;
    }

    // Dengarkan jika ada perubahan dari tab Sync
    window.addEventListener('storage', (e) => {
        if(e.key === 'stopwatch_sync_queue') {
            updateQueueUI();
        }
    });

    window.addEventListener('DOMContentLoaded', () => {
        fetchEvents();
        updateQueueUI();
    });

    async function selectBackupFolder() {
        try {
            backupDirHandle = await window.showDirectoryPicker({ mode: 'readwrite' });
            document.getElementById('folderStatus').textContent = "✅ Folder terpilih: " + backupDirHandle.name;
            document.getElementById('folderStatus').style.color = "#2ecc71";
        } catch(e) {
            console.error(e);
            document.getElementById('folderStatus').textContent = "❌ Pemilihan folder dibatalkan / tidak didukung";
            document.getElementById('folderStatus').style.color = "#e74c3c";
        }
    }

    async function saveFileSilently(filename, content) {
        if(backupDirHandle) {
            try {
                const fileHandle = await backupDirHandle.getFileHandle(filename, { create: true });
                const writable = await fileHandle.createWritable();
                await writable.write(content);
                await writable.close();
                return true;
            } catch(e) {
                console.error("Gagal save silent:", e);
                return false;
            }
        }
        return false;
    }

    async function fetchEvents() {
      const select = document.getElementById('eventSelect');
      try {
        const res = await fetch('?action=get_events');
        const json = await res.json();
        select.innerHTML = '<option value="">-- Pilih Kejuaraan --</option>';
        if(json.status === 'success') {
          json.data.forEach(e => {
            const opt = document.createElement('option'); opt.value = e.id; opt.textContent = e.event_name; select.appendChild(opt);
          });
        }
      } catch (e) { console.error(e); }
    }

    async function onEventChange() {
        const eid = document.getElementById('eventSelect').value;
        const raceSelect = document.getElementById('raceSelect');
        const heatSelect = document.getElementById('heatSelect');
        raceSelect.innerHTML = '<option>Loading...</option>';
        heatSelect.innerHTML = '';
        if(!eid) { raceSelect.innerHTML = '<option>-- Pilih Kejuaraan Dulu --</option>'; return; }
        try {
            const res = await fetch(`?action=get_races&event_id=${eid}`);
            const json = await res.json();
            raceSelect.innerHTML = '<option value="">-- Pilih Nomor Lomba --</option>';
            if(json.status === 'success' && json.data.length > 0) {
                json.data.forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r.id; 
                    opt.textContent = `#${r.event_number} - ${r.event_name} (${r.jenis_kelamin || '?'})`;
                    raceSelect.appendChild(opt);
                });
            } else { raceSelect.innerHTML = '<option value="">Tidak ada data lomba</option>'; }
        } catch (e) { console.error(e); }
    }

    async function onRaceChange() {
        const sel = document.getElementById('raceSelect');
        const raceId = sel.value;
        const heatSelect = document.getElementById('heatSelect');
        if(raceId) {
            document.getElementById('eventNameDisplay').value = sel.options[sel.selectedIndex].text;
            heatSelect.innerHTML = '<option>Loading...</option>';
            try {
                const res = await fetch(`?action=get_heats&race_id=${raceId}`);
                const json = await res.json();
                heatSelect.innerHTML = '';
                if(json.status === 'success' && json.data.length > 0) {
                    json.data.forEach(h => {
                        const opt = document.createElement('option');
                        opt.value = h; opt.textContent = "Heat " + h; heatSelect.appendChild(opt);
                    });
                    loadDatabaseData(); 
                } else { heatSelect.innerHTML = '<option value="1">Heat 1</option>'; }
            } catch(e) { heatSelect.innerHTML = '<option value="1">Heat 1</option>'; }
        }
    }

    function nextHeat() {
        const h = document.getElementById('heatSelect');
        if(h.selectedIndex < h.options.length - 1) {
            h.selectedIndex += 1;
            loadDatabaseData();
        } else { alert("Ini Heat terakhir."); }
    }

    async function loadDatabaseData() {
        const rid = document.getElementById('raceSelect').value;
        const heat = document.getElementById('heatSelect').value;
        if(!rid) return;

        for(let i=0; i<10; i++) {
            document.getElementById('swimmer'+i).textContent = "- KOSONG -";
            document.getElementById('row'+i).classList.remove('active-lane', 'finished-lane');
            document.getElementById("sw"+i).classList.remove('finished-time');
            stopwatches[i].db_entry_id = null;
            stopwatches[i].elapsed = 0;
            stopwatches[i].running = false;
            document.getElementById("sw"+i).textContent = "00:00:00";
            document.getElementById("chk"+i).checked = false;
            toggleLane(i); 
        }

        try {
            const res = await fetch(`?action=get_participants&race_id=${rid}&heat=${heat}`);
            const json = await res.json();
            if(json.status === 'success') {
                json.data.forEach(e => {
                    // === LOGIKA DB ===
                    // Jika di DB tertulis lane "1", maka akan masuk ke Index 1 (Lintasan 1)
                    // Jika user input lane "0" di DB, akan masuk ke Index 0
                    let dbLane = parseInt(e.lane); 
                    
                    // Kita tidak kurangi 1 lagi, anggap DB juga simpan 0-9
                    let idx = dbLane; 

                    if(idx >= 0 && idx < 10) {
                        document.getElementById('swimmer'+idx).textContent = e.swimmer_name || "Tanpa Nama";
                        document.getElementById('row'+idx).classList.add('active-lane');
                        stopwatches[idx].db_entry_id = e.entry_id;
                        document.getElementById("chk"+idx).checked = true;
                        toggleLane(idx);
                        if(e.final_time && e.final_time.length > 4) {
                            let displayTime = e.final_time.replace(/\./g, ':');
                            document.getElementById("sw"+idx).textContent = displayTime;
                            document.getElementById("sw"+idx).classList.add('finished-time');
                            document.getElementById('row'+idx).classList.add('finished-lane');
                        }
                    }
                });
            }
        } catch (e) { console.error(e); }
    }

    async function saveResults() {
        if(!confirm("Simpan Hasil & Update Database?")) return;
        const ev = document.getElementById('eventNameDisplay').value.replace(/[^a-zA-Z0-9]/g, "_");
        const ht = document.getElementById('heatSelect').value;
        let txt = `HASIL LOMBA: ${ev} HEAT ${ht}\n\n`;
        let updateData = [];

        stopwatches.forEach((sw, i) => {
            const laneLabel = i; 
            const nm = document.getElementById('swimmer'+i).textContent;
            const tm = document.getElementById("sw"+i).textContent;
            const isChecked = document.getElementById("chk"+i).checked;
            
            if(isChecked || sw.db_entry_id) {
                txt += `Lintasan ${laneLabel} [${nm}]: ${tm}\n`;
                if(sw.db_entry_id) {
                    updateData.push({ id: sw.db_entry_id, time: tm });
                }
            }
        });

        // Tambahkan timestamp di nama file agar unik jika save berulang kali
        const filename = `Hasil_${ev}_H${ht}_${Date.now()}.txt`;

        let savedSilently = false;
        if(backupDirHandle) {
            savedSilently = await saveFileSilently(filename, txt);
        }
        
        if(!savedSilently) {
            const a = document.createElement("a");
            a.href = URL.createObjectURL(new Blob([txt], {type: "text/plain"}));
            a.download = filename; 
            a.click();
        }

        if(updateData.length > 0) {
            // HANYA MASUKKAN KE ANTREAN LOKAL (PRODUCER MODE)
            // TIDAK ADA FETCH INTERNET SAMA SEKALI
            let q = getOfflineQueue();
            q.push({ 
                id: Date.now().toString() + Math.floor(Math.random()*1000), // ID unik antrean
                timestamp: Date.now(), 
                ev: ev, 
                ht: ht, 
                updateData: updateData 
            });
            saveOfflineQueue(q);
            
            // Beri notifikasi visual kecil (bisa flash color)
            const btnSave = document.querySelector('.btn-save');
            const oldHtml = btnSave.innerHTML;
            btnSave.innerHTML = "✅ TERSIMPAN LOKAL!";
            btnSave.style.background = "#2ecc71";
            setTimeout(() => {
                btnSave.innerHTML = oldHtml;
                btnSave.style.background = "#17a2b8";
            }, 1500);
        } else {
            alert("Tidak ada waktu atlet yang terisi. File backup teks tetap terbuat.");
        }
    }

    // ============================================
    // KEYBOARD HANDLER (0=0, 1=1... 9=9)
    // ============================================
    document.addEventListener("keydown", (e) => {
        if(e.code === "Space") { e.preventDefault(); startAll(); }
        if(e.key === "r" || e.key === "R") resetAll();

        if(e.key >= "0" && e.key <= "9") {
            // === LOGIKA KEYBOARD SESUAI REQUEST ===
            // Tekan '0' -> stopOne(0) -> Lintasan 0
            // Tekan '1' -> stopOne(1) -> Lintasan 1
            let keyNum = parseInt(e.key);
            stopOne(keyNum);
        }
    });

    // ============================================
    // ARDUINO HANDLER (0=0, 1=1... 9=9)
    // ============================================
    let port, reader, keepReading=false;
    const btnCon = document.getElementById("connectBtn");

    async function connectArduino() {
      if(port && port.readable) { 
          keepReading=false; if(reader) reader.cancel(); if(port) port.close(); 
          port=null; btnCon.classList.remove("connected"); return; 
      }
      try { 
          port = await navigator.serial.requestPort(); 
          await port.open({baudRate:9600}); 
          keepReading=true; btnCon.classList.add("connected"); readLoop(); 
      } catch(e) { alert("Koneksi Gagal: " + e); }
    }

    async function readLoop() {
      const dec = new TextDecoderStream(); 
      port.readable.pipeTo(dec.writable); 
      reader = dec.readable.getReader();
      let buff = "";
      try { 
          while(keepReading) { 
              const {value, done} = await reader.read(); 
              if(done) break; 
              if(value) { 
                  buff += value; 
                  let lines = buff.split("\n"); 
                  buff = lines.pop(); 
                  for(let l of lines) handleData(l.trim()); 
              } 
          } 
      } catch(e){}
    }

    // === GANTI FUNGSI handledata DENGAN INI (VERSI FIX -1) ===

function handleData(line) {
    try { 
        // Abaikan pesan startup jika ada
        if(line.includes("Arduino")) return;

        const d = JSON.parse(line); 
        
        // 1. LOGIKA START
        if(d.event === "START") {
            startAll(); 
        }
        // 2. LOGIKA STOP (DENGAN KOREKSI)
        else if(d.lane !== undefined && d.time) { 
            
            const angkaDariArduino = parseInt(d.lane);
            
            // === RUMUS PERBAIKAN: DIKURANGI 1 ===
            // Jika Arduino kirim 5 (tombol 4) -> 5 - 1 = 4. 
            // Jika Arduino kirim 1 (tombol 0) -> 1 - 1 = 0.
            const targetIndex = angkaDariArduino - 1; 

            // Debugging di console biar yakin
            console.log("Arduino kirim:", angkaDariArduino, "--> Diperbaiki jadi Lane:", targetIndex);
            
            // Pastikan hasil pengurangan valid (antara 0 sampai 9)
            if(targetIndex >= 0 && targetIndex <= 9) {
                stopOne(targetIndex); 
                
                // Update waktu di layar
                if(stopwatches[targetIndex]) {
                    stopwatches[targetIndex].elapsed = d.time * 1000; 
                    document.getElementById("sw" + targetIndex).textContent = formatTime(stopwatches[targetIndex].elapsed); 
                }
            }
        }
    } catch(e) {
        // Error JSON yang tidak lengkap diabaikan saja
    }
}

    btnCon.addEventListener("click", connectArduino);
  </script>
</body>
</html>