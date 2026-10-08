// src/pages/auth/Register.jsx
import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { FaUser, FaEnvelope, FaLock, FaIdCard } from 'react-icons/fa';
import api from '../../api/axios';
import AuthLayout from '../../components/auth/AuthLayout';

const RegisterPage = () => {
    const [formData, setFormData] = useState({
        prenom: '',
        nom: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'patient',
        speciality_id: '',
    });
    const { register, authError, loading } = useAuth();
    const navigate = useNavigate();
    const [specialities, setSpecialities] = useState([]);
    const [specialityError, setSpecialityError] = useState('');
    useEffect(() => {
        api.get('/api/public/specialities').then(response => setSpecialities(response.data.data))
            .catch(() => setSpecialityError('Unable to load specialities. Please reload before registering as a doctor.'));
    }, []);

    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (formData.password !== formData.password_confirmation) {
            alert("Passwords do not match!"); // Replace with a proper UI message
            return;
        }
        const account = { ...formData };
        delete account.speciality_id;
        const success = await register(formData.role === 'medecin' ? formData : account);
        if (success) {
            // Navigate to email verification prompt or login
            // This depends on your backend's registration flow (auto-login, email verification needed)
            navigate('/verify-email');
        }
    };

    return (
        <AuthLayout title="Create Your Account" subtitle="Join SihaTech to connect with healthcare professionals.">
            <form onSubmit={handleSubmit} className="space-y-5">
                {authError && <p className="text-red-500 text-sm bg-red-100 p-3 rounded-md">{authError}</p>}

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label htmlFor="prenom" className="block text-sm font-medium text-gray-700">First Name</label>
                        <div className="mt-1 relative">
                            <FaUser className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                            <input type="text" name="prenom" id="prenom" required value={formData.prenom} onChange={handleChange} className="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm shadow-sm" placeholder="John"/>
                        </div>
                    </div>
                    <div>
                        <label htmlFor="nom" className="block text-sm font-medium text-gray-700">Last Name</label>
                        <div className="mt-1 relative">
                            <FaUser className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                            <input type="text" name="nom" id="nom" required value={formData.nom} onChange={handleChange} className="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm shadow-sm" placeholder="Doe"/>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label htmlFor="email" className="block text-sm font-medium text-gray-700">Email</label>
                    <div className="mt-1 relative">
                        <FaEnvelope className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                        <input type="email" name="email" id="email" required value={formData.email} onChange={handleChange} className="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm shadow-sm" placeholder="you@example.com"/>
                    </div>
                </div>

                <div>
                    <label htmlFor="password" className="block text-sm font-medium text-gray-700">Password</label>
                    <div className="mt-1 relative">
                        <FaLock className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                        <input type="password" name="password" id="password" required value={formData.password} onChange={handleChange} className="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm shadow-sm" placeholder="••••••••"/>
                    </div>
                </div>

                <div>
                    <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <div className="mt-1 relative">
                        <FaLock className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                        <input type="password" name="password_confirmation" id="password_confirmation" required value={formData.password_confirmation} onChange={handleChange} className="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm shadow-sm" placeholder="••••••••"/>
                    </div>
                </div>
                
                <div>
                    <label htmlFor="role" className="block text-sm font-medium text-gray-700">Register as</label>
                    <div className="mt-1 relative">
                        <FaIdCard className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                        <select name="role" id="role" value={formData.role} onChange={handleChange} className="appearance-none block w-full px-3 py-2 pl-10 pr-8 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="patient">Patient</option>
                            <option value="medecin">Doctor</option>
                        </select>
                        <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                            <svg className="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                        </div>
                    </div>
                </div>

                {formData.role === 'medecin' && <div>
                    <label htmlFor="speciality_id" className="block text-sm font-medium">Speciality</label>
                    {specialityError && <p role="alert">{specialityError}</p>}
                    <select id="speciality_id" name="speciality_id" required value={formData.speciality_id} onChange={handleChange} className="mt-1 w-full p-2 border rounded">
                        <option value="">Choose a speciality</option>
                        {specialities.map(item => <option key={item.id} value={item.id}>{item.nom}</option>)}
                    </select>
                </div>}
                <div>
                    <button
                        type="submit"
                        disabled={loading}
                        className="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 transition-all duration-150"
                    >
                        {loading ? 'Creating account...' : 'Create Account'}
                    </button>
                </div>
            </form>

            <p className="mt-8 text-center text-sm text-gray-600">
                Already have an account?{' '}
                <Link to="/login" className="font-medium text-indigo-600 hover:text-indigo-500">
                    Sign in
                </Link>
            </p>
        </AuthLayout>
    );
};

export default RegisterPage;
