import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { selectIsAuthenticated, selectCurrentUser } from '../../redux/slices/authSlice';
import { Spinner } from 'react-bootstrap';

const PrivateRoute = ({ children, allowedRoles = [], requireVerification = true }) => {
  const location = useLocation();
  const isAuthenticated = useSelector(selectIsAuthenticated);
  const user = useSelector(selectCurrentUser);
  
  // If not authenticated, redirect to login
  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }
  
  // If specific roles are required, check if user has one of the allowed roles
  if (allowedRoles.length > 0 && !allowedRoles.includes(user?.role)) {
    // Redirect to appropriate dashboard based on role
    const redirectPath = user?.role === 'patient' ? '/patient' : 
                         user?.role === 'medecin' ? '/doctor' : 
                         '/dashboard';
    return <Navigate to={redirectPath} replace />;
  }
  
  // If email verification is required, check if user's email is verified
  if (requireVerification && !user?.email_verified_at) {
    return <Navigate to="/verify-email" replace />;
  }
  
  return children;
};

export default PrivateRoute;