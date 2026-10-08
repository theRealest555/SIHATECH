import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getAdminDashboard } from '../../services/adminService';
import { apiError } from '../../utils/apiErrors';
const fields = [['total_users', 'Total users'], ['total_doctors', 'Doctors'], ['total_patients', 'Patients'], ['total_appointments', 'Appointments'], ['pending_doctor_verifications', 'Doctors awaiting verification'], ['pending_reviews', 'Reviews awaiting moderation']];
export default function AdminDashboardPage() {
    const [stats, setStats] = useState(null); const [loading, setLoading] = useState(true); const [error, setError] = useState(''); const [reload, setReload] = useState(0);
    useEffect(() => {
        const controller = new AbortController(); setLoading(true); setError('');
        getAdminDashboard({ signal: controller.signal }).then(response => setStats(response.data.data))
            .catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load admin statistics.')); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [reload]);
    return <main className="max-w-6xl mx-auto p-6 space-y-6">
        <h1 className="text-3xl font-bold">Admin dashboard</h1>
        <div className="flex flex-wrap gap-4"><Link to="/admin/users">Manage users</Link><Link to="/admin/doctors-verification">Review doctor credentials</Link><Link to="/admin/specialities">Manage specialities</Link><Link to="/admin/languages">Manage languages</Link><Link to="/admin/subscription-plans">Manage plans</Link><Link to="/admin/reports">View reports</Link><Link to="/admin/reviews">Moderate reviews</Link><Link to="/admin/audit-logs">View audit history</Link><button disabled={loading} onClick={() => setReload(value => value + 1)}>Refresh statistics</button></div>
        {error && <p role="alert" className="bg-red-50 text-red-800 p-4">{error}</p>}
        {loading ? <p role="status">Loading statistics…</p> : !error && stats && <>
            <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">{fields.map(([key, label]) => <article key={key} className="bg-white border rounded-xl p-6"><h2 className="text-lg font-semibold">{label}</h2><p className="text-3xl font-bold">{stats[key]}</p></article>)}</div>
            <section className="bg-white border rounded-xl p-6 space-y-3"><h2 className="text-xl font-semibold">Recent registrations</h2>
                {!stats.recent_registrations?.length ? <p>No registrations recorded.</p> : <ul>{stats.recent_registrations.map(person => <li key={person.id}>{person.prenom} {person.nom} · {person.role} · {person.created_at?.slice(0, 10)}</li>)}</ul>}
            </section>
        </>}
    </main>;
}
