import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Container, Row, Col, Card, Form, Button, Alert, Badge, Modal, Spinner, Table } from 'react-bootstrap';
import ApiService from '../../services/api';
import axios from 'axios';
import moment from 'moment';

const PatientProfile = () => {
  const navigate = useNavigate();
  const [profile, setProfile] = useState({
    user: {
      nom: '',
      prenom: '',
      email: '',
      telephone: '',
      adresse: '',
      sexe: '',
      date_de_naissance: '',
      photo: null,
    },
    patient: {
      medecin_favori_id: null,
      medecinFavori: null
    }
  });
  const [appointments, setAppointments] = useState([]);
  const [doctors, setDoctors] = useState([]);
  const [isEditing, setIsEditing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);
  const [showPasswordModal, setShowPasswordModal] = useState(false);
  const [passwordData, setPasswordData] = useState({
    current_password: '',
    password: '',
    password_confirmation: ''
  });
  const [showPhotoModal, setShowPhotoModal] = useState(false);
  const [photoFile, setPhotoFile] = useState(null);
  const [uploadingPhoto, setUploadingPhoto] = useState(false);

  useEffect(() => {
    fetchProfile();
    fetchDoctors();
  }, []);

  const fetchProfile = async () => {
    try {
      setLoading(true);
      const response = await axios.get('http://localhost:8000/api/patient/profile', {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
      });
      
      setProfile({
        user: response.data.user,
        patient: response.data.patient || {}
      });
      
      // Fetch appointments
      fetchAppointments(response.data.user.id);
    } catch (err) {
      console.error('Error fetching profile:', err);
      setError('Failed to load profile data');
      if (err.response?.status === 401) {
        navigate('/login');
      }
    } finally {
      setLoading(false);
    }
  };

  const fetchAppointments = async (userId) => {
    try {
      const response = await ApiService.getPatientAppointments(userId);
      setAppointments(response.data.data || []);
    } catch (err) {
      console.error('Error fetching appointments:', err);
    }
  };

  const fetchDoctors = async () => {
    try {
      const response = await ApiService.getDoctors();
      setDoctors(response.data.data || []);
    } catch (err) {
      console.error('Error fetching doctors:', err);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    if (name === 'medecin_favori_id') {
      setProfile(prev => ({
        ...prev,
        patient: { ...prev.patient, medecin_favori_id: value }
      }));
    } else {
      setProfile(prev => ({
        ...prev,
        user: { ...prev.user, [name]: value }
      }));
    }
  };

  const handleEdit = () => {
    setIsEditing(true);
    setError(null);
    setSuccess(false);
  };

  const handleCancel = () => {
    fetchProfile();
    setIsEditing(false);
    setError(null);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError(null);

    try {
      const updateData = {
        nom: profile.user.nom,
        prenom: profile.user.prenom,
        email: profile.user.email,
        telephone: profile.user.telephone,
        adresse: profile.user.adresse,
        sexe: profile.user.sexe,
        date_de_naissance: profile.user.date_de_naissance,
        medecin_favori_id: profile.patient.medecin_favori_id
      };

      await axios.put('http://localhost:8000/api/patient/profile', updateData, {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
      });
      
      setIsEditing(false);
      setSuccess(true);
      setTimeout(() => setSuccess(false), 3000);
      fetchProfile();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update profile');
    } finally {
      setSaving(false);
    }
  };

  const handlePasswordUpdate = async (e) => {
    e.preventDefault();
    setError(null);
    
    if (passwordData.password !== passwordData.password_confirmation) {
      setError('Passwords do not match');
      return;
    }

    try {
      await axios.put('http://localhost:8000/api/patient/profile/password', passwordData, {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
      });
      
      setShowPasswordModal(false);
      setPasswordData({ current_password: '', password: '', password_confirmation: '' });
      setSuccess(true);
      setTimeout(() => setSuccess(false), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update password');
    }
  };

  const handlePhotoUpload = async (e) => {
    e.preventDefault();
    if (!photoFile) return;

    setUploadingPhoto(true);
    const formData = new FormData();
    formData.append('photo', photoFile);

    try {
      const response = await axios.post('http://localhost:8000/api/patient/profile/photo', formData, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'multipart/form-data'
        }
      });
      
      setShowPhotoModal(false);
      setPhotoFile(null);
      fetchProfile();
      setSuccess(true);
      setTimeout(() => setSuccess(false), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to upload photo');
    } finally {
      setUploadingPhoto(false);
    }
  };

  const getStatusBadgeClass = (status) => {
    switch (status) {
      case 'confirmé':
        return 'success';
      case 'en_attente':
        return 'warning';
      case 'annulé':
        return 'danger';
      case 'terminé':
        return 'secondary';
      default:
        return 'light';
    }
  };

  const handleCancelAppointment = async (appointmentId) => {
    try {
      await ApiService.updateAppointmentStatus(appointmentId, { statut: 'annulé' });
      fetchAppointments(profile.user.id);
      setSuccess(true);
      setTimeout(() => setSuccess(false), 3000);
    } catch (err) {
      setError('Failed to cancel appointment');
    }
  };

  if (loading) {
    return (
      <Container className="mt-5">
        <div className="text-center">
          <Spinner animation="border" variant="primary" />
          <p className="mt-3">Loading profile...</p>
        </div>
      </Container>
    );
  }

  return (
    <Container className="py-4">
      <Row>
        <Col lg={12}>
          {/* Profile Header */}
          <Card className="profile-card mb-4">
            <Card.Body>
              <Row className="align-items-center">
                <Col md={2} className="text-center">
                  <div className="position-relative d-inline-block">
                    <img
                      src={profile.user.photo ? `http://localhost:8000/storage/${profile.user.photo}` : 'https://via.placeholder.com/150'}
                      alt="Profile"
                      className="rounded-circle"
                      style={{ width: '120px', height: '120px', objectFit: 'cover' }}
                    />
                    <Button
                      size="sm"
                      variant="primary"
                      className="position-absolute bottom-0 end-0 rounded-circle"
                      onClick={() => setShowPhotoModal(true)}
                      style={{ width: '35px', height: '35px', padding: 0 }}
                    >
                      <i className="fas fa-camera"></i>
                    </Button>
                  </div>
                </Col>
                <Col md={7}>
                  <h2 className="mb-1">{profile.user.prenom} {profile.user.nom}</h2>
                  <p className="text-muted mb-2">
                    <i className="fas fa-user me-2"></i>
                    Patient
                  </p>
                  {profile.user.email_verified_at && (
                    <Badge bg="info">
                      <i className="fas fa-check-circle me-1"></i>
                      Email Verified
                    </Badge>
                  )}
                  {profile.patient.medecinFavori && (
                    <p className="mt-2 mb-0">
                      <small className="text-muted">
                        <i className="fas fa-star me-1 text-warning"></i>
                        Favorite Doctor: Dr. {profile.patient.medecinFavori.user?.prenom} {profile.patient.medecinFavori.user?.nom}
                      </small>
                    </p>
                  )}
                </Col>
                <Col md={3} className="text-md-end">
                  <Button
                    variant="primary"
                    onClick={() => navigate('/patient')}
                    className="mb-2 w-100"
                  >
                    <i className="fas fa-search me-2"></i>
                    Find Doctor
                  </Button>
                  <Button
                    variant="outline-secondary"
                    onClick={() => {
                      localStorage.removeItem('token');
                      localStorage.removeItem('user');
                      navigate('/login');
                    }}
                    className="w-100"
                  >
                    <i className="fas fa-sign-out-alt me-2"></i>
                    Logout
                  </Button>
                </Col>
              </Row>
            </Card.Body>
          </Card>

          {error && <Alert variant="danger" dismissible onClose={() => setError(null)}>{error}</Alert>}
          {success && <Alert variant="success" dismissible onClose={() => setSuccess(false)}>
            <i className="fas fa-check-circle me-2"></i>
            Operation completed successfully!
          </Alert>}

          {/* Profile Information */}
          <Card className="mb-4">
            <Card.Header>
              <h5 className="mb-0">
                <i className="fas fa-user-circle me-2"></i>
                Personal Information
              </h5>
            </Card.Header>
            <Card.Body>
              <Form onSubmit={handleSave}>
                <Row>
                  <Col md={6}>
                    <Form.Group className="mb-3">
                      <Form.Label>First Name</Form.Label>
                      <Form.Control
                        type="text"
                        name="prenom"
                        value={profile.user.prenom}
                        onChange={handleChange}
                        disabled={!isEditing}
                        required
                      />
                    </Form.Group>
                  </Col>
                  <Col md={6}>
                    <Form.Group className="mb-3">
                      <Form.Label>Last Name</Form.Label>
                      <Form.Control
                        type="text"
                        name="nom"
                        value={profile.user.nom}
                        onChange={handleChange}
                        disabled={!isEditing}
                        required
                      />
                    </Form.Group>
                  </Col>
                </Row>

                <Row>
                  <Col md={6}>
                    <Form.Group className="mb-3">
                      <Form.Label>Email</Form.Label>
                      <Form.Control
                        type="email"
                        name="email"
                        value={profile.user.email}
                        onChange={handleChange}
                        disabled={!isEditing}
                        required
                      />
                    </Form.Group>
                  </Col>
                  <Col md={6}>
                    <Form.Group className="mb-3">
                      <Form.Label>Phone Number</Form.Label>
                      <Form.Control
                        type="tel"
                        name="telephone"
                        value={profile.user.telephone || ''}
                        onChange={handleChange}
                        disabled={!isEditing}
                      />
                    </Form.Group>
                  </Col>
                </Row>

                <Row>
                  <Col md={4}>
                    <Form.Group className="mb-3">
                      <Form.Label>Gender</Form.Label>
                      <Form.Select
                        name="sexe"
                        value={profile.user.sexe || ''}
                        onChange={handleChange}
                        disabled={!isEditing}
                      >
                        <option value="">Select...</option>
                        <option value="homme">Male</option>
                        <option value="femme">Female</option>
                      </Form.Select>
                    </Form.Group>
                  </Col>
                  <Col md={4}>
                    <Form.Group className="mb-3">
                      <Form.Label>Date of Birth</Form.Label>
                      <Form.Control
                        type="date"
                        name="date_de_naissance"
                        value={profile.user.date_de_naissance || ''}
                        onChange={handleChange}
                        disabled={!isEditing}
                      />
                    </Form.Group>
                  </Col>
                  <Col md={4}>
                    <Form.Group className="mb-3">
                      <Form.Label>Favorite Doctor</Form.Label>
                      <Form.Select
                        name="medecin_favori_id"
                        value={profile.patient.medecin_favori_id || ''}
                        onChange={handleChange}
                        disabled={!isEditing}
                      >
                        <option value="">No favorite doctor</option>
                        {doctors.map((doctor) => (
                          <option key={doctor.id} value={doctor.id}>
                            Dr. {doctor.name} - {doctor.speciality}
                          </option>
                        ))}
                      </Form.Select>
                    </Form.Group>
                  </Col>
                </Row>

                <Form.Group className="mb-3">
                  <Form.Label>Address</Form.Label>
                  <Form.Control
                    as="textarea"
                    rows={2}
                    name="adresse"
                    value={profile.user.adresse || ''}
                    onChange={handleChange}
                    disabled={!isEditing}
                  />
                </Form.Group>

                <div className="d-flex justify-content-end">
                  {!isEditing ? (
                    <>
                      <Button variant="secondary" className="me-2" onClick={() => setShowPasswordModal(true)}>
                        <i className="fas fa-key me-2"></i>
                        Change Password
                      </Button>
                      <Button variant="primary" onClick={handleEdit}>
                        <i className="fas fa-edit me-2"></i>
                        Edit Profile
                      </Button>
                    </>
                  ) : (
                    <>
                      <Button variant="secondary" className="me-2" onClick={handleCancel} disabled={saving}>
                        Cancel
                      </Button>
                      <Button type="submit" variant="primary" disabled={saving}>
                        {saving ? (
                          <>
                            <Spinner animation="border" size="sm" className="me-2" />
                            Saving...
                          </>
                        ) : (
                          <>
                            <i className="fas fa-save me-2"></i>
                            Save Changes
                          </>
                        )}
                      </Button>
                    </>
                  )}
                </div>
              </Form>
            </Card.Body>
          </Card>

          {/* Appointments History */}
          <Card>
            <Card.Header className="d-flex justify-content-between align-items-center">
              <h5 className="mb-0">
                <i className="fas fa-calendar-check me-2"></i>
                My Appointments
              </h5>
              <Button
                size="sm"
                variant="primary"
                onClick={() => navigate('/patient')}
              >
                <i className="fas fa-plus me-2"></i>
                Book New Appointment
              </Button>
            </Card.Header>
            <Card.Body>
              {appointments.length > 0 ? (
                <div className="table-responsive">
                  <Table hover>
                    <thead>
                      <tr>
                        <th>Doctor</th>
                        <th>Speciality</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      {appointments.map((appointment) => (
                        <tr key={appointment.id}>
                          <td>
                            <strong>{appointment.doctor_name || 'Dr. N/A'}</strong>
                          </td>
                          <td>
                            <small className="text-muted">{appointment.speciality || 'N/A'}</small>
                          </td>
                          <td>{moment(appointment.date_heure).format("MMM DD, YYYY HH:mm")}</td>
                          <td>
                            <Badge bg={getStatusBadgeClass(appointment.statut)}>
                              {appointment.statut || 'Unknown'}
                            </Badge>
                          </td>
                          <td>
                            {appointment.statut === 'en_attente' && (
                              <Button
                                size="sm"
                                variant="outline-danger"
                                onClick={() => handleCancelAppointment(appointment.id)}
                              >
                                <i className="fas fa-times me-1"></i>
                                Cancel
                              </Button>
                            )}
                            {appointment.statut === 'confirmé' && (
                              <Button
                                size="sm"
                                variant="outline-warning"
                                onClick={() => handleCancelAppointment(appointment.id)}
                              >
                                <i className="fas fa-times me-1"></i>
                                Cancel
                              </Button>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </Table>
                </div>
              ) : (
                <div className="text-center py-4">
                  <i className="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                  <p className="text-muted mb-3">You don't have any appointments yet.</p>
                  <Button
                    variant="primary"
                    onClick={() => navigate('/patient')}
                  >
                    Book Your First Appointment
                  </Button>
                </div>
              )}
            </Card.Body>
          </Card>
        </Col>
      </Row>

      {/* Password Modal */}
      <Modal show={showPasswordModal} onHide={() => setShowPasswordModal(false)}>
        <Modal.Header closeButton>
          <Modal.Title>Change Password</Modal.Title>
        </Modal.Header>
        <Form onSubmit={handlePasswordUpdate}>
          <Modal.Body>
            <Form.Group className="mb-3">
              <Form.Label>Current Password</Form.Label>
              <Form.Control
                type="password"
                value={passwordData.current_password}
                onChange={(e) => setPasswordData({...passwordData, current_password: e.target.value})}
                required
              />
            </Form.Group>
            <Form.Group className="mb-3">
              <Form.Label>New Password</Form.Label>
              <Form.Control
                type="password"
                value={passwordData.password}
                onChange={(e) => setPasswordData({...passwordData, password: e.target.value})}
                minLength="8"
                required
              />
            </Form.Group>
            <Form.Group className="mb-3">
              <Form.Label>Confirm New Password</Form.Label>
              <Form.Control
                type="password"
                value={passwordData.password_confirmation}
                onChange={(e) => setPasswordData({...passwordData, password_confirmation: e.target.value})}
                required
              />
            </Form.Group>
          </Modal.Body>
          <Modal.Footer>
            <Button variant="secondary" onClick={() => setShowPasswordModal(false)}>
              Cancel
            </Button>
            <Button variant="primary" type="submit">
              Update Password
            </Button>
          </Modal.Footer>
        </Form>
      </Modal>

      {/* Photo Upload Modal */}
      <Modal show={showPhotoModal} onHide={() => setShowPhotoModal(false)}>
        <Modal.Header closeButton>
          <Modal.Title>Update Profile Photo</Modal.Title>
        </Modal.Header>
        <Form onSubmit={handlePhotoUpload}>
          <Modal.Body>
            <Form.Group>
              <Form.Label>Choose a new photo</Form.Label>
              <Form.Control
                type="file"
                accept="image/*"
                onChange={(e) => setPhotoFile(e.target.files[0])}
                required
              />
              <Form.Text className="text-muted">
                Maximum file size: 5MB. Supported formats: JPG, PNG, GIF
              </Form.Text>
            </Form.Group>
          </Modal.Body>
          <Modal.Footer>
            <Button variant="secondary" onClick={() => setShowPhotoModal(false)}>
              Cancel
            </Button>
            <Button variant="primary" type="submit" disabled={!photoFile || uploadingPhoto}>
              {uploadingPhoto ? (
                <>
                  <Spinner animation="border" size="sm" className="me-2" />
                  Uploading...
                </>
              ) : (
                'Upload Photo'
              )}
            </Button>
          </Modal.Footer>
        </Form>
      </Modal>
    </Container>
  );
};

export default PatientProfile;