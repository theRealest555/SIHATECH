// src/services/doctorService.js
// Placeholder for doctor-specific API calls (both doctor-role and public-doctor data)
import apiClient from '../api/axios';

// For Doctor Role
export const getDoctorProfile = async () => {
    return apiClient.get('/api/doctor/profile');
};

export const updateDoctorProfile = async (profileData) => {
    // For multipart/form-data (e.g. profile picture), headers need to be set
    // const config = { headers: { 'Content-Type': 'multipart/form-data' } };
    // return apiClient.post('/api/doctor/profile', profileData, config); // Laravel uses POST for PUT with form-data
    return apiClient.put('/api/doctor/profile', profileData);
};
export const updateDoctorPassword = async (passwordData) => {
    return apiClient.put('/api/doctor/profile/password', passwordData);
};
// ... documents, availability, leaves, appointments, statistics

// For Public Doctor Data
export const getPublicDoctorDetails = async (doctorId, options = {}) => {
    return apiClient.get(`/api/public/doctors/${doctorId}`, options);
};
export const searchDoctors = async (params) => { // simple search
    return apiClient.get('/api/public/doctors/search', { params });
};
export const searchDoctorsAdvanced = async (searchCriteria, options = {}) => {
    return apiClient.get('/api/public/doctors/search', { ...options, params: searchCriteria });
};
export const getSpecialities = async (options = {}) => {
    return apiClient.get('/api/public/specialities', options);
};
export const getLanguages = async (options = {}) => {
    return apiClient.get('/api/public/languages', options);
};
export const getDoctorSlots = (doctorId, date, options = {}) =>
    apiClient.get(`/api/public/doctors/${doctorId}/slots`, { ...options, params: { date } });
export const getDoctorAppointments = (params, options = {}) =>
    apiClient.get('/api/doctor/appointments', { ...options, params });
export const updateAppointmentStatus = (id, statut) =>
    apiClient.patch(`/api/doctor/appointments/${id}/status`, { statut });
export const markAppointmentNoShow = (id) =>
    apiClient.post(`/api/doctor/appointments/${id}/no-show`, {});

export const getDoctorAvailability = (options = {}) => apiClient.get('/api/doctor/availability', options);
export const saveDoctorSchedule = (schedule, revision) => apiClient.put('/api/doctor/schedule', { schedule, expected_schedule_revision: revision });
export const createDoctorLeave = data => apiClient.post('/api/doctor/leaves', data);
export const deleteDoctorLeave = id => apiClient.delete('/api/doctor/leaves/' + id);
export const getDoctorDocuments = (options = {}) => apiClient.get('/api/doctor/documents', options);
export const uploadDoctorDocument = data => apiClient.post('/api/doctor/documents', data);
export const deleteDoctorDocument = id => apiClient.delete('/api/doctor/documents/' + id);
export const downloadDoctorDocument = id => apiClient.get('/api/doctor/documents/' + id + '/download', { responseType: 'blob' });

export const getDoctorStatistics = (params, options = {}) => apiClient.get('/api/doctor/stats', { ...options, params });
export const exportDoctorStatistics = params => apiClient.get('/api/doctor/stats/export', { params, responseType: 'blob' });
