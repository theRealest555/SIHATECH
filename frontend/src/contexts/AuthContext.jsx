import { createContext, useState, useEffect, useCallback } from 'react';
import PropTypes from 'prop-types';
import { useDispatch, useSelector } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import axios from '../api/axios';
import { login as loginAction, register as registerAction, logout as logoutAction, checkAuth, clearCredentials, setCredentials, selectCurrentUser } from '../redux/slices/authSlice';
const AuthContext = createContext(null);
export const AuthProvider = ({ children }) => {
  const dispatch = useDispatch();
  const user = useSelector(selectCurrentUser);
  const [initializing, setInitializing] = useState(true);
  const [loading, setLoading] = useState(false);
  const [authError, setAuthError] = useState(null);
  const navigate = useNavigate();
  const fetchUser = useCallback(async () => {
    const data = await dispatch(checkAuth()).unwrap();
    return data?.user || null;
  }, [dispatch]);
  useEffect(() => {
    const interceptor = axios.interceptors.response.use(response => response, error => {
      if (error.response?.status === 401) dispatch(clearCredentials());
      return Promise.reject(error);
    });
    return () => axios.interceptors.response.eject(interceptor);
  }, [dispatch]);
  useEffect(() => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    fetchUser().catch(() => setAuthError('Unable to check your session. Please retry.')).finally(() => setInitializing(false));
  }, [fetchUser]);
  const authenticate = async action => {
    setLoading(true); setAuthError(null);
    try { await dispatch(action).unwrap(); return true; }
    catch (error) { setAuthError(Object.values(error.errors || {}).flat().join(' ') || error.message || 'Authentication failed.'); return false; }
    finally { setLoading(false); }
  };
  const login = (credentials, isAdmin = false) => authenticate(loginAction({ ...credentials, isAdmin }));
  const register = data => authenticate(registerAction(data));
  const logout = async () => {
    const succeeded = await authenticate(logoutAction());
    if (succeeded) navigate('/login');
    return succeeded;
  };
  const setUser = value => dispatch(setCredentials({ user: typeof value === 'function' ? value(user) : value }));
  const completeDoctorProfile = async data => {
    setLoading(true);
    try { const response = await axios.post('/api/doctor/complete-profile', data); await fetchUser(); return { success: true, data: response.data }; }
    catch (error) { setAuthError(error.response?.data?.message || 'Profile update failed.'); return { success: false, error: error.response?.data }; }
    finally { setLoading(false); }
  };
  return <AuthContext.Provider value={{ user, loading, initializing, authError, login, register, logout, fetchUser, completeDoctorProfile, setUser, setLoading, setAuthError }}>{children}</AuthContext.Provider>;
};
export default AuthContext;
AuthProvider.propTypes = { children: PropTypes.node.isRequired };
