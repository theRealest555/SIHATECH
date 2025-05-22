import { useState, useEffect } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { Navbar as BootstrapNavbar, Nav, NavDropdown, Container, Button } from 'react-bootstrap';

const Navbar = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const [user, setUser] = useState(null);
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem('token');
    const userData = localStorage.getItem('user');
    
    if (token && userData) {
      try {
        const parsedUser = JSON.parse(userData);
        setUser(parsedUser);
        setIsAuthenticated(true);
      } catch (error) {
        console.error('Error parsing user data:', error);
        localStorage.removeItem('token');
        localStorage.removeItem('user');
      }
    }
  }, [location]);

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    setUser(null);
    setIsAuthenticated(false);
    navigate('/login');
  };

  // Don't show navbar on auth pages
  const hideNavbarPaths = ['/login', '/register', '/admin/login', '/verify-email'];
  if (hideNavbarPaths.includes(location.pathname)) {
    return null;
  }

  return (
    <BootstrapNavbar bg="primary" variant="dark" expand="lg" className="shadow-sm">
      <Container>
        <BootstrapNavbar.Brand as={Link} to="/dashboard" className="fw-bold">
          <i className="fas fa-stethoscope me-2"></i>
          MedConnect
        </BootstrapNavbar.Brand>
        
        <BootstrapNavbar.Toggle aria-controls="basic-navbar-nav" />
        <BootstrapNavbar.Collapse id="basic-navbar-nav">
          {isAuthenticated ? (
            <>
              <Nav className="me-auto">
                <Nav.Link as={Link} to="/dashboard" active={location.pathname === '/dashboard'}>
                  <i className="fas fa-home me-1"></i>
                  Dashboard
                </Nav.Link>
                
                {user?.role === 'patient' && (
                  <>
                    <Nav.Link as={Link} to="/patient" active={location.pathname === '/patient'}>
                      <i className="fas fa-search me-1"></i>
                      Find Doctor
                    </Nav.Link>
                  </>
                )}
                
                {user?.role === 'medecin' && (
                  <>
                    <Nav.Link as={Link} to="/doctor" active={location.pathname === '/doctor'}>
                      <i className="fas fa-calendar me-1"></i>
                      My Calendar
                    </Nav.Link>
                    <NavDropdown title={<><i className="fas fa-cog me-1"></i>Manage</>} id="doctor-dropdown">
                      <NavDropdown.Item as={Link} to="/schedule">
                        <i className="fas fa-clock me-2"></i>
                        Update Schedule
                      </NavDropdown.Item>
                      <NavDropdown.Item as={Link} to="/leaves">
                        <i className="fas fa-calendar-times me-2"></i>
                        Manage Leaves
                      </NavDropdown.Item>
                      <NavDropdown.Item as={Link} to={`/doctor/${user.id}/appointments`}>
                        <i className="fas fa-list me-2"></i>
                        View Appointments
                      </NavDropdown.Item>
                    </NavDropdown>
                  </>
                )}
              </Nav>
              
              <Nav>
                <NavDropdown 
                  title={
                    <span>
                      <i className="fas fa-user-circle me-1"></i>
                      {user?.prenom} {user?.nom}
                    </span>
                  } 
                  id="user-dropdown"
                  align="end"
                >
                  <NavDropdown.Item as={Link} to={user?.role === 'patient' ? '/patient/profile' : '/doctor/profile'}>
                    <i className="fas fa-user me-2"></i>
                    My Profile
                  </NavDropdown.Item>
                  <NavDropdown.Divider />
                  <NavDropdown.Item onClick={handleLogout}>
                    <i className="fas fa-sign-out-alt me-2"></i>
                    Logout
                  </NavDropdown.Item>
                </NavDropdown>
              </Nav>
            </>
          ) : (
            <Nav className="ms-auto">
              <Button 
                variant="outline-light" 
                className="me-2" 
                onClick={() => navigate('/login')}
              >
                Login
              </Button>
              <Button 
                variant="light" 
                onClick={() => navigate('/register')}
              >
                Register
              </Button>
            </Nav>
          )}
        </BootstrapNavbar.Collapse>
      </Container>
    </BootstrapNavbar>
  );
};

export default Navbar;