```jsx
import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import ApiService from '../../services/api';
import { toast } from 'react-toastify';

const DoctorProfile = () => {
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
    description: '',
    speciality_id: '',
    horaires: '',
  });
  const [passwordData, setPasswordData] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  const [photo, setPhoto] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [specialities, setSpecialities] = useState([]);

  useEffect(() => {
    fetchProfile();
    fetchSpecialities();
  }, []);

  const fetchProfile = async () => {
    try {
      const response = await ApiService.get('/doctor/profile');
      const { user, doctor } = response.data;
      setProfile({ ...user, ...doctor });
      setFormData({
        nom: user.nom || '',
        prenom: user.prenom || '',
        email: user.email || '',
        telephone: user.telephone || '',
        adresse: user.adresse || '',
        sexe: user.sexe || '',
        date_de_naissance: user.date_de_naissance || '',
        description: doctor?.description || '',
        speciality_id: doctor?.speciality_id || '',
        horaires: doctor?.horaires ? JSON.stringify(doctor.horaires) : '',
      });
      setLoading(false);
    } catch (err) {
      setError('Failed to load profile');
      setLoading(false);
    }
  };

  const fetchSpecialities = async () => {
    try {
      const response = await ApiService.getSpecialities();
      setSpecialities(response.data.data);
    } catch (err) {
      console.error('Failed to load specialities');
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
      await ApiService.put('/doctor/profile', formData);
      toast.success('Profile updated successfully');
      fetchProfile();
    } catch (err) {
      toast.error('Failed to update profile');
    }
  };

  const handlePasswordSubmit = async (e) => {
    e.preventDefault();
    try {
      await ApiService.put('/doctor/profile/password', passwordData);
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
      await ApiService.post('/doctor/profile/photo', formData);
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
      <h2>Doctor Profile</h2>
      
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
            <div className="mb-3">
              <label className="form-label">Speciality</label>
              <select
                name="speciality_id"
                className="form-select"
                value={formData.speciality_id}
                onChange={handleChange}
              >
                <option value="">Select Speciality</option>
                {specialities.map((speciality) => (
                  <option key={speciality} value={speciality}>
                    {speciality}
                  </option>
                ))}
              </select>
            </div>
            <div className="mb-3">
              <label className="form-label">Description</label>
              <textarea
                name="description"
                className="form-control"
                value={formData.description}
                onChange={handleChange}
                rows="4"
              ></textarea>
            </div>
            <div className="mb-3">
              <label className="form-label">Working Hours (JSON format)</label>
              <textarea
                name="horaires"
                className="form-control"
                value={formData.horaires}
                onChange={handleChange}
                rows="4"
                placeholder='{"monday":["09:00-12:00","14:00-18:00"]}'
              ></textarea>
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

export default DoctorProfile;
```