<?php 
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['role'] ?? 'guest'; 
$adminMode = $_SESSION['event_type'] ?? 'Langsung Final'; // Deteksi Mode EO
$page = basename($_SERVER['PHP_SELF']);
$req = $_SERVER['REQUEST_URI']; 

// --- CONFIGURATION STYLE ---
$baseLink = 'flex items-center px-6 py-3 text-slate-400 hover:text-white hover:bg-slate-800/50 transition-all border-l-4 border-transparent group';
$activeLink = 'flex items-center px-6 py-3 text-white bg-slate-800 border-l-4 border-blue-500 shadow-[inset_0px_1px_0px_0px_rgba(255,255,255,0.05)]';

// Link Dashboard dinamis
if ($role == 'master') $dashLink = '/swim-meet/src/master/dashboard.php';
elseif ($role == 'admin') $dashLink = '/swim-meet/src/admin/dashboard.php';
elseif ($role == 'user') $dashLink = '/swim-meet/src/user/dashboard.php';
else $dashLink = '/swim-meet/public/login.php';
?>

<aside id="logo-sidebar" class="fixed top-0 left-0 z-50 w-64 h-screen transition-transform -translate-x-full bg-[#0F172A] sm:translate-x-0 shadow-2xl flex flex-col border-r border-slate-800" aria-label="Sidebar">
   
   <div class="h-32 flex items-center justify-center px-4 bg-[#161e31] border-b border-slate-800 shrink-0">
      <img src="/swim-meet/public/img/logo.png" class="h-20 w-auto object-contain drop-shadow-2xl brightness-110" alt="Logo Web">
   </div>

   <div class="flex-1 overflow-y-auto py-6 space-y-1 custom-scrollbar">
      
      <a href="<?= $dashLink ?>" class="<?= (strpos($page,'dashboard')!==false) ? $activeLink : $baseLink ?>">
         <span class="w-6 text-xl mr-3 text-center opacity-80 group-hover:scale-110 transition">📊</span> 
         <span class="font-bold text-[11px] tracking-widest uppercase">Dashboard</span>
      </a>

      <?php if($role == 'master'): ?>
         <div class="px-8 mt-8 mb-2 text-[10px] font-black text-slate-600 uppercase tracking-widest">Main Control</div>
         
         <a href="/swim-meet/src/master/users/index.php?role=admin" class="<?= (strpos($req,"role=admin")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-center mr-3 text-lg">👔</span> 
            <span class="font-bold text-[11px] tracking-widest uppercase">Admin EO</span>
         </a>
         
         <a href="/swim-meet/src/master/users/index.php?role=user" class="<?= (strpos($req,"role=user")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-center mr-3 text-lg">🏊</span> 
            <span class="font-bold text-[11px] tracking-widest uppercase">User Klub</span>
         </a>

         <a href="/swim-meet/src/master/swimmers/index.php" class="<?= (strpos($req,"master/swimmers/index")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-center mr-3 text-lg">🗃️</span> 
            <span class="font-bold text-[11px] tracking-widest uppercase">Database Atlet</span>
         </a>

         <a href="/swim-meet/src/master/swimmers/history_transfer.php" class="<?= (strpos($req,"history_transfer")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-center mr-3 text-lg flex items-center justify-center">
                <svg class="w-5 h-5 opacity-80" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                   <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 20V7m0 13-4-4m4 4 4-4M16 4v13m0-13 4 4m-4-4-4 4"/>
                </svg>
            </span>
            <span class="font-bold text-[11px] tracking-widest uppercase">Mutasi Klub</span>
         </a>
         <a href="/swim-meet/src/master/maintenance/data_cleanup.php" class="<?= (strpos($req,"maintenance")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-center mr-3 text-lg">🧹</span> 
            <span class="font-bold text-[11px] tracking-widest uppercase">Maintenance</span>
         </a>
         <a href="/swim-meet/src/master/maintenance/system_health.php" class="<?= (strpos($req,"system_health")!==false) ? $activeLink : $baseLink ?>">
             <span class="w-6 text-center mr-3 text-lg">🛡️</span> 
             <span class="font-bold text-[11px] tracking-widest uppercase">System Health</span>
         </a>
         <a href="/swim-meet/src/master/finance/revenue.php" class="<?= (strpos($req,"finance")!==false) ? $activeLink : $baseLink ?>">
             <span class="w-6 text-center mr-3 text-lg">💰</span> 
             <span class="font-bold text-[11px] tracking-widest uppercase">Keuangan</span>
         </a>
         <a href="/swim-meet/src/master/settings/global_config.php" class="<?= (strpos($req,"global_config")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-center mr-3 text-lg">⚙️</span> 
            <span class="font-bold text-[11px] tracking-widest uppercase">Global Config</span>
         </a>

         <div class="px-8 mt-6 mb-2 text-[10px] font-black text-slate-600 uppercase tracking-widest">Global Settings</div>
         <a href="/swim-meet/src/master/settings/public_page.php" class="<?= (strpos($req,"public_page")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-center mr-3 text-lg">⚙️</span> 
            <span class="font-bold text-[11px] tracking-widest uppercase">Landing Page</span>
         </a>
      <?php endif; ?>

      <?php if($role == 'admin'): ?>
         <div class="px-8 mt-8 mb-2 text-[10px] font-black text-slate-600 uppercase tracking-widest">Event Config</div>
         <a href="/swim-meet/src/admin/settings/event_profile.php" class="<?= (strpos($req,"event_profile")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-xl mr-3 text-center opacity-80">⚙️</span>
            <span class="font-bold text-[11px] tracking-widest uppercase">Profil Event</span>
         </a>
         <a href="/swim-meet/src/events/index.php" class="<?= (strpos($req,"events/index")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-xl mr-3 text-center opacity-80">🏆</span>
            <span class="font-bold text-[11px] tracking-widest uppercase">Nomor Lomba</span>
         </a>
         
         <div class="px-8 mt-8 mb-2 text-[10px] font-black text-slate-600 uppercase tracking-widest">Race Management</div>
         <a href="/swim-meet/src/admin/entries/index.php" class="<?= (strpos($req,"entries")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-xl mr-3 text-center opacity-80">📋</span>
            <span class="font-bold text-[11px] tracking-widest uppercase">Verifikasi & Entries</span>
         </a>

         <a href="/swim-meet/src/admin/seeding/index.php" class="<?= (strpos($req,"seeding/index")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-xl mr-3 text-center opacity-80">⚡</span>
            <span class="font-bold text-[11px] tracking-widest uppercase">
                Start List <?= ($adminMode == 'Babak Penyisihan') ? 'Penyisihan' : '' ?>
            </span>
         </a>

         <?php if($adminMode == 'Babak Penyisihan'): ?>
         <a href="/swim-meet/src/admin/seeding/final.php" class="<?= (strpos($req,"seeding/final")!==false) ? $activeLink : $baseLink ?> text-orange-400">
            <span class="w-6 text-xl mr-3 text-center opacity-80 group-hover:scale-110 transition">🏆</span> 
            <span class="font-black text-[10px] tracking-widest uppercase italic">Seeding Final</span>
         </a>
         <?php endif; ?>

         <div class="px-8 mt-8 mb-2 text-[10px] font-black text-slate-600 uppercase tracking-widest">Results & Awards</div>
         <a href="/swim-meet/src/admin/results/index.php" class="<?= (strpos($req,"results/index")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-xl mr-3 text-center opacity-80">⏱️</span>
            <span class="font-bold text-[11px] tracking-widest uppercase">Input Hasil</span>
         </a>

         <a href="/swim-meet/src/admin/results/medal_tally.php" class="<?= (strpos($req,"medal_tally")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-xl mr-3 text-center opacity-80">🥇</span>
            <span class="font-bold text-[11px] tracking-widest uppercase">Rekap Medali</span>
         </a>

         <?php endif; ?>

      <?php if($role == 'user'): ?>
         <div class="px-8 mt-8 mb-2 text-[10px] font-black text-slate-600 uppercase tracking-widest">Club Management</div>
         <a href="/swim-meet/src/user/atlet/index.php" class="<?= (strpos($req,"user/atlet")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-xl mr-3 text-center opacity-80">🏊</span>
            <span class="font-bold text-[11px] tracking-widest uppercase">Atlet Saya</span>
         </a>
         
         <div class="px-8 mt-8 mb-2 text-[10px] font-black text-slate-600 uppercase tracking-widest">Registrations</div>
         <a href="/swim-meet/src/user/kompetisi/explore.php" class="<?= (strpos($req,"kompetisi")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-xl mr-3 text-center opacity-80">🚀</span>
            <span class="font-bold text-[11px] tracking-widest uppercase">Cari Lomba</span>
         </a>
         <a href="/swim-meet/src/user/pembayaran.php" class="<?= (strpos($req,"pembayaran")!==false) ? $activeLink : $baseLink ?>">
            <span class="w-6 text-xl mr-3 text-center opacity-80">💸</span>
            <span class="font-bold text-[11px] tracking-widest uppercase">Status Bayar</span>
         </a>
      <?php endif; ?>

   </div>

   <div class="p-6 border-t border-slate-800 bg-[#0F172A] shrink-0 text-center">
      <p class="text-[9px] text-slate-600 font-bold uppercase tracking-widest">&copy; 2026 SwimMeet System</p>
   </div>

</aside>