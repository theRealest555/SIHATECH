// src/App.jsx
import { Suspense, lazy } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import MainLayout from './components/layouts/MainLayout';
import PrivateRoute from './components/ui/PrivateRoute';
import { useAuth } from './hooks/useAuth';


// Lazy load pages
const HomePage = lazy(() => import('./pages/HomePage'));
const LoginPage = lazy(() => import('./pages/auth/Login'));
const RegisterPage = lazy(() => import('./pages/auth/Register'));
const AdminLoginPage = lazy(() => import('./pages/auth/AdminLogin'));
const VerifyEmailPage = lazy(() => import('./pages/auth/VerifyEmail')); // Assuming this exists or will be created
const ForgotPasswordPage = lazy(() => import('./components/auth/ForgotPasswordPage'));
const ResetPasswordPage = lazy(() => import('./components/auth/ResetPasswordPage'));


// User specific Profile Pages
const DoctorProfilePage = lazy(() => import('./pages/doctor/Profile'));
const PatientProfilePage = lazy(() => import('./pages/patient/Profile'));
const DoctorCompleteProfilePage = lazy(() => import('./pages/auth/DoctorCompleteProfile'));

// Dashboards
const DashboardPage = lazy(() => import('./pages/Dashboard')); // Generic or role-based redirector
const AdminDashboardPage = lazy(() => import('./pages/admin/DashboardPage'));
const DoctorDashboardPage = lazy(() => import('./pages/doctor/DashboardPage'));
const PatientDashboardPage = lazy(() => import('./pages/patient/DashboardPage'));

// Admin Pages
const DoctorVerificationPage = lazy(() => import('./pages/admin/DoctorVerificationPage'));
const UserListPage = lazy(() => import('./pages/admin/UserListPage'));
const SpecialityManagementPage = lazy(() => import('./pages/admin/SpecialityManagementPage'));
const LanguageManagementPage = lazy(() => import('./pages/admin/LanguageManagementPage'));
const AuditLogPage = lazy(() => import('./pages/admin/AuditLogPage'));

// Doctor Pages
const DoctorAppointmentsPage = lazy(() => import('./pages/doctor/AppointmentsPage'));
const DoctorAvailabilityPage = lazy(() => import('./pages/doctor/AvailabilityPage'));
const DoctorDocumentsPage = lazy(() => import('./pages/doctor/DocumentsPage'));
const DoctorStatisticsPage = lazy(() => import('./pages/doctor/StatisticsPage'));


// Patient Pages
const PatientReviewPage = lazy(() => import('./pages/patient/ReviewPage'));
const SubscriptionPlanManagementPage = lazy(() => import('./pages/admin/SubscriptionPlanManagementPage'));
const ReviewModerationPage = lazy(() => import('./pages/admin/ReviewModerationPage'));
const AdminReportsPage = lazy(() => import('./pages/admin/ReportsPage'));
const PatientAppointmentsPage = lazy(() => import('./pages/patient/AppointmentsPage'));
const FindDoctorPage = lazy(() => import('./pages/patient/FindDoctorPage'));
const PublicDoctorProfileViewPage = lazy(() => import('./pages/patient/DoctorProfilePage')); // Public view

// Subscription Pages
const SubscriptionPlansPage = lazy(() => import('./pages/subscriptions/PlansPage'));
const SubscriptionStatusPage = lazy(() => import('./pages/subscriptions/SubscriptionStatusPage'));

// Social Auth Callback
const SocialAuthCallback = lazy(() => import('./components/SocialAuthCallback'));


function App() {
  const { user, initializing } = useAuth();

  if (initializing) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-xl font-semibold">Loading Application...</div>
        {/* You can use a spinner component here */}
      </div>
    );
  }

  return (
    <Suspense fallback={<div className="flex items-center justify-center min-h-screen">Loading page...</div>}>
      <Routes>
        {/* Public Routes */}
        <Route path="/" element={<MainLayout />}>
          <Route index element={<HomePage />} />
          <Route path="login" element={!user ? <LoginPage /> : <Navigate to="/dashboard" />} />
          <Route path="register" element={!user ? <RegisterPage /> : <Navigate to="/dashboard" />} />
          <Route path="admin/login" element={!user ? <AdminLoginPage /> : <Navigate to="/admin/dashboard" />} />
          <Route path="forgot-password" element={<ForgotPasswordPage />} />
          <Route path="reset-password/:token" element={<ResetPasswordPage />} /> {/* Ensure backend route matches */}
          <Route path="email/verify/:id/:hash" element={<VerifyEmailPage />} /> {/* Ensure backend route matches */}
          <Route path="verify-email" element={<VerifyEmailPage />} />
          <Route path="doctors" element={<FindDoctorPage />} /> {/* Public doctor search */}
          <Route path="doctors/:doctorId" element={<PublicDoctorProfileViewPage />} /> {/* Public doctor profile */}
          <Route path="subscription-plans" element={<SubscriptionPlansPage />} />
          <Route path="/auth/:provider/callback" element={<SocialAuthCallback />} />


          {/* Authenticated Routes */}
          <Route path="dashboard" element={<PrivateRoute><DashboardPage /></PrivateRoute>} />
          
          {/* Patient Routes */}
          <Route path="patient/dashboard" element={<PrivateRoute roles={['patient']}><PatientDashboardPage /></PrivateRoute>} />
          <Route path="patient/profile" element={<PrivateRoute roles={['patient']}><PatientProfilePage /></PrivateRoute>} />
          <Route path="patient/appointments/:appointmentId/review" element={<PrivateRoute roles={['patient']}><PatientReviewPage /></PrivateRoute>} />
          <Route path="patient/appointments" element={<PrivateRoute roles={['patient']}><PatientAppointmentsPage /></PrivateRoute>} />
          
          {/* Doctor Routes */}
          <Route path="doctor/dashboard" element={<PrivateRoute roles={['medecin']}><DoctorDashboardPage /></PrivateRoute>} />
          <Route path="doctor/complete-profile" element={<PrivateRoute roles={['medecin']}><DoctorCompleteProfilePage /></PrivateRoute>} />
          <Route path="doctor/profile" element={<PrivateRoute roles={['medecin']}><DoctorProfilePage /></PrivateRoute>} />
          <Route path="doctor/appointments" element={<PrivateRoute roles={['medecin']}><DoctorAppointmentsPage /></PrivateRoute>} />
          <Route path="doctor/availability" element={<PrivateRoute roles={['medecin']}><DoctorAvailabilityPage /></PrivateRoute>} />
          <Route path="doctor/documents" element={<PrivateRoute roles={['medecin']}><DoctorDocumentsPage /></PrivateRoute>} />
          <Route path="doctor/statistics" element={<PrivateRoute roles={['medecin']}><DoctorStatisticsPage /></PrivateRoute>} />


          {/* Admin Routes */}
          <Route path="admin/dashboard" element={<PrivateRoute roles={['admin']}><AdminDashboardPage /></PrivateRoute>} />
          <Route path="admin/users" element={<PrivateRoute roles={['admin']}><UserListPage /></PrivateRoute>} />
          <Route path="admin/doctors-verification" element={<PrivateRoute roles={['admin']}><DoctorVerificationPage /></PrivateRoute>} />
          <Route path="admin/specialities" element={<PrivateRoute roles={['admin']}><SpecialityManagementPage /></PrivateRoute>} />
          <Route path="admin/languages" element={<PrivateRoute roles={['admin']}><LanguageManagementPage /></PrivateRoute>} />
          <Route path="admin/subscription-plans" element={<PrivateRoute roles={['admin']}><SubscriptionPlanManagementPage /></PrivateRoute>} />
          <Route path="admin/reviews" element={<PrivateRoute roles={['admin']}><ReviewModerationPage /></PrivateRoute>} />
          <Route path="admin/audit-logs" element={<PrivateRoute roles={['admin']}><AuditLogPage /></PrivateRoute>} />
          <Route path="admin/reports" element={<PrivateRoute roles={['admin']}><AdminReportsPage /></PrivateRoute>} />

          {/* Subscription Management for Authenticated Users */}
          <Route path="my-subscription" element={<PrivateRoute><SubscriptionStatusPage /></PrivateRoute>} />

          {/* Fallback for authenticated users inside MainLayout */}
          <Route path="*" element={<Navigate to="/" />} /> 
        </Route>
      </Routes>
    </Suspense>
  );
}

export default App;
