// src/services/authService.js
import apiClient from '../api/axios';
export const getSocialProviders = (options = {}) => apiClient.get('/api/public/auth/providers', options);

export const getCsrfCookie = async () => {
    return apiClient.get('/sanctum/csrf-cookie');
};

// Login, register, logout are handled in AuthContext for now to manage state easily
// but can be moved here if preferred, returning promises.

export const fetchAuthenticatedUser = async () => {
    return apiClient.get('/api/user');
};

export const sendPasswordResetLink = async (email) => {
    await getCsrfCookie();
    return apiClient.post('/api/forgot-password', { email });
};

export const resetPassword = async (data) => {
    // data: { token, email, password, password_confirmation }
    await getCsrfCookie();
    return apiClient.post('/api/reset-password', data);
};

export const resendVerificationEmail = async () => {
    // Assumes user is somewhat authenticated to request this (e.g., logged in but not verified)
    return apiClient.post('/api/email/verification-notification');
};
