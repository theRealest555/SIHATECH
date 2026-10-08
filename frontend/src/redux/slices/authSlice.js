import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import axios from '../../api/axios';

const authRequest = (name, request) => createAsyncThunk(name, async (data, { rejectWithValue }) => {
  try { return await request(data); }
  catch (error) { return rejectWithValue({ message: error.response?.data?.message || 'Request failed', errors: error.response?.data?.errors || {} }); }
});
export const login = authRequest('auth/login', async credentials => {
  await axios.get('/sanctum/csrf-cookie');
  const { isAdmin, ...body } = credentials;
  await axios.post(isAdmin ? '/api/admin/login' : '/api/login', body);
  return (await axios.get('/api/user')).data;
});
export const register = authRequest('auth/register', async data => {
  await axios.get('/sanctum/csrf-cookie');
  await axios.post('/api/register', data);
  return (await axios.get('/api/user')).data;
});
export const logout = authRequest('auth/logout', async () => { await axios.post('/api/logout'); return null; });
export const checkAuth = authRequest('auth/checkAuth', async () => {
  try { return (await axios.get('/api/user')).data; }
  catch (error) { if (error.response?.status === 401) return null; throw error; }
});
export const checkEmailVerification = authRequest('auth/checkEmailVerification', async () => (await axios.get('/api/email/verify/check')).data.verified);
export const resendVerificationEmail = authRequest('auth/resendVerification', async () => (await axios.post('/api/email/verification-notification')).data);
const initialState = { user: null, status: 'idle', error: null, isAuthenticated: false, isEmailVerified: false, emailVerificationStatus: 'idle' };
const applyUser = (state, user) => {
  state.user = user;
  state.isAuthenticated = !!user;
  state.isEmailVerified = !!user?.email_verified_at;
};
const authSlice = createSlice({
  name: 'auth', initialState,
  reducers: {
    setCredentials: (state, action) => applyUser(state, action.payload.user),
    clearCredentials: state => applyUser(state, null),
  },
  extraReducers: builder => {
    for (const thunk of [login, register, checkAuth]) {
      builder.addCase(thunk.pending, state => { state.status = 'loading'; state.error = null; })
        .addCase(thunk.fulfilled, (state, action) => { state.status = 'succeeded'; applyUser(state, action.payload?.user || null); })
        .addCase(thunk.rejected, (state, action) => { state.status = 'failed'; state.error = action.payload?.message; });
    }
    builder.addCase(logout.fulfilled, state => { state.status = 'idle'; applyUser(state, null); })
      .addCase(checkEmailVerification.fulfilled, (state, action) => { state.isEmailVerified = action.payload; })
      .addCase(resendVerificationEmail.fulfilled, state => { state.emailVerificationStatus = 'succeeded'; });
  },
});
export const { setCredentials, clearCredentials } = authSlice.actions;
export const selectCurrentUser = state => state.auth.user;
export const selectIsAuthenticated = state => state.auth.isAuthenticated;
export const selectIsEmailVerified = state => state.auth.isEmailVerified;
export const selectAuthStatus = state => state.auth.status;
export const selectEmailVerificationStatus = state => state.auth.emailVerificationStatus;
export const selectAuthError = state => state.auth.error;
export default authSlice.reducer;
