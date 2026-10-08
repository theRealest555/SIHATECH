import { useEffect, useState } from 'react';
import { getModerationReviews, moderateReview } from '../../services/adminService';
import { apiError } from '../../utils/apiErrors';
export default function ReviewModerationPage() {
    const [status, setStatus] = useState('pending'); const [page, setPage] = useState(1); const [revision, setRevision] = useState(0); const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true); const [busy, setBusy] = useState(false); const [error, setError] = useState(''); const [actionError, setActionError] = useState(''); const [decision, setDecision] = useState(null); const [reason, setReason] = useState(''); const [message, setMessage] = useState('');
    useEffect(() => {
        const controller = new AbortController(); setLoading(true); setData(null); setError(''); setDecision(null);
        getModerationReviews({ ...(status ? { status } : {}), page, per_page: 15 }, { signal: controller.signal }).then(response => { if (!controller.signal.aborted) setData(response.data); })
            .catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load reviews.')); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [status, page, revision]);
    async function submit(event) {
        event.preventDefault(); setBusy(true); setActionError(''); setMessage('');
        try { await moderateReview(decision.review.id, { action: decision.action, expected_status: decision.review.status, ...(decision.action === 'reject' ? { reason: reason.trim() } : {}) }); setMessage('Moderation saved.'); setRevision(value => value + 1); }
        catch (err) { setActionError(apiError(err, 'Could not save this decision. Refresh to check its current status.')); if (err.response?.status === 409) setRevision(value => value + 1); }
        finally { setBusy(false); }
    }
    return <main className="max-w-5xl mx-auto p-6 space-y-5"><h1 className="text-3xl font-bold">Review moderation</h1>
        <p>Approve feedback only when its appointment context is valid. Reject private medical details, contact information, abusive content or irrelevant feedback.</p>
        <label>Status <select value={status} disabled={busy} onChange={e => { setStatus(e.target.value); setPage(1); setActionError(''); }} className="border rounded p-2"><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="">All</option></select></label>
        <button disabled={loading || busy} onClick={() => setRevision(value => value + 1)}>Refresh reviews</button>
        {message && <p role="status">{message}</p>}{error && <p role="alert">{error}</p>}{actionError && <p role="alert">{actionError}</p>}{loading && <p role="status">Loading reviews…</p>}
        {decision && <form onSubmit={submit} className="border rounded p-4 space-y-3" aria-label="Moderation decision"><h2>{decision.action === 'approve' ? 'Approve' : 'Reject'} review #{decision.review.id}?</h2>
            {decision.action === 'reject' && <label className="block">Reason <textarea required maxLength={500} value={reason} disabled={busy} onChange={e => setReason(e.target.value)} className="border rounded p-2 w-full" /></label>}
            <button disabled={busy} className="bg-blue-700 text-white rounded px-4 py-2">{busy ? 'Saving…' : 'Confirm decision'}</button><button type="button" disabled={busy} onClick={() => setDecision(null)}>Cancel decision</button>
        </form>}
        {data && <><p>{data.meta.total} reviews</p>{!data.data.length && <p>No reviews match this status.</p>}
            {data.data.map(review => <article key={review.id} className="border rounded p-4 space-y-2"><h2 className="text-lg font-semibold">Review #{review.id} · {review.rating} / 5 · {review.status}</h2><p>{review.patient_name} → {review.doctor_name} · Appointment #{review.appointment_id ?? 'unavailable'}</p><p className="whitespace-pre-wrap break-words">{review.comment || 'No written feedback.'}</p>{review.reason && <p>Reason: {review.reason}</p>}
                <div className="flex gap-4">{review.status !== 'approved' && <button disabled={busy} onClick={() => { setDecision({ review, action: 'approve' }); setReason(''); setActionError(''); }}>Approve review #{review.id}</button>}{review.status !== 'rejected' && <button disabled={busy} onClick={() => { setDecision({ review, action: 'reject' }); setReason(''); setActionError(''); }}>Reject review #{review.id}</button>}</div>
            </article>)}
            <nav aria-label="Review pages" className="flex gap-4"><button disabled={busy || data.meta.current_page <= 1} onClick={() => setPage(value => value - 1)}>Previous page</button><span>Page {data.meta.current_page} of {data.meta.last_page}</span><button disabled={busy || data.meta.current_page >= data.meta.last_page} onClick={() => setPage(value => value + 1)}>Next page</button></nav>
        </>}
    </main>;
}
