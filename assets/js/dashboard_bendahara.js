document.addEventListener('DOMContentLoaded', function() {
    // Notifikasi jatuh tempo
    const notifyButtons = document.querySelectorAll('.btn-notify');
    notifyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tagihanId = this.getAttribute('data-id');
            
            // Tampilkan konfirmasi
            if (confirm('Kirim notifikasi pengingat kepada siswa dan wali murid?')) {
                // AJAX untuk mengirim notifikasi
                fetch('../ajax/send_notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        tagihan_id: tagihanId,
                        type: 'reminder' 
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Notifikasi telah dikirim!');
                    } else {
                        alert('Gagal mengirim notifikasi: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat mengirim notifikasi');
                });
            }
        });
    });

    // Toggle sidebar di mobile
    const sidebarToggle = document.createElement('button');
    sidebarToggle.className = 'sidebar-toggle';
    sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
    document.querySelector('.main-content').prepend(sidebarToggle);
    
    sidebarToggle.addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
    });

    // Tutup sidebar saat mengklik di luar
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.sidebar') && !e.target.closest('.sidebar-toggle')) {
            document.querySelector('.sidebar').classList.remove('active');
        }
    });
});