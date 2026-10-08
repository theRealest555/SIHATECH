// src/services/adminService.js
// Placeholder for admin-specific API calls
import apiClient from '../api/axios';

export const getAdminProfile = async () => {
    return apiClient.get('/api/admin/profile');
};

export const getAllUsers = async (params) => { // params for pagination, search
    return apiClient.get('/api/admin/users', { params });
};

export const getUserDetails = async (userId) => {
    return apiClient.get(`/api/admin/users/${userId}`);
};

export const updateUserStatus = async (userId, status, expectedStatusRevision, reason) => {
    return apiClient.put(`/api/admin/users/${userId}/status`, { status, expected_status_revision: expectedStatusRevision, reason });
};
// ... more admin functions (deleteUser, resetUserPassword, manageAdmins, doctorVerifications, reports, auditLogs)


export const deleteUser = id => apiClient.delete(`/api/admin/users/${id}`);
export const getPendingVerificationDoctors = () => apiClient.get('/api/admin/doctors/pending');
export const verifyDoctor = id => apiClient.post(`/api/admin/doctors/${id}/verify`);
export const rejectDoctorDocument = (id, reason, expectedStatus) => apiClient.post(`/api/admin/documents/${id}/reject`, { rejection_reason: reason, expected_status: expectedStatus });

export const getVerificationDoctors = (params, options = {}) => apiClient.get('/api/admin/doctors', { ...options, params });
export const getVerificationDoctor = (id, options = {}) => apiClient.get('/api/admin/doctors/' + id, options);
export const approveDoctorDocument = (id, expectedStatus) => apiClient.post('/api/admin/documents/' + id + '/approve', { expected_status: expectedStatus });
export const revokeDoctorVerification = (id, reason) => apiClient.post('/api/admin/doctors/' + id + '/revoke', { reason });
export const downloadAdminDocument = id => apiClient.get('/api/admin/documents/' + id + '/download', { responseType: 'blob' });
export const getAdminDashboard = (options = {}) => apiClient.get('/api/admin/dashboard', options);

export const getAuditLogs = (params, options = {}) => apiClient.get('/api/admin/audit-logs', { ...options, params });

export const getCatalogue = (kind, params, options = {}) => apiClient.get('/api/admin/' + kind, { ...options, params });
export const createCatalogueRecord = (kind, data) => apiClient.post('/api/admin/' + kind, data);
export const updateCatalogueRecord = (kind, id, data) => apiClient.put('/api/admin/' + kind + '/' + id, data);

export const getModerationReviews = (params, options = {}) => apiClient.get('/api/admin/reviews', { ...options, params });
export const moderateReview = (id, data) => apiClient.post('/api/admin/reviews/' + id + '/moderate', data);

export const getAdminPlans = (params, options = {}) => apiClient.get('/api/admin/subscription-plans', { ...options, params });
export const createAdminPlan = data => apiClient.post('/api/admin/subscription-plans', data);
export const updateAdminPlan = (id, data) => apiClient.put('/api/admin/subscription-plans/' + id, data);
export const getAdminReport = (type, params, options = {}) => apiClient.get('/api/admin/reports/' + type, { ...options, params });
export const exportAdminReport = (type, params, options = {}) => apiClient.get('/api/admin/reports/export/' + type, { ...options, params, responseType: 'blob' });
