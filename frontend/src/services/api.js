/**
 * This file serves as a bridge to help transition from direct API calls to Redux
 * It wraps Redux actions in a familiar API service interface
 * 
 * IMPORTANT: All method names and parameters match the original ApiService,
 * but internally they dispatch Redux actions with proper backend URL alignment
 */
import store from '../redux/store';
import { 
  login, 
  register, 
  logout,
  checkAuth
} from '../redux/slices/authSlice';

import {
  fetchUserProfile,
  updateUserProfile,
  updateUserPassword,
  uploadUserPhoto
} from '../redux/slices/userSlice';

import {
  fetchAllDoctors,
  fetchDoctorSpecialities,
  fetchDoctorLocations,
  searchDoctors,
  fetchDoctorAvailability,
  fetchDoctorSlots,
  updateDoctorSchedule,
  createDoctorLeave,
  deleteDoctorLeave,
  uploadDoctorDocument,
  fetchDoctorDocuments,
  completeDoctorProfile
} from '../redux/slices/doctorSlice';

import {
  fetchPatientAppointments,
  bookAppointment,
  updateAppointmentStatus
} from '../redux/slices/patientSlice';

// Redux-powered API service
const ApiService = {
  // Auth methods
  login: (credentials) => {
    return store.dispatch(login(credentials)).unwrap();
  },
  
  register: (userData) => {
    return store.dispatch(register(userData)).unwrap();
  },
  
  logout: () => {
    return store.dispatch(logout()).unwrap();
  },
  
  checkAuth: () => {
    return store.dispatch(checkAuth()).unwrap();
  },
  
  // User profile methods
  getProfile: () => {
    return store.dispatch(fetchUserProfile()).unwrap();
  },
  
  updateProfile: (profileData) => {
    return store.dispatch(updateUserProfile(profileData)).unwrap();
  },
  
  updatePassword: (passwordData) => {
    return store.dispatch(updateUserPassword(passwordData)).unwrap();
  },
  
  uploadPhoto: (photoFile) => {
    return store.dispatch(uploadUserPhoto(photoFile)).unwrap();
  },
  
  // Doctor methods
  getDoctors: () => {
    return store.dispatch(fetchAllDoctors()).unwrap();
  },
  
  getSpecialities: () => {
    return store.dispatch(fetchDoctorSpecialities()).unwrap();
  },
  
  getLocations: () => {
    return store.dispatch(fetchDoctorLocations()).unwrap();
  },
  
  searchDoctors: (filters = {}) => {
    return store.dispatch(searchDoctors(filters)).unwrap();
  },
  
  getAvailability: (doctorId) => {
    return store.dispatch(fetchDoctorAvailability(doctorId)).unwrap();
  },
  
  getSlots: (doctorId, date) => {
    return store.dispatch(fetchDoctorSlots({ doctorId, date })).unwrap();
  },
  
  updateSchedule: (doctorId, schedule) => {
    // Backend expects just the schedule object
    return store.dispatch(updateDoctorSchedule({ schedule })).unwrap();
  },
  
  createLeave: (doctorId, leaveData) => {
    // Backend API doesn't need the doctorId in the URL
    return store.dispatch(createDoctorLeave({ leaveData })).unwrap();
  },
  
  deleteLeave: (doctorId, leaveId) => {
    // Backend only needs the leaveId
    return store.dispatch(deleteDoctorLeave({ leaveId })).unwrap();
  },
  
  uploadDocument: (file, type) => {
    return store.dispatch(uploadDoctorDocument({ file, type })).unwrap();
  },
  
  getDocuments: () => {
    return store.dispatch(fetchDoctorDocuments()).unwrap();
  },
  
  completeDoctorProfile: (profileData) => {
    return store.dispatch(completeDoctorProfile(profileData)).unwrap();
  },
  
  // Appointment methods
  getAppointments: (doctorId) => {
    return store.dispatch(fetchPatientAppointments(doctorId)).unwrap();
  },
  
  getPatientAppointments: (patientId) => {
    return store.dispatch(fetchPatientAppointments(patientId)).unwrap();
  },
  
  bookAppointment: ({ doctor_id, patient_id, date_heure }) => {
    // Convert to doctorId to match the Redux action format
    return store.dispatch(bookAppointment({ 
      doctorId: doctor_id, 
      patientId: patient_id, 
      date_heure 
    })).unwrap();
  },
  
  updateAppointmentStatus: (appointmentId, { statut }) => {
    // Convert statut to status to match the Redux action format
    return store.dispatch(updateAppointmentStatus({ 
      appointmentId, 
      status: statut 
    })).unwrap();
  }
};

export default ApiService;