import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getAdminDashboard } from '../../services/adminService';
import { apiError } from '../../utils/apiErrors';

const fields = [['total_users', 'Total users', 'people'], ['total_doctors', 'Doctors', 'heart-pulse'], ['total_patients', 'Patients', 'person'], ['total_appointments', 'Appointments', 'calendar2-week']];
const roles = { admin: 'Administrator', medecin: 'Doctor', patient: 'Patient' };
export default function AdminDashboardPage() {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [reload, setReload] = useState(0);
    useEffect(() => {
        const controller = new AbortController();
        setLoading(true); setError('');
        getAdminDashboard({ signal: controller.signal })
            .then(response => { if (!controller.signal.aborted) setStats(response.data.data); })
            .catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load admin statistics.')); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [reload]);
    return <main className="admin-dashboard">
        <header className="dashboard-header"><div><p className="eyebrow">YOUR PLATFORM, AT A GLANCE</p><h1>Admin dashboard</h1><p>Keep care moving. Here’s what needs your attention.</p></div><button className="premium-button secondary" disabled={loading} onClick={() => setReload(value => value + 1)}><i className="bi bi-arrow-clockwise" aria-hidden="true" />Refresh statistics</button></header>
        {error && <p role="alert" className="bg-red-50 text-red-800 p-4 rounded-lg">{error}</p>}
        {loading ? <p role="status">Loading statistics…</p> : !error && stats && <>
            <section className="dashboard-metrics" aria-label="Platform totals">{fields.map(([key, label, icon]) => <article key={key} className="metric-card"><h2>{label}<i className={`bi bi-${icon}`} aria-hidden="true" /></h2><p>{Number(stats[key]).toLocaleString()}</p><small>Platform total</small></article>)}</section>
            <div className="dashboard-columns"><section className="dashboard-panel"><div className="panel-heading"><h2>Recent registrations</h2><Link to="/admin/users">Manage users <i className="bi bi-arrow-up-right" aria-hidden="true" /></Link></div>
                {!stats.recent_registrations?.length ? <p className="py-4 text-slate-500">No registrations recorded.</p> : <ul className="registrations-list">{stats.recent_registrations.map(person => <li className="registration-row" key={person.id}><span className="account-avatar" aria-hidden="true">{person.prenom?.[0]}{person.nom?.[0]}</span><div><strong>{person.prenom} {person.nom}</strong><small>{roles[person.role] || person.role}</small></div><time dateTime={person.created_at}>{person.created_at?.slice(0, 10)}</time></li>)}</ul>}
            </section><div><section className="dashboard-panel"><div className="panel-heading"><h2>Needs your attention</h2><i className="bi bi-arrow-down-right" aria-hidden="true" /></div>
                <Link className="action-row" aria-label="Review doctor credentials" to="/admin/doctors-verification"><span className="action-icon"><i className="bi bi-patch-check" aria-hidden="true" /></span><div><h3>Doctors awaiting verification</h3><p>Review professional credentials</p></div><span className="queue-count">{stats.pending_doctor_verifications}</span></Link>
                <Link className="action-row" to="/admin/reviews"><span className="action-icon"><i className="bi bi-chat-square-text" aria-hidden="true" /></span><div><h3>Reviews awaiting moderation</h3><p>Keep patient feedback useful</p></div><span className="queue-count">{stats.pending_reviews}</span></Link>
            </section><section className="dashboard-note"><h2>A clear record of every decision.</h2><p>Account and moderation changes are recorded in your audit history.</p><Link className="text-action mt-3" to="/admin/audit-logs">View audit history <i className="bi bi-arrow-right" aria-hidden="true" /></Link></section></div></div>
            <section className="dashboard-panel mt-6"><div className="panel-heading"><h2>Platform management</h2><span className="text-xs text-slate-500">Your everyday tools</span></div><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{[['/admin/specialities', 'Manage specialities', 'Care categories', 'heart-pulse'], ['/admin/languages', 'Manage languages', 'Consultation languages', 'translate'], ['/admin/subscription-plans', 'Manage plans', 'Plans and pricing', 'layers'], ['/admin/reports', 'View reports', 'Financial reporting', 'bar-chart']].map(([path,title,description,icon]) => <Link key={path} to={path} className="action-row"><span className="action-icon"><i className={`bi bi-${icon}`} aria-hidden="true" /></span><div><h3>{title}</h3><p>{description}</p></div></Link>)}</div></section>
        </>}
    </main>;
}
