import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import axios from '../../api/axios';

// Async thunks for patient data
export const fetchPatientAppointments = createAsyncThunk(
  'patient/fetchAppointments',
  async (patientId, { rejectWithValue }) => {
    try {
      const response = await axios.get('/api/appointments', {
        params: { patient_id: patientId }
      });
      return response.data.data || [];
    } catch (error) {
      return rejectWithValue(error.response?.data || { message: 'Failed to fetch appointments' });
    }
  }
);

export const bookAppointment = createAsyncThunk(
  'patient/bookAppointment',
  async ({ doctorId, patientId, date_heure }, { rejectWithValue }) => {
    try {
      const response = await axios.post(`/api/appointments`, {
        doctor_id: doctorId,
        patient_id: patientId,
        date_heure,
      });
      return response.data.data || {};
    } catch (error) {
      return rejectWithValue(error.response?.data || { message: 'Failed to book appointment' });
    }
  }
);

export const updateAppointmentStatus = createAsyncThunk(
  'patient/updateAppointmentStatus',
  async ({ appointmentId, status }, { rejectWithValue }) => {
    try {
      const response = await axios.patch(`/api/appointments/${appointmentId}/status`, {
        statut: status
      });
      return response.data.data || {};
    } catch (error) {
      return rejectWithValue(error.response?.data || { message: 'Failed to update appointment status' });
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

export const { clearPatientData } = patientSlice.actions;

export const selectPatientAppointments = (state) => state.patient.appointments;
export const selectPatientStatus = (state) => state.patient.status;
export const selectPatientError = (state) => state.patient.error;

export default patientSlice.reducer;