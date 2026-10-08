import { useEffect, useState } from 'react';
import { getAuditLogs } from '../../services/adminService';
import { apiError } from '../../utils/apiErrors';
import { formatAppointmentTime } from '../../utils/appointmentTime';

const blank = { user_id: '', action: '', start_date: '', end_date: '' };
const actions = ['marked_appointment_no_show', 'created_subscription_plan', 'updated_subscription_plan', 'approved_review', 'rejected_review', 'created_catalogue_record', 'updated_catalogue_record', 'created_admin', 'updated_admin_status', 'updated_user_status', 'reset_user_password', 'deleted_user', 'approved_document', 'rejected_document', 'verified_doctor', 'revoked_doctor_verification'];
export default function AuditLogPage() {
    const [draft, setDraft] = useState(blank);
    const [filters, setFilters] = useState({});
    const [page, setPage] = useState(1);
    const [reload, setReload] = useState(0);
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    useEffect(() => {
        const controller = new AbortController(); setLoading(true); setResult(null); setError('');
        getAuditLogs({ ...filters, page, per_page: 25 }, { signal: controller.signal }).then(response => {
            if (!controller.signal.aborted) setResult(response.data);
        }).catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load audit history.')); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [filters, page, reload]);
    function apply(event) {
        event.preventDefault(); setPage(1);
        setFilters(Object.fromEntries(Object.entries(draft).filter(([, value]) => value !== '')));
    }
    function clear() { setDraft(blank); setFilters({}); setPage(1); }
    const change = event => setDraft(values => ({ ...values, [event.target.name]: event.target.value }));
    return <main className="max-w-6xl mx-auto p-6 space-y-6">
        <h1 className="text-3xl font-bold">Audit history</h1>
        <p>Recorded administrative and attendance actions, newest first. This history covers actions the application records; it does not include every sign-in or page visit.</p>
        <form onSubmit={apply} className="flex flex-wrap gap-4 items-end">
            <label>Actor user ID <input name="user_id" type="number" min="1" step="1" value={draft.user_id} onChange={change} className="border rounded p-2" /></label>
            <label>Action <select name="action" value={draft.action} onChange={change} className="border rounded p-2"><option value="">All actions</option>{actions.map(action => <option key={action} value={action}>{action.replaceAll('_', ' ')}</option>)}</select></label>
            <label>Start date <input name="start_date" type="date" value={draft.start_date} onChange={change} className="border rounded p-2" /></label>
            <label>End date <input name="end_date" type="date" min={draft.start_date} value={draft.end_date} onChange={change} className="border rounded p-2" /></label>
            <button className="bg-blue-700 text-white rounded px-4 py-2">Filter history</button>
            <button type="button" onClick={clear} className="border rounded px-4 py-2">Clear filters</button>
        </form>
        <button disabled={loading} onClick={() => setReload(value => value + 1)}>Refresh history</button>
        {loading && <p role="status">Loading audit history…</p>}
        {error && <p role="alert">{error} <button onClick={() => setReload(value => value + 1)}>Retry</button></p>}
        {result && <>
            <p>{result.meta.total} recorded actions · Times shown in {result.meta.timezone}. Date filters include both dates.</p>
            {!result.data.length ? <p>No recorded actions match these filters.</p> : <div className="overflow-x-auto"><table className="w-full text-left"><thead><tr>{['Timestamp', 'Actor', 'Action', 'Target', 'Decision details'].map(label => <th scope="col" key={label} className="p-2">{label}</th>)}</tr></thead><tbody>{result.data.map(log => <tr key={log.id} className="border-t align-top">
                <td className="p-2 whitespace-nowrap">{log.created_at ? formatAppointmentTime(log.created_at) : 'Unknown time'}</td>
                <td className="p-2">{log.actor ? <><span>{log.actor.name || 'Unnamed user'}</span><br />User #{log.actor.id}</> : 'Actor account deleted or unavailable'}</td>
                <td className="p-2">{log.action.replaceAll('_', ' ')}</td>
                <td className="p-2">{log.target.type} #{log.target.id}</td>
                <td className="p-2 whitespace-pre-wrap break-words max-w-md">{log.details.transition && <p>{log.details.transition}</p>}{log.details.reason && <p>{log.details.reason}</p>}{!log.details.transition && !log.details.reason && 'No decision details recorded'}{log.details.access_revoked === true && <p>Existing sessions and API tokens revoked.</p>}{log.details.document_id && <p>Document #{log.details.document_id}</p>}</td>
            </tr>)}</tbody></table></div>}
            <nav aria-label="Audit history pages" className="flex gap-4 items-center">
                <button disabled={result.meta.current_page <= 1} onClick={() => setPage(value => value - 1)}>Previous page</button>
                <span>Page {result.meta.current_page} of {result.meta.last_page}</span>
                <button disabled={result.meta.current_page >= result.meta.last_page} onClick={() => setPage(value => value + 1)}>Next page</button>
            </nav>
        </>}
    </main>;
}
