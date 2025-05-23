import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import axios from '../../api/axios';

// Async thunks for doctor data
export const fetchAllDoctors = createAsyncThunk(
  'doctor/fetchAll',
  async (_, { rejectWithValue }) => {
    try {
      const response = await axios.get('/api/doctors');
      return response.data.data || [];
    } catch (error) {
      return rejectWithValue(error.response?.data || { message: 'Failed to fetch doctors' });
    }
  }
);

export const fetchDoctorSpecialities = createAsyncThunk(
  'doctor/fetchSpecialities',
  async (_, { rejectWithValue }) => {
    try {
      const response = await axios.get('/api/doctors/specialities');
      return response.data.data || [];
    } catch (error) {
      return rejectWithValue(error.response?.data || { message: 'Failed to fetch specialities' });
    }
  }
);

export const fetchDoctorLocations = createAsyncThunk(
  'doctor/fetchLocations',
  async (_, { rejectWithValue }) => {
    try {
      const response = await axios.get('/api/doctors/locations');
      return response.data.data || [];
    } catch (error) {
      return rejectWithValue(error.response?.data || { message: 'Failed to fetch locations' });
    }
  }
);

export const searchDoctors = createAsyncThunk(
  'doctor/search',
  async (filters, { rejectWithValue }) => {
    try {
      const response = await axios.get('/api/doctors/search', { params: filters });
      return response.data.data || [];
    } catch (error) {
      return rejectWithValue(error.response?.data || { message: 'Failed to search doctors' });
    }
  }
);

export const fetchDoctorAvailability = createAsyncThunk(
  'doctor/fetchAvailability',
  async (doctorId, { rejectWithValue }) => {
    try {
      const response = await axios.get(`/api/doctors/${doctorId}/availability`);
      return response.data.data || { schedule: {}, leaves: [] };
    } catch (error) {
      return rejectWithValue(error.response?.data || { message: 'Failed to fetch availability' });
    }
  }
);

export const fetchDoctorSlots = createAsyncThunk(
  'doctor/fetchSlots',
  async ({ doctorId, date }, { rejectWithValue }) => {
    try {
      const response = await axios.get(`/api/doctors/${doctorId}/slots`, { params: { date } });
      return { doctorId, date, slots: response.data.data || [] };
    } catch (error) {
      return rejectWithValue(error.response?.data || { message: 'Failed to fetch slots' });
    }
  }
);

export const updateDoctorSchedule = createAsyncThunk(
  'doctor/updateSchedule',
  async ({ doctorId, schedule }, { rejectWithValue }) => {
    try {
      const response = await axios.post(`/api/doctor/schedule`, { schedule });
      return response.data.data || {};
    } catch (error) {
      return rejectWithValue(error.response?.data || { message: 'Failed to update schedule' });
    }
  }
);

export const createDoctorLeave = createAsyncThunk(
  'doctor/createLeave',
  async ({ doctorId, leaveData }, { rejectWithValue }) => {
    try {
      const response = await axios.post(`/api/doctor/leaves`, leaveData);
      return response.data.data || {};
    } catch (error) {
      return rejectWithValue(error.response?.data || { message: 'Failed to create leave' });
    }
  }
);

export const deleteDoctorLeave = createAsyncThunk(
  'doctor/deleteLeave',
  async ({ doctorId, leaveId }, { rejectWithValue }) => {
    try {
      await axios.delete(`/api/doctor/leaves/${leaveId}`);
      return { doctorId, leaveId };
    } catch (error) {
      return rejectWithValue(error.response?.data || { message: 'Failed to delete leave' });
    }
  }
);

export const uploadDoctorDocument = createAsyncThunk(
  'doctor/uploadDocument',
  async ({ file, type }, { rejectWithValue }) => {
    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('type', type);
      
      const response = await axios.post('/api/doctor/documents', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      return response.data.document || {};
    } catch (error) {
      return rejectWithValue(error.response?.data || { message: 'Failed to upload document' });
    }
  }
);

export const fetchDoctorDocuments = createAsyncThunk(
  'doctor/fetchDocuments',
  async (_, { rejectWithValue }) => {
    try {
      const response = await axios.get('/api/doctor/documents');
      return response.data.documents || [];
    } catch (error) {
      return rejectWithValue(error.response?.data || { message: 'Failed to fetch documents' });
    }
  }
);

const initialState = {
  doctors: [],
  specialities: [],
  locations: [],
  searchResults: [],
  availability: { schedule: {}, leaves: [] },
  slots: {},
  documents: [],
  status: 'idle', // 'idle' | 'loading' | 'succeeded' | 'failed'
  error: null,
};

const doctorSlice = createSlice({
  name: 'doctor',
  initialState,
  reducers: {
    clearDoctorData: (state) => {
      state.doctors = [];
      state.searchResults = [];
      state.slots = {};
      state.status = 'idle';
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      // Fetch all doctors
      .addCase(fetchAllDoctors.pending, (state) => {
        state.status = 'loading';
      })
      .addCase(fetchAllDoctors.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.doctors = action.payload;
      })
      .addCase(fetchAllDoctors.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to fetch doctors';
      })
      
      // Fetch specialities
      .addCase(fetchDoctorSpecialities.pending, (state) => {
        state.status = 'loading';
      })
      .addCase(fetchDoctorSpecialities.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.specialities = action.payload;
      })
      .addCase(fetchDoctorSpecialities.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to fetch specialities';
      })
      
      // Fetch locations
      .addCase(fetchDoctorLocations.pending, (state) => {
        state.status = 'loading';
      })
      .addCase(fetchDoctorLocations.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.locations = action.payload;
      })
      .addCase(fetchDoctorLocations.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to fetch locations';
      })
      
      // Search doctors
      .addCase(searchDoctors.pending, (state) => {
        state.status = 'loading';
      })
      .addCase(searchDoctors.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.searchResults = action.payload;
      })
      .addCase(searchDoctors.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to search doctors';
      })
      
      // Fetch availability
      .addCase(fetchDoctorAvailability.pending, (state) => {
        state.status = 'loading';
      })
      .addCase(fetchDoctorAvailability.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.availability = action.payload;
      })
      .addCase(fetchDoctorAvailability.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to fetch availability';
      })
      
      // Fetch slots
      .addCase(fetchDoctorSlots.pending, (state) => {
        state.status = 'loading';
      })
      .addCase(fetchDoctorSlots.fulfilled, (state, action) => {
        state.status = 'succeeded';
        // Store slots by doctorId and date
        if (!state.slots[action.payload.doctorId]) {
          state.slots[action.payload.doctorId] = {};
        }
        state.slots[action.payload.doctorId][action.payload.date] = action.payload.slots;
      })
      .addCase(fetchDoctorSlots.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to fetch slots';
      })
      
      // Update schedule
      .addCase(updateDoctorSchedule.pending, (state) => {
        state.status = 'loading';
      })
      .addCase(updateDoctorSchedule.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.availability.schedule = action.payload;
      })
      .addCase(updateDoctorSchedule.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to update schedule';
      })
      
      // Create leave
      .addCase(createDoctorLeave.pending, (state) => {
        state.status = 'loading';
      })
      .addCase(createDoctorLeave.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.availability.leaves.push(action.payload);
      })
      .addCase(createDoctorLeave.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to create leave';
      })
      
      // Delete leave
      .addCase(deleteDoctorLeave.pending, (state) => {
        state.status = 'loading';
      })
      .addCase(deleteDoctorLeave.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.availability.leaves = state.availability.leaves.filter(
          leave => leave.id !== action.payload.leaveId
        );
      })
      .addCase(deleteDoctorLeave.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to delete leave';
      })
      
      // Upload document
      .addCase(uploadDoctorDocument.pending, (state) => {
        state.status = 'loading';
      })
      .addCase(uploadDoctorDocument.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.documents.push(action.payload);
      })
      .addCase(uploadDoctorDocument.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to upload document';
      })
      
      // Fetch documents
      .addCase(fetchDoctorDocuments.pending, (state) => {
        state.status = 'loading';
      })
      .addCase(fetchDoctorDocuments.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.documents = action.payload;
      })
      .addCase(fetchDoctorDocuments.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to fetch documents';
      });
  },
});

export const { clearDoctorData } = doctorSlice.actions;

export const selectAllDoctors = (state) => state.doctor.doctors;
export const selectDoctorSpecialities = (state) => state.doctor.specialities;
export const selectDoctorLocations = (state) => state.doctor.locations;
export const selectDoctorSearchResults = (state) => state.doctor.searchResults;
export const selectDoctorAvailability = (state) => state.doctor.availability;
export const selectDoctorSlots = (state) => state.doctor.slots;
export const selectDoctorDocuments = (state) => state.doctor.documents;
export const selectDoctorStatus = (state) => state.doctor.status;
export const selectDoctorError = (state) => state.doctor.error;

export default doctorSlice.reducer;