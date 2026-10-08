import DoctorAvatar from '../../components/ui/DoctorAvatar';
import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import { Container, Row, Col, Card, Form, Button, Alert, Badge, Modal, Spinner, Table } from 'react-bootstrap';
import { 
  fetchUserProfile, 
  updateUserProfile, 
  updateUserPassword,
  uploadUserPhoto,
  selectUserProfile,
  selectUserStatus,
  selectUserError
} from '../../redux/slices/userSlice';
import { 
  fetchAllDoctors,
  selectAllDoctors,
  selectDoctorStatus
} from '../../redux/slices/doctorSlice';
import {
  fetchPatientAppointments,
  updateAppointmentStatus,
  selectPatientAppointments,
  selectPatientStatus,
  selectPatientError
} from '../../redux/slices/patientSlice';
import { 
  selectCurrentUser,
  logout
} from '../../redux/slices/authSlice';
import moment from 'moment';

const PatientProfile = () => {
  const navigate = useNavigate();
  const dispatch = useDispatch();

  // Get data from Redux
  const user = useSelector(selectCurrentUser);
  const profile = useSelector(selectUserProfile);
  const userStatus = useSelector(selectUserStatus);
  const userError = useSelector(selectUserError);
  const doctors = useSelector(selectAllDoctors);
  const doctorStatus = useSelector(selectDoctorStatus);
  const appointments = useSelector(selectPatientAppointments);
  const appointmentStatus = useSelector(selectPatientStatus);
  const appointmentError = useSelector(selectPatientError);

  // Local state
  const [emailPassword, setEmailPassword] = useState('');
  const [isEditing, setIsEditing] = useState(false);
  const [formData, setFormData] = useState({
    nom: '',
    prenom: '',
    email: '',
    telephone: '',
    adresse: '',
    sexe: '',
    date_de_naissance: '',
    medecin_favori_id: '',
  });
  const [showPasswordModal, setShowPasswordModal] = useState(false);
  const [passwordData, setPasswordData] = useState({
    current_password: '',
    password: '',
    password_confirmation: ''
  });
  const [showPhotoModal, setShowPhotoModal] = useState(false);
  const [photoFile, setPhotoFile] = useState(null);
  
  // UI states
  const [success, setSuccess] = useState(false);

  // Check loading states
  const isLoading = userStatus === 'loading';
  const isAppointmentLoading = appointmentStatus === 'loading';
  const isDoctorsLoading = doctorStatus === 'loading';

  // Initialize data
  useEffect(() => {
    dispatch(fetchUserProfile());
    dispatch(fetchAllDoctors());
    if (user?.id) {
      dispatch(fetchPatientAppointments(user.id));
    }
  }, [dispatch, user?.id]);

  // Update form data when profile loads
  useEffect(() => {
    if (profile && !isEditing) {
      setFormData({
        expected_profile_revision: profile.profile_revision,
        nom: profile.user?.nom || '',
        prenom: profile.user?.prenom || '',
        email: profile.user?.email || '',
        telephone: profile.user?.telephone || '',
        adresse: profile.user?.adresse || '',
        sexe: profile.user?.sexe || '',
        date_de_naissance: profile.user?.date_de_naissance?.split('T')[0] || '',
        medecin_favori_id: profile.patient?.medecin_favori_id || '',
      });
    }
  }, [profile, isEditing]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData({ ...formData, [name]: value });
  };

  const handleEdit = event => {
    event.preventDefault();
    setIsEditing(true);
    setSuccess(false);
  };

  const handleCancel = () => {
    if (profile) {
      setFormData({
        expected_profile_revision: profile.profile_revision,
        nom: profile.user?.nom || '',
        prenom: profile.user?.prenom || '',
        email: profile.user?.email || '',
        telephone: profile.user?.telephone || '',
        adresse: profile.user?.adresse || '',
        sexe: profile.user?.sexe || '',
        date_de_naissance: profile.user?.date_de_naissance?.split('T')[0] || '',
        medecin_favori_id: profile.patient?.medecin_favori_id || '',
      });
    }
    setIsEditing(false);
  };

  const handleSave = (e) => {
    e.preventDefault();
    dispatch(updateUserProfile({ ...formData, current_password: formData.email !== profile?.user?.email ? emailPassword : undefined }))
      .unwrap()
      .then(result => {
        setEmailPassword('');
        if (result.email_verification_required) {
          navigate('/verify-email', { replace: true });
          return;
        }
        setIsEditing(false);
        setSuccess('Profile updated successfully.');
        setTimeout(() => setSuccess(false), 3000);
      })
      .catch((error) => {
        console.error('Profile update error:', error);
      });
  };

  const handlePasswordChange = (e) => {
    const { name, value } = e.target;
    setPasswordData({ ...passwordData, [name]: value });
  };

  const handlePasswordUpdate = (e) => {
    e.preventDefault();
    
    if (passwordData.password !== passwordData.password_confirmation) {
      alert('Passwords do not match');
      return;
    }
    
    dispatch(updateUserPassword(passwordData))
      .unwrap()
      .then(() => {
        setShowPasswordModal(false);
        setPasswordData({ current_password: '', password: '', password_confirmation: '' });
        setSuccess('Password updated. Other devices must sign in again.');
        setTimeout(() => setSuccess(false), 3000);
      })
      .catch((error) => {
        console.error('Password update error:', error);
      });
  };

  const handlePhotoUpload = (e) => {
    e.preventDefault();
    if (!photoFile) return;
    
    dispatch(uploadUserPhoto(photoFile))
      .unwrap()
      .then(() => {
        setShowPhotoModal(false);
        setPhotoFile(null);
        setSuccess('Photo updated successfully.');
        setTimeout(() => setSuccess(false), 3000);
      })
      .catch((error) => {
        console.error('Photo upload error:', error);
      });
  };

  const handleLogout = () => {
    dispatch(logout())
      .unwrap()
      .then(() => {
        navigate('/login');
      })
      .catch((error) => {
        console.error('Logout error:', error);
        navigate('/login');
      });
  };

  const handleCancelAppointment = (appointmentId) => {
    dispatch(updateAppointmentStatus({ appointmentId, status: 'annulé' }))
      .unwrap()
      .then(() => {
        if (user?.id) {
          dispatch(fetchPatientAppointments(user.id));
        }
        setSuccess('Profile updated successfully.');
        setTimeout(() => setSuccess(false), 3000);
      })
      .catch((error) => {
        console.error('Cancel appointment error:', error);
      });
  };

  const getStatusBadgeClass = (status) => {
    switch (status) {
      case 'confirmé': return 'success';
      case 'en_attente': return 'warning';
      case 'annulé': return 'danger';
      case 'terminé': return 'secondary';
      default: return 'light';
    }
  };

  if (isLoading && !profile) {
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
    <Container className="py-4" style={{ background: 'linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)', minHeight: '100vh' }}>
      <Row>
        <Col lg={12}>
          {/* Profile Header */}
          <Card className="profile-card mb-4 shadow-lg border-0 rounded-4">
            <Card.Body>
              <Row className="align-items-center">
                <Col md={2} className="text-center">
                  <div className="position-relative d-inline-block">
                    <DoctorAvatar key={profile?.user?.photo || 'initials'} name={`${profile?.user?.prenom || ''} ${profile?.user?.nom || ''}`} photo={profile?.user?.photo} />
                    <Button
                      size="sm"
                      variant="primary"
                      className="position-absolute bottom-0 end-0 rounded-circle border border-white shadow"
                      aria-label="Update profile photo"
                      onClick={() => setShowPhotoModal(true)}
                      style={{ width: '35px', height: '35px', padding: 0 }}
                    >
                      <i className="fas fa-camera"></i>
                    </Button>
                  </div>
                </Col>
                <Col md={7}>
                  <h2 className="mb-1 fw-bold">{profile?.user?.prenom} {profile?.user?.nom}</h2>
                  <p className="text-muted mb-2">
                    <i className="fas fa-user me-2"></i>
                    Patient
                  </p>
                  {profile?.user?.email_verified_at && (
                    <Badge bg="info" className="me-2">
                      <i className="fas fa-check-circle me-1"></i>
                      Email Verified
                    </Badge>
                  )}
                  {profile?.patient?.medecinFavori && (
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
                    className="mb-2 w-100 shadow-sm"
                  >
                    <i className="fas fa-search me-2"></i>
                    Find Doctor
                  </Button>
                  <Button
                    variant="outline-secondary"
                    onClick={handleLogout}
                    className="w-100 shadow-sm"
                  >
                    <i className="fas fa-sign-out-alt me-2"></i>
                    Logout
                  </Button>
                </Col>
              </Row>
            </Card.Body>
          </Card>

          {userError && <Alert variant="danger" dismissible>{userError}{isEditing && <Button variant="outline-danger" disabled={isLoading} onClick={() => { setIsEditing(false); setEmailPassword(''); dispatch(fetchUserProfile()); }}>Reload profile and discard draft</Button>}</Alert>}
          {appointmentError && <Alert variant="danger" dismissible>{appointmentError}</Alert>}
          {success && (
            <Alert variant="success" dismissible onClose={() => setSuccess(false)}>
              <i className="fas fa-check-circle me-2"></i>
              {success}
            </Alert>
          )}

          {/* Profile Information */}
          <Card className="mb-4 shadow border-0 rounded-4">
            <Card.Header className="bg-white border-0">
              <h5 className="mb-0 fw-bold">
                <i className="fas fa-user-circle me-2"></i>
                Personal Information
              </h5>
            </Card.Header>
            <Card.Body>
              <Form onSubmit={handleSave}>
                <Row>
                  <Col md={6}>
                    <Form.Group className="mb-3" controlId="patient-profile-prenom">
                      <Form.Label>First Name</Form.Label>
                      <Form.Control
                        type="text"
                        name="prenom"
                        value={formData.prenom}
                        onChange={handleChange}
                        disabled={!isEditing || isLoading}
                        required
                      />
                    </Form.Group>
                  </Col>
                  <Col md={6}>
                    <Form.Group className="mb-3" controlId="patient-profile-nom">
                      <Form.Label>Last Name</Form.Label>
                      <Form.Control
                        type="text"
                        name="nom"
                        value={formData.nom}
                        onChange={handleChange}
                        disabled={!isEditing || isLoading}
                        required
                      />
                    </Form.Group>
                  </Col>
                </Row>

                <Row>
                  <Col md={6}>
                    <Form.Group className="mb-3" controlId="patient-profile-email">
                      <Form.Label>Email</Form.Label>
                      <Form.Control
                        type="email"
                        name="email"
                        value={formData.email}
                        onChange={handleChange}
                        disabled={!isEditing || isLoading}
                        required
                      />
                      {isEditing && formData.email !== profile?.user?.email && (
                        <Form.Group className="mt-3" controlId="email-change-password">
                          <Form.Label>Current password to change email</Form.Label>
                          <Form.Control type="password" autoComplete="current-password" value={emailPassword} onChange={event => setEmailPassword(event.target.value)} required />
                          <Form.Text>Verify your new address before using your account again. Signed in with a provider? Sign out and use Forgot your password to set one first.</Form.Text>
                        </Form.Group>
                      )}
                    </Form.Group>
                  </Col>
                  <Col md={6}>
                    <Form.Group className="mb-3" controlId="patient-profile-telephone">
                      <Form.Label>Phone Number</Form.Label>
                      <Form.Control
                        type="tel"
                        name="telephone"
                        value={formData.telephone || ''}
                        onChange={handleChange}
                        disabled={!isEditing || isLoading}
                      />
                    </Form.Group>
                  </Col>
                </Row>

                <Row>
                  <Col md={4}>
                    <Form.Group className="mb-3" controlId="patient-profile-sexe">
                      <Form.Label>Gender</Form.Label>
                      <Form.Select
                        name="sexe"
                        value={formData.sexe || ''}
                        onChange={handleChange}
                        disabled={!isEditing || isLoading}
                      >
                        <option value="">Select...</option>
                        <option value="homme">Male</option>
                        <option value="femme">Female</option>
                      </Form.Select>
                    </Form.Group>
                  </Col>
                  <Col md={4}>
                    <Form.Group className="mb-3" controlId="patient-profile-date_de_naissance">
                      <Form.Label>Date of Birth</Form.Label>
                      <Form.Control
                        type="date"
                        name="date_de_naissance"
                        value={formData.date_de_naissance || ''}
                        onChange={handleChange}
                        disabled={!isEditing || isLoading}
                      />
                    </Form.Group>
                  </Col>
                  <Col md={4}>
                    <Form.Group className="mb-3" controlId="patient-profile-medecin_favori_id">
                      <Form.Label>Favorite Doctor</Form.Label>
                      <Form.Select
                        name="medecin_favori_id"
                        value={formData.medecin_favori_id || ''}
                        onChange={handleChange}
                        disabled={!isEditing || isLoading || isDoctorsLoading}
                      >
                        <option value="">No favorite doctor</option>
                        {isDoctorsLoading ? (
                          <option disabled>Loading doctors...</option>
                        ) : (
                          doctors.map((doctor) => (
                            <option key={doctor.id} value={doctor.id}>
                              Dr. {doctor.name} - {doctor.speciality}
                            </option>
                          ))
                        )}
                      </Form.Select>
                    </Form.Group>
                  </Col>
                </Row>

                <Form.Group className="mb-3" controlId="patient-profile-adresse">
                  <Form.Label>Address</Form.Label>
                  <Form.Control
                    as="textarea"
                    rows={2}
                    name="adresse"
                    value={formData.adresse || ''}
                    onChange={handleChange}
                    disabled={!isEditing || isLoading}
                  />
                </Form.Group>

                <div className="d-flex justify-content-end">
                  {!isEditing ? (
                    <>
                      <Button 
                        variant="secondary" 
                        className="me-2" 
                        onClick={() => setShowPasswordModal(true)}
                        disabled={isLoading}
                      >
                        <i className="fas fa-key me-2"></i>
                        Change Password
                      </Button>
                      <Button 
                        variant="primary" 
                        onClick={handleEdit}
                        disabled={isLoading}
                      >
                        <i className="fas fa-edit me-2"></i>
                        Edit Profile
                      </Button>
                    </>
                  ) : (
                    <>
                      <Button 
                        variant="secondary" 
                        className="me-2" 
                        onClick={handleCancel} 
                        disabled={isLoading}
                      >
                        Cancel
                      </Button>
                      <Button 
                        type="submit" 
                        variant="primary" 
                        disabled={isLoading}
                      >
                        {isLoading ? (
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
          <Card className="shadow border-0 rounded-4">
            <Card.Header className="d-flex justify-content-between align-items-center bg-white border-0">
              <h5 className="mb-0 fw-bold">
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
              {isAppointmentLoading ? (
                <div className="text-center py-4">
                  <Spinner animation="border" variant="primary" />
                  <p className="mt-2">Loading appointments...</p>
                </div>
              ) : appointments.length > 0 ? (
                <div className="table-responsive">
                  <Table hover className="align-middle">
                    <thead className="table-light">
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
                            {appointment.statut === 'en_attente' || appointment.statut === 'confirmé' ? (
                              <Button
                                size="sm"
                                variant="outline-danger"
                                onClick={() => handleCancelAppointment(appointment.id)}
                                disabled={isAppointmentLoading}
                              >
                                {isAppointmentLoading ? (
                                  <Spinner animation="border" size="sm" />
                                ) : (
                                  <>
                                    <i className="fas fa-times me-1"></i>
                                    Cancel
                                  </>
                                )}
                              </Button>
                            ) : null}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </Table>
                </div>
              ) : (
                <div className="text-center py-4">
                  <i className="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                  <p className="text-muted mb-3">You don&apos;t have any appointments yet.</p>
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
            <Form.Group className="mb-3" controlId="patient-profile-current_password">
              <Form.Label>Current Password</Form.Label>
              <Form.Control
                type="password"
                name="current_password"
                value={passwordData.current_password}
                onChange={handlePasswordChange}
                required
              />
            </Form.Group>
            <Form.Group className="mb-3" controlId="patient-profile-password">
              <Form.Label>New Password</Form.Label>
              <Form.Control
                type="password"
                name="password"
                value={passwordData.password}
                onChange={handlePasswordChange}
                minLength="8"
                required
              />
            </Form.Group>
            <Form.Group className="mb-3" controlId="patient-profile-password_confirmation">
              <Form.Label>Confirm New Password</Form.Label>
              <Form.Control
                type="password"
                name="password_confirmation"
                value={passwordData.password_confirmation}
                onChange={handlePasswordChange}
                required
              />
            </Form.Group>
          </Modal.Body>
          <Modal.Footer>
            <Button variant="secondary" onClick={() => setShowPasswordModal(false)}>
              Cancel
            </Button>
            <Button 
              variant="primary" 
              type="submit"
              disabled={isLoading}
            >
              {isLoading ? (
                <Spinner animation="border" size="sm" />
              ) : (
                "Update Password"
              )}
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
            {userError && <Alert variant="danger">{userError}</Alert>}
            <Form.Group controlId="profile-photo-file">
              <Form.Label>Choose a new photo</Form.Label>
              <Form.Control
                type="file"
                accept="image/jpeg,image/png,image/webp"
                onChange={(e) => setPhotoFile(e.target.files[0])}
                required
              />
              <Form.Text className="text-muted">
                Maximum file size: 5MB. Supported formats: JPG, PNG, WebP
              </Form.Text>
            </Form.Group>
          </Modal.Body>
          <Modal.Footer>
            <Button variant="secondary" onClick={() => setShowPhotoModal(false)}>
              Cancel
            </Button>
            <Button 
              variant="primary" 
              type="submit" 
              disabled={!photoFile || isLoading}
            >
              {isLoading ? (
                <Spinner animation="border" size="sm" />
              ) : (
                "Upload Photo"
              )}
            </Button>
          </Modal.Footer>
        </Form>
      </Modal>
    </Container>
  );
};

export default PatientProfile;
