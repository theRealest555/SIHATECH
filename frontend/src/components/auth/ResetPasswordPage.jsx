
// src/components/auth/ResetPasswordPage.jsx
import { useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import axios from '../../api/axios';
import AuthLayout from './AuthLayout';
import { FaLock, FaUndo } from 'react-icons/fa';

const ResetPasswordPage = () => {
    const { token } = useParams(); // Gets token from URL: /reset-password/:token
    const [searchParams] = useSearchParams();
    const [email, setEmail] = useState(() => searchParams.get('email') || '');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (loading || message) return;
        if (password !== passwordConfirmation) {
            setError('Passwords do not match.');
            return;
        }
        setLoading(true);
        setMessage('');
        setError('');
        try {
            await axios.get('/sanctum/csrf-cookie');
            const response = await axios.post('/api/reset-password', {
                token,
                email, // Send email if your backend requires it
                password,
                password_confirmation: passwordConfirmation,
            });
            setMessage(response.data.message || 'Password reset successfully. Sign in with your new password.');
            setPassword('');
            setPasswordConfirmation('');
        } catch (err) {
            setError(err.response?.status === 429
                ? 'Too many reset attempts. Wait a minute before trying again.'
                : err.response?.data?.errors?.password?.[0] || err.response?.data?.errors?.email?.[0]
                    || err.response?.data?.errors?.token?.[0] || 'We could not reset your password. Try again or request a new link.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <AuthLayout title="Reset Your Password" subtitle="Choose a new strong password.">
            <form onSubmit={handleSubmit} className="space-y-6">
                {message && <p role="status" className="text-green-600 bg-green-100 p-3 rounded-md text-sm">{message}</p>}
                {error && <p role="alert" className="text-red-500 bg-red-100 p-3 rounded-md text-sm">{error}</p>}
                
                {/* Include email field if your backend's reset password requires it */}
                <div>
                    <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                        Your Email Address (as used in forgot password request)
                    </label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        disabled={loading || Boolean(message)}
                        autoComplete="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        className="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="you@example.com"
                    />
                </div>


                <div>
                    <label htmlFor="password" className="block text-sm font-medium text-gray-700">New Password</label>
                    <div className="mt-1 relative rounded-md shadow-sm">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <FaLock className="h-5 w-5 text-gray-400" />
                        </div>
                        <input id="password" name="password" type="password" autoComplete="new-password" disabled={loading || Boolean(message)} required value={password} onChange={(e) => setPassword(e.target.value)} className="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="New strong password"/>
                    </div>
                </div>
                <div>
                    <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700">Confirm New Password</label>
                     <div className="mt-1 relative rounded-md shadow-sm">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <FaLock className="h-5 w-5 text-gray-400" />
                        </div>
                        <input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" disabled={loading || Boolean(message)} required value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)} className="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Confirm new password"/>
                    </div>
                </div>
                <div>
                    <button
                        type="submit"
                        disabled={loading || Boolean(message)}
                        className="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                    >
                        {loading ? 'Resetting...' : message ? 'Password reset' : 'Reset Password'}
                         {!loading && <FaUndo className="ml-2 h-4 w-4" />}
                    </button>
                </div>
            </form>
            {!message && <p className="mt-4 text-center text-sm"><Link to="/forgot-password" className="font-medium text-indigo-600 hover:text-indigo-500">Request a new reset link</Link></p>}
             <p className="mt-8 text-center text-sm text-gray-600">
                <Link to="/login" className="font-medium text-indigo-600 hover:text-indigo-500">
                    Back to Sign in
                </Link>
            </p>
        </AuthLayout>
    );
};

export default ResetPasswordPage;

