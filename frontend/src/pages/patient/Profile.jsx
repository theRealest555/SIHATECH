import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import ApiService from '../../services/api';
import moment from 'moment';

const PatientProfile = () => {
  const navigate = useNavigate();
  const [profile, setProfile] = useState({
    nom: '',
    prenom: '',
    email: '',
    telephone: '',
    date_naissance: '',
    adresse: '',
  });
  const [appointments, setAppointments] = useState([]);
  const [isEditing, setIsEditing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    if (user && user.role === 'patient') {
      setProfile({
        nom: user.nom || '',
        prenom: user.prenom || '',
        email: user.email || '',
        telephone: user.telephone || '',
        date_naissance: user.date_naissance || '',
        adresse: user.adresse || '',
      });
      fetchAppointments();
    } else {
      navigate('/login');
    }
    setLoading(false);
  }, [navigate]);

  const fetchAppointments = async () => {
    try {
      const user = JSON.parse(localStorage.getItem('user') || '{}');
      const response = await ApiService.getPatientAppointments(user.id || 1);
      setAppointments(response.data.data || []);
    } catch (err) {
      console.error('Error fetching appointments:', err);
    }
  };

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
      date_naissance: user.date_naissance || '',
      adresse: user.adresse || '',
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

  const getStatusBadgeClass = (status) => {
    switch (status) {
      case 'confirmé':
        return 'badge bg-success';
      case 'en_attente':
        return 'badge bg-warning';
      case 'annulé':
        return 'badge bg-danger';
      case 'terminé':
        return 'badge bg-secondary';
      default:
        return 'badge bg-light text-dark';
    }
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
              <h3 className="mb-0">Patient Profile</h3>
              <div>
                <button
                  className="btn btn-primary me-2"
                  onClick={() => navigate('/patient')}
                >
                  Find Doctor
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

                <div className="row mb-3">
                  <div className="col-md-6">
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
                  <div className="col-md-6">
                    <label className="form-label">Date of Birth</label>
                    <input
                      type="date"
                      name="date_naissance"
                      className="form-control"
                      value={profile.date_naissance}
                      onChange={handleChange}
                      disabled={!isEditing}
                    />
                  </div>
                </div>

                <div className="mb-3">
                  <label className="form-label">Address</label>
                  <textarea
                    name="adresse"
                    className="form-control"
                    rows="3"
                    value={profile.adresse}
                    onChange={handleChange}
                    disabled={!isEditing}
                    placeholder="Enter your full address"
                  ></textarea>
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

          {/* Appointments History */}
          <div className="card mt-4">
            <div className="card-header d-flex justify-content-between align-items-center">
              <h5 className="mb-0">My Appointments</h5>
              <button
                className="btn btn-sm btn-primary"
                onClick={() => navigate('/patient')}
              >
                Book New Appointment
              </button>
            </div>
            <div className="card-body">
              {appointments.length > 0 ? (
                <div className="table-responsive">
                  <table className="table table-hover">
                    <thead>
                      <tr>
                        <th>Doctor</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      {appointments.map((appointment) => (
                        <tr key={appointment.id}>
                          <td>
                            <strong>{appointment.doctor_name || 'Dr. N/A'}</strong>
                            <br />
                            <small className="text-muted">{appointment.doctor_speciality || 'N/A'}</small>
                          </td>
                          <td>{moment(appointment.date_heure).format("MMM DD, YYYY HH:mm")}</td>
                          <td>
                            <span className={getStatusBadgeClass(appointment.statut)}>
                              {appointment.statut || 'Unknown'}
                            </span>
                          </td>
                          <td>
                            {appointment.statut === 'en_attente' && (
                              <button
                                className="btn btn-sm btn-outline-danger"
                                onClick={() => {
                                  // You can implement cancel appointment functionality
                                  console.log('Cancel appointment:', appointment.id);
                                }}
                              >
                                Cancel
                              </button>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="text-center py-4">
                  <p className="text-muted mb-3">You don't have any appointments yet.</p>
                  <button
                    className="btn btn-primary"
                    onClick={() => navigate('/patient')}
                  >
                    Book Your First Appointment
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PatientProfile;