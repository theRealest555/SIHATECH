import { useCallback, useEffect, useState } from 'react';
import { approveDoctorDocument, downloadAdminDocument, getVerificationDoctor, getVerificationDoctors, rejectDoctorDocument, revokeDoctorVerification, verifyDoctor } from '../../services/adminService';
import { apiError } from '../../utils/apiErrors';
const types = { licence: 'Medical licence', cni: 'Identity document', diplome: 'Diploma', autre: 'Other' };
const statuses = { pending: 'Awaiting review', approved: 'Approved', rejected: 'Rejected' };
export default function DoctorVerificationPage() {
    const [filters, setFilters] = useState({ status: 'pending', search: '', page: 1 });
    const [search, setSearch] = useState(''); const [doctors, setDoctors] = useState([]); const [meta, setMeta] = useState({});
    const [selected, setSelected] = useState(null); const [detail, setDetail] = useState(null);
    const [loading, setLoading] = useState(true); const [detailLoading, setDetailLoading] = useState(false);
    const [error, setError] = useState(''); const [message, setMessage] = useState(''); const [busy, setBusy] = useState(false);
    const [action, setAction] = useState(null); const [reason, setReason] = useState('');
    const loadList = useCallback(async (signal) => {
        const response = await getVerificationDoctors(filters, { signal });
        setDoctors(response.data.data); setMeta(response.data.meta);
    }, [filters]);
    const loadDetail = useCallback(async (signal) => {
        if (!selected) return;
        const response = await getVerificationDoctor(selected, { signal }); setDetail(response.data);
    }, [selected]);
    useEffect(() => {
        const controller = new AbortController(); setLoading(true); setError('');
        loadList(controller.signal).catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load doctor applications.')); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [loadList]);
    useEffect(() => {
        const controller = new AbortController(); setDetail(null); setAction(null); setReason('');
        if (selected) {
            setDetailLoading(true);
            loadDetail(controller.signal).catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load doctor credentials.')); })
                .finally(() => { if (!controller.signal.aborted) setDetailLoading(false); });
        }
        return () => controller.abort();
    }, [selected, loadDetail]);
    async function refresh() {
        setBusy(true); setError('');
        try { await Promise.all([loadList(), loadDetail()]); } catch (err) { setError(apiError(err, 'Could not refresh applications.')); }
        finally { setBusy(false); }
    }
    function selectAction(kind, document) { setAction({ kind, document }); setReason(''); setError(''); setMessage(''); }
    async function decide(event) {
        event.preventDefault(); setBusy(true); setError(''); setMessage('');
        try {
            if (action.kind === 'approve') await approveDoctorDocument(action.document.id, action.document.status);
            if (action.kind === 'reject') await rejectDoctorDocument(action.document.id, reason.trim(), action.document.status);
            if (action.kind === 'verify') await verifyDoctor(selected);
            if (action.kind === 'revoke') await revokeDoctorVerification(selected, reason.trim());
            setMessage('Decision saved.'); setAction(null); setReason('');
            try { await Promise.all([loadList(), loadDetail()]); } catch { setError('Decision saved, but refreshing failed. Reload to see the latest state.'); }
        } catch (err) {
            setError(apiError(err, 'Could not save the decision.'));
            // A conflict can mean another administrator already reviewed the credential.
            if (err.response?.status === 409) { setAction(null); try { await Promise.all([loadList(), loadDetail()]); } catch { /* Keep the decision error visible. */ } }
        } finally { setBusy(false); }
    }
    async function download(document) {
        setBusy(true); setError('');
        try {
            const response = await downloadAdminDocument(document.id); const url = URL.createObjectURL(response.data);
            const anchor = window.document.createElement('a'); anchor.href = url; anchor.download = document.original_name;
            window.document.body.appendChild(anchor); anchor.click(); anchor.remove(); window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (err) { setError(apiError(err, 'The private document could not be downloaded. It may no longer be available.')); }
        finally { setBusy(false); }
    }
    const doctor = detail?.data;
    const canVerify = doctor && !doctor.is_verified && !detail.meta.missing_document_types.length && doctor.user?.email_verified_at && doctor.user.status === 'actif' && doctor.speciality_id;
    return <main className="max-w-6xl mx-auto p-6 space-y-6">
        <h1 className="text-3xl font-bold">Doctor verification</h1>
        <p>Review credentials before verifying a doctor. Rejecting a required credential can revoke verification. Existing appointments remain recorded.</p>
        {error && <p role="alert" className="bg-red-50 text-red-800 p-4">{error}</p>}
        {message && <p role="status" className="bg-green-50 text-green-800 p-4">{message}</p>}
        <form onSubmit={event => { event.preventDefault(); setFilters({ ...filters, search: search.trim(), page: 1 }); }} className="flex flex-wrap gap-3 items-end">
            <div><label htmlFor="application-status">Verification status</label><select id="application-status" className="block border rounded p-2" disabled={busy} value={filters.status} onChange={event => setFilters({ ...filters, status: event.target.value, page: 1 })}><option value="pending">Pending</option><option value="verified">Verified</option><option value="all">All doctors</option></select></div>
            <div><label htmlFor="application-search">Name or email</label><input id="application-search" className="block border rounded p-2" maxLength={120} disabled={busy} value={search} onChange={event => setSearch(event.target.value)} /></div>
            <button disabled={busy} type="submit">Search</button><button disabled={busy || loading} type="button" onClick={refresh}>Reload applications</button>
        </form>
        <div className="grid md:grid-cols-3 gap-6">
            <section className="space-y-3"><h2 className="text-xl font-semibold">Doctors</h2>
                {loading ? <p role="status">Loading applications…</p> : !doctors.length ? <p>No matching doctors.</p> : doctors.map(item => <article key={item.id} className="bg-white border rounded-xl p-4 space-y-2">
                    <h3 className="font-semibold">Dr. {item.user?.prenom} {item.user?.nom}</h3><p>{item.speciality?.nom || 'Speciality missing'}</p><p>{item.is_verified ? 'Verified' : 'Pending verification'} · {item.pending_documents_count} documents awaiting review</p>
                    <button disabled={busy} aria-pressed={selected === item.id} onClick={() => { setSelected(item.id); setMessage(''); }}>Review Dr. {item.user?.prenom} {item.user?.nom}</button>
                </article>)}
                {!loading && meta.total > 0 && <div className="flex flex-wrap gap-3"><button disabled={busy || meta.current_page <= 1} onClick={() => setFilters({ ...filters, page: filters.page - 1 })}>Previous</button><span>Page {meta.current_page} of {meta.last_page}</span><button disabled={busy || meta.current_page >= meta.last_page} onClick={() => setFilters({ ...filters, page: filters.page + 1 })}>Next</button></div>}
            </section>
            <section className="md:col-span-2 bg-white border rounded-xl p-6 space-y-4"><h2 className="text-xl font-semibold">Credential review</h2>
                {detailLoading ? <p role="status">Loading credentials…</p> : !doctor ? <p>Select a doctor to review their documents.</p> : <>
                    <h3 className="text-xl font-semibold">Dr. {doctor.user?.prenom} {doctor.user?.nom}</h3><p>{doctor.user?.email} · Account: {doctor.user?.status}</p>
                    <p>Email: {doctor.user?.email_verified_at ? 'Verified' : 'Not verified'} · Doctor: {doctor.is_verified ? 'Verified' : 'Not verified'}</p>
                    <p>Required credentials: {detail.meta.required_document_types.map(type => types[type] || type).join(', ')}.</p>
                    {detail.meta.missing_document_types.length > 0 && <p>Missing approved files: {detail.meta.missing_document_types.map(type => types[type] || type).join(', ')}.</p>}
                    <div className="flex gap-4">{doctor.is_verified ? <button disabled={busy} onClick={() => selectAction('revoke')}>Revoke verification</button> : <button disabled={busy || !canVerify} onClick={() => selectAction('verify')}>Verify doctor</button>}</div>
                    {!doctor.documents.length && <p>No credentials uploaded.</p>}
                    {doctor.documents.map(document => <article key={document.id} className="border-t pt-4 space-y-2">
                        <h4 className="font-semibold break-all">{document.original_name}</h4><p>{types[document.type] || document.type} · {statuses[document.status] || document.status}</p>
                        {document.rejection_reason && <p>Review feedback: {document.rejection_reason}</p>}
                        {!document.file_available && <p className="text-red-800">File missing. Ask the doctor to upload it again.</p>}
                        <div className="flex flex-wrap gap-4"><button disabled={busy || !document.file_available} onClick={() => download(document)}>Download {document.original_name}</button>
                            {document.status !== 'approved' && <button disabled={busy || !document.file_available} onClick={() => selectAction('approve', document)}>Approve {document.original_name}</button>}
                            {document.status !== 'rejected' && <button disabled={busy} onClick={() => selectAction('reject', document)}>Reject {document.original_name}</button>}
                        </div>
                    </article>)}
                    {action && <form onSubmit={decide} className="border rounded bg-blue-50 p-4 space-y-3">
                        <h4 className="font-semibold">Confirm {action.kind === 'verify' ? 'doctor verification' : action.kind === 'revoke' ? 'verification revocation' : `${action.kind === 'approve' ? 'approval' : 'rejection'} of ${action.document.original_name}`}</h4>
                        {action.kind === 'reject' && <p>Rejecting a required credential also revokes verification if no other approved copy remains.</p>}
                        {['reject', 'revoke'].includes(action.kind) && <div><label htmlFor="review-reason">Reason</label><textarea id="review-reason" className="block w-full border rounded p-2" required maxLength={500} value={reason} disabled={busy} onChange={event => setReason(event.target.value)} /></div>}
                        <button disabled={busy || (['reject', 'revoke'].includes(action.kind) && !reason.trim())} type="submit">{busy ? 'Saving decision…' : 'Confirm decision'}</button><button disabled={busy} type="button" onClick={() => setAction(null)}>Go back</button>
                    </form>}
                </>}
            </section>
        </div>
    </main>;
}
