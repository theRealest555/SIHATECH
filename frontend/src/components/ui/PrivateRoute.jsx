import PropTypes from 'prop-types';
// src/components/ui/PrivateRoute.jsx
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';

const PrivateRoute = ({ children, roles }) => {
    const { user, initializing } = useAuth();
    const location = useLocation();

    if (initializing) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <div>Loading user authentication...</div>
            </div>
        ); // Or a spinner component
    }

    if (!user) {
        // Not logged in, redirect to login page
        return <Navigate to="/login" state={{ from: location }} replace />;
    }

    if (!user.email_verified_at) {
        return <Navigate to="/verify-email" replace />;
    }

    // If roles are specified, check if the user has one of the required roles
    // Assuming user object has a 'role' property like: user.role = 'admin' or user.role = 'medecin'
    // Or user.roles = ['medecin', 'verified']
    // Adjust this logic based on your actual user object structure
    if (roles && roles.length > 0) {
        const userRole = user.role; // Example: user.role = "doctor"
        // If user.roles is an array: !roles.some(role => user.roles.includes(role))
        if (!userRole || !roles.includes(userRole)) {
            // Role not authorized, redirect to a fallback page (e.g., home or an unauthorized page)
            // Or to a specific dashboard based on their actual role
            let fallbackPath = '/';
            if (userRole === 'admin') fallbackPath = '/admin/dashboard';
            else if (userRole === 'medecin') fallbackPath = '/doctor/dashboard';
            else if (userRole === 'patient') fallbackPath = '/patient/dashboard';
            
            return <Navigate to={fallbackPath} state={{ from: location }} replace />;
        }
    }
    
    // Specifically for doctors, check if their profile is complete for certain routes
    // This is a common pattern for onboarding.
    // Example: if (user.role === 'medecin' && !user.doctor_profile_completed && location.pathname !== '/doctor/complete-profile') {
    //     return <Navigate to="/doctor/complete-profile" state={{ from: location }} replace />;
    // }


    return children;
};

PrivateRoute.propTypes = { children: PropTypes.node.isRequired, roles: PropTypes.arrayOf(PropTypes.oneOf(['admin', 'medecin', 'patient'])) };

export default PrivateRoute;
