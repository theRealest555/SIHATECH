import { useEffect, useState } from 'react';

const SocialAuthCallback = () => {
  const [status, setStatus] = useState('processing');
  const [message, setMessage] = useState('Please wait while we complete your authentication...');
  const [error, setError] = useState(null);

  useEffect(() => {
    processCallback();
  }, []);

  const processCallback = async () => {
    try {
      // Get URL parameters
      const urlParams = new URLSearchParams(window.location.search);
      const code = urlParams.get('code');
      const state = urlParams.get('state');
      const error = urlParams.get('error');
      const provider = window.location.pathname.split('/').slice(-2, -1)[0];

      // Handle OAuth errors
      if (error) {
        setStatus('error');
        setError(`Authentication failed: ${error}`);
        
        // Send message to parent window if opened as popup
        if (window.opener) {
          window.opener.postMessage({
            type: 'SOCIAL_AUTH_ERROR',
            error: `Authentication failed: ${error}`
          }, '*');
          setTimeout(() => window.close(), 2000);
        }
        return;
      }

      // Check for authorization code
      if (!code) {
        const errorMsg = 'No authorization code received';
        setStatus('error');
        setError(errorMsg);
        
        if (window.opener) {
          window.opener.postMessage({
            type: 'SOCIAL_AUTH_ERROR',
            error: errorMsg
          }, '*');
          setTimeout(() => window.close(), 2000);
        }
        return;
      }

      // Make request to backend callback
      const callbackUrl = `/api/auth/social/${provider}/callback`;
      const params = new URLSearchParams({
        code: code,
        state: state || ''
      });

      setMessage('Verifying with authentication provider...');

      const response = await fetch(`http://localhost:8000${callbackUrl}?${params.toString()}`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      
      // Store authentication data
      if (data.token) {
        localStorage.setItem('token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));
      }

      setStatus('success');
      setMessage('Authentication successful! Redirecting...');

      // Send success data to parent window if opened as popup
      if (window.opener) {
        window.opener.postMessage({
          type: 'SOCIAL_AUTH_SUCCESS',
          data: data
        }, '*');
        setTimeout(() => window.close(), 1000);
      } else {
        // Fallback: redirect directly
        setTimeout(() => {
          if (data.requires_profile_completion) {
            window.location.href = '/complete-profile';
          } else {
            window.location.href = '/dashboard';
          }
        }, 2000);
      }

    } catch (err) {
      console.error('Callback error:', err);
      const errorMsg = `Authentication failed: ${err.message}`;
      setStatus('error');
      setError(errorMsg);
      
      if (window.opener) {
        window.opener.postMessage({
          type: 'SOCIAL_AUTH_ERROR',
          error: errorMsg
        }, '*');
        setTimeout(() => window.close(), 2000);
      }
    }
  };

  const getStatusIcon = () => {
    switch (status) {
      case 'processing':
        return (
          <div className="spinner-border text-primary mb-4" role="status" style={{ width: '3rem', height: '3rem' }}>
            <span className="visually-hidden">Loading...</span>
          </div>
        );
      case 'success':
        return <i className="fas fa-check-circle text-success mb-4" style={{ fontSize: '3rem' }}></i>;
      case 'error':
        return <i className="fas fa-exclamation-triangle text-danger mb-4" style={{ fontSize: '3rem' }}></i>;
      default:
        return (
          <div className="spinner-border text-primary mb-4" role="status" style={{ width: '3rem', height: '3rem' }}>
            <span className="visually-hidden">Loading...</span>
          </div>
        );
    }
  };

  const getAlertVariant = () => {
    switch (status) {
      case 'success':
        return 'alert-success';
      case 'error':
        return 'alert-danger';
      default:
        return 'alert-info';
    }
  };

  const handleBackToLogin = () => {
    window.location.href = '/login';
  };

  const handleTryAgain = () => {
    window.location.reload();
  };

  return (
    <div className="auth-container">
      <div className="container">
        <div className="row justify-content-center">
          <div className="col-md-6">
            <div className="card auth-card">
              <div className="card-body text-center p-5">
                <h2 className="mb-4 auth-header">
                  {status === 'processing' && 'Processing Authentication'}
                  {status === 'success' && 'Authentication Successful'}
                  {status === 'error' && 'Authentication Failed'}
                </h2>
                
                <div className="mb-4">
                  {getStatusIcon()}
                </div>

                <div className={`alert ${getAlertVariant()} fade-in`}>
                  <i className={`fas fa-${error ? 'exclamation-triangle' : status === 'success' ? 'check' : 'clock'} me-2`}></i>
                  {error || message}
                </div>

                {status === 'processing' && (
                  <div className="mt-4">
                    <div className="progress" style={{ height: '4px' }}>
                      <div 
                        className="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                        role="progressbar" 
                        style={{ width: '100%' }}
                      ></div>
                    </div>
                    <small className="text-muted mt-2 d-block">
                      This may take a few moments...
                    </small>
                  </div>
                )}

                {status === 'success' && (
                  <div className="mt-4">
                    <div className="d-flex justify-content-center">
                      <div className="spinner-border spinner-border-sm text-success me-2" role="status"></div>
                      <span className="text-success">Redirecting to dashboard...</span>
                    </div>
                  </div>
                )}

                {status === 'error' && !window.opener && (
                  <div className="mt-4">
                    <button 
                      className="btn btn-primary me-2"
                      onClick={handleBackToLogin}
                    >
                      <i className="fas fa-arrow-left me-2"></i>
                      Back to Login
                    </button>
                    <button 
                      className="btn btn-outline-primary"
                      onClick={handleTryAgain}
                    >
                      <i className="fas fa-redo me-2"></i>
                      Try Again
                    </button>
                  </div>
                )}

                {/* Loading animation for visual feedback */}
                {status === 'processing' && (
                  <div className="mt-4">
                    <div className="d-flex justify-content-center align-items-center">
                      <div className="me-3">
                        <i className="fab fa-google text-danger"></i>
                      </div>
                      <div className="flex-grow-1">
                        <div className="progress" style={{ height: '2px' }}>
                          <div className="progress-bar bg-primary progress-bar-animated" style={{ width: '60%' }}></div>
                        </div>
                      </div>
                      <div className="ms-3">
                        <i className="fas fa-stethoscope text-primary"></i>
                      </div>
                    </div>
                    <small className="text-muted">Connecting your account...</small>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default SocialAuthCallback;