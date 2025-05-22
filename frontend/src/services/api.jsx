import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Add request interceptor to add token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Add response interceptor to handle auth errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

const ApiService = {
  // Auth endpoints
  register: (data) => api.post('/register', data),
  login: (data) => api.post('/login', data),
  adminLogin: (data) => api.post('/admin/login', data),
  logout: () => api.post('/logout'),
  verifyEmail: (id, hash) => api.get(`/email/verify/${id}/${hash}`),
  resendVerification: () => api.post('/email/verification-notification'),
  checkVerification: () => api.get('/email/verify/check'),

  // Profile endpoints
  getProfile: () => api.get('/profile'),
  updateProfile: (data) => api.put('/profile', data),

  // Doctor endpoints
  getDoctors: () => api.get('/doctors'),
  getSpecialities: () => api.get('/doctors/specialities'),
  getLocations: () => api.get('/doctors/locations'),
  searchDoctors: (filters) => api.get('/doctors/search', { params: filters }),
  getAvailability: (doctorId) => api.get(`/doctors/${doctorId}/availability`),
  getSlots: (doctorId, date) => api.get(`/doctors/${doctorId}/slots`, { params: { date } }),
  updateSchedule: (doctorId, schedule) => api.post(`/doctors/${doctorId}/schedule`, schedule),
  createLeave: (doctorId, leave) => api.post(`/doctors/${doctorId}/leaves`, leave),
  deleteLeave: (doctorId, leaveId) => api.delete(`/doctors/${doctorId}/leaves/${leaveId}`),

  // Appointment endpoints
  bookAppointment: (data) => api.post('/appointments', data),
  getAppointments: (doctorId, date) => api.get('/appointments', { params: { doctor_id: doctorId, date } }),
  getPatientAppointments: (patientId) => api.get('/appointments', { params: { patient_id: patientId } }),
  updateAppointmentStatus: (id, data) => api.patch(`/appointments/${id}/status`, data),
  cancelAppointment: (id) => api.patch(`/appointments/${id}/cancel`),

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
export const getAvailability = ApiService.getAvailability;
export const getSlots = ApiService.getSlots;
export const bookAppointment = ApiService.bookAppointment;
export const updateAppointmentStatus = ApiService.updateAppointmentStatus;
export const updateSchedule = ApiService.updateSchedule;
export const createLeave = ApiService.createLeave;
export const deleteLeave = ApiService.deleteLeave;
export const getAppointments = ApiService.getAppointments;
export const getPatientAppointments = ApiService.getPatientAppointments;