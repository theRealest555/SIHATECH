import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

const ApiService = {
  getSlots: async (doctorId, date) => {
    const response = await api.get(`/doctors/${doctorId}/slots?date=${date}`);
    return response.data; // {status: "success", data: [...], meta: {...}}
  },
  bookAppointment: async (data) => {
    const response = await api.post('/appointments', data);
    return response.data; // {status: "success", data: {...}}
  },
  updateAppointmentStatus: async (id, data) => {
    const response = await api.patch(`/appointments/${id}/status`, data);
    return response.data; // {status: "success", data: {...}}
  },
  getAppointments: async (doctorId, date) => {
    const response = await api.get(`/appointments?doctor_id=${doctorId}&date=${date}`);
    return response.data; // {status: "success", data: [...]}
  },
  searchDoctors: async (params = {}) => {
    const response = await api.get('/doctors/search', { params });
    return response.data; // {status: "success", data: [...]} 
  },
  getDoctors() {
    return apiClient.get("/doctors");
},

getPatientAppointments(patientId) {
    return apiClient.get("/appointments", { params: { patient_id: patientId } });
},

};

export default ApiService;