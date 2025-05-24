import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import axios from '../../api/axios';
import { API_URLS } from '../../constants/apiUrls';

/**
 * Login user async thunk
 * Matches backend route: POST /api/login
 */
export const login = createAsyncThunk(
  'auth/login',
  async (credentials, { rejectWithValue }) => {
    try {
      const response = await axios.post(API_URLS.AUTH.LOGIN, credentials);
      
      // Backend returns data inside a 'data' property for some endpoints
      return response.data?.data || response.data;
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Login failed',
        errors: error.response?.data?.errors || {}
      });
    }
  }
);

/**
 * Register user async thunk
 * Matches backend route: POST /api/register
 */
export const register = createAsyncThunk(
  'auth/register',
  async (userData, { rejectWithValue }) => {
    try {
      const response = await axios.post(API_URLS.AUTH.REGISTER, userData);
      return response.data;
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Registration failed',
        errors: error.response?.data?.errors || {}
      });
    }
  }
);

/**
 * Logout user async thunk
 * Matches backend route: POST /api/logout
 */
export const logout = createAsyncThunk(
  'auth/logout',
  async (_, { rejectWithValue }) => {
    try {
      await axios.post(API_URLS.AUTH.LOGOUT);
      return null;
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Logout failed'
      });
    }
  }
);

/**
 * Check if user is authenticated
 * Matches backend route: GET /api/user
 */
export const checkAuth = createAsyncThunk(
  'auth/checkAuth',
  async (_, { rejectWithValue }) => {
    try {
      const token = localStorage.getItem('token');
      if (!token) return null;
      
      const response = await axios.get(API_URLS.AUTH.USER);
      return {
        user: response.data,
        token,
      };
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Authentication check failed'
      });
    }
  }
);

/**
 * Check email verification status
 * Matches backend route: GET /api/email/verify/check
 */
export const checkEmailVerification = createAsyncThunk(
  'auth/checkEmailVerification',
  async (_, { rejectWithValue }) => {
    try {
      const response = await axios.get(API_URLS.AUTH.VERIFY_EMAIL);
      return response.data.verified;
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to check email verification'
      });
    }
  }
);

/**
 * Resend email verification
 * Matches backend route: POST /api/email/verification-notification
 */
export const resendVerificationEmail = createAsyncThunk(
  'auth/resendVerification',
  async (_, { rejectWithValue }) => {
    try {
      const response = await axios.post(API_URLS.AUTH.RESEND_VERIFICATION);
      return response.data;
    } catch (error) {
      return rejectWithValue({
        message: error.response?.data?.message || 'Failed to resend verification email'
      });
    }
  }
);

const initialState = {
  user: JSON.parse(localStorage.getItem('user')) || null,
  token: localStorage.getItem('token') || null,
  status: 'idle', // 'idle' | 'loading' | 'succeeded' | 'failed'
  error: null,
  isAuthenticated: !!localStorage.getItem('token'),
  isEmailVerified: null,
  emailVerificationStatus: 'idle'
};

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setCredentials: (state, action) => {
      const { user, token } = action.payload;
      state.user = user;
      state.token = token;
      state.isAuthenticated = true;
      localStorage.setItem('token', token);
      localStorage.setItem('user', JSON.stringify(user));
    },
    clearCredentials: (state) => {
      state.user = null;
      state.token = null;
      state.isAuthenticated = false;
      localStorage.removeItem('token');
      localStorage.removeItem('user');
    },
  },
  extraReducers: (builder) => {
    builder
      // Login
      .addCase(login.pending, (state) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(login.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.user = action.payload.user;
        state.token = action.payload.token;
        state.isAuthenticated = true;
        localStorage.setItem('token', action.payload.token);
        localStorage.setItem('user', JSON.stringify(action.payload.user));
      })
      .addCase(login.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Login failed';
      })
      
      // Register
      .addCase(register.pending, (state) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(register.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.user = action.payload.user;
        state.token = action.payload.token;
        state.isAuthenticated = true;
        localStorage.setItem('token', action.payload.token);
        localStorage.setItem('user', JSON.stringify(action.payload.user));
      })
      .addCase(register.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message || 'Registration failed';
      })
      
      // Logout
      .addCase(logout.pending, (state) => {
        state.status = 'loading';
      })
      .addCase(logout.fulfilled, (state) => {
        state.status = 'idle';
        state.user = null;
        state.token = null;
        state.isAuthenticated = false;
        localStorage.removeItem('token');
        localStorage.removeItem('user');
      })
      .addCase(logout.rejected, (state) => {
        state.status = 'failed';
        // Still clear the user data even if the server logout fails
        state.user = null;
        state.token = null;
        state.isAuthenticated = false;
        localStorage.removeItem('token');
        localStorage.removeItem('user');
      })
      
      // Check Auth
      .addCase(checkAuth.pending, (state) => {
        state.status = 'loading';
      })
      .addCase(checkAuth.fulfilled, (state, action) => {
        state.status = 'idle';
        if (action.payload) {
          state.user = action.payload.user;
          state.token = action.payload.token;
          state.isAuthenticated = true;
        } else {
          state.user = null;
          state.token = null;
          state.isAuthenticated = false;
          localStorage.removeItem('token');
          localStorage.removeItem('user');
        }
      })
      .addCase(checkAuth.rejected, (state) => {
        state.status = 'failed';
        state.user = null;
        state.token = null;
        state.isAuthenticated = false;
        localStorage.removeItem('token');
        localStorage.removeItem('user');
      })
      
      // Email Verification Check
      .addCase(checkEmailVerification.pending, (state) => {
        state.emailVerificationStatus = 'loading';
      })
      .addCase(checkEmailVerification.fulfilled, (state, action) => {
        state.emailVerificationStatus = 'succeeded';
        state.isEmailVerified = action.payload;
      })
      .addCase(checkEmailVerification.rejected, (state) => {
        state.emailVerificationStatus = 'failed';
      })
      
      // Resend Verification Email
      .addCase(resendVerificationEmail.pending, (state) => {
        state.emailVerificationStatus = 'loading';
      })
      .addCase(resendVerificationEmail.fulfilled, (state) => {
        state.emailVerificationStatus = 'succeeded';
      })
      .addCase(resendVerificationEmail.rejected, (state) => {
        state.emailVerificationStatus = 'failed';
      });
  },
});

// Actions
export const { setCredentials, clearCredentials } = authSlice.actions;

// Selectors
export const selectCurrentUser = (state) => state.auth.user;
export const selectIsAuthenticated = (state) => state.auth.isAuthenticated;
export const selectIsEmailVerified = (state) => state.auth.isEmailVerified;
export const selectAuthStatus = (state) => state.auth.status;
export const selectEmailVerificationStatus = (state) => state.auth.emailVerificationStatus;
export const selectAuthError = (state) => state.auth.error;

export default authSlice.reducer;