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

const ApiService = {
  // Auth endpoints
  register: (data) => api.post('/register', data),
  login: (data) => api.post('/login', data),
  adminLogin: (data) => api.post('/admin/login', data),
  logout: () => api.post('/logout'),
  verifyEmail: (id, hash) => api.get(`/email/verify/${id}/${hash}`),
  resendVerification: () => api.post('/email/verification-notification'),
  checkVerification: () => api.get('/email/verify/check'),

  // Existing endpoints
  getDoctors: () => api.get('/doctors'),
  getSpecialities: () => api.get('/doctors/specialities'),
  getLocations: () => api.get('/doctors/locations'),
  searchDoctors: (filters) => api.get('/doctors/search', { params: filters }),
  getAvailability: (doctorId) => api.get(`/doctors/${doctorId}/availability`),
  getSlots: (doctorId, date) => api.get(`/doctors/${doctorId}/slots`, { params: { date } }),
  bookAppointment: (data) => api.post('/appointments', data),
  updateAppointmentStatus: (id, data) => api.patch(`/appointments/${id}/status`, data),
  updateSchedule: (doctorId, schedule) => api.post(`/doctors/${doctorId}/schedule`, schedule),
  createLeave: (doctorId, leave) => api.post(`/doctors/${doctorId}/leaves`, leave),
  deleteLeave: (doctorId, leaveId) => api.delete(`/doctors/${doctorId}/leaves/${leaveId}`),
  getAppointments: (doctorId, date) => api.get('/appointments', { params: { doctor_id: doctorId, date } }),
  getPatientAppointments: (patientId) => api.get('/appointments', { params: { patient_id: patientId } }),
};

export default ApiService;