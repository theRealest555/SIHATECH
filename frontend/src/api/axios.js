// src/api/axios.js
import axios from 'axios';

const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000', // Ensure this is in your .env
    withCredentials: true,
    withXSRFToken: true, // Important for Sanctum to send cookies
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
    }
});

// No explicit token setting here for Sanctum if using cookie-based sessions.
// Axios will automatically handle cookies due to `withCredentials: true`.
// If you were using tokens in headers (e.g. for mobile apps), you'd add an interceptor:
/*
apiClient.interceptors.request.use(config => {
    const token = localStorage.getItem('authToken'); // Or get from AuthContext
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
}, error => {
    return Promise.reject(error);
});
*/

export default apiClient;
