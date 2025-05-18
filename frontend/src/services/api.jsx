import axios from "axios";

const api = axios.create({
    baseURL: "http://localhost:8000/api",
    withCredentials: true,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

const ApiService = {
    getDoctors: () => api.get("/doctors"),
    getSpecialities: () => api.get("/doctors/specialities"),
    getLocations: () => api.get("/doctors/locations"),
    searchDoctors: (filters) => api.get("/doctors/search", { params: filters }),
    getAvailability: (doctorId) => api.get(`/doctors/${doctorId}/availability`),
    getSlots: (doctorId, date) => api.get(`/doctors/${doctorId}/slots`, { params: { date } }),
    bookAppointment: (data) => api.post('/appointments', data),
    updateAppointmentStatus: (id, data) => api.patch(`/appointments/${id}/status`, data),
    updateSchedule: (doctorId, schedule) => api.post(`/doctors/${doctorId}/schedule`, schedule),
    createLeave: (doctorId, leave) => api.post(`/doctors/${doctorId}/leaves`, leave),
    deleteLeave: (doctorId, leaveId) => api.delete(`/doctors/${doctorId}/leaves/${leaveId}`),
    getAppointments: (doctorId, date) => api.get(`/appointments`, { params: { doctor_id: doctorId, date } }),
    getPatientAppointments: (patientId) => api.get(`/appointments`, { params: { patient_id: patientId } }),
};

// Export individual functions for backward compatibility
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

export default ApiService;