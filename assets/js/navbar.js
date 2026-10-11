// Handle profile dropdown
const profileTrigger = document.querySelector('.profile-dropdown-trigger');
const dropdown = document.querySelector('.profile-dropdown');

if (profileTrigger && dropdown) {
    profileTrigger.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('show');
    });
}

// Tutup dropdown ketika klik di luar
document.addEventListener('click', function(e) {
    if (dropdown && dropdown.classList.contains('show') && 
        !e.target.closest('.user-profile-container')) {
        dropdown.classList.remove('show');
    }
});

// Tutup dropdown ketika tekan Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && dropdown && dropdown.classList.contains('show')) {
        dropdown.classList.remove('show');
    }
});

// Handle hamburger menu untuk mobile
const hamburgerMenu = document.querySelector('.hamburger-menu');
const mobileNav = document.querySelector('.mobile-nav');

if (hamburgerMenu && mobileNav) {
    hamburgerMenu.addEventListener('click', function() {
        mobileNav.classList.toggle('open');
        
        // Animasi hamburger icon
        const spans = hamburgerMenu.querySelectorAll('span');
        if (mobileNav.classList.contains('open')) {
            spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
            spans[1].style.opacity = '0';
            spans[2].style.transform = 'rotate(-45deg) translate(7px, -6px)';
        } else {
            spans[0].style.transform = 'none';
            spans[1].style.opacity = '1';
            spans[2].style.transform = 'none';
        }
    });
}