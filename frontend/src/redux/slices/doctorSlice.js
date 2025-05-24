import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import axios from '../../api/axios';
import { API_URLS } from '../../constants/apiUrls';

/**
 * Fetch all doctors
 * Matches backend route: GET /api/doctors
 */
export const fetchAllDoctors = createAsyncThunk(
  'doctor/fetchAll',
  async (_, { rejectWithValue }) => {
    try {
      const response = await axios.get(API_URLS.DOCTORS.LIST);
      return response.data.data || [];
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to fetch doctors'
      });
    }
  }
);

/**
 * Fetch doctor specialities
 * Matches backend route: GET /api/doctors/specialities
 */
export const fetchDoctorSpecialities = createAsyncThunk(
  'doctor/fetchSpecialities',
  async (_, { rejectWithValue }) => {
    try {
      const response = await axios.get(API_URLS.DOCTORS.SPECIALITIES);
      return response.data.data || [];
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to fetch specialities'
      });
    }
  }
);

/**
 * Fetch doctor locations
 * Matches backend route: GET /api/doctors/locations
 */
export const fetchDoctorLocations = createAsyncThunk(
  'doctor/fetchLocations',
  async (_, { rejectWithValue }) => {
    try {
      const response = await axios.get(API_URLS.DOCTORS.LOCATIONS);
      return response.data.data || [];
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to fetch locations'
      });
    }
  }
);

/**
 * Search for doctors
 * Matches backend route: GET /api/doctors/search
 */
export const searchDoctors = createAsyncThunk(
  'doctor/search',
  async (filters, { rejectWithValue }) => {
    try {
      const response = await axios.get(API_URLS.DOCTORS.SEARCH, { params: filters });
      return response.data.data || [];
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to search doctors'
      });
    }
  }
);

/**
 * Fetch doctor availability
 * Matches backend route: GET /api/doctors/{doctorId}/availability
 */
export const fetchDoctorAvailability = createAsyncThunk(
  'doctor/fetchAvailability',
  async (doctorId, { rejectWithValue }) => {
    try {
      const response = await axios.get(API_URLS.DOCTORS.AVAILABILITY(doctorId));
      // The backend returns data in the format: { status: 'success', data: { schedule: {}, leaves: [] } }
      return response.data.data || { schedule: {}, leaves: [] };
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to fetch availability'
      });
    }
  }
);

/**
 * Fetch doctor available slots
 * Matches backend route: GET /api/doctors/{doctorId}/slots
 */
export const fetchDoctorSlots = createAsyncThunk(
  'doctor/fetchSlots',
  async ({ doctorId, date }, { rejectWithValue }) => {
    try {
      const response = await axios.get(API_URLS.DOCTORS.SLOTS(doctorId), { params: { date } });
      // The backend returns data in the format: { status: 'success', data: ['09:00', '09:30', ...], meta: {} }
      return { doctorId, date, slots: response.data.data || [] };
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to fetch slots'
      });
    }
  }
);

/**
 * Update doctor schedule
 * Matches backend route: POST /api/doctor/schedule
 */
export const updateDoctorSchedule = createAsyncThunk(
  'doctor/updateSchedule',
  async ({ schedule }, { rejectWithValue }) => {
    try {
      const response = await axios.post(API_URLS.DOCTOR.SCHEDULE, { schedule });
      return response.data.data || {};
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to update schedule',
        errors: error.response?.data?.errors || {}
      });
    }
  }
);

/**
 * Create doctor leave
 * Matches backend route: POST /api/doctor/leaves
 */
export const createDoctorLeave = createAsyncThunk(
  'doctor/createLeave',
  async ({ leaveData }, { rejectWithValue }) => {
    try {
      const response = await axios.post(API_URLS.DOCTOR.LEAVES, leaveData);
      return response.data.data || {};
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to create leave',
        errors: error.response?.data?.errors || {}
      });
    }
  }
);

/**
 * Delete doctor leave
 * Matches backend route: DELETE /api/doctor/leaves/{leaveId}
 */
export const deleteDoctorLeave = createAsyncThunk(
  'doctor/deleteLeave',
  async ({ leaveId }, { rejectWithValue }) => {
    try {
      await axios.delete(API_URLS.DOCTOR.LEAVE(leaveId));
      return { leaveId };
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to delete leave'
      });
    }
  }
);

/**
 * Upload doctor document
 * Matches backend route: POST /api/doctor/documents
 */
export const uploadDoctorDocument = createAsyncThunk(
  'doctor/uploadDocument',
  async ({ file, type }, { rejectWithValue }) => {
    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('type', type);
      
      const response = await axios.post(API_URLS.DOCTOR.DOCUMENTS, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      return response.data.document || {};
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to upload document',
        errors: error.response?.data?.errors || {}
      });
    }
  }
);

/**
 * Fetch doctor documents
 * Matches backend route: GET /api/doctor/documents
 */
export const fetchDoctorDocuments = createAsyncThunk(
  'doctor/fetchDocuments',
  async (_, { rejectWithValue }) => {
    try {
      const response = await axios.get(API_URLS.DOCTOR.DOCUMENTS);
      return response.data.documents || [];
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to fetch documents'
      });
    }
  }
);

/**
 * Complete doctor profile (after social registration)
 * Matches backend route: POST /api/doctor/complete-profile
 */
export const completeDoctorProfile = createAsyncThunk(
  'doctor/completeProfile',
  async (profileData, { rejectWithValue }) => {
    try {
      const response = await axios.post(API_URLS.DOCTOR.COMPLETE_PROFILE, profileData);
      return response.data;
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to complete profile',
        errors: error.response?.data?.errors || {}
      });
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
        state.error = null;
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
        state.error = null;
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
        state.error = null;
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
        state.error = null;
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
        state.error = null;
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
        state.error = null;
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
        state.error = null;
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
        state.error = null;
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
        state.error = null;
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
        state.error = null;
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
        state.error = null;
      })
      .addCase(fetchDoctorDocuments.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.documents = action.payload;
      })
      .addCase(fetchDoctorDocuments.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to fetch documents';
      })
      
      // Complete profile
      .addCase(completeDoctorProfile.pending, (state) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(completeDoctorProfile.fulfilled, (state) => {
        state.status = 'succeeded';
      })
      .addCase(completeDoctorProfile.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to complete profile';
      });
  },
});

// Actions
export const { clearDoctorData } = doctorSlice.actions;

// Selectors
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