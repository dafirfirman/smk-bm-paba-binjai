// kwitansi.js
document.addEventListener('DOMContentLoaded', function() {
    // Function to open email modal
    window.openEmailModal = function(id, nama) {
        document.getElementById('email_id').value = id;
        document.getElementById('email_nama').textContent = nama;
        document.getElementById('emailModal').style.display = 'block';
        document.body.classList.add('modal-open');
    };
    
    // Function to open delete modal
    window.openDeleteModal = function(id, nama) {
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_nama').textContent = nama;
        document.getElementById('deleteModal').style.display = 'block';
        document.body.classList.add('modal-open');
    };
    
    // Function to close modals
    window.closeModal = function(modalId) {
        document.getElementById(modalId).style.display = 'none';
        document.body.classList.remove('modal-open');
    };
    
    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            closeModal(event.target.id);
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (modal.style.display === 'block') {
                    closeModal(modal.id);
                }
            });
        }
    });
    
    // Enhance table with search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const table = document.querySelector('.data-table');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;
                
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j]) {
                        const text = cells[j].textContent.toLowerCase();
                        if (text.indexOf(filter) > -1) {
                            match = true;
                            break;
                        }
                    }
                }
                
                rows[i].style.display = match ? '' : 'none';
            }
        });
    }
});