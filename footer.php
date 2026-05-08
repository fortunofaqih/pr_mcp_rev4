        </div><!-- end container-fluid -->
    </div><!-- end #content -->
</div><!-- end .wrapper -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>

<!-- Chart.js (untuk finance dashboard dan chart lainnya) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- jQuery (jika diperlukan) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Sidebar Toggle Script -->
<script>
    const overlay = document.getElementById('overlay');
    const sidebar = document.getElementById('sidebar');
    const sidebarCollapse = document.getElementById('sidebarCollapse');

    // Toggle sidebar pada mobile
    sidebarCollapse.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    });

    // Tutup sidebar saat overlay di-klik
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // Tutup sidebar saat link diklik (mobile)
    const navLinks = sidebar.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        });
    });

    // Resize handler
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
    });
</script>

<!-- Additional Scripts Hook (untuk custom script per halaman) -->
<?php if (isset($additional_js)) { echo $additional_js; } ?>

</body>
</html>
