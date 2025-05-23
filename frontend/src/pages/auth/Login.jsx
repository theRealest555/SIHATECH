import React, { useState, useEffect } from 'react';
import { useNavigate, Link, useLocation } from 'react-router-dom';
import { Container, Row, Col, Card, Form, Button, Alert, Spinner } from 'react-bootstrap';
import { useDispatch, useSelector } from 'react-redux';
import { login, selectAuthStatus, selectAuthError } from '../../redux/slices/authSlice';
import { loginSchema } from '../../utils/validation';
import { initializeCSRF } from '../../api/axios';
import { toast } from 'react-toastify';

const Login = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const dispatch = useDispatch();
  const authStatus = useSelector(selectAuthStatus);
  const authError = useSelector(selectAuthError);
  
  const [formData, setFormData] = useState({
    email: '',
    password: '',
  });
  const [errors, setErrors] = useState({});
  const [socialAuthError, setSocialAuthError] = useState(null);

  useEffect(() => {
    // Initialize CSRF token
    initializeCSRF();
    
    // Listen for social auth messages from popup window
    const handleMessage = (event) => {
      if (event.data.type === 'SOCIAL_AUTH_SUCCESS') {
        // Redirect to dashboard
        navigate('/dashboard');
      } else if (event.data.type === 'SOCIAL_AUTH_ERROR') {
        setSocialAuthError(event.data.error);
      }
    };

    window.addEventListener('message', handleMessage);
    return () => window.removeEventListener('message', handleMessage);
  }, [navigate]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData({ ...formData, [name]: value });
    
    // Clear field-specific error when user types
    if (errors[name]) {
      setErrors({ ...errors, [name]: null });
    }
  };

  const validateForm = async () => {
    try {
      await loginSchema.validate(formData, { abortEarly: false });
      return true;
    } catch (validationError) {
      const newErrors = {};
      validationError.inner.forEach((error) => {
        newErrors[error.path] = error.message;
      });
      setErrors(newErrors);
      return false;
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    // Clear previous errors
    setErrors({});
    setSocialAuthError(null);
    
    // Validate form
    const isValid = await validateForm();
    if (!isValid) return;

    // Dispatch login action
    dispatch(login(formData))
      .unwrap()
      .then(() => {
        // Redirect to intended page or dashboard
        const from = location.state?.from?.pathname || '/dashboard';
        navigate(from);
      })
      .catch((error) => {
        // Handle specific validation errors from backend
        if (error.errors) {
          const newErrors = {};
          Object.entries(error.errors).forEach(([key, messages]) => {
            newErrors[key] = messages[0];
          });
          setErrors(newErrors);
        }
        
        // Show error toast
        toast.error(error.message || 'Login failed');
      });
  };

  const handleSocialAuth = (provider) => {
    window.location.href = `http://localhost:8000/api/auth/social/${provider}/redirect`;
  };

  return (
    <div className="auth-container">
      <Container>
        <Row className="justify-content-center">
          <Col md={6} lg={5}>
            <Card className="auth-card">
              <Card.Body className="p-5">
                <div className="text-center mb-4">
                  <i className="fas fa-stethoscope fa-3x text-primary mb-3"></i>
                  <h2 className="auth-header">Welcome Back</h2>
                  <p className="text-muted">Sign in to your account</p>
                </div>

                {(authError || socialAuthError) && (
                  <Alert variant="danger" className="fade-in">
                    <i className="fas fa-exclamation-triangle me-2"></i>
                    {socialAuthError || authError}
                  </Alert>
                )}

                <Form onSubmit={handleSubmit} noValidate>
                  <Form.Group className="mb-3">
                    <Form.Label>
                      <i className="fas fa-envelope me-2" /> Email Address
                    </Form.Label>
                    <Form.Control
                      type="email"
                      name="email"
                      value={formData.email}
                      onChange={handleChange}
                      placeholder="Enter your email"
                      isInvalid={!!errors.email}
                      disabled={authStatus === 'loading'}
                    />
                    <Form.Control.Feedback type="invalid">
                      {errors.email}
                    </Form.Control.Feedback>
                  </Form.Group>

                  <Form.Group className="mb-4">
                    <Form.Label>
                      <i className="fas fa-lock me-2"></i>
                      Password
                    </Form.Label>
                    <Form.Control
                      type="password"
                      name="password"
                      value={formData.password}
                      onChange={handleChange}
                      placeholder="Enter your password"
                      isInvalid={!!errors.password}
                      disabled={authStatus === 'loading'}
                    />
                    <Form.Control.Feedback type="invalid">
                      {errors.password}
                    </Form.Control.Feedback>
                  </Form.Group>

                  <Button 
                    type="submit" 
                    variant="primary" 
                    size="lg" 
                    className="w-100 mb-3"
                    disabled={authStatus === 'loading'}
                  >
                    {authStatus === 'loading' ? (
                      <>
                        <Spinner animation="border" size="sm" className="me-2" />
                        Signing In...
                      </>
                    ) : (
                      <>
                        <i className="fas fa-sign-in-alt me-2"></i>
                        Sign In
                      </>
                    )}
                  </Button>

                  <div className="text-center mb-3">
                    <span className="text-muted">Don't have an account? </span>
                    <Link to="/register" className="text-decoration-none fw-bold">
                      Create Account
                    </Link>
                  </div>

                  <hr className="my-4" />
                  
                  <div className="text-center mb-2">
                    <small className="text-muted">Or continue with</small>
                  </div>

                  <div className="d-grid gap-2">
                    <Button
                      variant="outline-danger"
                      className="social-btn"
                      onClick={() => handleSocialAuth('google')}
                      disabled={authStatus === 'loading'}
                    >
                      <i className="fab fa-google me-2"></i>
                      Continue with Google
                    </Button>
                    <Button
                      variant="outline-primary"
                      className="social-btn"
                      onClick={() => handleSocialAuth('facebook')}
                      disabled={authStatus === 'loading'}
                    >
                      <i className="fab fa-facebook-f me-2"></i>
                      Continue with Facebook
                    </Button>
                  </div>

                  <div className="text-center mt-4">
                    <Link to="/admin/login" className="text-muted text-decoration-none">
                      <small>
                        <i className="fas fa-user-shield me-1"></i>
                        Admin Login
                      </small>
                    </Link>
                  </div>
                </Form>
              </Card.Body>
            </Card>
          </Col>
        </Row>
      </Container>
    </div>
  );
};

export default Login;