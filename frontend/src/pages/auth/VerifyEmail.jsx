import { useState } from 'react';
import { Link, Navigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { resendVerificationEmail } from '../../services/authService';

export default function VerifyEmail() {
  const { user, fetchUser } = useAuth();
  const [searchParams] = useSearchParams();
  const [message, setMessage] = useState(() => searchParams.get('error') === 'invalid-link'
    ? 'This verification link is invalid or expired. Request a new email and open its link while signed in.'
    : 'Check your inbox and open the verification link while signed in.');
  const [busy, setBusy] = useState(false);
  if (!user) return <div className="p-8">Please <Link to="/login">sign in</Link> to verify your email.</div>;
  if (user.email_verified_at) return <Navigate to="/dashboard" replace />;
  const check = async resend => {
    if (busy) return;
    setBusy(true);
    try {
      if (resend) {
        const response = await resendVerificationEmail();
        if (response?.data?.status === 'already-verified') {
          await fetchUser();
          setMessage('Your email is already verified. Continue to your dashboard.');
        } else if (response?.data?.status === 'verification-link-sent') {
          setMessage('Verification email sent. Check your inbox and spam folder.');
        } else {
          setMessage('Unable to confirm delivery. Check verification or try again later.');
        }
      }
      else { const current = await fetchUser(); if (!current?.email_verified_at) setMessage('Your email is not verified yet. Open the email link first.'); }
    } catch (error) {
      setMessage(error.response?.status === 429 ? 'Too many verification requests. Wait a minute before trying again.'
        : error.response?.status === 503 ? 'Unable to send verification email. Please try again later.'
          : 'Unable to complete the request. Please retry.');
    }
    finally { setBusy(false); }
  };
  return <section className="max-w-lg mx-auto p-8 bg-white rounded-xl shadow">
    <h1 className="text-2xl font-semibold mb-4">Verify your email</h1>
    <p className="mb-3">Verification address: <strong>{user.email}</strong></p>
    <p role="status" className="mb-4">{message}</p>
    <div className="flex flex-wrap gap-2">
      <button disabled={busy} onClick={() => check(false)} className="rounded-md bg-indigo-600 text-white px-4 py-2 hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50">Check verification</button>
      <button disabled={busy} onClick={() => check(true)} className="rounded-md border border-indigo-600 bg-white text-indigo-700 px-4 py-2 hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50">Resend email</button>
    </div>
  </section>;
}
