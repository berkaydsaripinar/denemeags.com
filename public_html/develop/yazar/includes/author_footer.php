<?php
// yazar/includes/author_footer.php
?>
    <div class="mt-5 text-center pb-5">
        <p class="text-muted small">© <?php echo date('Y'); ?> DenemeAGS Yazar Yönetim Sistemi V2.0</p>
    </div>
</div> <!-- .main-panel kapanış -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar toggle ve diğer UI etkileşimleri
    document.addEventListener('DOMContentLoaded', function() {
        // Aktif menüyü vurgula
        const currentPath = window.location.pathname.split('/').pop();
        document.querySelectorAll('.nav-link').forEach(link => {
            if(link.getAttribute('href') === currentPath) {
                link.classList.add('active');
            }
        });
    });
</script>
</body>
</html>