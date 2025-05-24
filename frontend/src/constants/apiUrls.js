/**
 * API URL constants for the application
 * These match the Laravel backend routes defined in routes/api.php
 */

export const API_URLS = {
  // Authentication endpoints
  AUTH: {
    LOGIN: '/api/login',
    REGISTER: '/api/register',
    LOGOUT: '/api/logout',
    USER: '/api/user',
    VERIFY_EMAIL: '/api/email/verify/check',
    RESEND_VERIFICATION: '/api/email/verification-notification',
  },
  
  // Doctor public endpoints (for searching/listing)
  DOCTORS: {
    LIST: '/api/doctors',
    SPECIALITIES: '/api/doctors/specialities',
    LOCATIONS: '/api/doctors/locations',
    SEARCH: '/api/doctors/search',
    AVAILABILITY: (doctorId) => `/api/doctors/${doctorId}/availability`,
    SLOTS: (doctorId) => `/api/doctors/${doctorId}/slots`,
  },
  
  // Doctor authenticated endpoints (for logged-in doctor)
  DOCTOR: {
    PROFILE: '/api/doctor/profile',
    UPDATE_PROFILE: '/api/doctor/profile',
    UPDATE_PASSWORD: '/api/doctor/profile/password',
    UPDATE_PHOTO: '/api/doctor/profile/photo',
    COMPLETE_PROFILE: '/api/doctor/complete-profile',
    DOCUMENTS: '/api/doctor/documents',
    DOCUMENT: (id) => `/api/doctor/documents/${id}`,
    SCHEDULE: '/api/doctor/schedule',
    LEAVES: '/api/doctor/leaves',
    LEAVE: (id) => `/api/doctor/leaves/${id}`,
  },
  
  // Patient endpoints
  PATIENT: {
    PROFILE: '/api/patient/profile',
    UPDATE_PROFILE: '/api/patient/profile',
    UPDATE_PASSWORD: '/api/patient/profile/password',
    UPDATE_PHOTO: '/api/patient/profile/photo',
  },
  
  // Appointment endpoints
  APPOINTMENTS: {
    LIST: '/api/appointments',
    BOOK: '/api/appointments',
    UPDATE_STATUS: (id) => `/api/appointments/${id}/status`,
  },
  
  // Admin endpoints
  ADMIN: {
    LOGIN: '/api/admin/login',
    USERS: '/api/admin/users',
    USER: (id) => `/api/admin/users/${id}`,
    USER_STATUS: (id) => `/api/admin/users/${id}/status`,
    RESET_PASSWORD: (id) => `/api/admin/users/${id}/password`,
    PENDING_DOCTORS: '/api/admin/doctors/pending',
    PENDING_DOCUMENTS: '/api/admin/documents/pending',
    DOCUMENT: (id) => `/api/admin/documents/${id}`,
    APPROVE_DOCUMENT: (id) => `/api/admin/documents/${id}/approve`,
    REJECT_DOCUMENT: (id) => `/api/admin/documents/${id}/reject`,
    VERIFY_DOCTOR: (id) => `/api/admin/doctors/${id}/verify`,
    REVOKE_VERIFICATION: (id) => `/api/admin/doctors/${id}/revoke`,
  }
};

export default API_URLS;