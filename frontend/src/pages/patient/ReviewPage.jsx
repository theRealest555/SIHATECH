import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getAppointmentReview, submitAppointmentReview } from '../../services/patientService';
import { apiError } from '../../utils/apiErrors';
import { formatAppointmentTime } from '../../utils/appointmentTime';
export default function ReviewPage() {
    const { appointmentId } = useParams(); const currentVisit = useRef(appointmentId); currentVisit.current = appointmentId; const [data, setData] = useState(null); const [rating, setRating] = useState('5'); const [comment, setComment] = useState('');
    const [loading, setLoading] = useState(true); const [busy, setBusy] = useState(false); const [error, setError] = useState(''); const [revision, setRevision] = useState(0);
    useEffect(() => { setRating('5'); setComment(''); setBusy(false); }, [appointmentId]);
    useEffect(() => {
        const controller = new AbortController(); setLoading(true); setData(null); setError('');
        getAppointmentReview(appointmentId, { signal: controller.signal }).then(response => { if (!controller.signal.aborted) setData(response.data.data); })
            .catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load this visit.')); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [appointmentId, revision]);
    async function submit(event) {
        event.preventDefault(); setBusy(true); setError('');
        try { const response = await submitAppointmentReview(appointmentId, { rating: Number(rating), comment: comment.trim() || null }); if (currentVisit.current === appointmentId) setData(current => ({ ...current, can_submit: false, review: response.data.review })); }
        catch (err) { if (currentVisit.current === appointmentId) setError(apiError(err, 'Could not submit this review. Refresh to check whether it was saved.')); }
        finally { if (currentVisit.current === appointmentId) setBusy(false); }
    }
    return <main className="max-w-3xl mx-auto p-6 space-y-5"><h1 className="text-3xl font-bold">Review your visit</h1><Link to="/patient/appointments">Back to appointments</Link>
        {loading && <p role="status">Loading visit…</p>}{error && <p role="alert">{error}</p>}
        <button disabled={loading || busy} onClick={() => setRevision(value => value + 1)}>Refresh visit</button>
        {data && <><h2 className="text-xl">{data.doctor_name}</h2><p>{formatAppointmentTime(data.starts_at)}</p>
            {data.review ? <section className="border rounded p-4"><h2>Review status: {data.review.status}</h2><p>{data.review.rating} / 5</p><p className="whitespace-pre-wrap">{data.review.comment}</p>{data.review.reason && <p>Moderation reason: {data.review.reason}</p>}</section> : data.can_submit ? <form onSubmit={submit} className="space-y-4">
                <p>Reviews are checked before they contribute to a doctor’s public rating. Share feedback about your experience and avoid private medical details or contact information.</p>
                <label className="block">Rating <select value={rating} disabled={busy} onChange={e => setRating(e.target.value)} className="border rounded p-2">{[5,4,3,2,1].map(value => <option key={value} value={value}>{value} / 5</option>)}</select></label>
                <label className="block">Feedback (optional) <textarea maxLength={2000} value={comment} disabled={busy} onChange={e => setComment(e.target.value)} className="border rounded p-2 w-full" /></label>
                <button disabled={busy} className="bg-blue-700 text-white rounded px-4 py-2">{busy ? 'Submitting…' : 'Submit for moderation'}</button>
            </form> : <p>Reviews are available after a completed appointment. Pending, cancelled and missed visits cannot be reviewed.</p>}
        </>}
    </main>;
}
