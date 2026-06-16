<?php
require_once __DIR__ . '/../config/database.php';

class LoginController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function login($email, $password) {
        try {
            // 1. Cari User berdasarkan Email
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // 2. Verifikasi Password
            if ($user && password_verify($password, $user['password'])) {
                // Login Sukses - Set Session
                if (session_status() === PHP_SESSION_NONE) session_start();
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];

                // 3. Redirect Sesuai Role
                if ($user['role'] === 'master') {
                    header("Location: ../src/master/dashboard.php");
                } elseif ($user['role'] === 'admin') {
                    header("Location: ../src/admin/dashboard.php");
                } elseif ($user['role'] === 'user') {
                    header("Location: ../src/user/dashboard.php");
                } else {
                    echo "Role tidak dikenali.";
                }
                exit();
            } else {
                return "Email atau password salah.";
            }
        } catch (PDOException $e) {
            return "Database Error: " . $e->getMessage();
        }
    }
}
