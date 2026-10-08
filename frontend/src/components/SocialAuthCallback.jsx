import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

export default function SocialAuthCallback() {
  const { fetchUser } = useAuth();
  const navigate = useNavigate();
  const [error, setError] = useState('');
  useEffect(() => {
    let active = true;
    fetchUser().then(user => {
      if (!active) return;
      if (user) navigate('/dashboard', { replace: true });
      else setError('Your session could not be established. Please sign in again.');
    }).catch(() => { if (active) setError('Unable to verify your session. Please retry.'); });
    return () => { active = false; };
  }, [fetchUser, navigate]);
  return <div className="p-8" role="status">{error || 'Checking your session…'}{error && <p><Link to="/login">Return to login</Link></p>}</div>;
}
