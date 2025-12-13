<?php
session_start();
require_once __DIR__ . '/../src/config/database.php';

// 1. CEK LOGIN (Jika sudah login, langsung lempar ke dashboard)
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role == 'master') header("Location: ../src/master/dashboard.php");
    elseif ($role == 'admin') header("Location: ../src/admin/dashboard.php"); // <--- SUDAH DIPERBAIKI
    else header("Location: ../src/user/dashboard.php");
    exit;
}

// 2. AMBIL GAMBAR SLIDER DARI DATABASE
$sliders = $pdo->query("SELECT * FROM hero_slides ORDER BY id DESC")->fetchAll();
if (empty($sliders)) {
    $sliders[] = ['image_path' => 'https://images.unsplash.com/photo-1530549387789-4c1017266635'];
}

// 3. LOGIC LOGIN
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $pass  = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        
        // --- PENGALIHAN SESUAI ROLE ---
        if ($user['role'] == 'master') header("Location: ../src/master/dashboard.php");
        elseif ($user['role'] == 'admin') header("Location: ../src/admin/dashboard.php"); // <--- SUDAH DIPERBAIKI
        else header("Location: ../src/user/dashboard.php");
        exit;
    } else {
        $error = "Akun tidak ditemukan atau password salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SET System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Animasi Background Slider Crossfade */
        .bg-slide {
            position: absolute; inset: 0; 
            background-size: cover; background-position: center;
            opacity: 0; transition: opacity 1.5s ease-in-out;
        }
        .bg-slide.active { opacity: 1; }
        
        /* Logo Floating Animation */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .logo-float { animation: float 6s ease-in-out infinite; }
    </style>
</head>
<body class="bg-white h-screen w-full flex overflow-hidden">

    <div class="hidden lg:flex w-1/2 relative bg-slate-900 items-center justify-center overflow-hidden">
        
        <div id="login-slider-container" class="absolute inset-0 w-full h-full">
            <?php foreach($sliders as $index => $slide): ?>
                <?php 
                    $src = $slide['image_path'];
                    if (strpos($src, 'http') !== 0) $src = $src . "?t=" . time();
                ?>
                <div class="bg-slide <?= $index === 0 ? 'active' : '' ?>" style="background-image: url('<?= $src ?>');"></div>
            <?php endforeach; ?>
        </div>

        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]"></div>
        
        <div class="relative z-10 p-12 logo-float">
            <img src="img/logo.png" class="w-64 h-auto drop-shadow-2xl filter brightness-110" alt="Logo SET System">
        </div>
    </div>

    <div class="w-full lg:w-1/2 flex flex-col justify-center items-center p-6 bg-white overflow-y-auto">
        <div class="w-full max-w-md">
            
            <div class="text-center mb-8 lg:hidden">
                <img src="img/logo.png" class="h-20 w-auto mx-auto mb-4" alt="Logo Mobile">
            </div>

            <div class="text-center mb-10">
                <h2 class="text-3xl font-black text-slate-900 tracking-tight">Selamat Datang</h2>
                <p class="text-slate-500 mt-2 text-sm font-medium">Masuk untuk mengelola kompetisi Anda.</p>
            </div>

            <?php if($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 text-sm flex items-center gap-3 shadow-sm animate-pulse">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                    <span class="font-bold"><?= $error ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                
                <div>
                    <label class="block text-slate-700 font-bold mb-2 text-xs uppercase tracking-wide">Email atau Username</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 group-focus-within:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path></svg>
                        </div>
                        <input type="text" name="email" class="w-full pl-10 pr-4 py-3.5 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none transition font-semibold text-slate-800" placeholder="Masukkan akun Anda" required>
                    </div>
                </div>
                
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-slate-700 font-bold text-xs uppercase tracking-wide">Password</label>
                        <a href="#" class="text-xs text-blue-600 font-bold hover:text-blue-800 transition" tabindex="-1">Lupa sandi?</a>
                    </div>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 group-focus-within:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </div>
                        <input type="password" id="password" name="password" class="w-full pl-10 pr-10 py-3.5 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none transition font-semibold text-slate-800" placeholder="••••••••" required>
                        
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer text-slate-400 hover:text-blue-600 transition" onclick="togglePassword()">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full bg-[#0F172A] hover:bg-blue-700 text-white font-black py-4 rounded-xl shadow-lg shadow-slate-900/20 transition transform hover:-translate-y-0.5 uppercase tracking-wide text-sm flex justify-center items-center gap-2 group">
                    <span>Masuk Dashboard</span>
                    <svg class="w-4 h-4 group-hover:translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <p class="text-slate-500 text-sm mb-4">Ingin mendaftarkan klub?</p>
                <a href="register.php" class="inline-block border-2 border-slate-200 text-slate-700 hover:border-blue-600 hover:text-blue-600 font-bold py-2.5 px-6 rounded-lg transition text-sm">
                    Buat Akun Baru
                </a>
            </div>
            
            <div class="mt-6 text-center">
                <a href="index.php" class="text-xs font-bold text-slate-400 hover:text-slate-600 transition">
                    &larr; Kembali ke Beranda
                </a>
            </div>

        </div>
    </div>

    <script>
        // Toggle Show/Hide Password
        function togglePassword() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('eye-icon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>';
                icon.classList.add('text-blue-600');
            } else {
                pwd.type = 'password';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
                icon.classList.remove('text-blue-600');
            }
        }

        // Logic Slider Background
        let currentSlide = 0;
        const slides = document.querySelectorAll('.bg-slide');
        const totalSlides = slides.length;
        if (totalSlides > 1) {
            setInterval(() => {
                slides[currentSlide].classList.remove('active');
                currentSlide = (currentSlide + 1) % totalSlides;
                slides[currentSlide].classList.add('active');
            }, 5000); // Ganti setiap 5 detik
        }
    </script>
</body>
</html>