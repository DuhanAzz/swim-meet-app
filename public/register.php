<?php
session_start();
require_once __DIR__ . '/../src/config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $userType = 'user'; // Default daftar sebagai user klub

    // Cek Email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $error = "Email sudah terdaftar.";
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        // Buat username simple dari nama
        $username = strtolower(str_replace(' ', '', $nama)) . rand(100,999);
        
        $ins = $pdo->prepare("INSERT INTO users (username, nama_lengkap, email, password, role, account_status) VALUES (?, ?, ?, ?, ?, 'pending')");
        if($ins->execute([$username, $nama, $email, $hash, $userType])) {
            $waNumber = $pdo->query("SELECT contact_wa FROM site_settings WHERE id=1")->fetchColumn() ?: '6281993189787';
            $success = true;
        } else {
            $error = "Gagal mendaftar.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - SET System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
</head>
<body class="bg-slate-50 h-screen flex items-center justify-center font-[Inter]">

    <div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-xl border border-slate-200">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-black text-slate-900 uppercase">Buat Akun</h1>
            <p class="text-slate-500 text-sm mt-1">Daftarkan klub renang Anda sekarang.</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm font-bold text-center"><?= $error ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="bg-green-100 text-green-800 p-6 rounded-2xl mb-6 text-center shadow-md border border-green-200">
                <div class="text-4xl mb-3">✅</div>
                <h3 class="font-black text-xl mb-1 uppercase tracking-tight">Pendaftaran Berhasil!</h3>
                <p class="text-sm font-medium mb-5">Akun Anda sedang dalam status <strong>PENDING</strong>. Silakan hubungi Admin via WhatsApp untuk proses verifikasi dan aktivasi.</p>
                <a href="https://wa.me/<?= htmlspecialchars($waNumber) ?>?text=Halo%20Admin,%20saya%20baru%20saja%20mendaftar%20akun%20di%20Set%20System%20dengan%20email:%20<?= urlencode($email) ?>.%20Mohon%20untuk%20di-approve." target="_blank" class="block w-full bg-[#25D366] hover:bg-[#128C7E] text-white font-black py-3 px-4 rounded-xl transition shadow-lg mb-3">
                    HUBUNGI ADMIN VIA WA
                </a>
                <a href="login.php" class="inline-block text-xs font-bold text-slate-500 hover:text-slate-800 underline">Kembali ke halaman Login</a>
            </div>
            <style> form { display: none; } </style>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Lengkap / Klub</label>
                <input type="text" name="nama" class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-600 outline-none" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email</label>
                <input type="email" name="email" class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-600 outline-none" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Password</label>
                <input type="password" name="password" class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-600 outline-none" required>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 transition shadow-lg mt-2">DAFTAR SEKARANG</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Sudah punya akun? <a href="login.php" class="text-blue-600 font-bold hover:underline">Login</a>
        </p>
    </div>

</body>
</html>
