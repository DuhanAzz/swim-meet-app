<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php if(isset($_SESSION['swal_type'])): ?>
        
        // Setting Global Toast
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',    // Pojok Kanan Atas
            showConfirmButton: false, 
            timer: 2000,            // Hilang dalam 2 detik
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        // Tembak Notifikasi
        Toast.fire({
            icon: '<?= $_SESSION['swal_type'] ?>',
            title: '<?= $_SESSION['swal_msg'] ?>'
        });

        // Hapus session agar tidak muncul lagi saat refresh
        <?php unset($_SESSION['swal_type']); unset($_SESSION['swal_msg']); ?>
    <?php endif; ?>
</script>