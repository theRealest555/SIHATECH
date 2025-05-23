import { useEffect } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { ToastContainer } from 'react-toastify';
import { useDispatch } from 'react-redux';
import 'react-toastify/dist/ReactToastify.css';

import { checkAuth } from './redux/slices/authSlice';
import { initializeCSRF } from './api/axios';

// Layouts
import MainLayout from './components/layouts/MainLayout';

// Auth pages
import Login from './pages/auth/Login';
import Register from './pages/auth/Register';
import AdminLogin from './pages/auth/AdminLogin';
import VerifyEmail from './pages/auth/VerifyEmail';
import DoctorCompleteProfile from './pages/auth/DoctorCompleteProfile';

// Dashboard and profiles
import Dashboard from './pages/Dashboard';
import PatientProfile from './pages/patient/Profile';
import DoctorProfile from './pages/doctor/Profile';

// Components
import PrivateRoute from './components/ui/PrivateRoute';
import DoctorCalendar from './components/DoctorCalendar';
import DoctorSearch from './components/DoctorSearch';
import PatientAppointments from './components/PatientAppointments';
import ScheduleForm from './components/ScheduleForm';
import LeaveForm from './components/LeaveForm';

const App = () => {
  const dispatch = useDispatch();

  useEffect(() => {
    // Initialize CSRF token
    initializeCSRF();
    
    // Check if user is authenticated
    dispatch(checkAuth());
  }, [dispatch]);

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      
      <Routes>
        {/* Public Routes */}
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/admin/login" element={<AdminLogin />} />
        <Route path="/verify-email" element={<VerifyEmail />} />

        {/* Main Layout with Protected Routes */}
        <Route path="/" element={<MainLayout />}>
          {/* Doctor Profile Completion Route (requires authentication but not verification) */}
          <Route path="doctor/complete-profile" element={
            <PrivateRoute allowedRoles={['medecin']} requireVerification={false}>
              <DoctorCompleteProfile />
            </PrivateRoute>
          } />

          {/* Dashboard */}
          <Route path="dashboard" element={
            <PrivateRoute>
              <Dashboard />
            </PrivateRoute>
          } />
          
          {/* Patient Routes */}
          <Route path="patient/profile" element={
            <PrivateRoute allowedRoles={['patient']}>
              <PatientProfile />
            </PrivateRoute>
          } />
          
          <Route path="patient" element={
            <PrivateRoute allowedRoles={['patient']}>
              <DoctorSearch />
            </PrivateRoute>
          } />
          
          {/* Doctor Routes */}
          <Route path="doctor/profile" element={
            <PrivateRoute allowedRoles={['medecin']}>
              <DoctorProfile />
            </PrivateRoute>
          } />

          <Route path="doctor" element={
            <PrivateRoute allowedRoles={['medecin']}>
              <DoctorCalendar />
            </PrivateRoute>
          } />
          
          <Route path="doctor-calendar/:doctorId" element={
            <PrivateRoute>
              <DoctorCalendar />
            </PrivateRoute>
          } />
          
          <Route path="doctor/:doctorId/appointments" element={
            <PrivateRoute>
              <PatientAppointments />
            </PrivateRoute>
          } />
          
          <Route path="schedule" element={
            <PrivateRoute allowedRoles={['medecin']}>
              <ScheduleForm />
            </PrivateRoute>
          } />
          
          <Route path="leaves" element={
            <PrivateRoute allowedRoles={['medecin']}>
              <LeaveForm />
            </PrivateRoute>
          } />
          
          {/* Default Route */}
          <Route index element={<Navigate to="/dashboard" replace />} />
        </Route>
        
        {/* Catch all route */}
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </>
  );
};

export default App;