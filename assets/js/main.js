document.addEventListener('DOMContentLoaded', function() {
    const iconBars = document.querySelector('.icon-bars');
    const navbarNav = document.querySelector('.navbar-nav');
    
    if (iconBars && navbarNav) {
        iconBars.addEventListener('click', function(e) {
            e.preventDefault();
            navbarNav.classList.toggle('active');
        });
    }
});
