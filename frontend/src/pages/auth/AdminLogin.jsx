import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Container, Row, Col, Card, Form, Button, Alert, Spinner } from 'react-bootstrap';
import axios from '../../api/axios';
import { loginSchema } from '../../utils/validation';
import { useDispatch } from 'react-redux';
import { setCredentials } from '../../redux/slices/authSlice';
import { initializeCSRF } from '../../api/axios';
import { toast } from 'react-toastify';

const AdminLogin = () => {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  
  const [formData, setFormData] = useState({
    email: '',
    password: '',
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [errors, setErrors] = useState({});

  useEffect(() => {
    // Initialize CSRF token
    initializeCSRF();
  }, []);

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
    setError(null);
    
    // Validate form
    const isValid = await validateForm();
    if (!isValid) return;

    setLoading(true);
    
    try {
      const response = await axios.post('/api/admin/login', formData);
      
      if (response.data.token) {
        // Store auth data in Redux and localStorage
        dispatch(setCredentials({
          user: response.data.user,
          token: response.data.token
        }));
        
        // Redirect to admin dashboard
        navigate('/admin/dashboard');
      }
    } catch (err) {
      // Handle error
      setError(err.response?.data?.message || 'Login failed');
      
      // Handle validation errors
      if (err.response?.data?.errors) {
        const validationErrors = {};
        Object.entries(err.response.data.errors).forEach(([key, messages]) => {
          validationErrors[key] = messages[0];
        });
        setErrors(validationErrors);
      }
      
      toast.error(err.response?.data?.message || 'Admin login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-container">
      <Container>
        <Row className="justify-content-center">
          <Col md={6} lg={5}>
            <Card className="auth-card">
              <Card.Body className="p-5">
                <div className="text-center mb-4">
                  <i className="fas fa-user-shield fa-3x text-primary mb-3"></i>
                  <h2 className="auth-header">Admin Login</h2>
                  <p className="text-muted">Sign in to access administrative features</p>
                </div>

                {error && (
                  <Alert variant="danger" className="fade-in">
                    <i className="fas fa-exclamation-triangle me-2"></i>
                    {error}
                  </Alert>
                )}

                <Form onSubmit={handleSubmit} noValidate>
                  <Form.Group className="mb-3">
                    <Form.Label>
                      <i className="fas fa-envelope me-2"></i>
                      Email Address
                    </Form.Label>
                    <Form.Control
                      type="email"
                      name="email"
                      value={formData.email}
                      onChange={handleChange}
                      placeholder="Enter admin email"
                      isInvalid={!!errors.email}
                      disabled={loading}
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
                      placeholder="Enter admin password"
                      isInvalid={!!errors.password}
                      disabled={loading}
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
                    disabled={loading}
                  >
                    {loading ? (
                      <>
                        <Spinner animation="border" size="sm" className="me-2" />
                        Signing In...
                      </>
                    ) : (
                      <>
                        <i className="fas fa-sign-in-alt me-2"></i>
                        Admin Sign In
                      </>
                    )}
                  </Button>

                  <div className="text-center">
                    <Button 
                      variant="link" 
                      className="text-decoration-none" 
                      onClick={() => navigate('/login')}
                    >
                      <i className="fas fa-arrow-left me-1"></i> Back to Main Login
                    </Button>
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

export default AdminLogin;