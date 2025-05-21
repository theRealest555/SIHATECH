import { Routes, Route, Navigate } from 'react-router-dom';
import Register from './pages/auth/Register';
import Login from './pages/auth/Login';
import AdminLogin from './pages/auth/AdminLogin';
import VerifyEmail from './pages/auth/VerifyEmail';
import DoctorCalendar from './components/DoctorCalendar';
import DoctorSearch from './components/DoctorSearch';
import ScheduleForm from './components/ScheduleForm';
import LeaveForm from './components/LeaveForm';
import PatientAppointments from './components/PatientAppointments';

const App = () => {
  return (
    <div>
      <Routes>
        {/* Auth Routes */}
        <Route path="/register" element={<Register />} />
        <Route path="/login" element={<Login />} />
        <Route path="/admin/login" element={<AdminLogin />} />
        <Route path="/verify-email" element={<VerifyEmail />} />

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