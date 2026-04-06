document.addEventListener('DOMContentLoaded', () => {
    const authed = AuthManager.isAuthenticated();

    // Toggle auth-aware elements
    document.querySelectorAll('.auth-only').forEach(el => {
        el.style.display = authed ? '' : 'none';
    });
    document.querySelectorAll('.guest-only').forEach(el => {
        el.style.display = authed ? 'none' : '';
    });

    // Logout button
    document.querySelectorAll('.logout-btn').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            AuthManager.logout();
        });
    });
});
