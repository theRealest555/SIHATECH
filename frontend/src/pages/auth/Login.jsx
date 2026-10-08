// src/pages/auth/Login.jsx
import { useEffect, useState } from 'react';
import { getSocialProviders } from '../../services/authService';
import { Link, useNavigate, useLocation, useSearchParams } from 'react-router-dom';
import { socialAuthError } from '../../utils/socialAuthErrors';
import { useAuth } from '../../hooks/useAuth';
import { FaEnvelope, FaLock, FaSignInAlt, FaGoogle, FaFacebook } from 'react-icons/fa'; // Example icons
import AuthLayout from '../../components/auth/AuthLayout'; // Assuming you create this

const LoginPage = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [rememberMe, setRememberMe] = useState(false);
    const { login, authError, loading } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const [searchParams, setSearchParams] = useSearchParams();
    const socialError = socialAuthError(searchParams.get('error'));
    const [providers, setProviders] = useState(null);
    const [providerError, setProviderError] = useState(false);
    const [providerReload, setProviderReload] = useState(0);
    useEffect(() => {
        const controller = new AbortController();
        setProviders(null); setProviderError(false);
        getSocialProviders({ signal: controller.signal }).then(response => {
            if (controller.signal.aborted) return;
            const options = response?.data?.data;
            if (typeof options?.google?.available !== 'boolean' || typeof options?.facebook?.available !== 'boolean') throw new Error('Invalid social options');
            setProviders(options);
        }).catch(() => { if (!controller.signal.aborted) setProviderError(true); });
        return () => controller.abort();
    }, [providerReload]);
    const returnLocation = location.state?.from;
    const from = returnLocation?.pathname ? `${returnLocation.pathname}${returnLocation.search || ''}` : '/dashboard';

    const handleSubmit = async (e) => {
        e.preventDefault();
        const success = await login({ email, password, remember: rememberMe });
        if (success) {
            // Navigation will be handled by App.jsx based on user role after user state is updated
            // Or, if login directly returns user data with role:
            // const user = authContext.user; // assuming login updates context immediately
            // if (user.role === 'admin') navigate('/admin/dashboard', { replace: true });
            // else navigate(from, { replace: true });
             navigate(from, { replace: true });
        }
    };
    
    const handleSocialLogin = (provider) => {
        if (loading || providers?.[provider]?.available !== true) return;
        // Construct the full backend URL for Socialite redirect
        const backendUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';
        window.location.href = `${backendUrl}/api/auth/social/${provider}/redirect`;
    };


    return (
        <AuthLayout title="Welcome Back!" subtitle="Login to access your SihaTech account.">
            {socialError && <div role="alert" className="text-red-700 bg-red-50 p-3 rounded-md mb-4">
                <p>{socialError}</p>
                <button type="button" className="underline mt-2" onClick={() => setSearchParams(previous => { const next = new URLSearchParams(previous); next.delete('error'); return next; }, { replace: true })}>Dismiss sign-in message</button>
            </div>}
            <form onSubmit={handleSubmit} className="space-y-6">
                {authError && <p className="text-red-500 text-sm bg-red-100 p-3 rounded-md">{authError}</p>}
                
                <div>
                    <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                        Email address
                    </label>
                    <div className="mt-1 relative rounded-md shadow-sm">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <FaEnvelope className="h-5 w-5 text-gray-400" />
                        </div>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autoComplete="email"
                            required
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            className="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            placeholder="you@example.com"
                        />
                    </div>
                </div>

                <div>
                    <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                        Password
                    </label>
                    <div className="mt-1 relative rounded-md shadow-sm">
                         <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <FaLock className="h-5 w-5 text-gray-400" />
                        </div>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autoComplete="current-password"
                            required
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            className="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            placeholder="••••••••"
                        />
                    </div>
                </div>

                <div className="flex items-center justify-between">
                    <div className="flex items-center">
                        <input
                            id="remember-me"
                            name="remember-me"
                            type="checkbox"
                            checked={rememberMe}
                            onChange={(e) => setRememberMe(e.target.checked)}
                            className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                        />
                        <label htmlFor="remember-me" className="ml-2 block text-sm text-gray-900">
                            Remember me
                        </label>
                    </div>
                    <div className="text-sm">
                        <Link to="/forgot-password" className="font-medium text-indigo-600 hover:text-indigo-500">
                            Forgot your password?
                        </Link>
                    </div>
                </div>

                <div>
                    <button
                        type="submit"
                        disabled={loading}
                        className="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                    >
                        {loading ? 'Signing in...' : 'Sign in'}
                        {!loading && <FaSignInAlt className="ml-2 h-5 w-5" />}
                    </button>
                </div>
            </form>
            
            <div className="mt-6">
                <div className="relative">
                    <div className="absolute inset-0 flex items-center">
                        <div className="w-full border-t border-gray-300" />
                    </div>
                    <div className="relative flex justify-center text-sm">
                        <span className="px-2 bg-white text-gray-500">Or continue with</span>
                    </div>
                </div>

                <div className="mt-6 grid grid-cols-2 gap-3">
                    <div>
                        <button
                            type="button" disabled={loading || providers?.google?.available !== true}
                            onClick={() => handleSocialLogin('google')}
                            className="w-full inline-flex justify-center items-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <FaGoogle className="w-5 h-5 mr-2 text-red-500" />
                            Google
                        </button>
                    </div>
                    <div>
                        <button
                            type="button" disabled={loading || providers?.facebook?.available !== true}
                            onClick={() => handleSocialLogin('facebook')}
                            className="w-full inline-flex justify-center items-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                           <FaFacebook className="w-5 h-5 mr-2 text-blue-600" />
                            Facebook
                        </button>
                    </div>
                </div>
                {!providers && !providerError && <p role="status" className="mt-3 text-sm text-gray-600">Checking social sign-in options…</p>}
                {providerError && <p role="alert" className="mt-3 text-sm text-gray-600">Social sign-in options could not be loaded. Email sign-in is still available. <button type="button" className="underline" onClick={() => setProviderReload(value => value + 1)}>Retry social options</button></p>}
                {providers && !providers.google?.available && !providers.facebook?.available && <p className="mt-3 text-sm text-gray-600">Social sign-in is currently unavailable. Use email and password.</p>}
                {providers?.facebook?.available && providers.facebook.existing_accounts_only && <p className="mt-3 text-sm text-gray-600">Facebook sign-in is available for accounts already linked to Facebook.</p>}
            </div>


            <p className="mt-8 text-center text-sm text-gray-600">
                Not a member?{' '}
                <Link to="/register" className="font-medium text-indigo-600 hover:text-indigo-500">
                    Create an account
                </Link>
            </p>
             <p className="mt-2 text-center text-sm text-gray-600">
                Are you an Admin?{' '}
                <Link to="/admin/login" className="font-medium text-green-600 hover:text-green-500">
                    Login here
                </Link>
            </p>
        </AuthLayout>
    );
};

export default LoginPage;
