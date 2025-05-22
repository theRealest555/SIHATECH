import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import ApiService from '../../services/api';

const DoctorProfile = () => {
  const navigate = useNavigate();
  const [profile, setProfile] = useState({
    nom: '',
    prenom: '',
    email: '',
    telephone: '',
    speciality: '',
    location: '',
  });
  const [isEditing, setIsEditing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    if (user && user.role === 'medecin') {
      setProfile({
        nom: user.nom || '',
        prenom: user.prenom || '',
        email: user.email || '',
        telephone: user.telephone || '',
        speciality: user.speciality || '',
        location: user.location || '',
      });
    } else {
      navigate('/login');
    }
    setLoading(false);
  }, [navigate]);

  const handleChange = (e) => {
    setProfile({ ...profile, [e.target.name]: e.target.value });
  };

  const handleEdit = () => {
    setIsEditing(true);
    setError(null);
    setSuccess(false);
  };

  const handleCancel = () => {
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    setProfile({
      nom: user.nom || '',
      prenom: user.prenom || '',
      email: user.email || '',
      telephone: user.telephone || '',
      speciality: user.speciality || '',
      location: user.location || '',
    });
    setIsEditing(false);
    setError(null);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError(null);

    try {
      // You'll need to implement this API endpoint
      const response = await ApiService.updateProfile(profile);
      
      // Update local storage
      const updatedUser = { ...JSON.parse(localStorage.getItem('user')), ...profile };
      localStorage.setItem('user', JSON.stringify(updatedUser));
      
      setIsEditing(false);
      setSuccess(true);
      setTimeout(() => setSuccess(false), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update profile');
    } finally {
      setSaving(false);
    }
  };

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    navigate('/login');
  };

  if (loading) {
    return (
      <div className="container mt-5">
        <div className="text-center">
          <div className="spinner-border" role="status">
            <span className="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="container mt-5">
      <div className="row">
        <div className="col-md-8 mx-auto">
          <div className="card">
            <div className="card-header d-flex justify-content-between align-items-center">
              <h3 className="mb-0">Doctor Profile</h3>
              <div>
                <button
                  className="btn btn-primary me-2"
                  onClick={() => navigate('/doctor')}
                >
                  My Calendar
                </button>
                <button
                  className="btn btn-outline-secondary"
                  onClick={handleLogout}
                >
                  Logout
                </button>
              </div>
            </div>
            <div className="card-body">
              {error && <div className="alert alert-danger">{error}</div>}
              {success && <div className="alert alert-success">Profile updated successfully!</div>}

              <form onSubmit={handleSave}>
                <div className="row mb-3">
                  <div className="col-md-6">
                    <label className="form-label">First Name</label>
                    <input
                      type="text"
                      name="prenom"
                      className="form-control"
                      value={profile.prenom}
                      onChange={handleChange}
                      disabled={!isEditing}
                      required
                    />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label">Last Name</label>
                    <input
                      type="text"
                      name="nom"
                      className="form-control"
                      value={profile.nom}
                      onChange={handleChange}
                      disabled={!isEditing}
                      required
                    />
                  </div>
                </div>

                <div className="mb-3">
                  <label className="form-label">Email</label>
                  <input
                    type="email"
                    name="email"
                    className="form-control"
                    value={profile.email}
                    onChange={handleChange}
                    disabled={!isEditing}
                    required
                  />
                </div>

                <div className="mb-3">
                  <label className="form-label">Phone Number</label>
                  <input
                    type="tel"
                    name="telephone"
                    className="form-control"
                    value={profile.telephone}
                    onChange={handleChange}
                    disabled={!isEditing}
                  />
                </div>

                <div className="mb-3">
                  <label className="form-label">Medical Speciality</label>
                  <input
                    type="text"
                    name="speciality"
                    className="form-control"
                    value={profile.speciality}
                    onChange={handleChange}
                    disabled={!isEditing}
                    placeholder="e.g., Cardiology, Pediatrics"
                  />
                </div>

                <div className="mb-3">
                  <label className="form-label">Practice Location</label>
                  <input
                    type="text"
                    name="location"
                    className="form-control"
                    value={profile.location}
                    onChange={handleChange}
                    disabled={!isEditing}
                    placeholder="e.g., New York, Los Angeles"
                  />
                </div>

                <div className="d-flex justify-content-end">
                  {!isEditing ? (
                    <button
                      type="button"
                      className="btn btn-primary"
                      onClick={handleEdit}
                    >
                      Edit Profile
                    </button>
                  ) : (
                    <div>
                      <button
                        type="button"
                        className="btn btn-secondary me-2"
                        onClick={handleCancel}
                        disabled={saving}
                      >
                        Cancel
                      </button>
                      <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={saving}
                      >
                        {saving ? 'Saving...' : 'Save Changes'}
                      </button>
                    </div>
                  )}
                </div>
              </form>
            </div>
          </div>

          {/* Quick Actions */}
          <div className="card mt-4">
            <div className="card-header">
              <h5 className="mb-0">Quick Actions</h5>
            </div>
            <div className="card-body">
              <div className="row">
                <div className="col-md-4 mb-3">
                  <button
                    className="btn btn-outline-primary w-100"
                    onClick={() => navigate('/schedule')}
                  >
                    Update Schedule
                  </button>
                </div>
                <div className="col-md-4 mb-3">
                  <button
                    className="btn btn-outline-primary w-100"
                    onClick={() => navigate('/leaves')}
                  >
                    Manage Leaves
                  </button>
                </div>
                <div className="col-md-4 mb-3">
                  <button
                    className="btn btn-outline-primary w-100"
                    onClick={() => navigate(`/doctor/1/appointments`)}
                  >
                    View Appointments
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DoctorProfile;