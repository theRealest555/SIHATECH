import DoctorAvatar from '../../components/ui/DoctorAvatar';
import { useState, useEffect } from 'react';
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
  selectDoctorSpecialities, // Returns array of {id, nom, ...}
  selectDoctorDocuments,
  selectDoctorStatus as selectDocSliceStatus // Renamed to avoid conflict
} from '../../redux/slices/doctorSlice';
import { selectCurrentUser, logout } from '../../redux/slices/authSlice';
import { doctorProfileSchema, passwordUpdateSchema } from '../../utils/validation';
import { toast } from 'react-toastify';

const DoctorProfile = () => {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  
  // Redux state selectors
  const user = useSelector(selectCurrentUser);
  const profile = useSelector(selectUserProfile); // Contains { user: {}, doctor: {} }
  const userStatus = useSelector(selectUserStatus); // For user slice operations (profile, password, photo)
  const userSliceError = useSelector(selectUserError); // Error from userSlice
  
  const specialities = useSelector(selectDoctorSpecialities); // Array of {id, nom, description}
  const documents = useSelector(selectDoctorDocuments);
  const doctorSliceStatus = useSelector(selectDocSliceStatus); // For doctor slice operations (docs, specialities)


  // Local component state
  const [formData, setFormData] = useState({
    nom: '',
    prenom: '',
    email: '',
    telephone: '',
    adresse: '',
    sexe: '',
    date_de_naissance: '',
    speciality_id: '', // Will hold the ID of the speciality
    description: '',
  });
  
  const [emailPassword, setEmailPassword] = useState('');
  const [isEditing, setIsEditing] = useState(false);
  const [errors, setErrors] = useState({}); // For client-side and backend validation errors
  const [showPasswordModal, setShowPasswordModal] = useState(false);
  const [passwordData, setPasswordData] = useState({
    current_password: '',
    password: '',
    password_confirmation: ''
  });
  const [passwordErrors, setPasswordErrors] = useState({});
  const [showPhotoModal, setShowPhotoModal] = useState(false);
  const [photoFile, setPhotoFile] = useState(null);
  const [documentFile, setDocumentFile] = useState(null);
  const [documentType, setDocumentType] = useState('licence');


  // Fetch initial data
  useEffect(() => {
    dispatch(fetchUserProfile());
    dispatch(fetchDoctorSpecialities());
    dispatch(fetchDoctorDocuments());
  }, [dispatch]);

  // Populate form when profile data is loaded or updated
  useEffect(() => {
    if (profile && profile.user && profile.doctor && !isEditing) {
      setFormData({
        expected_profile_revision: profile.profile_revision,
        nom: profile.user.nom || '',
        prenom: profile.user.prenom || '',
        email: profile.user.email || '',
        telephone: profile.user.telephone || '',
        adresse: profile.user.adresse || '',
        sexe: profile.user.sexe || '',
        date_de_naissance: profile.user.date_de_naissance ? profile.user.date_de_naissance.split('T')[0] : '', // Format date
        speciality_id: profile.doctor.speciality_id || '',
        description: profile.doctor.description || '',
      });
    }
  }, [profile, isEditing]);

  // Handle form input changes
  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors(prev => ({ ...prev, [name]: null }));
    }
  };

  // Handle password input changes
  const handlePasswordChange = (e) => {
    const { name, value } = e.target;
    setPasswordData(prev => ({ ...prev, [name]: value }));
    if (passwordErrors[name]) {
      setPasswordErrors(prev => ({ ...prev, [name]: null }));
    }
  };

  // Validate profile form
  const validateProfileForm = async () => {
    try {
      await doctorProfileSchema.validate(formData, { abortEarly: false });
      setErrors({});
      return true;
    } catch (validationError) {
      const newErrors = {};
      validationError.inner.forEach((err) => {
        if (err.path) newErrors[err.path] = err.message;
      });
      setErrors(newErrors);
      toast.error("Please correct the form errors.");
      return false;
    }
  };
  
  // Validate password form
  const validatePasswordForm = async () => {
    try {
      await passwordUpdateSchema.validate(passwordData, { abortEarly: false });
      setPasswordErrors({});
      return true;
    } catch (validationError) {
      const newErrors = {};
      validationError.inner.forEach((err) => {
        if (err.path) newErrors[err.path] = err.message;
      });
      setPasswordErrors(newErrors);
      toast.error("Please correct the password form errors.");
      return false;
    }
  };

  // Toggle edit mode
  const handleEdit = event => { event.preventDefault(); setIsEditing(true); };
  const handleCancelEdit = () => {
    setIsEditing(false);
    // Reset form to profile data if needed (useEffect already does this on profile change)
    if (profile && profile.user && profile.doctor) {
         setFormData({
            expected_profile_revision: profile.profile_revision,
            nom: profile.user.nom || '',
            prenom: profile.user.prenom || '',
            email: profile.user.email || '',
            telephone: profile.user.telephone || '',
            adresse: profile.user.adresse || '',
            sexe: profile.user.sexe || '',
            date_de_naissance: profile.user.date_de_naissance ? profile.user.date_de_naissance.split('T')[0] : '',
            speciality_id: profile.doctor.speciality_id || '',
            description: profile.doctor.description || '',
        });
    }
    setErrors({});
  };

  // Save profile changes
  const handleSaveProfile = async (e) => {
    e.preventDefault();
    if (!await validateProfileForm()) return;

    dispatch(updateUserProfile({ ...formData, current_password: formData.email !== profile?.user?.email ? emailPassword : undefined }))
      .unwrap()
      .then(result => {
        setEmailPassword('');
        if (result.email_verification_required) {
          navigate('/verify-email', { replace: true });
          return;
        }
        setIsEditing(false);
        toast.success('Profile updated successfully!');
        dispatch(fetchUserProfile()); // Re-fetch to ensure UI consistency
      })
      .catch(error => {
        if (error.errors) setErrors(error.errors);
        toast.error(error.message || 'Failed to update profile.');
      });
  };

  // Update password
  const handlePasswordUpdate = async (e) => {
    e.preventDefault();
    if (!await validatePasswordForm()) return;

    dispatch(updateUserPassword(passwordData))
      .unwrap()
      .then(() => {
        setShowPasswordModal(false);
        setPasswordData({ current_password: '', password: '', password_confirmation: '' });
        toast.success('Password updated. Other devices must sign in again.');
      })
      .catch(error => {
        if (error.errors) setPasswordErrors(error.errors);
        else setPasswordErrors({ current_password: error.message || 'Failed to update password.' });
        toast.error(error.message || 'Failed to update password.');
      });
  };

  // Upload profile photo
  const handlePhotoUpload = async (e) => {
    e.preventDefault();
    if (!photoFile) {
      toast.error("Please select a photo to upload.");
      return;
    }
    dispatch(uploadUserPhoto(photoFile))
      .unwrap()
      .then(() => {
        setShowPhotoModal(false);
        setPhotoFile(null);
        toast.success('Photo uploaded successfully!');
        dispatch(fetchUserProfile()); // Re-fetch to update photo URL
      })
      .catch(error => toast.error(error.message || 'Failed to upload photo.'));
  };

  // Upload document
  const handleDocumentUpload = async (e) => {
    e.preventDefault();
    if (!documentFile) {
        toast.error("Please select a document to upload.");
        return;
    }
    dispatch(uploadDoctorDocument({ file: documentFile, type: documentType }))
      .unwrap()
      .then(() => {
        toast.success(`${documentType.charAt(0).toUpperCase() + documentType.slice(1)} uploaded successfully!`);
        setDocumentFile(null); // Reset file input
        dispatch(fetchDoctorDocuments()); // Refresh document list
      })
      .catch(error => toast.error(error.message || `Failed to upload ${documentType}.`));
  };
  
  const handleLogout = () => {
    dispatch(logout()).unwrap().then(() => navigate('/login')).catch(() => navigate('/login'));
  };

  // Loading states
  const isProfileLoading = userStatus === 'loading' && !profile;
  const isSavingProfile = userStatus === 'loading' && isEditing;
  const isUpdatingPassword = userStatus === 'loading' && showPasswordModal;
  const isUploadingPhoto = userStatus === 'loading' && showPhotoModal;
  const isUploadingDocument = doctorSliceStatus === 'loading' && !!documentFile;
  const isLoadingSpecialities = doctorSliceStatus === 'loading' && specialities.length === 0;
  const isLoadingDocuments = doctorSliceStatus === 'loading' && documents.length === 0 && !isUploadingDocument;


  if (isProfileLoading) {
    return (
      <Container className="mt-5 text-center">
        <Spinner animation="border" variant="primary" />
        <p className="mt-3">Loading profile...</p>
      </Container>
    );
  }

  return (
    <Container className="py-4 profile-workspace">
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
                      disabled={isUploadingPhoto}
                    >
                      <i className="fas fa-camera"></i>
                    </Button>
                  </div>
                </Col>
                <Col md={7}>
                  <h2 className="mb-1 fw-bold">Dr. {profile?.user?.prenom} {profile?.user?.nom}</h2>
                  <p className="text-muted mb-2">
                    <i className="fas fa-stethoscope me-2"></i>
                    {specialities.find(s => s.id === profile?.doctor?.speciality_id)?.nom || 'Not specified'}
                  </p>
                  <div className="d-flex align-items-center">
                    <Badge bg={profile?.doctor?.is_verified ? 'success' : 'warning'} className="me-2">
                      {profile?.doctor?.is_verified ? 'Verified' : 'Pending Verification'}
                    </Badge>
                    {profile?.user?.email_verified_at && (
                      <Badge bg="info"><i className="fas fa-check-circle me-1"></i>Email Verified</Badge>
                    )}
                  </div>
                </Col>
                <Col md={3} className="text-md-end mt-3 mt-md-0">
                  <Button variant="primary" onClick={() => navigate('/doctor')} className="mb-2 w-100 shadow-sm">
                    <i className="fas fa-calendar me-2"></i>My Calendar
                  </Button>
                  <Button variant="outline-secondary" onClick={handleLogout} className="w-100 shadow-sm">
                    <i className="fas fa-sign-out-alt me-2"></i>Logout
                  </Button>
                </Col>
              </Row>
            </Card.Body>
          </Card>

          {userSliceError && <Alert variant="danger" dismissible>{userSliceError}{isEditing && <Button variant="outline-danger" disabled={isSavingProfile} onClick={() => { setIsEditing(false); setEmailPassword(''); dispatch(fetchUserProfile()); }}>Reload profile and discard draft</Button>}</Alert>}

          <Card className="shadow border-0 rounded-4">
            <Card.Body>
              <Tabs defaultActiveKey="personal" id="doctor-profile-tabs" className="mb-3 nav-tabs-custom">
                {/* Personal & Professional Info Tab */}
                <Tab eventKey="personal" title={<><i className="fas fa-user-edit me-2"></i>Personal & Professional Info</>}>
                  <Form onSubmit={handleSaveProfile}>
                    {/* Personal Info Fields */}
                    <Row>
                      <Col md={6}><Form.Group className="mb-3" controlId="doctor-profile-prenom">
                        <Form.Label>First Name</Form.Label>
                        <Form.Control type="text" name="prenom" value={formData.prenom} onChange={handleChange} disabled={!isEditing || isSavingProfile} isInvalid={!!errors.prenom} />
                        <Form.Control.Feedback type="invalid">{errors.prenom}</Form.Control.Feedback>
                      </Form.Group></Col>
                      <Col md={6}><Form.Group className="mb-3" controlId="doctor-profile-nom">
                        <Form.Label>Last Name</Form.Label>
                        <Form.Control type="text" name="nom" value={formData.nom} onChange={handleChange} disabled={!isEditing || isSavingProfile} isInvalid={!!errors.nom} />
                        <Form.Control.Feedback type="invalid">{errors.nom}</Form.Control.Feedback>
                      </Form.Group></Col>
                    </Row>
                    <Row>
                      <Col md={6}><Form.Group className="mb-3" controlId="doctor-profile-email">
                        <Form.Label>Email</Form.Label>
                        <Form.Control type="email" name="email" value={formData.email} onChange={handleChange} disabled={!isEditing || isSavingProfile} isInvalid={!!errors.email} />
                        <Form.Control.Feedback type="invalid">{errors.email}</Form.Control.Feedback>
                      {isEditing && formData.email !== profile?.user?.email && (
                        <Form.Group className="mt-3" controlId="email-change-password">
                          <Form.Label>Current password to change email</Form.Label>
                          <Form.Control type="password" autoComplete="current-password" value={emailPassword} onChange={event => setEmailPassword(event.target.value)} required />
                          <Form.Text>Verify your new address before using your account again. Signed in with a provider? Sign out and use Forgot your password to set one first.</Form.Text>
                        </Form.Group>
                      )}
                      </Form.Group></Col>
                      <Col md={6}><Form.Group className="mb-3" controlId="doctor-profile-telephone">
                        <Form.Label>Phone Number</Form.Label>
                        <Form.Control type="tel" name="telephone" value={formData.telephone || ''} onChange={handleChange} disabled={!isEditing || isSavingProfile} isInvalid={!!errors.telephone} />
                        <Form.Control.Feedback type="invalid">{errors.telephone}</Form.Control.Feedback>
                      </Form.Group></Col>
                    </Row>
                     <Row>
                      <Col md={6}><Form.Group className="mb-3" controlId="doctor-profile-sexe">
                        <Form.Label>Gender</Form.Label>
                        <Form.Select name="sexe" value={formData.sexe || ''} onChange={handleChange} disabled={!isEditing || isSavingProfile} isInvalid={!!errors.sexe}>
                          <option value="">Select...</option><option value="homme">Male</option><option value="femme">Female</option>
                        </Form.Select>
                        <Form.Control.Feedback type="invalid">{errors.sexe}</Form.Control.Feedback>
                      </Form.Group></Col>
                      <Col md={6}><Form.Group className="mb-3" controlId="doctor-profile-date_de_naissance">
                        <Form.Label>Date of Birth</Form.Label>
                        <Form.Control type="date" name="date_de_naissance" value={formData.date_de_naissance || ''} onChange={handleChange} disabled={!isEditing || isSavingProfile} isInvalid={!!errors.date_de_naissance} />
                        <Form.Control.Feedback type="invalid">{errors.date_de_naissance}</Form.Control.Feedback>
                      </Form.Group></Col>
                    </Row>
                    <Form.Group className="mb-3" controlId="doctor-profile-adresse">
                      <Form.Label>Address</Form.Label>
                      <Form.Control as="textarea" rows={2} name="adresse" value={formData.adresse || ''} onChange={handleChange} disabled={!isEditing || isSavingProfile} isInvalid={!!errors.adresse} />
                      <Form.Control.Feedback type="invalid">{errors.adresse}</Form.Control.Feedback>
                    </Form.Group>

                    {/* Professional Info Fields */}
                    <h5 className="mt-4 mb-3">Professional Details</h5>
                     <Form.Group className="mb-3" controlId="doctor-profile-speciality_id">
                        <Form.Label>Medical Speciality</Form.Label>
                        <Form.Select name="speciality_id" value={formData.speciality_id || ''} onChange={handleChange} disabled={!isEditing || isSavingProfile || isLoadingSpecialities} isInvalid={!!errors.speciality_id}>
                          <option value="">{isLoadingSpecialities ? "Loading..." : "Select Speciality..."}</option>
                          {specialities.map(spec => <option key={spec.id} value={spec.id}>{spec.nom}</option>)}
                        </Form.Select>
                        <Form.Control.Feedback type="invalid">{errors.speciality_id}</Form.Control.Feedback>
                      </Form.Group>
                    <Form.Group className="mb-3" controlId="doctor-profile-description">
                      <Form.Label>Professional Description</Form.Label>
                      <Form.Control as="textarea" rows={3} name="description" value={formData.description || ''} onChange={handleChange} disabled={!isEditing || isSavingProfile} isInvalid={!!errors.description} placeholder="Tell patients about your experience..." />
                      <Form.Control.Feedback type="invalid">{errors.description}</Form.Control.Feedback>
                    </Form.Group>
                    
                    <div className="d-flex justify-content-end mt-4">
                      {!isEditing ? (
                        <>
                          <Button variant="secondary" className="me-2" onClick={() => setShowPasswordModal(true)} disabled={isSavingProfile}>
                            <i className="fas fa-key me-2"></i>Change Password
                          </Button>
                          <Button variant="primary" onClick={handleEdit} disabled={isSavingProfile}>
                            <i className="fas fa-edit me-2"></i>Edit Profile
                          </Button>
                        </>
                      ) : (
                        <>
                          <Button variant="outline-secondary" className="me-2" onClick={handleCancelEdit} disabled={isSavingProfile}>Cancel</Button>
                          <Button type="submit" variant="primary" disabled={isSavingProfile}>
                            {isSavingProfile ? <><Spinner as="span" animation="border" size="sm" role="status" aria-hidden="true" className="me-2" />Saving...</> : <><i className="fas fa-save me-2"></i>Save Changes</>}
                          </Button>
                        </>
                      )}
                    </div>
                  </Form>
                </Tab>

                {/* Documents Tab */}
                <Tab eventKey="documents" title={<><i className="fas fa-file-medical me-2"></i>Verification Documents</>}>
                  <Form onSubmit={handleDocumentUpload} className="mb-4 p-3 border rounded bg-light">
                    <h5 className="mb-3">Upload New Document</h5>
                    <Row>
                      <Col md={5}><Form.Group className="mb-3 mb-md-0" controlId="doctor-profile-documentType">
                        <Form.Label>Document Type</Form.Label>
                        <Form.Select name="documentType" value={documentType} onChange={(e) => setDocumentType(e.target.value)} disabled={isUploadingDocument}>
                          <option value="licence">License</option><option value="cni">ID Card (CNI)</option><option value="diplome">Diploma</option><option value="autre">Other</option>
                        </Form.Select>
                      </Form.Group></Col>
                      <Col md={5}><Form.Group className="mb-3 mb-md-0">
                        <Form.Label>Choose File</Form.Label>
                        <Form.Control type="file" onChange={(e) => setDocumentFile(e.target.files[0])} accept=".pdf,.jpg,.jpeg,.png" disabled={isUploadingDocument} />
                      </Form.Group></Col>
                      <Col md={2} className="d-flex align-items-end">
                        <Button type="submit" variant="success" className="w-100" disabled={!documentFile || isUploadingDocument}>
                          {isUploadingDocument ? <Spinner as="span" animation="border" size="sm" /> : <><i className="fas fa-upload me-1"></i> Upload</>}
                        </Button>
                      </Col>
                    </Row>
                     <Form.Text className="text-muted d-block mt-2">Max file size: 10MB. Supported formats: PDF, JPG, PNG.</Form.Text>
                  </Form>
                  
                  <h5>Uploaded Documents</h5>
                  {isLoadingDocuments ? <div className="text-center p-3"><Spinner animation="border" variant="secondary" /><p>Loading documents...</p></div> :
                   documents && documents.length > 0 ? (
                    <div className="table-responsive">
                      <table className="table table-hover">
                        <thead className="table-light"><tr><th>Type</th><th>File Name</th><th>Status</th><th>Uploaded At</th><th>Reason (if rejected)</th></tr></thead>
                        <tbody>
                          {documents.map(doc => (
                            <tr key={doc.id}>
                              <td>{doc.type.charAt(0).toUpperCase() + doc.type.slice(1)}</td>
                              <td>{doc.original_name}</td>
                              <td><Badge bg={doc.status === 'approved' ? 'success' : doc.status === 'rejected' ? 'danger' : 'warning'}>{doc.status}</Badge></td>
                              <td>{new Date(doc.created_at).toLocaleDateString()}</td>
                              <td>{doc.rejection_reason || '-'}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  ) : <Alert variant="info"><i className="fas fa-info-circle me-2"></i>No documents uploaded yet.</Alert>}
                </Tab>

                {/* Quick Actions Tab */}
                <Tab eventKey="actions" title={<><i className="fas fa-tasks me-2"></i>Quick Actions</>}>
                  <Row className="g-3">
                    <Col md={4}><Card className="h-100 text-center shadow-sm hover-lift rounded-3">
                      <Card.Body><i className="fas fa-clock fa-3x text-primary mb-3"></i><h5>Update Schedule</h5>
                      <p className="text-muted small">Manage your working hours and availability.</p>
                      <Button variant="outline-primary" onClick={() => navigate('/doctor/availability')}>Manage Schedule</Button></Card.Body>
                    </Card></Col>
                    <Col md={4}><Card className="h-100 text-center shadow-sm hover-lift rounded-3">
                      <Card.Body><i className="fas fa-calendar-times fa-3x text-warning mb-3"></i><h5>Manage Leaves</h5>
                      <p className="text-muted small">Set your vacation days or periods of absence.</p>
                      <Button variant="outline-warning" onClick={() => navigate('/leaves')}>Manage Leaves</Button></Card.Body>
                    </Card></Col>
                    <Col md={4}><Card className="h-100 text-center shadow-sm hover-lift rounded-3">
                      <Card.Body><i className="fas fa-list-alt fa-3x text-success mb-3"></i><h5>View Appointments</h5>
                      <p className="text-muted small">Check your upcoming and past appointments.</p>
                      <Button variant="outline-success" onClick={() => navigate(`/doctor/${user?.id}/appointments`)}>View Appointments</Button></Card.Body>
                    </Card></Col>
                  </Row>
                </Tab>
              </Tabs>
            </Card.Body>
          </Card>
        </Col>
      </Row>

      {/* Password Modal */}
      <Modal show={showPasswordModal} onHide={() => setShowPasswordModal(false)} centered>
        <Modal.Header closeButton><Modal.Title>Change Password</Modal.Title></Modal.Header>
        <Form onSubmit={handlePasswordUpdate}>
          <Modal.Body>
            <Form.Group className="mb-3" controlId="doctor-profile-current_password">
              <Form.Label>Current Password</Form.Label>
              <Form.Control type="password" name="current_password" value={passwordData.current_password} onChange={handlePasswordChange} isInvalid={!!passwordErrors.current_password} required />
              <Form.Control.Feedback type="invalid">{passwordErrors.current_password}</Form.Control.Feedback>
            </Form.Group>
            <Form.Group className="mb-3" controlId="doctor-profile-password">
              <Form.Label>New Password</Form.Label>
              <Form.Control type="password" name="password" value={passwordData.password} onChange={handlePasswordChange} isInvalid={!!passwordErrors.password} minLength="8" required />
              <Form.Control.Feedback type="invalid">{passwordErrors.password}</Form.Control.Feedback>
            </Form.Group>
            <Form.Group className="mb-3" controlId="doctor-profile-password_confirmation">
              <Form.Label>Confirm New Password</Form.Label>
              <Form.Control type="password" name="password_confirmation" value={passwordData.password_confirmation} onChange={handlePasswordChange} isInvalid={!!passwordErrors.password_confirmation} required />
              <Form.Control.Feedback type="invalid">{passwordErrors.password_confirmation}</Form.Control.Feedback>
            </Form.Group>
          </Modal.Body>
          <Modal.Footer>
            <Button variant="secondary" onClick={() => setShowPasswordModal(false)}>Cancel</Button>
            <Button variant="primary" type="submit" disabled={isUpdatingPassword}>
              {isUpdatingPassword ? <><Spinner as="span" animation="border" size="sm" /> Updating...</> : 'Update Password'}
            </Button>
          </Modal.Footer>
        </Form>
      </Modal>

      {/* Photo Upload Modal */}
      <Modal show={showPhotoModal} onHide={() => setShowPhotoModal(false)} centered>
        <Modal.Header closeButton><Modal.Title>Update Profile Photo</Modal.Title></Modal.Header>
        <Form onSubmit={handlePhotoUpload}>
          <Modal.Body>
            {userSliceError && <Alert variant="danger">{userSliceError}</Alert>}
            <Form.Group controlId="profile-photo-file">
              <Form.Label>Choose a new photo</Form.Label>
              <Form.Control type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => setPhotoFile(e.target.files[0])} required />
              <Form.Text className="text-muted">Max file size: 5MB. Formats: JPG, PNG, WebP.</Form.Text>
            </Form.Group>
          </Modal.Body>
          <Modal.Footer>
            <Button variant="secondary" onClick={() => setShowPhotoModal(false)}>Cancel</Button>
            <Button variant="primary" type="submit" disabled={!photoFile || isUploadingPhoto}>
              {isUploadingPhoto ? <><Spinner as="span" animation="border" size="sm" /> Uploading...</> : 'Upload Photo'}
            </Button>
          </Modal.Footer>
        </Form>
      </Modal>
    </Container>
  );
};

export default DoctorProfile;
