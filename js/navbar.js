document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.querySelector('#theme-toggle');
    const navbarToggle = document.querySelector('.navbar-toggle');
    const navbarMenus = document.querySelector('.navbar-menus');
    const userProfile = document.querySelector('.user-profile');

    // Load saved theme
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark');
        themeToggle.checked = true;
    } else {
        document.body.classList.remove('dark');
        themeToggle.checked = false;
    }

    // Theme toggle
    themeToggle.addEventListener('change', () => {
        if (themeToggle.checked) {
            document.body.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        } else {
            document.body.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        }
    });

    // Mobile menu toggle
    if (navbarToggle && navbarMenus) {
        navbarToggle.addEventListener('click', () => {
            console.log('Hamburger clicked');
            navbarToggle.classList.toggle('active');
            navbarMenus.classList.toggle('active');
        });
    }

    // User profile dropdown
    if (userProfile) {
        userProfile.addEventListener('click', (e) => {
            e.stopPropagation();
            console.log('User profile clicked');
            userProfile.classList.toggle('active');
        });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (userProfile && !userProfile.contains(e.target)) {
            console.log('Clicked outside user profile');
            userProfile.classList.remove('active');
        }
    });
});