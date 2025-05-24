import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import { Card, Row, Col, Button, Container, Alert, Badge, Spinner, ProgressBar } from 'react-bootstrap';
import { Calendar, momentLocalizer } from 'react-big-calendar';
import moment from 'moment';
import 'react-big-calendar/lib/css/react-big-calendar.css';

// Redux selectors and actions
import { selectCurrentUser, logout } from '../redux/slices/authSlice';
import { 
  fetchUserProfile, 
  selectUserProfile, 
  selectUserStatus, 
  selectUserError 
} from '../redux/slices/userSlice';
import { 
  fetchPatientAppointments, 
  selectPatientAppointments, 
  selectPatientStatus 
} from '../redux/slices/patientSlice';

const localizer = momentLocalizer(moment);

const Dashboard = () => {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  
  // Get data from Redux store
  const user = useSelector(selectCurrentUser);
  const profile = useSelector(selectUserProfile);
  const userStatus = useSelector(selectUserStatus);
  const userError = useSelector(selectUserError);
  const appointments = useSelector(selectPatientAppointments);
  const appointmentStatus = useSelector(selectPatientStatus);
  
  // Local state for derived data
  const [stats, setStats] = useState({
    total: 0,
    confirmed: 0,
    pending: 0,
    completed: 0,
    cancelled: 0,
    todayCount: 0,
    weekCount: 0,
    monthCount: 0
  });
  const [calendarEvents, setCalendarEvents] = useState([]);
  const [nextAppointment, setNextAppointment] = useState(null);

  // Check loading states
  const isLoading = userStatus === 'loading' || appointmentStatus === 'loading';

  useEffect(() => {
    // Fetch profile data
    dispatch(fetchUserProfile());
    
    // Fetch appointments if user is available
    if (user?.id) {
      dispatch(fetchPatientAppointments(user.id));
    }
  }, [dispatch, user?.id]);

  // Process appointments data when it changes
  useEffect(() => {
    if (appointments && appointments.length > 0) {
      calculateStats(appointments);
      prepareCalendarEvents(appointments, user?.role);
      findNextAppointment(appointments);
    }
  }, [appointments, user?.role]);

  const calculateStats = (appointmentsData) => {
    const now = moment();
    const stats = {
      total: appointmentsData.length,
      confirmed: appointmentsData.filter(apt => apt.statut === 'confirmé').length,
      pending: appointmentsData.filter(apt => apt.statut === 'en_attente').length,
      completed: appointmentsData.filter(apt => apt.statut === 'terminé').length,
      cancelled: appointmentsData.filter(apt => apt.statut === 'annulé').length,
      todayCount: appointmentsData.filter(apt => 
        moment(apt.date_heure).isSame(now, 'day') && apt.statut !== 'annulé'
      ).length,
      weekCount: appointmentsData.filter(apt => 
        moment(apt.date_heure).isSame(now, 'week') && apt.statut !== 'annulé'
      ).length,
      monthCount: appointmentsData.filter(apt => 
        moment(apt.date_heure).isSame(now, 'month') && apt.statut !== 'annulé'
      ).length
    };
    setStats(stats);
  };

  const prepareCalendarEvents = (appointmentsData, role) => {
    const events = appointmentsData
      .filter(apt => apt.statut !== 'annulé')
      .map(apt => ({
        id: apt.id,
        title: role === 'patient' 
          ? `Dr. ${apt.doctor_name || 'N/A'}`
          : apt.patient_name || 'Patient',
        start: new Date(apt.date_heure),
        end: moment(apt.date_heure).add(30, 'minutes').toDate(),
        resource: apt,
        color: apt.statut === 'confirmé' ? '#28a745' : 
               apt.statut === 'terminé' ? '#6c757d' : '#ffc107'
      }));
    setCalendarEvents(events);
  };

  const findNextAppointment = (appointmentsData) => {
    const upcoming = appointmentsData
      .filter(apt => 
        moment(apt.date_heure).isAfter(moment()) && 
        apt.statut !== 'annulé' && 
        apt.statut !== 'terminé'
      )
      .sort((a, b) => moment(a.date_heure).diff(moment(b.date_heure)));
    
    if (upcoming.length > 0) {
      setNextAppointment(upcoming[0]);
    }
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

  const getProfileCompletionPercentage = () => {
    if (!profile) return 0;
    
    let completed = 0;
    let total = 0;
    
    if (user?.role === 'patient') {
      const fields = ['nom', 'prenom', 'email', 'telephone', 'adresse', 'date_de_naissance', 'sexe'];
      total = fields.length;
      fields.forEach(field => {
        if (profile.user?.[field]) completed++;
      });
    } else if (user?.role === 'medecin') {
      const userFields = ['nom', 'prenom', 'email', 'telephone', 'adresse', 'date_de_naissance', 'sexe'];
      const doctorFields = ['speciality_id', 'description'];
      total = userFields.length + doctorFields.length + 1; // +1 for documents
      
      userFields.forEach(field => {
        if (profile.user?.[field]) completed++;
      });
      
      doctorFields.forEach(field => {
        if (profile.doctor?.[field]) completed++;
      });
      
      if (profile.doctor?.documents?.length > 0) completed++;
    }
    
    return Math.round((completed / total) * 100);
  };

  const eventStyleGetter = (event) => {
    return {
      style: {
        backgroundColor: event.color,
        borderRadius: '5px',
        opacity: 0.8,
        color: 'white',
        border: '0px',
        display: 'block'
      }
    };
  };

  const handleLogout = () => {
    dispatch(logout())
      .unwrap()
      .then(() => {
        navigate('/login');
      })
      .catch(() => {
        navigate('/login');
      });
  };

  if (isLoading && !profile) {
    return (
      <Container className="mt-5">
        <div className="text-center">
          <Spinner animation="border" variant="primary" />
          <p className="mt-3">Loading dashboard...</p>
        </div>
      </Container>
    );
  }

  if (!user) {
    return (
      <Container className="mt-4">
        <Alert variant="danger">
          Unable to load user data. Please try logging in again.
        </Alert>
        <Button 
          variant="primary" 
          onClick={() => navigate('/login')}
          className="mt-3"
        >
          Go to Login
        </Button>
      </Container>
    );
  }

  const profileCompletion = getProfileCompletionPercentage();

  return (
    <Container fluid className="py-4">
      {/* Welcome Header */}
      <Row className="mb-4">
        <Col>
          <Card className="bg-gradient-primary text-white">
            <Card.Body>
              <Row className="align-items-center">
                <Col md={8}>
                  <h1 className="h2 mb-2">
                    Welcome back, {user.prenom} {user.nom}!
                  </h1>
                  <p className="mb-3 opacity-75">
                    {user.role === 'patient' ? 'Patient Dashboard' : 'Doctor Dashboard'}
                    {user.role === 'medecin' && profile?.doctor && (
                      <Badge 
                        bg={profile.doctor.is_verified ? 'success' : 'warning'} 
                        className="ms-2"
                      >
                        {profile.doctor.is_verified ? 'Verified' : 'Pending Verification'}
                      </Badge>
                    )}
                  </p>
                  {nextAppointment && (
                    <Alert variant="light" className="mb-0 py-2 px-3">
                      <i className="fas fa-clock me-2"></i>
                      <strong>Next appointment:</strong> {moment(nextAppointment.date_heure).format('MMM DD at HH:mm')} 
                      with {user.role === 'patient' ? `Dr. ${nextAppointment.doctor_name}` : nextAppointment.patient_name}
                    </Alert>
                  )}
                </Col>
                <Col md={4} className="text-md-end">
                  <div className="mb-3">
                    <small className="d-block mb-1">Profile Completion</small>
                    <ProgressBar 
                      now={profileCompletion} 
                      label={`${profileCompletion}%`} 
                      variant={profileCompletion === 100 ? 'success' : profileCompletion >= 75 ? 'warning' : 'danger'}
                    />
                  </div>
                  <Button 
                    variant="light" 
                    onClick={() => navigate(user.role === 'patient' ? '/patient/profile' : '/doctor/profile')}
                  >
                    <i className="fas fa-user me-2"></i>
                    Complete Profile
                  </Button>
                </Col>
              </Row>
            </Card.Body>
          </Card>
        </Col>
      </Row>

      {userError && (
        <Alert variant="danger" className="mb-4">
          {userError}
        </Alert>
      )}

      {/* Statistics Cards */}
      <Row className="g-4 mb-4">
        <Col md={3}>
          <Card className="text-center h-100 dashboard-card">
            <Card.Body>
              <i className="fas fa-calendar-check fa-3x text-primary mb-3"></i>
              <h3 className="mb-1">{stats.total}</h3>
              <p className="text-muted mb-0">Total Appointments</p>
            </Card.Body>
          </Card>
        </Col>
        <Col md={3}>
          <Card className="text-center h-100 dashboard-card">
            <Card.Body>
              <i className="fas fa-calendar-day fa-3x text-info mb-3"></i>
              <h3 className="mb-1">{stats.todayCount}</h3>
              <p className="text-muted mb-0">Today's Appointments</p>
            </Card.Body>
          </Card>
        </Col>
        <Col md={3}>
          <Card className="text-center h-100 dashboard-card">
            <Card.Body>
              <i className="fas fa-hourglass-half fa-3x text-warning mb-3"></i>
              <h3 className="mb-1">{stats.pending}</h3>
              <p className="text-muted mb-0">Pending Confirmation</p>
            </Card.Body>
          </Card>
        </Col>
        <Col md={3}>
          <Card className="text-center h-100 dashboard-card">
            <Card.Body>
              <i className="fas fa-check-circle fa-3x text-success mb-3"></i>
              <h3 className="mb-1">{stats.confirmed}</h3>
              <p className="text-muted mb-0">Confirmed</p>
            </Card.Body>
          </Card>
        </Col>
      </Row>

      <Row className="g-4">
        {/* Quick Actions */}
        <Col lg={4}>
          <Card className="h-100">
            <Card.Header className="bg-white">
              <h5 className="mb-0">
                <i className="fas fa-bolt me-2"></i>
                Quick Actions
              </h5>
            </Card.Header>
            <Card.Body>
              <div className="d-grid gap-2">
                {user.role === 'patient' ? (
                  <>
                    <Button 
                      variant="primary" 
                      onClick={() => navigate('/patient')}
                      className="text-start"
                    >
                      <i className="fas fa-search me-2"></i>
                      Find a Doctor
                    </Button>
                    <Button 
                      variant="outline-primary" 
                      onClick={() => navigate('/patient/profile')}
                      className="text-start"
                    >
                      <i className="fas fa-user me-2"></i>
                      My Profile
                    </Button>
                    {profile?.patient?.medecinFavori && (
                      <Button 
                        variant="outline-success" 
                        onClick={() => navigate(`/doctor-calendar/${profile.patient.medecin_favori_id}`)}
                        className="text-start"
                      >
                        <i className="fas fa-star me-2"></i>
                        Book with Favorite Doctor
                      </Button>
                    )}
                  </>
                ) : (
                  <>
                    <Button 
                      variant="primary" 
                      onClick={() => navigate('/doctor')}
                      className="text-start"
                    >
                      <i className="fas fa-calendar me-2"></i>
                      My Calendar
                    </Button>
                    {profile?.doctor?.is_verified ? (
                      <>
                        <Button 
                          variant="outline-primary" 
                          onClick={() => navigate('/schedule')}
                          className="text-start"
                        >
                          <i className="fas fa-clock me-2"></i>
                          Update Schedule
                        </Button>
                        <Button 
                          variant="outline-primary" 
                          onClick={() => navigate('/leaves')}
                          className="text-start"
                        >
                          <i className="fas fa-calendar-times me-2"></i>
                          Manage Leaves
                        </Button>
                      </>
                    ) : (
                      <Alert variant="warning" className="mb-0">
                        <i className="fas fa-exclamation-triangle me-2"></i>
                        Complete verification to access all features
                      </Alert>
                    )}
                    <Button 
                      variant="outline-primary" 
                      onClick={() => navigate('/doctor/profile')}
                      className="text-start"
                    >
                      <i className="fas fa-user me-2"></i>
                      My Profile
                    </Button>
                  </>
                )}
              </div>

              {user.role === 'medecin' && profile?.doctor && !profile.doctor.is_verified && (
                <Card className="mt-3 bg-warning bg-opacity-10 border-warning">
                  <Card.Body>
                    <h6 className="text-warning">
                      <i className="fas fa-info-circle me-2"></i>
                      Verification Required
                    </h6>
                    <p className="small mb-2">Please upload the following documents:</p>
                    <ul className="small mb-2">
                      <li>Medical License</li>
                      <li>ID Card (CNI)</li>
                      <li>Medical Diploma</li>
                    </ul>
                    <Button 
                      size="sm" 
                      variant="warning" 
                      onClick={() => navigate('/doctor/profile')}
                    >
                      Upload Documents
                    </Button>
                  </Card.Body>
                </Card>
              )}
            </Card.Body>
          </Card>
        </Col>

        {/* Calendar */}
        <Col lg={8}>
          <Card className="h-100">
            <Card.Header className="bg-white">
              <Row className="align-items-center">
                <Col>
                  <h5 className="mb-0">
                    <i className="fas fa-calendar-alt me-2"></i>
                    Appointments Overview
                  </h5>
                </Col>
                <Col xs="auto">
                  <Button 
                    size="sm" 
                    variant="outline-primary"
                    onClick={() => navigate(user.role === 'patient' ? '/patient' : `/doctor/${user.id}/appointments`)}
                  >
                    View All
                  </Button>
                </Col>
              </Row>
            </Card.Header>
            <Card.Body style={{ height: '400px' }}>
              {isLoading ? (
                <div className="text-center my-5">
                  <Spinner animation="border" variant="primary" />
                  <p className="mt-2">Loading calendar data...</p>
                </div>
              ) : (
                <Calendar
                  localizer={localizer}
                  events={calendarEvents}
                  startAccessor="start"
                  endAccessor="end"
                  style={{ height: '100%' }}
                  eventPropGetter={eventStyleGetter}
                  views={['month', 'week', 'day']}
                  defaultView="week"
                  popup
                  onSelectEvent={(event) => {
                    if (user.role === 'medecin') {
                      navigate(`/doctor/${user.id}/appointments`);
                    }
                  }}
                />
              )}
            </Card.Body>
          </Card>
        </Col>
      </Row>

      {/* Upcoming Appointments List */}
      <Row className="mt-4">
        <Col>
          <Card>
            <Card.Header className="bg-white">
              <h5 className="mb-0">
                <i className="fas fa-clock me-2"></i>
                Upcoming Appointments
              </h5>
            </Card.Header>
            <Card.Body>
              {isLoading ? (
                <div className="text-center py-4">
                  <Spinner animation="border" variant="primary" />
                  <p className="mt-2">Loading appointments...</p>
                </div>
              ) : appointments
                .filter(apt => 
                  moment(apt.date_heure).isAfter(moment()) && 
                  apt.statut !== 'annulé' && 
                  apt.statut !== 'terminé'
                )
                .sort((a, b) => moment(a.date_heure).diff(moment(b.date_heure)))
                .slice(0, 5)
                .length > 0 ? (
                <div className="table-responsive">
                  <table className="table table-hover">
                    <thead>
                      <tr>
                        <th>{user.role === 'patient' ? 'Doctor' : 'Patient'}</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Time Until</th>
                      </tr>
                    </thead>
                    <tbody>
                      {appointments
                        .filter(apt => 
                          moment(apt.date_heure).isAfter(moment()) && 
                          apt.statut !== 'annulé' && 
                          apt.statut !== 'terminé'
                        )
                        .sort((a, b) => moment(a.date_heure).diff(moment(b.date_heure)))
                        .slice(0, 5)
                        .map((appointment) => (
                          <tr key={appointment.id}>
                            <td>
                              <div>
                                <strong>
                                  {user.role === 'patient' 
                                    ? appointment.doctor_name || 'Dr. N/A'
                                    : appointment.patient_name || 'Patient N/A'
                                  }
                                </strong>
                                {user.role === 'patient' && (
                                  <small className="d-block text-muted">
                                    {appointment.speciality || 'N/A'}
                                  </small>
                                )}
                              </div>
                            </td>
                            <td>{moment(appointment.date_heure).format('MMM DD, YYYY HH:mm')}</td>
                            <td>
                              <Badge bg={getStatusBadgeClass(appointment.statut)}>
                                {appointment.statut}
                              </Badge>
                            </td>
                            <td>
                              <small className="text-muted">
                                {moment(appointment.date_heure).fromNow()}
                              </small>
                            </td>
                          </tr>
                        ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="text-center py-4">
                  <i className="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                  <p className="text-muted">No upcoming appointments</p>
                  {user.role === 'patient' && (
                    <Button 
                      variant="primary" 
                      onClick={() => navigate('/patient')}
                    >
                      Book Appointment
                    </Button>
                  )}
                </div>
              )}
            </Card.Body>
          </Card>
        </Col>
      </Row>
    </Container>
  );
};

export default Dashboard;