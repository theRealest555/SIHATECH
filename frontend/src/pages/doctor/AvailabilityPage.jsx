import { useCallback, useEffect, useState } from 'react';
import { createDoctorLeave, deleteDoctorLeave, getDoctorAvailability, saveDoctorSchedule } from '../../services/doctorService';
import { apiError } from '../../utils/apiErrors';

const days = [['lundi', 'Monday'], ['mardi', 'Tuesday'], ['mercredi', 'Wednesday'], ['jeudi', 'Thursday'], ['vendredi', 'Friday'], ['samedi', 'Saturday'], ['dimanche', 'Sunday']];
const blank = Object.fromEntries(days.map(([key]) => [key, '']));

export default function AvailabilityPage() {
    const [availability, setAvailability] = useState(null);
    const [draft, setDraft] = useState(blank);
    const [leave, setLeave] = useState({ start_date: '', end_date: '', reason: '' });
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [removing, setRemoving] = useState(null);
    const load = useCallback(async (signal) => {
        const response = await getDoctorAvailability({ signal });
        if (signal?.aborted) return;
        const data = response.data.data;
        setAvailability(data);
        setDraft(Object.fromEntries(days.map(([key]) => [key, (data.schedule?.[key] ?? []).join(', ')])));
    }, []);
    useEffect(() => {
        const controller = new AbortController();
        load(controller.signal).catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load availability.')); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [load]);
    async function refresh() {
        setLoading(true); setError('');
        try { await load(); } catch (err) { setError(apiError(err, 'Could not load availability.')); }
        finally { setLoading(false); }
    }
    async function mutate(action, success) {
        setBusy(true); setError(''); setMessage('');
        try {
            await action(); setMessage(success); setRemoving(null);
            try { await load(); } catch { setError('Changes saved, but refreshing failed. Reload to see the latest availability.'); }
            return true;
        } catch (err) { setError(apiError(err, 'Could not save changes.')); return false; }
        finally { setBusy(false); }
    }
    async function save(event) {
        event.preventDefault();
        const schedule = Object.fromEntries(days.map(([key]) => [key, draft[key].split(',').map(range => range.trim()).filter(Boolean)]));
        await mutate(() => saveDoctorSchedule(schedule, availability.schedule_revision), 'Weekly schedule saved.');
    }
    async function addLeave(event) {
        event.preventDefault();
        if (await mutate(() => createDoctorLeave(leave), 'Leave added.')) setLeave({ start_date: '', end_date: '', reason: '' });
    }
    return <main className="max-w-4xl mx-auto p-6 space-y-6">
        <h1 className="text-3xl font-bold">Availability</h1>
        <p>Appointments last 30 minutes. Schedule and leave changes must preserve your existing bookings.</p>
        {error && <div role="alert" className="p-4 bg-red-50 text-red-800">{error} <button type="button" disabled={busy || loading} onClick={refresh}>Reload availability</button></div>}
        {message && <p role="status" className="p-4 bg-green-50 text-green-800">{message}</p>}
        {loading ? <p role="status">Loading availability…</p> : availability && <>
            <p>Times are shown in {availability.timezone}. {!availability.can_edit && 'Your credentials must be approved before you can edit availability.'}</p>
            <form onSubmit={save} className="bg-white rounded-xl border p-6 space-y-4">
                <h2 className="text-xl font-semibold">Weekly schedule</h2>
                <p id="schedule-help">Enter ranges such as 09:00-12:00, 14:00-17:00. Leave a day empty when you are unavailable.</p>
                <fieldset disabled={busy || !availability.can_edit} className="space-y-3">
                    {days.map(([key, label]) => <div key={key} className="grid sm:grid-cols-3 gap-2 items-center">
                        <label htmlFor={`schedule-${key}`}>{label}</label>
                        <input id={`schedule-${key}`} aria-describedby="schedule-help" className="sm:col-span-2 border rounded p-2" value={draft[key]} onChange={event => setDraft({ ...draft, [key]: event.target.value })} />
                    </div>)}
                    <button className="bg-blue-700 text-white rounded px-4 py-2" type="submit">{busy ? 'Saving…' : 'Save schedule'}</button>
                </fieldset>
            </form>
            <section className="bg-white rounded-xl border p-6 space-y-4">
                <h2 className="text-xl font-semibold">Leave</h2>
                <p>Start and end dates are inclusive. Patients cannot book during leave.</p>
                <form onSubmit={addLeave}><fieldset disabled={busy || !availability.can_edit} className="grid sm:grid-cols-2 gap-4">
                    <div><label htmlFor="leave-start">Start date</label><input id="leave-start" className="block border rounded p-2 w-full" type="date" required min={availability.today} value={leave.start_date} onChange={event => setLeave({ ...leave, start_date: event.target.value })} /></div>
                    <div><label htmlFor="leave-end">End date</label><input id="leave-end" className="block border rounded p-2 w-full" type="date" required min={leave.start_date || availability.today} value={leave.end_date} onChange={event => setLeave({ ...leave, end_date: event.target.value })} /></div>
                    <div className="sm:col-span-2"><label htmlFor="leave-reason">Reason (optional, private)</label><input id="leave-reason" className="block border rounded p-2 w-full" maxLength={255} value={leave.reason} onChange={event => setLeave({ ...leave, reason: event.target.value })} /></div>
                    <button type="submit" className="bg-blue-700 text-white rounded px-4 py-2">Add leave</button>
                </fieldset></form>
                {!availability.leaves.length && <p>No upcoming leave.</p>}
                {availability.leaves.map(item => <div key={item.id} className="border-t py-3 flex flex-wrap justify-between gap-2">
                    <div>{item.start_date.slice(0, 10)} – {item.end_date.slice(0, 10)}{item.reason && <p>{item.reason}</p>}</div>
                    {removing === item.id ? <div><span>Remove this leave?</span> <button disabled={busy} onClick={() => mutate(() => deleteDoctorLeave(item.id), 'Leave removed.')}>Confirm removal</button> <button disabled={busy} onClick={() => setRemoving(null)}>Keep leave</button></div>
                        : <button disabled={busy || !availability.can_edit} onClick={() => setRemoving(item.id)}>Remove leave</button>}
                </div>)}
            </section>
        </>}
    </main>;
}
