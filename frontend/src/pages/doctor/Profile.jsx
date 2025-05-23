import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Container, Row, Col, Card, Form, Button, Alert, Badge, Tabs, Tab, Modal, Spinner } from 'react-bootstrap';
import ApiService from '../../services/api';
import axios from 'axios';

const DoctorProfile = () => {
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
    doctor: {
      speciality_id: '',
      description: '',
      horaires: {},
      is_verified: false,
      documents: []
    }
  });
  const [specialities, setSpecialities] = useState([]);
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
    fetchSpecialities();
  }, []);

  const fetchProfile = async () => {
    try {
      setLoading(true);
      const response = await axios.get('http://localhost:8000/api/doctor/profile', {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
      });
      
      setProfile({
        user: response.data.user,
        doctor: response.data.doctor || {}
      });
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
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    if (name.startsWith('doctor.')) {
      const field = name.replace('doctor.', '');
      setProfile(prev => ({
        ...prev,
        doctor: { ...prev.doctor, [field]: value }
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
        description: profile.doctor.description,
        speciality_id: profile.doctor.speciality_id,
        horaires: profile.doctor.horaires
      };

      await axios.put('http://localhost:8000/api/doctor/profile', updateData, {
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
      await axios.put('http://localhost:8000/api/doctor/profile/password', passwordData, {
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
      const response = await axios.post('http://localhost:8000/api/doctor/profile/photo', formData, {
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

  const handleDocumentUpload = async (file, type) => {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', type);

    try {
      await axios.post('http://localhost:8000/api/doctor/documents', formData, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'multipart/form-data'
        }
      });
      
      fetchProfile();
      setSuccess(true);
      setTimeout(() => setSuccess(false), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to upload document');
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
                  <h2 className="mb-1">Dr. {profile.user.prenom} {profile.user.nom}</h2>
                  <p className="text-muted mb-2">
                    <i className="fas fa-stethoscope me-2"></i>
                    {specialities.find(s => s.id === profile.doctor.speciality_id)?.nom || 'Not specified'}
                  </p>
                  <div className="d-flex align-items-center">
                    <Badge bg={profile.doctor.is_verified ? 'success' : 'warning'} className="me-2">
                      {profile.doctor.is_verified ? 'Verified' : 'Pending Verification'}
                    </Badge>
                    {profile.user.email_verified_at && (
                      <Badge bg="info">
                        <i className="fas fa-check-circle me-1"></i>
                        Email Verified
                      </Badge>
                    )}
                  </div>
                </Col>
                <Col md={3} className="text-md-end">
                  <Button
                    variant="primary"
                    onClick={() => navigate('/doctor')}
                    className="mb-2 w-100"
                  >
                    <i className="fas fa-calendar me-2"></i>
                    My Calendar
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

          {/* Profile Tabs */}
          <Card>
            <Card.Body>
              <Tabs defaultActiveKey="personal" className="mb-3">
                <Tab eventKey="personal" title={<><i className="fas fa-user me-2"></i>Personal Information</>}>
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
                          <Form.Label>Medical Speciality</Form.Label>
                          <Form.Select
                            name="doctor.speciality_id"
                            value={profile.doctor.speciality_id || ''}
                            onChange={handleChange}
                            disabled={!isEditing}
                            required
                          >
                            <option value="">Select...</option>
                            {specialities.map((spec) => (
                              <option key={spec.id} value={spec.id}>
                                {spec.nom}
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

                    <Form.Group className="mb-3">
                      <Form.Label>Professional Description</Form.Label>
                      <Form.Control
                        as="textarea"
                        rows={3}
                        name="doctor.description"
                        value={profile.doctor.description || ''}
                        onChange={handleChange}
                        disabled={!isEditing}
                        placeholder="Tell patients about your experience and approach..."
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
                </Tab>

                <Tab eventKey="documents" title={<><i className="fas fa-file-medical me-2"></i>Documents</>}>
                  <div className="mb-4">
                    <h5>Upload Documents</h5>
                    <p className="text-muted">Upload your professional documents for verification</p>
                    <Row>
                      {['licence', 'cni', 'diplome', 'autre'].map(type => (
                        <Col md={3} key={type} className="mb-3">
                          <Card>
                            <Card.Body className="text-center">
                              <i className={`fas fa-${type === 'licence' ? 'id-card' : type === 'diplome' ? 'graduation-cap' : 'file'} fa-3x mb-3 text-primary`}></i>
                              <h6>{type.charAt(0).toUpperCase() + type.slice(1)}</h6>
                              <Form.Control
                                type="file"
                                size="sm"
                                onChange={(e) => e.target.files[0] && handleDocumentUpload(e.target.files[0], type)}
                                accept=".pdf,.jpg,.jpeg,.png"
                              />
                            </Card.Body>
                          </Card>
                        </Col>
                      ))}
                    </Row>
                  </div>

                  <h5>Uploaded Documents</h5>
                  {profile.doctor.documents && profile.doctor.documents.length > 0 ? (
                    <div className="table-responsive">
                      <table className="table">
                        <thead>
                          <tr>
                            <th>Type</th>
                            <th>File Name</th>
                            <th>Status</th>
                            <th>Uploaded At</th>
                          </tr>
                        </thead>
                        <tbody>
                          {profile.doctor.documents.map(doc => (
                            <tr key={doc.id}>
                              <td>{doc.type}</td>
                              <td>{doc.original_name}</td>
                              <td>
                                <Badge bg={
                                  doc.status === 'approved' ? 'success' :
                                  doc.status === 'rejected' ? 'danger' : 'warning'
                                }>
                                  {doc.status}
                                </Badge>
                              </td>
                              <td>{new Date(doc.created_at).toLocaleDateString()}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  ) : (
                    <Alert variant="info">
                      <i className="fas fa-info-circle me-2"></i>
                      No documents uploaded yet. Please upload your professional documents for verification.
                    </Alert>
                  )}
                </Tab>

                <Tab eventKey="actions" title={<><i className="fas fa-tasks me-2"></i>Quick Actions</>}>
                  <Row>
                    <Col md={4} className="mb-3">
                      <Card className="h-100">
                        <Card.Body className="text-center">
                          <i className="fas fa-clock fa-3x text-primary mb-3"></i>
                          <h5>Update Schedule</h5>
                          <p className="text-muted">Manage your working hours</p>
                          <Button variant="outline-primary" onClick={() => navigate('/schedule')}>
                            Manage Schedule
                          </Button>
                        </Card.Body>
                      </Card>
                    </Col>
                    <Col md={4} className="mb-3">
                      <Card className="h-100">
                        <Card.Body className="text-center">
                          <i className="fas fa-calendar-times fa-3x text-warning mb-3"></i>
                          <h5>Manage Leaves</h5>
                          <p className="text-muted">Set your vacation days</p>
                          <Button variant="outline-warning" onClick={() => navigate('/leaves')}>
                            Manage Leaves
                          </Button>
                        </Card.Body>
                      </Card>
                    </Col>
                    <Col md={4} className="mb-3">
                      <Card className="h-100">
                        <Card.Body className="text-center">
                          <i className="fas fa-list fa-3x text-success mb-3"></i>
                          <h5>View Appointments</h5>
                          <p className="text-muted">Check your appointments</p>
                          <Button variant="outline-success" onClick={() => navigate(`/doctor/${profile.user.id}/appointments`)}>
                            View Appointments
                          </Button>
                        </Card.Body>
                      </Card>
                    </Col>
                  </Row>
                </Tab>
              </Tabs>
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

export default DoctorProfile;