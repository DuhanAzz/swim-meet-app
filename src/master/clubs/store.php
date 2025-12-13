<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_klub = $_POST['nama_klub'];
    $kode_klub = strtoupper($_POST['kode_klub']);
    $kota = $_POST['kota'];
    $logoName = null;

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['logo']['tmp_name'];
        $fileName = $_FILES['logo']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');
        
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $uploadFileDir = __DIR__ . '/../../../public/uploads/clubs/';
            
            if(move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
                $logoName = $newFileName;
            }
        }
    }

    try {
        $sql = "INSERT INTO clubs (nama_klub, kode_klub, kota, logo) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nama_klub, $kode_klub, $kota, $logoName]);

        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo "<script>alert('Error: Kode Klub sudah digunakan!'); window.history.back();</script>";
        } else {
            echo "Error: " . $e->getMessage();
        }
    }
}
?>
