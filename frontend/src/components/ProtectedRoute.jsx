import { Navigate, useLocation } from 'react-router-dom';
import { useState, useEffect } from 'react';
import axios from 'axios';

const ProtectedRoute = ({ children, allowedRoles = [], requireVerification = true }) => {
  const location = useLocation();
  const [isVerified, setIsVerified] = useState(null);
  const [loading, setLoading] = useState(true);
  const token = localStorage.getItem('token');
  const userData = localStorage.getItem('user');

  useEffect(() => {
    const checkVerification = async () => {
      if (!requireVerification) {
        setLoading(false);
        return;
      }

      try {
        const response = await axios.get('http://localhost:8000/api/email/verify/check', {
          headers: { Authorization: `Bearer ${token}` }
        });
        setIsVerified(response.data.verified);
      } catch (error) {
        console.error('Error checking verification status:', error);
        setIsVerified(false);
      } finally {
        setLoading(false);
      }
    };

    if (token && userData) {
      checkVerification();
    } else {
      setLoading(false);
    }
  }, [token, userData, requireVerification]);

  if (!token || !userData) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  try {
    const user = JSON.parse(userData);
    
    // If specific roles are required, check if user has the right role
    if (allowedRoles.length > 0 && !allowedRoles.includes(user.role)) {
      // Redirect to appropriate dashboard based on role
      const redirectPath = user.role === 'patient' ? '/patient' : 
                          user.role === 'medecin' ? '/doctor' : 
                          '/dashboard';
      return <Navigate to={redirectPath} replace />;
    }

    // If verification is required and still loading, show loading state
    if (requireVerification && loading) {
      return (
        <div className="container mt-5">
          <div className="text-center">
            <div className="spinner-border" role="status">
              <span className="visually-hidden">Loading...</span>
            </div>
            <p className="mt-3">Checking verification status...</p>
          </div>
        </div>
      );
    }

    // If verification is required and user is not verified, redirect to verify email page
    if (requireVerification && isVerified === false) {
      return <Navigate to="/verify-email" replace />;
    }
    
    return children;
  } catch (error) {
    console.error('Error parsing user data:', error);
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    return <Navigate to="/login" state={{ from: location }} replace />;
  }
};

export default ProtectedRoute;