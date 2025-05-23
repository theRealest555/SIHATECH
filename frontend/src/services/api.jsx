import axios from 'axios';

// Create axios instance
const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Function to get CSRF token from cookies
const getCSRFToken = () => {
  const match = document.cookie.match(new RegExp('(^| )XSRF-TOKEN=([^;]+)'));
  return match ? decodeURIComponent(match[2]) : null;
};

// Add request interceptor to add token and CSRF
api.interceptors.request.use(
  async (config) => {
    // Add auth token if available
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    
    // Add CSRF token for non-GET requests
    if (config.method !== 'get') {
      const csrfToken = getCSRFToken();
      if (csrfToken) {
        config.headers['X-XSRF-TOKEN'] = csrfToken;
      } else {
        // If no CSRF token, try to get one
        try {
          await axios.get('http://localhost:8000/sanctum/csrf-cookie', { withCredentials: true });
          const newCsrfToken = getCSRFToken();
          if (newCsrfToken) {
            config.headers['X-XSRF-TOKEN'] = newCsrfToken;
          }
        } catch (error) {
          console.error('Failed to get CSRF token:', error);
        }
      }
    }
    
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Add response interceptor to handle auth errors
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;
    
    // If 419 error (CSRF token mismatch), try to refresh CSRF token
    if (error.response?.status === 419 && !originalRequest._retry) {
      originalRequest._retry = true;
      
      try {
        // Get new CSRF token
        await axios.get('http://localhost:8000/sanctum/csrf-cookie', { withCredentials: true });
        
        // Retry the original request
        const csrfToken = getCSRFToken();
        if (csrfToken) {
          originalRequest.headers['X-XSRF-TOKEN'] = csrfToken;
        }
        
        return api(originalRequest);
      } catch (csrfError) {
        console.error('Failed to refresh CSRF token:', csrfError);
      }
    }
    
    // If 401 error (unauthorized), redirect to login
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    }
    
    return Promise.reject(error);
  }
);

const ApiService = {
  // Initialize CSRF token
  initializeCSRF: () => axios.get('http://localhost:8000/sanctum/csrf-cookie', { withCredentials: true }),
  
  // Auth endpoints
  register: (data) => api.post('/register', data),
  login: (data) => api.post('/login', data),
  adminLogin: (data) => api.post('/admin/login', data),
  logout: () => api.post('/logout'),
  verifyEmail: (id, hash) => api.get(`/email/verify/${id}/${hash}`),
  resendVerification: () => api.post('/email/verification-notification'),
  checkVerification: () => api.get('/email/verify/check'),

  // Profile endpoints
  getPatientProfile: () => api.get('/patient/profile'),
  getDoctorProfile: () => api.get('/doctor/profile'),
  updatePatientProfile: (data) => api.put('/patient/profile', data),
  updateDoctorProfile: (data) => api.put('/doctor/profile', data),
  updatePatientPassword: (data) => api.put('/patient/profile/password', data),
  updateDoctorPassword: (data) => api.put('/doctor/profile/password', data),
  uploadPatientPhoto: (formData) => api.post('/patient/profile/photo', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  uploadDoctorPhoto: (formData) => api.post('/doctor/profile/photo', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  completeDoctorProfile: (data) => api.post('/doctor/complete-profile', data),

  // Doctor endpoints
  getDoctors: () => api.get('/doctors'),
  getSpecialities: () => api.get('/doctors/specialities'),
  getLocations: () => api.get('/doctors/locations'),
  searchDoctors: (filters) => api.get('/doctors/search', { params: filters }),
  
  // Doctor documents
  getDoctorDocuments: () => api.get('/doctor/documents'),
  uploadDoctorDocument: (formData) => api.post('/doctor/documents', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  deleteDoctorDocument: (id) => api.delete(`/doctor/documents/${id}`),

  // Appointment endpoints
  getAppointments: (doctorId, date) => api.get('/appointments', { params: { doctor_id: doctorId, date } }),
  getPatientAppointments: (patientId) => api.get('/appointments', { params: { patient_id: patientId } }),

  // Social auth endpoints
  googleAuth: () => window.location.href = 'http://localhost:8000/api/auth/social/google/redirect',
  facebookAuth: () => window.location.href = 'http://localhost:8000/api/auth/social/facebook/redirect',
};

export default ApiService;

// Named exports for backward compatibility
export const getDoctors = ApiService.getDoctors;
export const getSpecialities = ApiService.getSpecialities;
export const getLocations = ApiService.getLocations;
export const searchDoctors = ApiService.searchDoctors;
export const getAppointments = ApiService.getAppointments;
export const getPatientAppointments = ApiService.getPatientAppointments;