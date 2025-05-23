import { useState } from 'react';
import { Container, Card, Button, Alert, Table } from 'react-bootstrap';
import axios from 'axios';

const ApiDiagnostic = () => {
  const [results, setResults] = useState([]);
  const [testing, setTesting] = useState(false);

  const endpoints = [
    // Auth endpoints
    { method: 'GET', path: '/api/user', requiresAuth: true, description: 'Get current user' },
    { method: 'POST', path: '/api/logout', requiresAuth: true, description: 'Logout' },
    { method: 'GET', path: '/api/email/verify/check', requiresAuth: true, description: 'Check email verification' },
    
    // Profile endpoints
    { method: 'GET', path: '/api/patient/profile', requiresAuth: true, description: 'Get patient profile' },
    { method: 'GET', path: '/api/doctor/profile', requiresAuth: true, description: 'Get doctor profile' },
    { method: 'PUT', path: '/api/patient/profile', requiresAuth: true, description: 'Update patient profile' },
    { method: 'PUT', path: '/api/doctor/profile', requiresAuth: true, description: 'Update doctor profile' },
    
    // Public doctor endpoints
    { method: 'GET', path: '/api/doctors', requiresAuth: false, description: 'List all doctors' },
    { method: 'GET', path: '/api/doctors/specialities', requiresAuth: false, description: 'List specialities' },
    { method: 'GET', path: '/api/doctors/locations', requiresAuth: false, description: 'List locations' },
    { method: 'GET', path: '/api/doctors/search', requiresAuth: false, description: 'Search doctors' },
    
    // Appointment endpoints
    { method: 'GET', path: '/api/appointments', requiresAuth: false, description: 'List appointments' },
    
    // Protected doctor endpoints
    { method: 'GET', path: '/api/doctor/documents', requiresAuth: true, description: 'List doctor documents' },
    { method: 'POST', path: '/api/doctor/documents', requiresAuth: true, description: 'Upload document' },
  ];

  const testEndpoint = async (endpoint) => {
    const headers = {};
    if (endpoint.requiresAuth) {
      const token = localStorage.getItem('token');
      if (token) {
        headers.Authorization = `Bearer ${token}`;
      }
    }

    try {
      const response = await axios({
        method: endpoint.method,
        url: `http://localhost:8000${endpoint.path}`,
        headers,
        validateStatus: () => true // Don't throw on any status
      });

      return {
        ...endpoint,
        status: response.status,
        statusText: response.statusText,
        success: response.status >= 200 && response.status < 300
      };
    } catch (error) {
      return {
        ...endpoint,
        status: 'ERROR',
        statusText: error.message,
        success: false
      };
    }
  };

  const runDiagnostic = async () => {
    setTesting(true);
    setResults([]);

    const testResults = [];
    for (const endpoint of endpoints) {
      const result = await testEndpoint(endpoint);
      testResults.push(result);
      setResults([...testResults]);
    }

    setTesting(false);
  };

  const getStatusColor = (status) => {
    if (status >= 200 && status < 300) return 'success';
    if (status === 401) return 'warning';
    if (status === 404) return 'danger';
    if (status >= 400 && status < 500) return 'warning';
    if (status >= 500) return 'danger';
    return 'secondary';
  };

  return (
    <Container className="py-4">
      <Card>
        <Card.Header>
          <h4 className="mb-0">API Endpoint Diagnostic</h4>
        </Card.Header>
        <Card.Body>
          <Alert variant="info">
            This tool tests which API endpoints are available and working. 
            Make sure you're logged in if testing authenticated endpoints.
          </Alert>
          
          <Button 
            onClick={runDiagnostic} 
            disabled={testing}
            className="mb-3"
          >
            {testing ? 'Testing...' : 'Run Diagnostic'}
          </Button>

          {results.length > 0 && (
            <Table striped bordered hover>
              <thead>
                <tr>
                  <th>Method</th>
                  <th>Endpoint</th>
                  <th>Description</th>
                  <th>Auth Required</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {results.map((result, index) => (
                  <tr key={index}>
                    <td>
                      <Badge bg="primary">{result.method}</Badge>
                    </td>
                    <td><code>{result.path}</code></td>
                    <td>{result.description}</td>
                    <td>{result.requiresAuth ? 'Yes' : 'No'}</td>
                    <td>
                      <Badge bg={getStatusColor(result.status)}>
                        {result.status} {result.statusText}
                      </Badge>
                    </td>
                  </tr>
                ))}
              </tbody>
            </Table>
          )}
        </Card.Body>
      </Card>
    </Container>
  );
};

export default ApiDiagnostic;