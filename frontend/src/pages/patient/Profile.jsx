```jsx
import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import ApiService from '../../services/api';
import { toast } from 'react-toastify';

const PatientProfile = () => {
  const navigate = useNavigate();
  const [profile, setProfile] = useState(null);
  const [formData, setFormData] = useState({
    nom: '',
    prenom: '',
    email: '',
    telephone: '',
    adresse: '',
    sexe: '',
    date_de_naissance: '',
    medecin_favori_id: '',
  });
  const [passwordData, setPasswordData] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  const [photo, setPhoto] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetchProfile();
  }, []);

  const fetchProfile = async () => {
    try {
      const response = await ApiService.get('/patient/profile');
      const { user, patient } = response.data;
      setProfile({ ...user, ...patient });
      setFormData({
        nom: user.nom || '',
        prenom: user.prenom || '',
        email: user.email || '',
        telephone: user.telephone || '',
        adresse: user.adresse || '',
        sexe: user.sexe || '',
        date_de_naissance: user.date_de_naissance || '',
        medecin_favori_id: patient?.medecin_favori_id || '',
      });
      setLoading(false);
    } catch (err) {
      setError('Failed to load profile');
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handlePasswordChange = (e) => {
    setPasswordData({ ...passwordData, [e.target.name]: e.target.value });
  };

  const handlePhotoChange = (e) => {
    setPhoto(e.target.files[0]);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      await ApiService.put('/patient/profile', formData);
      toast.success('Profile updated successfully');
      fetchProfile();
    } catch (err) {
      toast.error('Failed to update profile');
    }
  };

  const handlePasswordSubmit = async (e) => {
    e.preventDefault();
    try {
      await ApiService.put('/patient/profile/password', passwordData);
      toast.success('Password updated successfully');
      setPasswordData({
        current_password: '',
        password: '',
        password_confirmation: '',
      });
    } catch (err) {
      toast.error('Failed to update password');
    }
  };

  const handlePhotoSubmit = async (e) => {
    e.preventDefault();
    if (!photo) return;

    const formData = new FormData();
    formData.append('photo', photo);

    try {
      await ApiService.post('/patient/profile/photo', formData);
      toast.success('Photo updated successfully');
      fetchProfile();
    } catch (err) {
      toast.error('Failed to update photo');
    }
  };

  if (loading) return <div>Loading...</div>;
  if (error) return <div>{error}</div>;

  return (
    <div className="container mt-5">
      <h2>Patient Profile</h2>
      
      {/* Profile Photo */}
      <div className="card mb-4">
        <div className="card-body">
          <h4>Profile Photo</h4>
          {profile?.photo && (
            <img
              src={`http://localhost:8000/storage/${profile.photo}`}
              alt="Profile"
              className="img-thumbnail mb-3"
              style={{ maxWidth: '200px' }}
            />
          )}
          <form onSubmit={handlePhotoSubmit}>
            <div className="mb-3">
              <input
                type="file"
                className="form-control"
                onChange={handlePhotoChange}
                accept="image/*"
              />
            </div>
            <button type="submit" className="btn btn-primary">
              Update Photo
            </button>
          </form>
        </div>
      </div>

      {/* Profile Information */}
      <div className="card mb-4">
        <div className="card-body">
          <h4>Profile Information</h4>
          <form onSubmit={handleSubmit}>
            <div className="row">
              <div className="col-md-6 mb-3">
                <label className="form-label">First Name</label>
                <input
                  type="text"
                  name="prenom"
                  className="form-control"
                  value={formData.prenom}
                  onChange={handleChange}
                />
              </div>
              <div className="col-md-6 mb-3">
                <label className="form-label">Last Name</label>
                <input
                  type="text"
                  name="nom"
                  className="form-control"
                  value={formData.nom}
                  onChange={handleChange}
                />
              </div>
            </div>
            <div className="mb-3">
              <label className="form-label">Email</label>
              <input
                type="email"
                name="email"
                className="form-control"
                value={formData.email}
                onChange={handleChange}
              />
            </div>
            <div className="mb-3">
              <label className="form-label">Phone</label>
              <input
                type="tel"
                name="telephone"
                className="form-control"
                value={formData.telephone}
                onChange={handleChange}
              />
            </div>
            <div className="mb-3">
              <label className="form-label">Address</label>
              <input
                type="text"
                name="adresse"
                className="form-control"
                value={formData.adresse}
                onChange={handleChange}
              />
            </div>
            <div className="row">
              <div className="col-md-6 mb-3">
                <label className="form-label">Gender</label>
                <select
                  name="sexe"
                  className="form-select"
                  value={formData.sexe}
                  onChange={handleChange}
                >
                  <option value="">Select Gender</option>
                  <option value="homme">Male</option>
                  <option value="femme">Female</option>
                </select>
              </div>
              <div className="col-md-6 mb-3">
                <label className="form-label">Date of Birth</label>
                <input
                  type="date"
                  name="date_de_naissance"
                  className="form-control"
                  value={formData.date_de_naissance}
                  onChange={handleChange}
                />
              </div>
            </div>
            <button type="submit" className="btn btn-primary">
              Update Profile
            </button>
          </form>
        </div>
      </div>

      {/* Password Update */}
      <div className="card">
        <div className="card-body">
          <h4>Update Password</h4>
          <form onSubmit={handlePasswordSubmit}>
            <div className="mb-3">
              <label className="form-label">Current Password</label>
              <input
                type="password"
                name="current_password"
                className="form-control"
                value={passwordData.current_password}
                onChange={handlePasswordChange}
              />
            </div>
            <div className="mb-3">
              <label className="form-label">New Password</label>
              <input
                type="password"
                name="password"
                className="form-control"
                value={passwordData.password}
                onChange={handlePasswordChange}
              />
            </div>
            <div className="mb-3">
              <label className="form-label">Confirm New Password</label>
              <input
                type="password"
                name="password_confirmation"
                className="form-control"
                value={passwordData.password_confirmation}
                onChange={handlePasswordChange}
              />
            </div>
            <button type="submit" className="btn btn-primary">
              Update Password
            </button>
          </form>
        </div>
      </div>
    </div>
  );
};

export default PatientProfile;
```