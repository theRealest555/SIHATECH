import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

const VerifyEmail = () => {
  const navigate = useNavigate();
  const [status, setStatus] = useState('pending');
  const [error, setError] = useState(null);

  const checkVerificationStatus = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get('http://localhost:8000/api/email/verify/check', {
        headers: { Authorization: `Bearer ${token}` }
      });
      
      if (response.data.verified) {
        navigate('/dashboard');
      }
    } catch (err) {
      setError('Failed to check verification status');
    }
  };

  const resendVerification = async () => {
    try {
      const token = localStorage.getItem('token');
      await axios.post('http://localhost:8000/api/email/verification-notification', {}, {
        headers: { Authorization: `Bearer ${token}` }
      });
      setStatus('sent');
    } catch (err) {
      setError('Failed to resend verification email');
    }
  };

  useEffect(() => {
    checkVerificationStatus();
  }, []);

  return (
    <div className="container mt-5">
      <div className="row justify-content-center">
        <div className="col-md-6">
          <div className="card">
            <div className="card-body text-center">
              <h2 className="mb-4">Verify Your Email</h2>
              {error && <div className="alert alert-danger">{error}</div>}
              <p>Please check your email for a verification link.</p>
              {status === 'sent' && (
                <div className="alert alert-success">
                  Verification email has been resent!
                </div>
              )}
              <button
                className="btn btn-primary"
                onClick={resendVerification}
                disabled={status === 'sent'}
              >
                Resend Verification Email
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default VerifyEmail;