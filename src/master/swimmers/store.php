<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    $club_id = $_POST['club_id'];
    $nama_atlet = $_POST['nama_atlet'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $nomor_registrasi = $_POST['nomor_registrasi'];
    
    $fotoName = null;

    // Handle Upload Foto
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['foto']['tmp_name'];
        $fileName = $_FILES['foto']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');
        
        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Nama file unik: timestamp + id_klub + acak
            $newFileName = time() . '_' . $club_id . '_' . md5($fileName) . '.' . $fileExtension;
            $uploadFileDir = __DIR__ . '/../../../public/uploads/swimmers/';
            
            if(move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
                $fotoName = $newFileName;
            }
        }
    }

    try {
        $sql = "INSERT INTO swimmers (club_id, nama_atlet, jenis_kelamin, tanggal_lahir, nomor_registrasi, foto) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$club_id, $nama_atlet, $jenis_kelamin, $tanggal_lahir, $nomor_registrasi, $fotoName]);

        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>
