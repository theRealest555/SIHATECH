import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Container, Row, Col, Card, Form, Button, Alert, Badge, Tabs, Tab, Modal, Spinner } from 'react-bootstrap';
import { useDispatch, useSelector } from 'react-redux';
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
  fetchDoctorSpecialities, 
  fetchDoctorDocuments, 
  uploadDoctorDocument,
  selectDoctorSpecialities, 
  selectDoctorDocuments,
  selectDoctorStatus
} from '../../redux/slices/doctorSlice';
import { selectCurrentUser } from '../../redux/slices/authSlice';
import { doctorProfileSchema, passwordUpdateSchema } from '../../utils/validation';
import { toast } from 'react-toastify';

const DoctorProfile = () => {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  
  const user = useSelector(selectCurrentUser);
  const profile = useSelector(selectUserProfile);
  const userStatus = useSelector(selectUserStatus);
  const userError = useSelector(selectUserError);
  const specialities = useSelector(selectDoctorSpecialities);
  const documents = useSelector(selectDoctorDocuments);
  const doctorStatus = useSelector(selectDoctorStatus);

  const [formData, setFormData] = useState({
    nom: '',
    prenom: '',
    email: '',
    telephone: '',
    adresse: '',
    sexe: '',
    date_de_naissance: '',
    speciality_id: '',
    description: '',
    horaires: {}
  });
  
  const [isEditing, setIsEditing] = useState(false);
  const [errors, setErrors] = useState({});
  const [showPasswordModal, setShowPasswordModal] = useState(false);
  const [passwordData, setPasswordData] = useState({
    current_password: '',
    password: '',
    password_confirmation: ''
  });
  const [passwordErrors, setPasswordErrors] = useState({});
  const [showPhotoModal, setShowPhotoModal] = useState(false);
  const [photoFile, setPhotoFile] = useState(null);

  useEffect(() => {
    // Load profile data
    dispatch(fetchUserProfile());
    
    // Load specialities and documents
    dispatch(fetchDoctorSpecialities());
    dispatch(fetchDoctorDocuments());
  }, [dispatch]);

  // Initialize form when profile is loaded
  useEffect(() => {
    if (profile) {
      setFormData({
        nom: profile.user?.nom || '',
        prenom: profile.user?.prenom || '',
        email: profile.user?.email || '',
        telephone: profile.user?.telephone || '',
        adresse: profile.user?.adresse || '',
        sexe: profile.user?.sexe || '',
        date_de_naissance: profile.user?.date_de_naissance || '',
        speciality_id: profile.doctor?.speciality_id || '',
        description: profile.doctor?.description || '',
        horaires: profile.doctor?.horaires || {}
      });
    }
  }, [profile]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData({ ...formData, [name]: value });
    
    // Clear field-specific error when user types
    if (errors[name]) {
      setErrors({ ...errors, [name]: null });
    }
  };

  const handlePasswordChange = (e) => {
    const { name, value } = e.target;
    setPasswordData({ ...passwordData, [name]: value });
    
    // Clear field-specific error when user types
    if (passwordErrors[name]) {
      setPasswordErrors({ ...passwordErrors, [name]: null });
    }
  };

  const validateForm = async () => {
    try {
      await doctorProfileSchema.validate(formData, { abortEarly: false });
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

  const validatePasswordForm = async () => {
    try {
      await passwordUpdateSchema.validate(passwordData, { abortEarly: false });
      return true;
    } catch (validationError) {
      const newErrors = {};
      validationError.inner.forEach((error) => {
        newErrors[error.path] = error.message;
      });
      setPasswordErrors(newErrors);
      return false;
    }
  };

  const handleEdit = () => {
    setIsEditing(true);
    setErrors({});
  };

  const handleCancel = () => {
    // Reset form to profile data
    if (profile) {
      setFormData({
        nom: profile.user?.nom || '',
        prenom: profile.user?.prenom || '',
        email: profile.user?.email || '',
        telephone: profile.user?.telephone || '',
        adresse: profile.user?.adresse || '',
        sexe: profile.user?.sexe || '',
        date_de_naissance: profile.user?.date_de_naissance || '',
        speciality_id: profile.doctor?.speciality_id || '',
        description: profile.doctor?.description || '',
        horaires: profile.doctor?.horaires || {}
      });
    }
    setIsEditing(false);
    setErrors({});
  };

  const handleSave = async (e) => {
    e.preventDefault();
    
    // Validate form
    const isValid = await validateForm();
    if (!isValid) return;

    // Update profile
    dispatch(updateUserProfile(formData))
      .unwrap()
      .then(() => {
        setIsEditing(false);
        toast.success('Profile updated successfully!');
      })
      .catch((error) => {
        // Handle validation errors from backend
        if (error.errors) {
          const newErrors = {};
          Object.entries(error.errors).forEach(([key, messages]) => {
            newErrors[key] = messages[0];
          });
          setErrors(newErrors);
        }
        
        toast.error(error.message || 'Failed to update profile');
      });
  };

  const handlePasswordUpdate = async (e) => {
    e.preventDefault();
    
    // Validate password form
    const isValid = await validatePasswordForm();
    if (!isValid) return;

    // Update password
    dispatch(updateUserPassword(passwordData))
      .unwrap()
      .then(() => {
        setShowPasswordModal(false);
        setPasswordData({
          current_password: '',
          password: '',
          password_confirmation: ''
        });
        toast.success('Password updated successfully!');
      })
      .catch((error) => {
        // Handle validation errors from backend
        if (error.errors) {
          const newErrors = {};
          Object.entries(error.errors).forEach(([key, messages]) => {
            newErrors[key] = messages[0];
          });
          setPasswordErrors(newErrors);
        } else {
          setPasswordErrors({
            current_password: error.message || 'Failed to update password'
          });
        }
        
        toast.error(error.message || 'Failed to update password');
      });
  };

  const handlePhotoUpload = async (e) => {
    e.preventDefault();
    if (!photoFile) return;

    // Upload photo
    dispatch(uploadUserPhoto(photoFile))
      .unwrap()
      .then(() => {
        setShowPhotoModal(false);
        setPhotoFile(null);
        dispatch(fetchUserProfile());
        toast.success('Photo uploaded successfully!');
      })
      .catch((error) => {
        toast.error(error.message || 'Failed to upload photo');
      });
  };

  const handleDocumentUpload = async (file, type) => {
    if (!file) return;

    // Upload document
    dispatch(uploadDoctorDocument({ file, type }))
      .unwrap()
      .then(() => {
        dispatch(fetchDoctorDocuments());
        toast.success('Document uploaded successfully!');
      })
      .catch((error) => {
        toast.error(error.message || 'Failed to upload document');
      });
  };

  if (userStatus === 'loading' && !profile) {
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
                      src={profile?.user?.photo ? `http://localhost:8000/storage/${profile.user.photo}` : 'https://via.placeholder.com/150'}
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
                  <h2 className="mb-1">Dr. {profile?.user?.prenom} {profile?.user?.nom}</h2>
                  <p className="text-muted mb-2">
                    <i className="fas fa-stethoscope me-2"></i>
                    {specialities[profile?.doctor?.speciality_id - 1] || 'Not specified'}
                  </p>
                  <div className="d-flex align-items-center">
                    <Badge bg={profile?.doctor?.is_verified ? 'success' : 'warning'} className="me-2">
                      {profile?.doctor?.is_verified ? 'Verified' : 'Pending Verification'}
                    </Badge>
                    {profile?.user?.email_verified_at && (
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
                    onClick={() => dispatch(logout())}
                    className="w-100"
                  >
                    <i className="fas fa-sign-out-alt me-2"></i>
                    Logout
                  </Button>
                </Col>
              </Row>
            </Card.Body>
          </Card>

          {userError && (
            <Alert variant="danger" dismissible>
              <i className="fas fa-exclamation-triangle me-2"></i>
              {userError}
            </Alert>
          )}

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
                            value={formData.prenom}
                            onChange={handleChange}
                            disabled={!isEditing || userStatus === 'loading'}
                            isInvalid={!!errors.prenom}
                          />
                          <Form.Control.Feedback type="invalid">
                            {errors.prenom}
                          </Form.Control.Feedback>
                        </Form.Group>
                      </Col>
                      <Col md={6}>
                        <Form.Group className="mb-3">
                          <Form.Label>Last Name</Form.Label>
                          <Form.Control
                            type="text"
                            name="nom"
                            value={formData.nom}
                            onChange={handleChange}
                            disabled={!isEditing || userStatus === 'loading'}
                            isInvalid={!!errors.nom}
                          />
                          <Form.Control.Feedback type="invalid">
                            {errors.nom}
                          </Form.Control.Feedback>
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
                            value={formData.email}
                            onChange={handleChange}
                            disabled={!isEditing || userStatus === 'loading'}
                            isInvalid={!!errors.email}
                          />
                          <Form.Control.Feedback type="invalid">
                            {errors.email}
                          </Form.Control.Feedback>
                        </Form.Group>
                      </Col>
                      <Col md={6}>
                        <Form.Group className="mb-3">
                          <Form.Label>Phone Number</Form.Label>
                          <Form.Control
                            type="tel"
                            name="telephone"
                            value={formData.telephone}
                            onChange={handleChange}
                            disabled={!isEditing || userStatus === 'loading'}
                            isInvalid={!!errors.telephone}
                          />
                          <Form.Control.Feedback type="invalid">
                            {errors.telephone}
                          </Form.Control.Feedback>
                        </Form.Group>
                      </Col>
                    </Row>

                    <Row>
                      <Col md={4}>
                        <Form.Group className="mb-3">
                          <Form.Label>Gender</Form.Label>
                          <Form.Select
                            name="sexe"
                            value={formData.sexe || ''}
                            onChange={handleChange}
                            disabled={!isEditing || userStatus === 'loading'}
                            isInvalid={!!errors.sexe}
                          >
                            <option value="">Select...</option>
                            <option value="homme">Male</option>
                            <option value="femme">Female</option>
                          </Form.Select>
                          <Form.Control.Feedback type="invalid">
                            {errors.sexe}
                          </Form.Control.Feedback>
                        </Form.Group>
                      </Col>
                      <Col md={4}>
                        <Form.Group className="mb-3">
                          <Form.Label>Date of Birth</Form.Label>
                          <Form.Control
                            type="date"
                            name="date_de_naissance"
                            value={formData.date_de_naissance || ''}
                            onChange={handleChange}
                            disabled={!isEditing || userStatus === 'loading'}
                            isInvalid={!!errors.date_de_naissance}
                          />
                          <Form.Control.Feedback type="invalid">
                            {errors.date_de_naissance}
                          </Form.Control.Feedback>
                        </Form.Group>
                      </Col>
                      <Col md={4}>
                        <Form.Group className="mb-3">
                          <Form.Label>Medical Speciality</Form.Label>
                          <Form.Select
                            name="speciality_id"
                            value={formData.speciality_id || ''}
                            onChange={handleChange}
                            disabled={!isEditing || userStatus === 'loading'}
                            isInvalid={!!errors.speciality_id}
                          >
                            <option value="">Select...</option>
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
                      </Col>
                    </Row>

                    <Form.Group className="mb-3">
                      <Form.Label>Address</Form.Label>
                      <Form.Control
                        as="textarea"
                        rows={2}
                        name="adresse"
                        value={formData.adresse || ''}
                        onChange={handleChange}
                        disabled={!isEditing || userStatus === 'loading'}
                        isInvalid={!!errors.adresse}
                      />
                      <Form.Control.Feedback type="invalid">
                        {errors.adresse}
                      </Form.Control.Feedback>
                    </Form.Group>

                    <Form.Group className="mb-3">
                      <Form.Label>Professional Description</Form.Label>
                      <Form.Control
                        as="textarea"
                        rows={3}
                        name="description"
                        value={formData.description || ''}
                        onChange={handleChange}
                        disabled={!isEditing || userStatus === 'loading'}
                        isInvalid={!!errors.description}
                        placeholder="Tell patients about your experience and approach..."
                      />
                      <Form.Control.Feedback type="invalid">
                        {errors.description}
                      </Form.Control.Feedback>
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
                          <Button 
                            variant="secondary" 
                            className="me-2" 
                            onClick={handleCancel}
                            disabled={userStatus === 'loading'}
                          >
                            Cancel
                          </Button>
                          <Button 
                            type="submit" 
                            variant="primary" 
                            disabled={userStatus === 'loading'}
                          >
                            {userStatus === 'loading' ? (
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
                                disabled={doctorStatus === 'loading'}
                              />
                            </Card.Body>
                          </Card>
                        </Col>
                      ))}
                    </Row>
                  </div>

                  <h5>Uploaded Documents</h5>
                  {documents && documents.length > 0 ? (
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
                          {documents.map(doc => (
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
                          <Button variant="outline-success" onClick={() => navigate(`/doctor/${user?.id}/appointments`)}>
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
                name="current_password"
                value={passwordData.current_password}
                onChange={handlePasswordChange}
                isInvalid={!!passwordErrors.current_password}
              />
              <Form.Control.Feedback type="invalid">
                {passwordErrors.current_password}
              </Form.Control.Feedback>
            </Form.Group>
            <Form.Group className="mb-3">
              <Form.Label>New Password</Form.Label>
              <Form.Control
                type="password"
                name="password"
                value={passwordData.password}
                onChange={handlePasswordChange}
                isInvalid={!!passwordErrors.password}
              />
              <Form.Control.Feedback type="invalid">
                {passwordErrors.password}
              </Form.Control.Feedback>
            </Form.Group>
            <Form.Group className="mb-3">
              <Form.Label>Confirm New Password</Form.Label>
              <Form.Control
                type="password"
                name="password_confirmation"
                value={passwordData.password_confirmation}
                onChange={handlePasswordChange}
                isInvalid={!!passwordErrors.password_confirmation}
              />
              <Form.Control.Feedback type="invalid">
                {passwordErrors.password_confirmation}
              </Form.Control.Feedback>
            </Form.Group>
          </Modal.Body>
          <Modal.Footer>
            <Button variant="secondary" onClick={() => setShowPasswordModal(false)}>
              Cancel
            </Button>
            <Button 
              variant="primary" 
              type="submit"
              disabled={userStatus === 'loading'}
            >
              {userStatus === 'loading' ? (
                <>
                  <Spinner animation="border" size="sm" className="me-2" />
                  Updating...
                </>
              ) : (
                'Update Password'
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
            <Button 
              variant="primary" 
              type="submit" 
              disabled={!photoFile || userStatus === 'loading'}
            >
              {userStatus === 'loading' ? (
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