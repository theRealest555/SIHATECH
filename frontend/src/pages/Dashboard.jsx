import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, Row, Col, Button, Container, Alert } from 'react-bootstrap';
import ApiService from '../services/api';
import moment from 'moment';

const Dashboard = () => {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const userData = localStorage.getItem('user');
    if (userData) {
      try {
        const parsedUser = JSON.parse(userData);
        setUser(parsedUser);
        fetchDashboardData(parsedUser);
      } catch (error) {
        console.error('Error parsing user data:', error);
        setError('Failed to load user data');
      }
    }
    setLoading(false);
  }, []);

  const fetchDashboardData = async (userData) => {
    try {
      let response;
      if (userData.role === 'patient') {
        response = await ApiService.getPatientAppointments(userData.id || 1);
      } else if (userData.role === 'medecin') {
        response = await ApiService.getAppointments(userData.id || 1);
      }
      setAppointments(response?.data?.data || []);
    } catch (error) {
      console.error('Error fetching appointments:', error);
      setError('Failed to load appointments');
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

  const getUpcomingAppointments = () => {
    return appointments
      .filter(apt => moment(apt.date_heure).isAfter(moment()) && apt.statut !== 'annulé')
      .sort((a, b) => moment(a.date_heure).diff(moment(b.date_heure)))
      .slice(0, 5);
  };

  const getTodayAppointments = () => {
    return appointments.filter(apt => 
      moment(apt.date_heure).isSame(moment(), 'day') && apt.statut !== 'annulé'
    );
  };

  if (loading) {
    return (
      <Container className="mt-4">
        <div className="text-center">
          <div className="spinner-border" role="status">
            <span className="visually-hidden">Loading...</span>
          </div>
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
      </Container>
    );
  }

  return (
    <Container fluid className="py-4">
      <div className="mb-4">
        <h1 className="h2 mb-0">
          Welcome back, {user.prenom} {user.nom}!
        </h1>
        <p className="text-muted">
          {user.role === 'patient' ? 'Patient Dashboard' : 'Doctor Dashboard'}
        </p>
      </div>

      {error && (
        <Alert variant="danger" className="mb-4">
          {error}
        </Alert>
      )}

      <Row className="g-4">
        {/* Quick Actions */}
        <Col lg={4}>
          <Card className="h-100">
            <Card.Header>
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
            </Card.Body>
          </Card>
        </Col>

        {/* Today's Appointments */}
        <Col lg={4}>
          <Card className="h-100">
            <Card.Header>
              <h5 className="mb-0">
                <i className="fas fa-calendar-day me-2"></i>
                Today's Appointments
              </h5>
            </Card.Header>
            <Card.Body>
              {getTodayAppointments().length > 0 ? (
                <div className="list-group list-group-flush">
                  {getTodayAppointments().map((appointment) => (
                    <div key={appointment.id} className="list-group-item px-0 py-2">
                      <div className="d-flex justify-content-between align-items-start">
                        <div>
                          <h6 className="mb-1">
                            {user.role === 'patient' 
                              ? appointment.doctor_name || 'Dr. N/A'
                              : appointment.patient_name || 'Patient N/A'
                            }
                          </h6>
                          <p className="mb-1 small text-muted">
                            {moment(appointment.date_heure).format('HH:mm')}
                          </p>
                        </div>
                        <span className={`badge bg-${getStatusBadgeClass(appointment.statut)}`}>
                          {appointment.statut}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-4">
                  <i className="fas fa-calendar-check fa-2x text-muted mb-3"></i>
                  <p className="text-muted">No appointments today</p>
                </div>
              )}
            </Card.Body>
          </Card>
        </Col>

        {/* Upcoming Appointments */}
        <Col lg={4}>
          <Card className="h-100">
            <Card.Header>
              <h5 className="mb-0">
                <i className="fas fa-clock me-2"></i>
                Upcoming Appointments
              </h5>
            </Card.Header>
            <Card.Body>
              {getUpcomingAppointments().length > 0 ? (
                <div className="list-group list-group-flush">
                  {getUpcomingAppointments().map((appointment) => (
                    <div key={appointment.id} className="list-group-item px-0 py-2">
                      <div className="d-flex justify-content-between align-items-start">
                        <div>
                          <h6 className="mb-1">
                            {user.role === 'patient' 
                              ? appointment.doctor_name || 'Dr. N/A'
                              : appointment.patient_name || 'Patient N/A'
                            }
                          </h6>
                          <p className="mb-1 small text-muted">
                            {moment(appointment.date_heure).format('MMM DD, HH:mm')}
                          </p>
                        </div>
                        <span className={`badge bg-${getStatusBadgeClass(appointment.statut)}`}>
                          {appointment.statut}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-4">
                  <i className="fas fa-calendar fa-2x text-muted mb-3"></i>
                  <p className="text-muted">No upcoming appointments</p>
                  {user.role === 'patient' && (
                    <Button 
                      variant="outline-primary" 
                      size="sm"
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

      {/* Statistics Row */}
      <Row className="mt-4 g-4">
        <Col md={3}>
          <Card className="text-center bg-primary text-white">
            <Card.Body>
              <i className="fas fa-calendar-check fa-2x mb-2"></i>
              <h3 className="mb-0">{appointments.length}</h3>
              <small>Total Appointments</small>
            </Card.Body>
          </Card>
        </Col>
        <Col md={3}>
          <Card className="text-center bg-success text-white">
            <Card.Body>
              <i className="fas fa-check-circle fa-2x mb-2"></i>
              <h3 className="mb-0">
                {appointments.filter(apt => apt.statut === 'confirmé').length}
              </h3>
              <small>Confirmed</small>
            </Card.Body>
          </Card>
        </Col>
        <Col md={3}>
          <Card className="text-center bg-warning text-white">
            <Card.Body>
              <i className="fas fa-clock fa-2x mb-2"></i>
              <h3 className="mb-0">
                {appointments.filter(apt => apt.statut === 'en_attente').length}
              </h3>
              <small>Pending</small>
            </Card.Body>
          </Card>
        </Col>
        <Col md={3}>
          <Card className="text-center bg-secondary text-white">
            <Card.Body>
              <i className="fas fa-history fa-2x mb-2"></i>
              <h3 className="mb-0">
                {appointments.filter(apt => apt.statut === 'terminé').length}
              </h3>
              <small>Completed</small>
            </Card.Body>
          </Card>
        </Col>
      </Row>
    </Container>
  );
};

export default Dashboard;