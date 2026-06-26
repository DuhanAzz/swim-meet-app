<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stopwatch Sync Dashboard</title>
  <style>
    :root {
      --bg-gradient: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
      --panel-bg: rgba(255, 255, 255, 0.1);
      --text-color: #ffffff;
      --accent-color: #e67e22;
      --success-color: #2ecc71;
      --error-color: #e74c3c;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
    body {
      min-height: 100vh; background: var(--bg-gradient); color: var(--text-color);
      display: flex; justify-content: center; align-items: flex-start; padding: 40px 20px;
    }
    .container {
      width: 100%; max-width: 800px; background: var(--panel-bg); 
      border-radius: 15px; padding: 30px; border: 1px solid rgba(255,255,255,0.1);
      box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    h1 {
      font-size: 2em; text-align: center; margin-bottom: 5px; color: var(--accent-color);
    }
    p.subtitle {
      text-align: center; color: #ccc; margin-bottom: 25px;
    }
    .status-panel {
      display: flex; justify-content: space-between; align-items: center;
      background: rgba(0,0,0,0.3); padding: 20px; border-radius: 10px; margin-bottom: 20px;
    }
    .status-box {
      text-align: center; flex: 1;
    }
    .status-box h3 { font-size: 0.9em; color: #aaa; text-transform: uppercase; margin-bottom: 5px; }
    .status-box .value { font-size: 2em; font-weight: bold; }
    
    .btn {
      padding: 15px 30px; font-size: 1.1em; font-weight: bold; border: none; 
      border-radius: 8px; cursor: pointer; text-transform: uppercase; transition: 0.2s;
    }
    .btn-sync { background: var(--accent-color); color: white; width: 100%; }
    .btn-sync:hover { background: #d35400; }
    .btn-sync:disabled { background: #7f8c8d; cursor: not-allowed; }
    
    .log-container {
      margin-top: 30px; background: rgba(0,0,0,0.5); border-radius: 10px; padding: 15px;
      height: 300px; overflow-y: auto; font-family: 'Courier New', Courier, monospace; font-size: 0.9em;
    }
    .log-entry { margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .log-time { color: #888; font-size: 0.8em; margin-right: 10px; }
    .log-success { color: var(--success-color); }
    .log-error { color: var(--error-color); }
    .log-info { color: #3498db; }
  </style>
</head>
<body>

  <div class="container">
    <h1>🚀 Sync Dashboard</h1>
    <p class="subtitle">Biarkan halaman ini terbuka di tab terpisah untuk sinkronisasi otomatis</p>
    
    <div class="status-panel">
      <div class="status-box" style="border-right: 1px solid rgba(255,255,255,0.1);">
        <h3>Sisa Antrean</h3>
        <div class="value" id="queueCount">0</div>
      </div>
      <div class="status-box">
        <h3>Status Internet</h3>
        <div class="value" id="netStatus" style="color: var(--success-color);">ONLINE</div>
      </div>
    </div>
    
    <button id="btnForceSync" class="btn btn-sync" onclick="processQueue()">🔄 Paksa Sinkronisasi Sekarang</button>
    
    <div class="log-container" id="logBox">
      <div class="log-entry"><span class="log-info">Sistem siap. Mendengarkan antrean dari Stopwatch...</span></div>
    </div>
  </div>

  <script>
    let isSyncing = false;

    function getOfflineQueue() {
        const q = localStorage.getItem('stopwatch_sync_queue');
        return q ? JSON.parse(q) : [];
    }

    function saveOfflineQueue(q) {
        localStorage.setItem('stopwatch_sync_queue', JSON.stringify(q));
    }

    function logMessage(msg, type='info') {
        const box = document.getElementById('logBox');
        const d = new Date();
        const timeStr = `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}:${String(d.getSeconds()).padStart(2,'0')}`;
        
        let colorClass = 'log-info';
        if(type === 'success') colorClass = 'log-success';
        if(type === 'error') colorClass = 'log-error';

        const entry = document.createElement('div');
        entry.className = 'log-entry';
        entry.innerHTML = `<span class="log-time">[${timeStr}]</span> <span class="${colorClass}">${msg}</span>`;
        
        box.insertBefore(entry, box.firstChild); // Insert at top
    }

    function updateUI() {
        const q = getOfflineQueue();
        document.getElementById('queueCount').textContent = q.length;
        
        const btn = document.getElementById('btnForceSync');
        if(q.length === 0 || isSyncing || !navigator.onLine) {
            btn.disabled = true;
            if(isSyncing) btn.textContent = "⏳ SEDANG SYNCING...";
            else if(!navigator.onLine) btn.textContent = "🔴 OFFLINE";
            else btn.textContent = "✅ SEMUA SINKRON";
        } else {
            btn.disabled = false;
            btn.textContent = "🔄 PAKSA SINKRONISASI SEKARANG";
        }

        const netEl = document.getElementById('netStatus');
        if(navigator.onLine) {
            netEl.textContent = "ONLINE";
            netEl.style.color = "var(--success-color)";
        } else {
            netEl.textContent = "OFFLINE";
            netEl.style.color = "var(--error-color)";
        }
    }

    async function processQueue() {
        if(isSyncing || !navigator.onLine) return;
        
        let q = getOfflineQueue();
        if(q.length === 0) return;

        isSyncing = true;
        updateUI();
        logMessage(`Memulai proses sinkronisasi untuk ${q.length} antrean...`, 'info');

        // Kita proses satu per satu dari paling depan (FIFO)
        while(q.length > 0 && navigator.onLine) {
            let item = q[0];
            try {
                const res = await fetch('index.php?action=update_results', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(item.updateData)
                });
                
                const json = await res.json();
                if(json.status === 'success') {
                    logMessage(`[SUKSES] Lomba: ${item.ev} Heat: ${item.ht} terkirim ke DB.`, 'success');
                    // Hapus dari antrean jika sukses
                    q.shift();
                    saveOfflineQueue(q);
                    updateUI();
                } else {
                    logMessage(`[GAGAL] Lomba: ${item.ev} Heat: ${item.ht}. Error: ${json.message}`, 'error');
                    break; // Hentikan loop jika gagal
                }
            } catch(e) {
                logMessage(`[ERROR KONEKSI] Lomba: ${item.ev} Heat: ${item.ht}.`, 'error');
                break; // Hentikan loop jika koneksi terputus
            }
        }

        isSyncing = false;
        updateUI();
        
        if(q.length === 0) {
            logMessage(`✅ Semua antrean berhasil disinkronisasi ke Database!`, 'success');
        }
    }

    // Dengarkan perubahan dari tab lain (Stopwatch)
    window.addEventListener('storage', (e) => {
        if(e.key === 'stopwatch_sync_queue') {
            updateUI();
            logMessage(`Mendeteksi data baru dari Stopwatch. Menunggu giliran sync...`, 'info');
            // Otomatis jalan
            if(!isSyncing && navigator.onLine) {
                setTimeout(processQueue, 500); // Beri jeda sedikit
            }
        }
    });

    window.addEventListener('online', () => {
        updateUI();
        logMessage(`Koneksi internet kembali pulih!`, 'success');
        if(getOfflineQueue().length > 0) processQueue();
    });

    window.addEventListener('offline', () => {
        updateUI();
        logMessage(`Koneksi internet terputus! Sinkronisasi ditunda.`, 'error');
    });

    // Inisialisasi
    window.addEventListener('DOMContentLoaded', () => {
        updateUI();
        if(getOfflineQueue().length > 0) {
            setTimeout(processQueue, 1000);
        }
    });
  </script>
</body>
</html>
