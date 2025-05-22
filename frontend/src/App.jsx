import { Routes, Route, Navigate } from 'react-router-dom';
import { ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import Register from './pages/auth/Register';
import Login from './pages/auth/Login';
import AdminLogin from './pages/auth/AdminLogin';
import VerifyEmail from './pages/auth/VerifyEmail';
import PatientProfile from './pages/patient/Profile';
import DoctorProfile from './pages/doctor/Profile';
import DoctorCalendar from './components/DoctorCalendar';
import DoctorSearch from './components/DoctorSearch';
import ScheduleForm from './components/ScheduleForm';
import LeaveForm from './components/LeaveForm';
import PatientAppointments from './components/PatientAppointments';

const App = () => {
  return (
    <div>
      <ToastContainer />
      <Routes>
        {/* Auth Routes */}
        <Route path="/register" element={<Register />} />
        <Route path="/login" element={<Login />} />
        <Route path="/admin/login" element={<AdminLogin />} />
        <Route path="/verify-email" element={<VerifyEmail />} />

        {/* Profile Routes */}
        <Route path="/patient/profile" element={<PatientProfile />} />
        <Route path="/doctor/profile" element={<DoctorProfile />} />

        {/* Protected Routes */}
        <Route path="/doctor" element={<DoctorCalendar />} />
        <Route path="/patient" element={<DoctorSearch />} />
        <Route path="/doctor-calendar/:doctorId" element={<DoctorCalendar />} />
        <Route path="/doctor/:doctorId/appointments" element={<PatientAppointments />} />
        <Route path="/schedule" element={<ScheduleForm />} />
        <Route path="/leaves" element={<LeaveForm />} />
        
        {/* Default Route */}
        <Route path="/" element={<Navigate to="/login" />} />
      </Routes>
    </div>
  );
};

export default App;