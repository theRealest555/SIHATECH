// src/pages/Dashboard.jsx
import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

const DashboardPage = () => {
    const { user, loading } = useAuth();
    const navigate = useNavigate();

    useEffect(() => {
        if (!loading && user) {
            switch (user.role) {
                case 'admin':
                    navigate('/admin/dashboard', { replace: true });
                    break;
                case 'medecin':
                     // Check if doctor profile is complete
                    if (user.doctor_profile_completed === false || user.doctor_profile_completed === 0 || !user.doctor_profile_completed) {
                         navigate('/doctor/complete-profile', { replace: true });
                    } else {
                        navigate('/doctor/dashboard', { replace: true });
                    }
                    break;
                case 'patient':
                    navigate('/patient/dashboard', { replace: true });
                    break;
                default:
                    // Fallback for unknown roles or if role is not set
                    console.warn('Unknown user role or role not set, redirecting to home.');
                    navigate('/', { replace: true });
                    break;
            }
        } else if (!loading && !user) {
            // Should not happen if PrivateRoute is working, but as a safeguard
            navigate('/login', { replace: true });
        }
    }, [user, loading, navigate]);

    if (loading || !user) {
        return (
            <div className="flex flex-col items-center justify-center min-h-screen bg-gradient-to-br from-indigo-50 to-blue-100">
                <div className="flex items-center justify-center mb-6">
                    <span className="inline-block w-12 h-12 border-4 border-indigo-400 border-t-transparent rounded-full animate-spin"></span>
                </div>
                <div className="text-2xl font-semibold text-indigo-700">Loading Dashboard...</div>
            </div>
        );
    }

    // This content will likely not be shown due to immediate redirection
    return (
        <div className="p-6">
            <h1 className="text-2xl font-semibold text-gray-800">Redirecting to your dashboard...</h1>
        </div>
    );
};

export default DashboardPage;
