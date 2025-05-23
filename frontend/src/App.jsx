import { Routes, Route, Navigate } from 'react-router-dom';
import { ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import Navbar from './components/Navbar';
import Register from './pages/auth/Register';
import Login from './pages/auth/Login';
import AdminLogin from './pages/auth/AdminLogin';
import VerifyEmail from './pages/auth/VerifyEmail';
import DoctorCompleteProfile from './pages/auth/DoctorCompleteProfile';
import PatientProfile from './pages/patient/Profile';
import DoctorProfile from './pages/doctor/Profile';
import DoctorCalendar from './components/DoctorCalendar';
import DoctorSearch from './components/DoctorSearch';
import ScheduleForm from './components/ScheduleForm';
import LeaveForm from './components/LeaveForm';
import PatientAppointments from './components/PatientAppointments';
import Dashboard from './pages/Dashboard';
import ProtectedRoute from './components/ProtectedRoute';
import ApiDiagnostic from './pages/auth/ApiDiagnostic';

const App = () => {
  return (
    <div className="min-vh-100">
      <Navbar />
      <main className="container-fluid px-0">
        <ToastContainer position="top-right" autoClose={3000} />
        <Routes>
          {/* Public Routes */}
          <Route path="/register" element={<Register />} />
          <Route path="/login" element={<Login />} />
          <Route path="/admin/login" element={<AdminLogin />} />
          <Route path="/verify-email" element={<VerifyEmail />} />

          {/* Doctor Profile Completion Route (requires authentication but not verification) */}
          <Route path="/doctor/complete-profile" element={
            <ProtectedRoute allowedRoles={['medecin']} requireVerification={false}>
              <DoctorCompleteProfile />
            </ProtectedRoute>
          } />

          {/* Protected Routes */}
          <Route path="/dashboard" element={
            <ProtectedRoute>
              <Dashboard />
            </ProtectedRoute>
          } />
          
          <Route path="/patient/profile" element={
            <ProtectedRoute allowedRoles={['patient']}>
              <PatientProfile />
            </ProtectedRoute>
          } />
          
          <Route path="/doctor/profile" element={
            <ProtectedRoute allowedRoles={['medecin']}>
              <DoctorProfile />
            </ProtectedRoute>
          } />

          <Route path="/doctor" element={
            <ProtectedRoute allowedRoles={['medecin']}>
              <DoctorCalendar />
            </ProtectedRoute>
          } />
          
          <Route path="/patient" element={
            <ProtectedRoute allowedRoles={['patient']}>
              <DoctorSearch />
            </ProtectedRoute>
          } />
          
          <Route path="/doctor-calendar/:doctorId" element={
            <ProtectedRoute>
              <DoctorCalendar />
            </ProtectedRoute>
          } />
          
          <Route path="/doctor/:doctorId/appointments" element={
            <ProtectedRoute>
              <PatientAppointments />
            </ProtectedRoute>
          } />
          
          <Route path="/schedule" element={
            <ProtectedRoute allowedRoles={['medecin']}>
              <ScheduleForm />
            </ProtectedRoute>
          } />
          
          <Route path="/leaves" element={
            <ProtectedRoute allowedRoles={['medecin']}>
              <LeaveForm />
            </ProtectedRoute>
          } />
          
          {/* Default Route */}
          <Route path="/" element={<Navigate to="/dashboard" />} />
          
          {/* Catch all route */}
          <Route path="*" element={<Navigate to="/dashboard" />} />
          <Route path="/api-diagnostic" element={<ApiDiagnostic />} />
        </Routes>
      </main>
    </div>
  );
};

export default App;