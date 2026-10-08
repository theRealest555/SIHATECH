// src/pages/auth/AdminLogin.jsx
import { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { FaUserShield, FaEnvelope, FaLock, FaSignInAlt } from 'react-icons/fa';
import AuthLayout from '../../components/auth/AuthLayout';


const AdminLoginPage = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const { login, authError, loading } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const from = location.state?.from?.pathname || '/admin/dashboard';


    const handleSubmit = async (e) => {
        e.preventDefault();
        const success = await login({ email, password }, true); // true for isAdmin
        if (success) {
            navigate(from, { replace: true });
        }
    };

    return (
        <AuthLayout title="Admin Portal" subtitle="Secure login for SihaTech administrators.">
             <div className="text-center mb-6">
                <FaUserShield className="mx-auto h-16 w-16 text-indigo-600" />
            </div>
            <form onSubmit={handleSubmit} className="space-y-6">
                {authError && <p className="text-red-500 text-sm bg-red-100 p-3 rounded-md">{authError}</p>}
                
                <div>
                    <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                        Admin Email
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
                            placeholder="admin@sihatech.com"
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
                
                {/* Optional: Remember me for admin */}

                <div>
                    <button
                        type="submit"
                        disabled={loading}
                        className="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                    >
                        {loading ? 'Signing in...' : 'Admin Sign In'}
                         {!loading && <FaSignInAlt className="ml-2 h-5 w-5" />}
                    </button>
                </div>
            </form>
            <p className="mt-8 text-center text-sm text-gray-600">
                Not an admin?{' '}
                <Link to="/login" className="font-medium text-indigo-600 hover:text-indigo-500">
                    User Login
                </Link>
            </p>
        </AuthLayout>
    );
};

export default AdminLoginPage;
