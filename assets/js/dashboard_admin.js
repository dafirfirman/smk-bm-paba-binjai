/**
 * Dashboard Admin JavaScript
 * Grafik dan interaktivitas untuk dashboard admin
 */

// Initialize charts
function initCharts(transactionDates, transactionCounts, transactionAmounts, tagihanCategories, tagihanCounts, pendapatanBulanIni, totalTagihanBelumDibayar) {
    // Transaction Chart
    const transactionCtx = document.getElementById('transactionChart').getContext('2d');
    const transactionChart = new Chart(transactionCtx, {
        type: 'line',
        data: {
            labels: transactionDates,
            datasets: [{
                label: 'Jumlah Transaksi',
                data: transactionCounts,
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                borderColor: '#4361ee',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }, {
                label: 'Total Nominal (Rp)',
                data: transactionAmounts,
                backgroundColor: 'rgba(247, 37, 133, 0.1)',
                borderColor: '#f72585',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Jumlah Transaksi'
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    },
                    title: {
                        display: true,
                        text: 'Total Nominal (Rp)'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            }
        }
    });
    
    // Tagihan Chart (Pie)
    const tagihanCtx = document.getElementById('tagihanChart').getContext('2d');
    const tagihanChart = new Chart(tagihanCtx, {
        type: 'doughnut',
        data: {
            labels: tagihanCategories,
            datasets: [{
                data: tagihanCounts,
                backgroundColor: [
                    '#4361ee', '#4cc9f0', '#f72585', '#f77f00', '#2a9d8f', 
                    '#3a0ca3', '#4895ef', '#b5179e', '#d62828', '#264653'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12
                    }
                }
            }
        }
    });
    
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: ['Pendapatan Bulan Ini', 'Tagihan Belum Dibayar'],
            datasets: [{
                data: [pendapatanBulanIni, totalTagihanBelumDibayar],
                backgroundColor: ['#4cc9f0', '#f72585'],
                borderWidth: 0,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Rp ' + context.raw.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });
    
    return {
        transactionChart,
        tagihanChart,
        revenueChart
    };
}

// Sidebar toggle functionality
function initSidebar() {
    const sidebar = document.querySelector('.dashboard-sidebar');
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-open');
            sidebarOverlay.classList.toggle('active');
        });
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('sidebar-open');
            sidebarOverlay.classList.remove('active');
        });
    }
}

// Refresh data function (can be used for auto-refresh)
function refreshDashboardData() {
    // You can implement AJAX call here to refresh data
    console.log('Refreshing dashboard data...');
}

// Initialize dashboard when page loads
document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    
    // Add any additional initialization code here
    
    // Example: Auto-refresh every 5 minutes
    // setInterval(refreshDashboardData, 300000);
});