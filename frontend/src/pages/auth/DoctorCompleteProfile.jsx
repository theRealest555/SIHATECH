import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Container, Row, Col, Card, Form, Button, Alert, Spinner } from 'react-bootstrap';
import axios from 'axios';
import ApiService from '../../services/api';

const DoctorCompleteProfile = () => {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    speciality_id: '',
    telephone: '',
    adresse: '',
    sexe: '',
    date_de_naissance: '',
  });
  const [specialities, setSpecialities] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    // Check if user is authenticated and is a doctor
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    if (!user || user.role !== 'medecin') {
      navigate('/login');
      return;
    }

    // Fetch specialities
    const fetchSpecialities = async () => {
      try {
        const response = await ApiService.getSpecialities();
        const specs = response.data.data.map((name, index) => ({
          id: index + 1,
          nom: name
        }));
        setSpecialities(specs);
      } catch (err) {
        console.error('Failed to fetch specialities:', err);
        setError('Failed to load specialities');
      }
    };
    fetchSpecialities();
  }, [navigate]);

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const response = await axios.post('http://localhost:8000/api/doctor/complete-profile', formData, {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
      });

      if (response.data.user) {
        // Update user in localStorage
        localStorage.setItem('user', JSON.stringify(response.data.user));
        navigate('/dashboard');
      }
    } catch (err) {
      if (err.response?.data?.errors) {
        const errors = err.response.data.errors;
        const errorMessages = Object.values(errors).flat().join(' ');
        setError(errorMessages);
      } else {
        setError(err.response?.data?.message || 'Failed to complete profile');
      }
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
                  <i className="fas fa-user-md fa-3x text-primary mb-3"></i>
                  <h2 className="auth-header">Complete Your Profile</h2>
                  <p className="text-muted">Please provide your professional information to continue</p>
                </div>

                {error && (
                  <Alert variant="danger" className="fade-in">
                    <i className="fas fa-exclamation-triangle me-2"></i>
                    {error}
                  </Alert>
                )}

                <Form onSubmit={handleSubmit}>
                  <div className="bg-light p-4 rounded mb-4">
                    <h5 className="text-primary mb-3">
                      <i className="fas fa-stethoscope me-2"></i>
                      Professional Information
                    </h5>
                    
                    <Form.Group className="mb-3">
                      <Form.Label>
                        <i className="fas fa-graduation-cap me-2"></i>
                        Medical Speciality *
                      </Form.Label>
                      <Form.Select
                        name="speciality_id"
                        value={formData.speciality_id}
                        onChange={handleChange}
                        required
                        disabled={loading}
                      >
                        <option value="">Select your speciality...</option>
                        {specialities.map((spec) => (
                          <option key={spec.id} value={spec.id}>
                            {spec.nom}
                          </option>
                        ))}
                      </Form.Select>
                    </Form.Group>
                  </div>

                  <h5 className="text-primary mb-3">
                    <i className="fas fa-user-circle me-2"></i>
                    Personal Information
                  </h5>

                  <Form.Group className="mb-3">
                    <Form.Label>
                      <i className="fas fa-phone me-2"></i>
                      Phone Number *
                    </Form.Label>
                    <Form.Control
                      type="tel"
                      name="telephone"
                      value={formData.telephone}
                      onChange={handleChange}
                      placeholder="Enter your phone number"
                      required
                      disabled={loading}
                    />
                  </Form.Group>

                  <Form.Group className="mb-3">
                    <Form.Label>
                      <i className="fas fa-map-marker-alt me-2"></i>
                      Practice Address *
                    </Form.Label>
                    <Form.Control
                      as="textarea"
                      rows={2}
                      name="adresse"
                      value={formData.adresse}
                      onChange={handleChange}
                      placeholder="Enter your practice address"
                      required
                      disabled={loading}
                    />
                  </Form.Group>

                  <Row>
                    <Col md={6}>
                      <Form.Group className="mb-3">
                        <Form.Label>
                          <i className="fas fa-venus-mars me-2"></i>
                          Gender
                        </Form.Label>
                        <Form.Select
                          name="sexe"
                          value={formData.sexe}
                          onChange={handleChange}
                          disabled={loading}
                        >
                          <option value="">Select...</option>
                          <option value="homme">Male</option>
                          <option value="femme">Female</option>
                        </Form.Select>
                      </Form.Group>
                    </Col>
                    <Col md={6}>
                      <Form.Group className="mb-3">
                        <Form.Label>
                          <i className="fas fa-calendar me-2"></i>
                          Date of Birth
                        </Form.Label>
                        <Form.Control
                          type="date"
                          name="date_de_naissance"
                          value={formData.date_de_naissance}
                          onChange={handleChange}
                          disabled={loading}
                          max={new Date().toISOString().split('T')[0]}
                        />
                      </Form.Group>
                    </Col>
                  </Row>

                  <Alert variant="info" className="mb-4">
                    <i className="fas fa-info-circle me-2"></i>
                    <strong>Next Steps:</strong> After completing your profile, you'll need to upload 
                    your professional documents for verification before you can start accepting appointments.
                  </Alert>

                  <Button 
                    type="submit" 
                    variant="primary" 
                    size="lg" 
                    className="w-100"
                    disabled={loading}
                  >
                    {loading ? (
                      <>
                        <Spinner animation="border" size="sm" className="me-2" />
                        Completing Profile...
                      </>
                    ) : (
                      <>
                        <i className="fas fa-check me-2"></i>
                        Complete Profile
                      </>
                    )}
                  </Button>
                </Form>
              </Card.Body>
            </Card>
          </Col>
        </Row>
      </Container>
    </div>
  );
};

export default DoctorCompleteProfile;