document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('dashboardSidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    // const sidebarClose = document.getElementById('sidebarClose'); // Tombol ini sudah tidak dipakai
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    // Cek jika kita di layar mobile
    function isMobile() {
        return window.innerWidth <= 992;
    }

    // Fungsi untuk membuka/menutup sidebar
    function toggleSidebar() {
        if (!isMobile()) return;
        
        const isExpanded = sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        sidebarToggle.classList.toggle('active');
        document.body.classList.toggle('sidebar-open');
        
        // Update atribut aria-expanded untuk aksesibilitas
        sidebarToggle.setAttribute('aria-expanded', isExpanded);
    }

    // Fungsi untuk menutup sidebar (dipakai oleh overlay dan tombol Escape)
    function closeSidebar() {
        if (!isMobile() || !sidebar.classList.contains('active')) return;
        
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        sidebarToggle.classList.remove('active');
        document.body.classList.remove('sidebar-open');
        sidebarToggle.setAttribute('aria-expanded', 'false');
    }

    // Inisialisasi status sidebar berdasarkan ukuran layar
    function initSidebar() {
        if (isMobile()) {
            // Mobile: sidebar tertutup, tombol toggle terlihat
            sidebar.classList.remove('active');
            sidebarToggle.style.display = 'flex';
            sidebarToggle.setAttribute('aria-expanded', 'false');
        } else {
            // Desktop: sidebar terbuka, tombol toggle disembunyikan
            sidebar.classList.add('active'); // Di desktop, sidebar selalu 'active' secara visual
            sidebarToggle.style.display = 'none';
            sidebarOverlay.classList.remove('active');
            document.body.classList.remove('sidebar-open');
        }
    }

    // Event listeners
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar();
        });
    }

    // Tombol close yang lama sudah dihapus, jadi event listener-nya juga dihapus
    // if (sidebarClose) {
    //     sidebarClose.addEventListener('click', closeSidebar);
    // }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    // Tutup sidebar saat menekan tombol Escape
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    });

    // Tutup sidebar saat item menu diklik (di mobile)
    const menuLinks = document.querySelectorAll('.menu-link');
    menuLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (isMobile() && sidebar.classList.contains('active')) {
                closeSidebar();
            }
        });
    });

    // Handle saat ukuran window berubah
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            initSidebar();
        }, 250);
    });

    // Inisialisasi sidebar saat halaman dimuat
    initSidebar();
});
