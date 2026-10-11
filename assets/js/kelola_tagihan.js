// kelola_tagihan.js

// Fungsi konfirmasi hapus
function confirmDelete(id, name) {
    $('#delete-tagihan-name').text(name);  // Menampilkan nama tagihan yang akan dihapus
    $('#confirm-delete-btn').attr('href', 'kelola_tagihan.php?delete_id=' + id);  // URL untuk menghapus tagihan
    $('#deleteModal').css('display', 'block');  // Menampilkan modal
}

// Fungsi tutup modal
function closeDeleteModal() {
    $('#deleteModal').css('display', 'none');
}

// Export to Excel
function exportToExcel() {
    try {
        // Get table data
        const table = document.getElementById('invoices-table');
        const workbook = XLSX.utils.table_to_book(table);
        
        // Generate Excel file
        XLSX.writeFile(workbook, 'Daftar_Tagihan_' + new Date().toISOString().slice(0,10) + '.xlsx');
        
        // Show success message
        showNotification('Export berhasil! File Excel telah diunduh.', 'success');
    } catch (error) {
        console.error('Error exporting to Excel:', error);
        showNotification('Error saat mengekspor data: ' + error.message, 'error');
    }
}

// Show notification
function showNotification(message, type) {
    // Remove existing notifications
    $('.custom-notification').remove();
    
    // Create notification element
    const notification = $('<div class="custom-notification"></div>');
    notification.addClass(type === 'success' ? 'alert-success' : 'alert-error');
    notification.html('<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + message);
    
    // Add styles
    notification.css({
        'position': 'fixed',
        'top': '20px',
        'right': '20px',
        'z-index': '9999',
        'padding': '1rem 1.5rem',
        'border-radius': 'var(--border-radius)',
        'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
        'max-width': '400px',
        'animation': 'slideInRight 0.3s ease-out'
    });
    
    // Add to body
    $('body').append(notification);
    
    // Auto remove after 5 seconds
    setTimeout(function() {
        notification.fadeOut(300, function() {
            $(this).remove();
        });
    }, 5000);
}

// Initialize DataTable
$(document).ready(function() {
    $('#invoices-table').DataTable({
        responsive: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        dom: '<"top"<"row"<"col-md-6"l><"col-md-6"f>>>rt<"bottom"<"row"<"col-md-6"i><"col-md-6"p>>>',
        initComplete: function() {
            $('.dataTables_filter input').attr('placeholder', 'Cari...');
        },
        columnDefs: [
            { responsivePriority: 1, targets: 0 },
            { responsivePriority: 2, targets: -1 },
            { orderable: false, targets: -1 }
        ]
    });
    
    // Close modal when clicking outside
    $(document).on('click', function(event) {
        if ($(event.target).is('#deleteModal')) {
            closeDeleteModal();
        }
    });
    
    // Close modal with ESC key
    $(document).on('keyup', function(event) {
        if (event.key === "Escape") {
            closeDeleteModal();
        }
    });
});

// CSS for custom notification
const notificationStyles = `
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.custom-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    padding: 1rem 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    max-width: 400px;
    animation: slideInRight 0.3s ease-out;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
`;

// Add styles to document
$(document).ready(function() {
    $('head').append('<style>' + notificationStyles + '</style>');
});