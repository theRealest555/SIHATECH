import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Container, Row, Col, Card, Form, Button, Alert, ButtonGroup } from 'react-bootstrap';
import axios from 'axios';

const Register = () => {
  const navigate = useNavigate();
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
    location: '',
  });
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleUserTypeChange = (type) => {
    setUserType(type);
    setFormData({ ...formData, role: type, speciality_id: '', location: '' });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    // Validation
    if (formData.password !== formData.password_confirmation) {
      setError('Passwords do not match');
      setLoading(false);
      return;
    }

    if (userType === 'medecin' && !formData.speciality_id) {
      setError('Please enter your medical speciality');
      setLoading(false);
      return;
    }

    try {
      const response = await axios.post('http://localhost:8000/api/register', formData);
      if (response.data.token) {
        localStorage.setItem('token', response.data.token);
        localStorage.setItem('user', JSON.stringify(response.data.user));
        navigate('/verify-email');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Registration failed. Please try again.');
    } finally {
      setLoading(false);
    }
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

                {error && (
                  <Alert variant="danger" className="fade-in">
                    <i className="fas fa-exclamation-triangle me-2"></i>
                    {error}
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
                    >
                      <i className="fas fa-user me-2"></i>
                      Patient
                    </Button>
                    <Button
                      variant={userType === 'medecin' ? 'primary' : 'outline-primary'}
                      onClick={() => handleUserTypeChange('medecin')}
                      className="d-flex align-items-center justify-content-center"
                    >
                      <i className="fas fa-user-md me-2"></i>
                      Doctor
                    </Button>
                  </ButtonGroup>
                </div>

                <Form onSubmit={handleSubmit}>
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
                          required
                          disabled={loading}
                        />
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
                          required
                          disabled={loading}
                        />
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
                      required
                      disabled={loading}
                    />
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
                          minLength="8"
                          required
                          disabled={loading}
                        />
                        <Form.Text className="text-muted">
                          Minimum 8 characters
                        </Form.Text>
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
                          required
                          disabled={loading}
                        />
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
                      disabled={loading}
                    />
                  </Form.Group>

                  {userType === 'medecin' && (
                    <div className="bg-light p-3 rounded mb-3">
                      <h6 className="text-primary mb-3">
                        <i className="fas fa-stethoscope me-2"></i>
                        Professional Information
                      </h6>
                      <Form.Group className="mb-3">
                        <Form.Label>
                          <i className="fas fa-graduation-cap me-2"></i>
                          Medical Speciality *
                        </Form.Label>
                        <Form.Control
                          type="text"
                          name="speciality_id"
                          value={formData.speciality_id}
                          onChange={handleChange}
                          placeholder="e.g., Cardiology, Pediatrics, General Medicine"
                          required
                          disabled={loading}
                        />
                      </Form.Group>
                      <Form.Group className="mb-0">
                        <Form.Label>
                          <i className="fas fa-map-marker-alt me-2"></i>
                          Practice Location
                        </Form.Label>
                        <Form.Control
                          type="text"
                          name="location"
                          value={formData.location}
                          onChange={handleChange}
                          placeholder="e.g., New York, Los Angeles"
                          disabled={loading}
                        />
                      </Form.Group>
                    </div>
                  )}

                  <Button 
                    type="submit" 
                    variant="primary" 
                    size="lg" 
                    className="w-100 mb-3"
                    disabled={loading}
                  >
                    {loading ? (
                      <>
                        <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
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