const AuthManager = {
    key: 'nqh_token',

    getToken() {
        return localStorage.getItem(this.key);
    },

    setToken(token) {
        localStorage.setItem(this.key, token);
    },

    clearToken() {
        localStorage.removeItem(this.key);
    },

    isAuthenticated() {
        return !!this.getToken();
    },

    getAuthHeaders() {
        const token = this.getToken();
        return token ? { 'Authorization': 'Bearer ' + token } : {};
    },

    async fetch(url, options = {}) {
        const headers = Object.assign({}, options.headers || {}, this.getAuthHeaders());
        const res = await fetch(url, Object.assign({}, options, { headers }));
        if (res.status === 401) {
            this.clearToken();
            window.location.href = '/login';
            return null;
        }
        return res;
    },

    async logout() {
        await this.fetch('/logout', { method: 'POST' });
        this.clearToken();
        window.location.href = '/login';
    },
};
