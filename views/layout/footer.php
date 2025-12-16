<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.js"></script>

    <script>
        // Auto-hide toast notification setelah 3 detik
        setTimeout(() => {
            const toast = document.getElementById('toast-notification');
            if (toast) {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            }
        }, 3000);
    </script>
</body>
</html>