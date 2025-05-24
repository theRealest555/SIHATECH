import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import axios from '../../api/axios';
import { API_URLS } from '../../constants/apiUrls';

/**
 * Fetch the current user's profile
 * Routes: 
 * - GET /api/patient/profile (for patients)
 * - GET /api/doctor/profile (for doctors)
 * - GET /api/admin/profile (for admins)
 */
export const fetchUserProfile = createAsyncThunk(
  'user/fetchProfile',
  async (_, { getState, rejectWithValue }) => {
    try {
      const { auth } = getState();
      if (!auth.user) return null;
      
      const role = auth.user.role;
      let endpoint = '';
      
      if (role === 'patient') {
        endpoint = API_URLS.PATIENT.PROFILE;
      } else if (role === 'medecin') {
        endpoint = API_URLS.DOCTOR.PROFILE;
      } else if (role === 'admin') {
        endpoint = '/api/admin/profile'; // Not defined in our API_URLS yet
      }
      
      if (!endpoint) return null;
      
      const response = await axios.get(endpoint);
      return response.data;
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to fetch profile',
        errors: error.response?.data?.errors || {}
      });
    }
  }
);

/**
 * Update the user's profile
 * Routes:
 * - PUT /api/patient/profile (for patients)
 * - PUT /api/doctor/profile (for doctors)
 * - PUT /api/admin/profile (for admins)
 */
export const updateUserProfile = createAsyncThunk(
  'user/updateProfile',
  async (profileData, { getState, rejectWithValue }) => {
    try {
      const { auth } = getState();
      if (!auth.user) return null;
      
      const role = auth.user.role;
      let endpoint = '';
      
      if (role === 'patient') {
        endpoint = API_URLS.PATIENT.UPDATE_PROFILE;
      } else if (role === 'medecin') {
        endpoint = API_URLS.DOCTOR.UPDATE_PROFILE;
      } else if (role === 'admin') {
        endpoint = '/api/admin/profile'; // Not defined in our API_URLS yet
      }
      
      if (!endpoint) return null;
      
      const response = await axios.put(endpoint, profileData);
      return response.data;
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to update profile',
        errors: error.response?.data?.errors || {}
      });
    }
  }
);

/**
 * Update the user's password
 * Routes:
 * - PUT /api/patient/profile/password (for patients)
 * - PUT /api/doctor/profile/password (for doctors)
 * - PUT /api/admin/profile/password (for admins)
 */
export const updateUserPassword = createAsyncThunk(
  'user/updatePassword',
  async (passwordData, { getState, rejectWithValue }) => {
    try {
      const { auth } = getState();
      if (!auth.user) return null;
      
      const role = auth.user.role;
      let endpoint = '';
      
      if (role === 'patient') {
        endpoint = API_URLS.PATIENT.UPDATE_PASSWORD;
      } else if (role === 'medecin') {
        endpoint = API_URLS.DOCTOR.UPDATE_PASSWORD;
      } else if (role === 'admin') {
        endpoint = '/api/admin/profile/password'; // Not defined in our API_URLS yet
      }
      
      if (!endpoint) return null;
      
      const response = await axios.put(endpoint, passwordData);
      return response.data;
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to update password',
        errors: error.response?.data?.errors || {}
      });
    }
  }
);

/**
 * Upload user profile photo
 * Routes:
 * - POST /api/patient/profile/photo (for patients)
 * - POST /api/doctor/profile/photo (for doctors)
 * - POST /api/admin/profile/photo (for admins)
 */
export const uploadUserPhoto = createAsyncThunk(
  'user/uploadPhoto',
  async (photoFile, { getState, rejectWithValue }) => {
    try {
      const { auth } = getState();
      if (!auth.user) return null;
      
      const role = auth.user.role;
      let endpoint = '';
      
      if (role === 'patient') {
        endpoint = API_URLS.PATIENT.UPDATE_PHOTO;
      } else if (role === 'medecin') {
        endpoint = API_URLS.DOCTOR.UPDATE_PHOTO;
      } else if (role === 'admin') {
        endpoint = '/api/admin/profile/photo'; // Not defined in our API_URLS yet
      }
      
      if (!endpoint) return null;
      
      const formData = new FormData();
      formData.append('photo', photoFile);
      
      const response = await axios.post(endpoint, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      return response.data;
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to upload photo',
        errors: error.response?.data?.errors || {}
      });
    }
  }
);

const initialState = {
  profile: null,
  status: 'idle', // 'idle' | 'loading' | 'succeeded' | 'failed'
  error: null,
};

const userSlice = createSlice({
  name: 'user',
  initialState,
  reducers: {
    clearProfile: (state) => {
      state.profile = null;
      state.status = 'idle';
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      // Fetch profile
      .addCase(fetchUserProfile.pending, (state) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(fetchUserProfile.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.profile = action.payload;
      })
      .addCase(fetchUserProfile.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to fetch profile';
      })
      // Update profile
      .addCase(updateUserProfile.pending, (state) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(updateUserProfile.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.profile = action.payload;
      })
      .addCase(updateUserProfile.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to update profile';
      })
      // Update password
      .addCase(updateUserPassword.pending, (state) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(updateUserPassword.fulfilled, (state) => {
        state.status = 'succeeded';
      })
      .addCase(updateUserPassword.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to update password';
      })
      // Upload photo
      .addCase(uploadUserPhoto.pending, (state) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(uploadUserPhoto.fulfilled, (state, action) => {
        state.status = 'succeeded';
        if (state.profile && action.payload) {
          if (state.profile.user) {
            state.profile.user.photo = action.payload.photo_url || action.payload.path;
          }
        }
      })
      .addCase(uploadUserPhoto.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to upload photo';
      });
  },
});

// Actions
export const { clearProfile } = userSlice.actions;

// Selectors
export const selectUserProfile = (state) => state.user.profile;
export const selectUserStatus = (state) => state.user.status;
export const selectUserError = (state) => state.user.error;

export default userSlice.reducer;