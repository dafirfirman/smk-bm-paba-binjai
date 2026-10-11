function openNotifyModal(siswaId, tagihanId, nama, tagihan, jumlah, tanggal, hariTerlambat, denda, email, telepon, teleponOrtu) {
            document.getElementById('notify_siswa_id').value = siswaId;
            document.getElementById('notify_tagihan_id').value = tagihanId;
            document.getElementById('notify_nama').value = nama;
            document.getElementById('notify_tagihan').value = tagihan + ' (Rp ' + jumlah.toLocaleString('id-ID') + ')';
            
            const tanggalFormatted = new Date(tanggal).toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('notify_tanggal').value = tanggalFormatted;
            
            document.getElementById('notify_keterlambatan').value = hariTerlambat > 0 ? 
                'Terlambat ' + hariTerlambat + ' hari' : 'Masih dalam batas waktu';
            
            document.getElementById('notify_denda').value = denda > 0 ? 
                'Rp ' + denda.toLocaleString('id-ID') : 'Tidak ada denda';
            
            // Generate pesan default
            let pesanDefault = '';
            if (hariTerlambat > 0) {
                pesanDefault = `Yth. Orang Tua/Wali ${nama},
Dengan hormat, kami sampaikan bahwa pembayaran tagihan:
${tagihan} 
sebesar Rp ${jumlah.toLocaleString('id-ID')} 
sudah melewati batas waktu jatuh tempo (${tanggalFormatted}).
Keterlambatan: ${hariTerlambat} hari
Denda yang timbul: Rp ${denda > 0 ? denda.toLocaleString('id-ID') : '0'}
Total yang harus dibayar: Rp ${(jumlah + denda).toLocaleString('id-ID')}
Mohon untuk segera melakukan pembayaran untuk menghindari sanksi administrasi.
Terima kasih.
Hormat kami,
Bendahara SMK PABA Binjai`;
            } else {
                const daysLeft = Math.abs(hariTerlambat);
                pesanDefault = `Yth. Orang Tua/Wali ${nama},
Ini adalah pengingat bahwa tagihan:
${tagihan} 
sebesar Rp ${jumlah.toLocaleString('id-ID')} 
akan jatuh tempo pada ${tanggalFormatted}.
Sisa waktu: ${daysLeft} hari
Mohon untuk menyiapkan pembayaran tepat waktu.
Terima kasih.
Hormat kami,
Bendahara SMK PABA Binjai`;
            }
            
            document.getElementById('notify_pesan').value = pesanDefault;
            document.getElementById('notifyModal').style.display = 'block';
            document.body.classList.add('modal-open');
        }
        
        // Function to close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        });