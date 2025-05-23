import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import axios from '../../api/axios';

// Async thunks for user data
export const fetchUserProfile = createAsyncThunk(
  'user/fetchProfile',
  async (_, { getState, rejectWithValue }) => {
    try {
      const { auth } = getState();
      if (!auth.user) return null;
      
      const role = auth.user.role;
      let endpoint = '';
      
      if (role === 'patient') {
        endpoint = '/api/patient/profile';
      } else if (role === 'medecin') {
        endpoint = '/api/doctor/profile';
      } else if (role === 'admin') {
        endpoint = '/api/admin/profile';
      }
      
      if (!endpoint) return null;
      
      const response = await axios.get(endpoint);
      return response.data;
    } catch (error) {
      return rejectWithValue(error.response.data);
    }
  }
);

export const updateUserProfile = createAsyncThunk(
  'user/updateProfile',
  async (profileData, { getState, rejectWithValue }) => {
    try {
      const { auth } = getState();
      if (!auth.user) return null;
      
      const role = auth.user.role;
      let endpoint = '';
      
      if (role === 'patient') {
        endpoint = '/api/patient/profile';
      } else if (role === 'medecin') {
        endpoint = '/api/doctor/profile';
      } else if (role === 'admin') {
        endpoint = '/api/admin/profile';
      }
      
      if (!endpoint) return null;
      
      const response = await axios.put(endpoint, profileData);
      return response.data;
    } catch (error) {
      return rejectWithValue(error.response.data);
    }
  }
);

export const updateUserPassword = createAsyncThunk(
  'user/updatePassword',
  async (passwordData, { getState, rejectWithValue }) => {
    try {
      const { auth } = getState();
      if (!auth.user) return null;
      
      const role = auth.user.role;
      let endpoint = '';
      
      if (role === 'patient') {
        endpoint = '/api/patient/profile/password';
      } else if (role === 'medecin') {
        endpoint = '/api/doctor/profile/password';
      } else if (role === 'admin') {
        endpoint = '/api/admin/profile/password';
      }
      
      if (!endpoint) return null;
      
      const response = await axios.put(endpoint, passwordData);
      return response.data;
    } catch (error) {
      return rejectWithValue(error.response.data);
    }
  }
);

export const uploadUserPhoto = createAsyncThunk(
  'user/uploadPhoto',
  async (photoData, { getState, rejectWithValue }) => {
    try {
      const { auth } = getState();
      if (!auth.user) return null;
      
      const role = auth.user.role;
      let endpoint = '';
      
      if (role === 'patient') {
        endpoint = '/api/patient/profile/photo';
      } else if (role === 'medecin') {
        endpoint = '/api/doctor/profile/photo';
      } else if (role === 'admin') {
        endpoint = '/api/admin/profile/photo';
      }
      
      if (!endpoint) return null;
      
      const formData = new FormData();
      formData.append('photo', photoData);
      
      const response = await axios.post(endpoint, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      return response.data;
    } catch (error) {
      return rejectWithValue(error.response.data);
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
            state.profile.user.photo = action.payload.photo_url;
          }
        }
      })
      .addCase(uploadUserPhoto.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Failed to upload photo';
      });
  },
});

export const { clearProfile } = userSlice.actions;

export const selectUserProfile = (state) => state.user.profile;
export const selectUserStatus = (state) => state.user.status;
export const selectUserError = (state) => state.user.error;

export default userSlice.reducer;