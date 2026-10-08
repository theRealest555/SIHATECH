import { useState, useEffect } from 'react';
import { getAllUsers, updateUserStatus } from '../../services/adminService';
import { apiError } from '../../utils/apiErrors';

const roles = { patient: 'Patient', medecin: 'Doctor', admin: 'Administrator' };
const statuses = { actif: 'Active', inactif: 'Inactive', en_attente: 'Pending' };
export default function UserListPage() {
    const [result, setResult] = useState({ data: [], last_page: 1, total: 0 });
    const [filters, setFilters] = useState({ search: '', role: '', status: '' });
    const [draft, setDraft] = useState('');
    const [page, setPage] = useState(1);
    const [reload, setReload] = useState(0);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [decision, setDecision] = useState(null);
    useEffect(() => {
        let active = true;
        setLoading(true); setError(''); setDecision(null);
        getAllUsers({ page, ...filters }).then(response => {
            if (!active) return;
            if (!Array.isArray(response.data?.data) || !Number.isInteger(response.data?.last_page)) throw new Error('Invalid user response');
            setResult(response.data);
        }).catch(err => { if (active) setError(apiError(err, 'Could not load users.')); })
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, [page, filters, reload]);
    function filter(name, value) {
        setPage(1); setMessage(''); setFilters(previous => ({ ...previous, [name]: value }));
    }
    async function confirm() {
        if (busy || !decision || (decision.status !== 'actif' && !decision.reason.trim())) return;
        setBusy(true); setError(''); setMessage('');
        try {
            const response = await updateUserStatus(decision.id, decision.status, decision.revision, decision.status !== 'actif' ? decision.reason.trim() : undefined);
            if (response.data?.user?.status !== decision.status) throw new Error('Status update not confirmed');
            setResult(previous => ({ ...previous, data: previous.data.map(user => user.id === decision.id ? { ...user, status: decision.status } : user) }));
            setMessage(response.data.changed === false ? 'Account status is already current. No access changes were made.' : decision.status === 'actif' ? 'Account activated. Previously revoked access remains revoked.' : 'Account deactivated. Existing access was revoked; account history is preserved.');
            setDecision(null);
            setReload(value => value + 1);
        } catch (err) {
            if (err.response?.status === 409) setDecision(null);
            setError(apiError(err, 'Could not update account status.'));
        }
        finally { setBusy(false); }
    }
    return <main className="max-w-6xl mx-auto p-6 space-y-6">
        <h1 className="text-3xl font-bold">User management</h1>
        <p>Deactivate an account to revoke access while preserving appointments, credentials and billing history. Permanent deletion is unavailable. Deactivation does not cancel appointments or provider subscriptions.</p>
        <form onSubmit={event => { event.preventDefault(); filter('search', draft.trim()); }} className="flex flex-wrap gap-3">
            <label>Search users<input className="block border rounded p-2" value={draft} maxLength={100} onChange={event => setDraft(event.target.value)} placeholder="Name or email" disabled={busy} /></label>
            <button disabled={busy || loading} className="border rounded px-4" type="submit">Search</button>
            <label>Role<select className="block border rounded p-2" value={filters.role} onChange={event => filter('role', event.target.value)} disabled={busy}><option value="">All roles</option>{Object.entries(roles).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
            <label>Status<select className="block border rounded p-2" value={filters.status} onChange={event => filter('status', event.target.value)} disabled={busy}><option value="">All statuses</option>{Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
        </form>
        {error && <p role="alert" className="bg-red-50 text-red-800 p-3">{error} <button disabled={busy || loading} onClick={() => setReload(value => value + 1)}>Reload users</button></p>}
        {message && <p role="status" className="bg-green-50 text-green-800 p-3">{message}</p>}
        {decision && <section role="dialog" aria-label="Confirm account status" className="border rounded p-4 space-y-3">
            <p>{decision.status === 'actif' ? 'Activate' : 'Deactivate'} {decision.name}? {decision.status !== 'actif' && 'Existing sessions and API tokens will be revoked. History is retained; bookings and billing need separate follow-up.'}</p>
            {decision.status !== 'actif' && <>
                <label className="block">Reason for deactivation<textarea className="block w-full border rounded p-2" value={decision.reason} maxLength={500} required disabled={busy} onChange={event => setDecision(previous => ({ ...previous, reason: event.target.value }))} aria-describedby="status-reason-help" /></label>
                <p id="status-reason-help">Record the administrative reason. It will be visible to administrators in audit history.</p>
            </>}
            <button disabled={busy || (decision.status !== 'actif' && !decision.reason.trim())} onClick={confirm} className="border rounded px-3 py-2">Confirm status change</button>{' '}
            <button disabled={busy} onClick={() => setDecision(null)} className="border rounded px-3 py-2">Keep current status</button>
        </section>}
        {loading ? <p role="status">Loading users…</p> : !error && <>
            <div className="overflow-x-auto"><table className="w-full bg-white text-left"><thead><tr>{['Name', 'Email', 'Role', 'Status', 'Joined', 'Action'].map(label => <th key={label} className="p-3">{label}</th>)}</tr></thead>
                <tbody>{result.data.map(user => <tr key={user.id} className="border-t"><td className="p-3">{user.prenom} {user.nom}</td><td className="p-3">{user.email}</td><td className="p-3">{roles[user.role] || user.role}</td><td className="p-3">{statuses[user.status] || user.status}</td><td className="p-3">{user.created_at ? new Date(user.created_at).toLocaleDateString() : '—'}</td><td className="p-3"><button disabled={busy || Boolean(decision) || !/^[a-f0-9]{64}$/.test(user.status_revision || '')} onClick={() => setDecision({ id: user.id, name: `${user.prenom} ${user.nom}`, status: user.status === 'actif' ? 'inactif' : 'actif', revision: user.status_revision, reason: '' })} className="underline">{user.status === 'actif' ? 'Deactivate' : 'Activate'} {user.prenom} {user.nom}</button></td></tr>)}
                {result.data.length === 0 && <tr><td colSpan={6} className="p-6">No users match these filters.</td></tr>}</tbody></table></div>
            <nav aria-label="User pages" className="flex gap-3 items-center"><button disabled={busy || page === 1} onClick={() => setPage(value => value - 1)}>Previous</button><span>Page {page} of {result.last_page} · {result.total} users</span><button disabled={busy || page >= result.last_page} onClick={() => setPage(value => value + 1)}>Next</button></nav>
        </>}
    </main>;
}
