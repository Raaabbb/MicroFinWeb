document.addEventListener('DOMContentLoaded', () => {
    const loader = document.getElementById('loader');

    const loginForm = document.getElementById('login-form');

    if (loginForm) {
        loginForm.addEventListener('submit', () => {
            loader.classList.add('active');
        });
    }
});
