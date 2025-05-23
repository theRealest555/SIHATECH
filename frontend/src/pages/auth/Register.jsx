import React, { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Container, Row, Col, Card, Form, Button, Alert, ButtonGroup, Spinner } from 'react-bootstrap';
import { useDispatch, useSelector } from 'react-redux';
import { register, selectAuthStatus, selectAuthError } from '../../redux/slices/authSlice';
import { fetchDoctorSpecialities, selectDoctorSpecialities } from '../../redux/slices/doctorSlice';
import { registerSchema } from '../../utils/validation';
import { initializeCSRF } from '../../api/axios';
import { toast } from 'react-toastify';

const Register = () => {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const authStatus = useSelector(selectAuthStatus);
  const authError = useSelector(selectAuthError);
  const specialities = useSelector(selectDoctorSpecialities);
  
  const [userType, setUserType] = useState('patient');
  const [formData, setFormData] = useState({
    nom: '',
    prenom: '',
    email: '',
    password: '',
    password_confirmation: '',
    telephone: '',
    role: 'patient',
    speciality_id: '',
  });
  const [errors, setErrors] = useState({});

  useEffect(() => {
    // Initialize CSRF token
    initializeCSRF();
    
    // Fetch specialities for doctor registration
    dispatch(fetchDoctorSpecialities());
  }, [dispatch]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData({ ...formData, [name]: value });
    
    // Clear field-specific error when user types
    if (errors[name]) {
      setErrors({ ...errors, [name]: null });
    }
  };

  const handleUserTypeChange = (type) => {
    setUserType(type);
    setFormData({ ...formData, role: type, speciality_id: '' });
    
    // Clear speciality error if switching from doctor
    if (type !== 'medecin' && errors.speciality_id) {
      setErrors({ ...errors, speciality_id: null });
    }
  };

  const validateForm = async () => {
    try {
      await registerSchema.validate(formData, { abortEarly: false });
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
    
    // Validate form
    const isValid = await validateForm();
    if (!isValid) return;

    // Dispatch register action
    dispatch(register(formData))
      .unwrap()
      .then((result) => {
        // Check if email verification is required
        if (result.message && result.message.includes('verify')) {
          navigate('/verify-email');
        } else {
          navigate('/dashboard');
        }
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
        toast.error(error.message || 'Registration failed');
      });
  };

  const handleSocialAuth = (provider) => {
    // Set role in session storage to pass to social auth
    sessionStorage.setItem('authRole', userType);
    window.location.href = `http://localhost:8000/api/auth/social/${provider}/redirect?role=${userType}`;
  };

  return (
    <div className="auth-container">
      <Container>
        <Row className="justify-content-center">
          <Col md={8} lg={7}>
            <Card className="auth-card">
              <Card.Body className="p-5">
                <div className="text-center mb-4">
                  <i className="fas fa-user-plus fa-3x text-primary mb-3"></i>
                  <h2 className="auth-header">Create Account</h2>
                  <p className="text-muted">Join our healthcare platform</p>
                </div>

                {authError && (
                  <Alert variant="danger" className="fade-in">
                    <i className="fas fa-exclamation-triangle me-2"></i>
                    {authError}
                  </Alert>
                )}

                {/* User Type Selection */}
                <div className="mb-4">
                  <Form.Label className="fw-bold mb-3">I want to register as:</Form.Label>
                  <ButtonGroup className="w-100">
                    <Button
                      variant={userType === 'patient' ? 'primary' : 'outline-primary'}
                      onClick={() => handleUserTypeChange('patient')}
                      className="d-flex align-items-center justify-content-center"
                      disabled={authStatus === 'loading'}
                    >
                      <i className="fas fa-user me-2"></i>
                      Patient
                    </Button>
                    <Button
                      variant={userType === 'medecin' ? 'primary' : 'outline-primary'}
                      onClick={() => handleUserTypeChange('medecin')}
                      className="d-flex align-items-center justify-content-center"
                      disabled={authStatus === 'loading'}
                    >
                      <i className="fas fa-user-md me-2"></i>
                      Doctor
                    </Button>
                  </ButtonGroup>
                </div>

                <Form onSubmit={handleSubmit} noValidate>
                  <Row>
                    <Col md={6}>
                      <Form.Group className="mb-3">
                        <Form.Label>
                          <i className="fas fa-user me-2"></i>
                          First Name *
                        </Form.Label>
                        <Form.Control
                          type="text"
                          name="prenom"
                          value={formData.prenom}
                          onChange={handleChange}
                          placeholder="Enter your first name"
                          isInvalid={!!errors.prenom}
                          disabled={authStatus === 'loading'}
                        />
                        <Form.Control.Feedback type="invalid">
                          {errors.prenom}
                        </Form.Control.Feedback>
                      </Form.Group>
                    </Col>
                    <Col md={6}>
                      <Form.Group className="mb-3">
                        <Form.Label>
                          <i className="fas fa-user me-2"></i>
                          Last Name *
                        </Form.Label>
                        <Form.Control
                          type="text"
                          name="nom"
                          value={formData.nom}
                          onChange={handleChange}
                          placeholder="Enter your last name"
                          isInvalid={!!errors.nom}
                          disabled={authStatus === 'loading'}
                        />
                        <Form.Control.Feedback type="invalid">
                          {errors.nom}
                        </Form.Control.Feedback>
                      </Form.Group>
                    </Col>
                  </Row>

                  <Form.Group className="mb-3">
                    <Form.Label>
                      <i className="fas fa-envelope me-2"></i>
                      Email Address *
                    </Form.Label>
                    <Form.Control
                      type="email"
                      name="email"
                      value={formData.email}
                      onChange={handleChange}
                      placeholder="Enter your email address"
                      isInvalid={!!errors.email}
                      disabled={authStatus === 'loading'}
                    />
                    <Form.Control.Feedback type="invalid">
                      {errors.email}
                    </Form.Control.Feedback>
                  </Form.Group>

                  <Row>
                    <Col md={6}>
                      <Form.Group className="mb-3">
                        <Form.Label>
                          <i className="fas fa-lock me-2"></i>
                          Password *
                        </Form.Label>
                        <Form.Control
                          type="password"
                          name="password"
                          value={formData.password}
                          onChange={handleChange}
                          placeholder="Create a password"
                          isInvalid={!!errors.password}
                          disabled={authStatus === 'loading'}
                        />
                        <Form.Text className="text-muted">
                          Minimum 8 characters
                        </Form.Text>
                        <Form.Control.Feedback type="invalid">
                          {errors.password}
                        </Form.Control.Feedback>
                      </Form.Group>
                    </Col>
                    <Col md={6}>
                      <Form.Group className="mb-3">
                        <Form.Label>
                          <i className="fas fa-lock me-2"></i>
                          Confirm Password *
                        </Form.Label>
                        <Form.Control
                          type="password"
                          name="password_confirmation"
                          value={formData.password_confirmation}
                          onChange={handleChange}
                          placeholder="Confirm your password"
                          isInvalid={!!errors.password_confirmation}
                          disabled={authStatus === 'loading'}
                        />
                        <Form.Control.Feedback type="invalid">
                          {errors.password_confirmation}
                        </Form.Control.Feedback>
                      </Form.Group>
                    </Col>
                  </Row>

                  <Form.Group className="mb-3">
                    <Form.Label>
                      <i className="fas fa-phone me-2"></i>
                      Phone Number
                    </Form.Label>
                    <Form.Control
                      type="tel"
                      name="telephone"
                      value={formData.telephone}
                      onChange={handleChange}
                      placeholder="Enter your phone number"
                      isInvalid={!!errors.telephone}
                      disabled={authStatus === 'loading'}
                    />
                    <Form.Control.Feedback type="invalid">
                      {errors.telephone}
                    </Form.Control.Feedback>
                  </Form.Group>

                  {userType === 'medecin' && (
                    <div className="bg-light p-3 rounded mb-3">
                      <h6 className="text-primary mb-3">
                        <i className="fas fa-stethoscope me-2"></i>
                        Professional Information
                      </h6>
                      <Form.Group className="mb-0">
                        <Form.Label>
                          <i className="fas fa-graduation-cap me-2"></i>
                          Medical Speciality *
                        </Form.Label>
                        <Form.Select
                          name="speciality_id"
                          value={formData.speciality_id}
                          onChange={handleChange}
                          isInvalid={!!errors.speciality_id}
                          disabled={authStatus === 'loading'}
                        >
                          <option value="">Select a speciality...</option>
                          {specialities.map((spec, index) => (
                            <option key={index} value={index + 1}>
                              {spec}
                            </option>
                          ))}
                        </Form.Select>
                        <Form.Control.Feedback type="invalid">
                          {errors.speciality_id}
                        </Form.Control.Feedback>
                      </Form.Group>
                    </div>
                  )}

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
                        Creating Account...
                      </>
                    ) : (
                      <>
                        <i className="fas fa-user-plus me-2"></i>
                        Create {userType === 'patient' ? 'Patient' : 'Doctor'} Account
                      </>
                    )}
                  </Button>

                  <div className="text-center">
                    <span className="text-muted">Already have an account? </span>
                    <Link to="/login" className="text-decoration-none fw-bold">
                      Sign In
                    </Link>
                  </div>

                  <hr className="my-4" />
                  
                  <div className="text-center mb-2">
                    <small className="text-muted">Or register with</small>
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
                </Form>
              </Card.Body>
            </Card>
          </Col>
        </Row>
      </Container>
    </div>
  );
};

export default Register;