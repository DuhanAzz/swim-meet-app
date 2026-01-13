<?php
session_start();
require_once __DIR__ . '/../src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // SIMPAN DATA KE SESSION
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['username'] = $user['username'];
        // Simpan path foto profil (jika ada)
        $_SESSION['profile_image'] = $user['profile_image']; 

        // REDIRECT SESUAI ROLE
        if ($user['role'] == 'master') {
            header("Location: ../src/master/dashboard.php");
        } elseif ($user['role'] == 'admin') {
            header("Location: ../src/admin/dashboard.php");
        } else {
            header("Location: ../src/user/dashboard.php");
        }
        exit();
    } else {
        $_SESSION['error'] = "Username atau Password salah.";
        header("Location: login.php");
        exit();
    }
}
?>
