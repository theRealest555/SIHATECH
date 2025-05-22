import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import axios from 'axios';

const Register = () => {
  const navigate = useNavigate();
  const [userType, setUserType] = useState('patient'); // Default to patient
  const [formData, setFormData] = useState({
    nom: '',
    prenom: '',
    email: '',
    password: '',
    password_confirmation: '',
    telephone: '',
    role: 'patient',
    speciality_id: '',
    location: '',
  });
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleUserTypeChange = (type) => {
    setUserType(type);
    setFormData({ ...formData, role: type, speciality_id: '', location: '' });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    // Validation
    if (formData.password !== formData.password_confirmation) {
      setError('Passwords do not match');
      setLoading(false);
      return;
    }

    if (userType === 'medecin' && !formData.speciality_id) {
      setError('Please enter your speciality');
      setLoading(false);
      return;
    }

    try {
      const response = await axios.post('http://localhost:8000/api/register', formData);
      if (response.data.token) {
        localStorage.setItem('token', response.data.token);
        localStorage.setItem('user', JSON.stringify(response.data.user));
        navigate('/verify-email');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Registration failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="container mt-5">
      <div className="row justify-content-center">
        <div className="col-md-8">
          <div className="card">
            <div className="card-body">
              <h2 className="text-center mb-4">Create Account</h2>
              {error && <div className="alert alert-danger">{error}</div>}
              
              {/* User Type Selection */}
              <div className="mb-4">
                <div className="row">
                  <div className="col-md-6">
                    <button
                      type="button"
                      className={`btn w-100 ${userType === 'patient' ? 'btn-primary' : 'btn-outline-primary'}`}
                      onClick={() => handleUserTypeChange('patient')}
                    >
                      Register as Patient
                    </button>
                  </div>
                  <div className="col-md-6">
                    <button
                      type="button"
                      className={`btn w-100 ${userType === 'medecin' ? 'btn-primary' : 'btn-outline-primary'}`}
                      onClick={() => handleUserTypeChange('medecin')}
                    >
                      Register as Doctor
                    </button>
                  </div>
                </div>
              </div>

              <form onSubmit={handleSubmit}>
                <div className="row">
                  <div className="col-md-6">
                    <div className="mb-3">
                      <label className="form-label">First Name *</label>
                      <input
                        type="text"
                        name="prenom"
                        className="form-control"
                        value={formData.prenom}
                        onChange={handleChange}
                        required
                      />
                    </div>
                  </div>
                  <div className="col-md-6">
                    <div className="mb-3">
                      <label className="form-label">Last Name *</label>
                      <input
                        type="text"
                        name="nom"
                        className="form-control"
                        value={formData.nom}
                        onChange={handleChange}
                        required
                      />
                    </div>
                  </div>
                </div>

                <div className="mb-3">
                  <label className="form-label">Email *</label>
                  <input
                    type="email"
                    name="email"
                    className="form-control"
                    value={formData.email}
                    onChange={handleChange}
                    required
                  />
                </div>

                <div className="row">
                  <div className="col-md-6">
                    <div className="mb-3">
                      <label className="form-label">Password *</label>
                      <input
                        type="password"
                        name="password"
                        className="form-control"
                        value={formData.password}
                        onChange={handleChange}
                        minLength="8"
                        required
                      />
                      <small className="form-text text-muted">Minimum 8 characters</small>
                    </div>
                  </div>
                  <div className="col-md-6">
                    <div className="mb-3">
                      <label className="form-label">Confirm Password *</label>
                      <input
                        type="password"
                        name="password_confirmation"
                        className="form-control"
                        value={formData.password_confirmation}
                        onChange={handleChange}
                        required
                      />
                    </div>
                  </div>
                </div>

                <div className="mb-3">
                  <label className="form-label">Phone Number</label>
                  <input
                    type="tel"
                    name="telephone"
                    className="form-control"
                    value={formData.telephone}
                    onChange={handleChange}
                    placeholder="Enter your phone number"
                  />
                </div>

                {/* Doctor-specific fields */}
                {userType === 'medecin' && (
                  <>
                    <div className="mb-3">
                      <label className="form-label">Medical Speciality *</label>
                      <input
                        type="text"
                        name="speciality_id"
                        className="form-control"
                        value={formData.speciality_id}
                        onChange={handleChange}
                        placeholder="e.g., Cardiology, Pediatrics, General Medicine"
                        required
                      />
                    </div>
                    <div className="mb-3">
                      <label className="form-label">Practice Location</label>
                      <input
                        type="text"
                        name="location"
                        className="form-control"
                        value={formData.location}
                        onChange={handleChange}
                        placeholder="e.g., New York, Los Angeles"
                      />
                    </div>
                  </>
                )}

                <button 
                  type="submit" 
                  className="btn btn-primary w-100 mb-3"
                  disabled={loading}
                >
                  {loading ? 'Creating Account...' : `Register as ${userType === 'patient' ? 'Patient' : 'Doctor'}`}
                </button>

                <div className="text-center">
                  <Link to="/login">Already have an account? Sign in</Link>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Register;