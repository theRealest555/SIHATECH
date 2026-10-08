import { useEffect, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import api from '../../api/axios';

export default function DoctorCompleteProfile() {
  const { user, completeDoctorProfile, loading, authError } = useAuth();
  const [specialities, setSpecialities] = useState([]);
  const [error, setError] = useState(''); const [ready, setReady] = useState(false); const [reload, setReload] = useState(0);
  const [form, setForm] = useState({ speciality_id: '', telephone: '', adresse: '' });
  useEffect(() => {
    const controller = new AbortController(); setReady(false); setError('');
    Promise.all([api.get('/api/public/specialities', { signal: controller.signal }), api.get('/api/doctor/profile', { signal: controller.signal })])
      .then(([catalogue, profile]) => {
        if (controller.signal.aborted) return;
        setSpecialities(catalogue.data.data);
        setForm({ speciality_id: profile.data.doctor?.speciality_id || '', telephone: profile.data.user.telephone || '', adresse: profile.data.user.adresse || '', expected_profile_revision: profile.data.profile_revision }); setReady(true);
      }).catch(() => { if (!controller.signal.aborted) setError('Unable to load your current profile. Reload before saving.'); });
    return () => controller.abort();
  }, [reload]);
  if (user?.role !== 'medecin') return <Navigate to="/dashboard" replace />;
  if (user.doctor_profile_completed) return <Navigate to="/doctor/dashboard" replace />;
  const update = event => setForm({ ...form, [event.target.name]: event.target.value });
  const submit = async event => { event.preventDefault(); if (ready) await completeDoctorProfile(form); };
  return <section className="max-w-lg mx-auto p-8 bg-white rounded-xl shadow">
    <h1 className="text-2xl mb-4">Complete your doctor profile</h1>
    {(error || authError) && <p role="alert" className="text-red-700">{error || authError}</p>}
    <button disabled={loading} onClick={() => setReload(value => value + 1)}>Reload profile and discard draft</button>
    {!ready && <p role="status">Loading current profile…</p>}
    <form onSubmit={submit} className="space-y-4"><fieldset disabled={!ready || loading}>
      <label className="block">Speciality<select name="speciality_id" required value={form.speciality_id} onChange={update} className="form-select">
        <option value="">Choose a speciality</option>{specialities.map(item => <option key={item.id} value={item.id}>{item.nom}</option>)}
      </select></label>
      <label className="block">Telephone<input name="telephone" type="tel" value={form.telephone} onChange={update} className="form-control" /></label>
      <label className="block">Clinic address<input name="adresse" value={form.adresse} onChange={update} className="form-control" /></label>
      <button className="btn btn-primary">{loading ? 'Saving…' : 'Save profile'}</button>
    </fieldset></form>
  </section>;
}
