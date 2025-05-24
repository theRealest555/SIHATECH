import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import axios from '../../api/axios';
import { API_URLS } from '../../constants/apiUrls';

/**
 * Fetch appointments for a patient or doctor
 * Matches backend route: GET /api/appointments
 */
export const fetchPatientAppointments = createAsyncThunk(
  'patient/fetchAppointments',
  async (id, { getState, rejectWithValue }) => {
    try {
      const { auth } = getState();
      const userRole = auth.user?.role;
      
      // Determine if we're fetching for a patient or doctor
      const params = {};
      if (userRole === 'patient') {
        params.patient_id = id;
      } else if (userRole === 'medecin') {
        params.doctor_id = id;
      }
      
      const response = await axios.get(API_URLS.APPOINTMENTS.LIST, { params });
      
      // Backend returns { status: 'success', data: [...] }
      return response.data.data || [];
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to fetch appointments'
      });
    }
  }
);

/**
 * Book a new appointment
 * Matches backend route: POST /api/appointments
 */
export const bookAppointment = createAsyncThunk(
  'patient/bookAppointment',
  async ({ doctorId, patientId, date_heure }, { rejectWithValue }) => {
    try {
      // Backend expects doctor_id not doctorId
      const appointmentData = {
        doctor_id: doctorId,
        patient_id: patientId,
        date_heure,
      };
      
      const response = await axios.post(
        API_URLS.APPOINTMENTS.BOOK, 
        appointmentData
      );
      
      // Backend returns { status: 'success', data: {...} }
      return response.data.data || {};
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to book appointment',
        errors: error.response?.data?.errors || {}
      });
    }
  }
);

/**
 * Update an appointment's status
 * Matches backend route: PATCH /api/appointments/{id}/status
 */
export const updateAppointmentStatus = createAsyncThunk(
  'patient/updateAppointmentStatus',
  async ({ appointmentId, status }, { rejectWithValue }) => {
    try {
      // Backend expects 'statut' not 'status'
      const response = await axios.patch(
        API_URLS.APPOINTMENTS.UPDATE_STATUS(appointmentId), 
        { statut: status }
      );
      
      // Backend returns { status: 'success', data: {...} }
      return response.data.data || {};
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to update appointment status',
        errors: error.response?.data?.errors || {}
      });
    }
  }
);

const initialState = {
  appointments: [],
  status: 'idle', // 'idle' | 'loading' | 'succeeded' | 'failed'
  error: null,
};

const patientSlice = createSlice({
  name: 'patient',
  initialState,
  reducers: {
    clearPatientData: (state) => {
      state.appointments = [];
      state.status = 'idle';
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      // Fetch appointments
      .addCase(fetchPatientAppointments.pending, (state) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(fetchPatientAppointments.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.appointments = action.payload;
      })
      .addCase(fetchPatientAppointments.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to fetch appointments';
      })
      
      // Book appointment
      .addCase(bookAppointment.pending, (state) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(bookAppointment.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.appointments.push(action.payload);
      })
      .addCase(bookAppointment.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to book appointment';
      })
      
      // Update appointment status
      .addCase(updateAppointmentStatus.pending, (state) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(updateAppointmentStatus.fulfilled, (state, action) => {
        state.status = 'succeeded';
        const index = state.appointments.findIndex(
          appointment => appointment.id === action.payload.id
        );
        if (index !== -1) {
          state.appointments[index] = action.payload;
        }
      })
      .addCase(updateAppointmentStatus.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to update appointment status';
      });
  },
});

// Actions
export const { clearPatientData } = patientSlice.actions;

// Selectors
export const selectPatientAppointments = (state) => state.patient.appointments;
export const selectPatientStatus = (state) => state.patient.status;
export const selectPatientError = (state) => state.patient.error;

export default patientSlice.reducer;