import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Container, Row, Col, Card, Button, Alert, Spinner } from 'react-bootstrap';
import axios from 'axios';
import ApiService from '../../services/api';

const VerifyEmail = () => {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [status, setStatus] = useState('pending');
  const [error, setError] = useState(null);
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);
  const [verificationLoading, setVerificationLoading] = useState(false);

  // Check if we're coming back from email verification link
  useEffect(() => {
    const verified = searchParams.get('verified');
    if (verified === '1') {
      setStatus('verified');
      setMessage('Your email has been verified successfully!');
      
      // Check if doctor needs to complete profile
      const user = JSON.parse(localStorage.getItem('user') || '{}');
      if (user.role === 'medecin') {
        checkDoctorProfileCompletion();
      } else {
        setTimeout(() => navigate('/dashboard'), 2000);
      }
    }
  }, [searchParams, navigate]);

  // Check verification status on mount
  useEffect(() => {
    checkVerificationStatus();
  }, []);

  const checkVerificationStatus = async () => {
    try {
      setVerificationLoading(true);
      const token = localStorage.getItem('token');
      if (!token) {
        navigate('/login');
        return;
      }

      const response = await axios.get('http://localhost:8000/api/email/verify/check', {
        headers: { Authorization: `Bearer ${token}` }
      });
      
      if (response.data.verified) {
        setStatus('verified');
        setMessage('Your email is already verified!');
        
        // Check if doctor needs to complete profile
        const user = JSON.parse(localStorage.getItem('user') || '{}');
        if (user.role === 'medecin') {
          checkDoctorProfileCompletion();
        } else {
          setTimeout(() => navigate('/dashboard'), 2000);
        }
      }
    } catch (err) {
      console.error('Error checking verification status:', err);
      setError('Failed to check verification status');
    } finally {
      setVerificationLoading(false);
    }
  };

  const checkDoctorProfileCompletion = async () => {
    try {
      const response = await ApiService.getProfile();
      const doctor = response.data.doctor;
      
      if (!doctor || !doctor.speciality_id) {
        setMessage('Your email is verified! Please complete your profile to continue.');
        setTimeout(() => navigate('/doctor/complete-profile'), 3000);
      } else {
        setTimeout(() => navigate('/dashboard'), 2000);
      }
    } catch (err) {
      console.error('Error checking doctor profile:', err);
      setTimeout(() => navigate('/dashboard'), 2000);
    }
  };

  const resendVerification = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const token = localStorage.getItem('token');
      if (!token) {
        navigate('/login');
        return;
      }

      await axios.post('http://localhost:8000/api/email/verification-notification', {}, {
        headers: { Authorization: `Bearer ${token}` }
      });
      
      setStatus('sent');
      setMessage('Verification email has been resent! Please check your inbox.');
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to resend verification email');
    } finally {
      setLoading(false);
    }
  };

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    navigate('/login');
  };

  if (verificationLoading) {
    return (
      <Container className="mt-5">
        <div className="text-center">
          <Spinner animation="border" variant="primary" />
          <p className="mt-3">Checking verification status...</p>
        </div>
      </Container>
    );
  }

  return (
    <div className="auth-container">
      <Container>
        <Row className="justify-content-center">
          <Col md={6} lg={5}>
            <Card className="auth-card">
              <Card.Body className="p-5 text-center">
                {status === 'verified' ? (
                  <>
                    <i className="fas fa-check-circle fa-5x text-success mb-4"></i>
                    <h2 className="mb-3">Email Verified!</h2>
                    <p className="text-muted mb-4">{message}</p>
                    <Spinner animation="border" size="sm" className="me-2" />
                    <span>Redirecting...</span>
                  </>
                ) : (
                  <>
                    <i className="fas fa-envelope fa-5x text-primary mb-4"></i>
                    <h2 className="mb-3">Verify Your Email</h2>
                    
                    {error && (
                      <Alert variant="danger" className="mb-4">
                        <i className="fas fa-exclamation-triangle me-2"></i>
                        {error}
                      </Alert>
                    )}
                    
                    {status === 'sent' && (
                      <Alert variant="success" className="mb-4">
                        <i className="fas fa-check me-2"></i>
                        {message}
                      </Alert>
                    )}
                    
                    <p className="text-muted mb-4">
                      We've sent a verification link to your email address. 
                      Please check your inbox and click the link to verify your account.
                    </p>
                    
                    <div className="bg-light p-3 rounded mb-4">
                      <p className="mb-0">
                        <small>
                          <strong>Didn't receive the email?</strong><br />
                          Check your spam folder or click the button below to resend.
                        </small>
                      </p>
                    </div>
                    
                    <div className="d-grid gap-2">
                      <Button
                        variant="primary"
                        onClick={resendVerification}
                        disabled={loading || status === 'sent'}
                      >
                        {loading ? (
                          <>
                            <Spinner animation="border" size="sm" className="me-2" />
                            Sending...
                          </>
                        ) : (
                          <>
                            <i className="fas fa-paper-plane me-2"></i>
                            Resend Verification Email
                          </>
                        )}
                      </Button>
                      
                      <Button
                        variant="outline-secondary"
                        onClick={handleLogout}
                      >
                        <i className="fas fa-sign-out-alt me-2"></i>
                        Logout
                      </Button>
                    </div>
                  </>
                )}
              </Card.Body>
            </Card>
          </Col>
        </Row>
      </Container>
    </div>
  );
};

export default VerifyEmail;