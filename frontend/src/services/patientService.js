// src/services/patientService.js
// Placeholder for patient-specific API calls
import apiClient from '../api/axios';

export const getPatientProfile = async () => {
    return apiClient.get('/api/patient/profile');
};
export const updatePatientProfile = async (profileData) => {
    return apiClient.put('/api/patient/profile', profileData);
};
export const updatePatientPassword = async (passwordData) => {
    return apiClient.put('/api/patient/profile/password', passwordData);
};
export const getPatientAppointments = async (params, options = {}) => {
    return apiClient.get('/api/patient/appointments', { ...options, params });
};
export const bookAppointment = async (appointmentData) => {
    return apiClient.post(`/api/patient/doctors/${appointmentData.doctor_id}/appointments`, { date_heure: appointmentData.date_heure });
};
export const cancelAppointment = async (appointmentId) => {
    return apiClient.patch(`/api/patient/appointments/${appointmentId}/status`, { statut: 'annulé' });
};
export const addDoctorReview = async (doctorId, reviewData) => {
    return apiClient.post(`/api/doctors/${doctorId}/reviews`, reviewData);
};


export const getAppointmentReview = (id, options = {}) => apiClient.get('/api/patient/appointments/' + id + '/review', options);
export const submitAppointmentReview = (id, data) => apiClient.post('/api/patient/appointments/' + id + '/review', data);
