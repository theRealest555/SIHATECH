import { useEffect, useState } from 'react';
import PropTypes from 'prop-types';
import { Link, useLocation } from 'react-router-dom';
import { cancelAppointment, getPatientAppointments } from '../../services/patientService';
import { getDoctorAppointments, markAppointmentNoShow, updateAppointmentStatus } from '../../services/doctorService';
import { apiError } from '../../utils/apiErrors';
import { formatAppointmentTime } from '../../utils/appointmentTime';

const labels = { en_attente: 'Awaiting confirmation', confirmé: 'Confirmed', annulé: 'Cancelled', terminé: 'Completed', no_show: 'No-show' };

export default function AppointmentList({ kind }) {
    const isDoctor = kind === 'doctor';
    const location = useLocation();
    const [query, setQuery] = useState({ period: 'upcoming', page: 1 });
    const [appointments, setAppointments] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, timezone: 'UTC' });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [actionError, setActionError] = useState('');
    const [message, setMessage] = useState(location.state?.message || '');
    const [busy, setBusy] = useState(false);
    const [confirmation, setConfirmation] = useState(null);
    const [revision, setRevision] = useState(0);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError('');
        setConfirmation(null);
        const fetch = isDoctor ? getDoctorAppointments : getPatientAppointments;
        fetch({ ...query, per_page: 12 }, { signal: controller.signal }).then(response => {
            if (controller.signal.aborted) return;
            setAppointments(response.data.data);
            setMeta(response.data.meta);
        }).catch(err => {
            if (!controller.signal.aborted) setError(apiError(err, 'Appointments could not be loaded. Please try again.'));
        }).finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [isDoctor, query, revision]);

    async function update() {
        if (!confirmation || busy) return;
        setBusy(true);
        setActionError('');
        setMessage('');
        try {
            if (!isDoctor) await cancelAppointment(confirmation.id);
            else if (confirmation.status === 'no_show') await markAppointmentNoShow(confirmation.id);
            else await updateAppointmentStatus(confirmation.id, confirmation.status);
            setMessage(`Appointment updated: ${labels[confirmation.status]}.`);
            setConfirmation(null);
            setRevision(value => value + 1);
        } catch (err) {
            setActionError(apiError(err, 'The appointment could not be updated. Please refresh and check its status.'));
            setConfirmation(null);
            // Reconcile with server state after a conflict or an uncertain response.
            setRevision(value => value + 1);
        } finally { setBusy(false); }
    }

    return <div className="max-w-6xl mx-auto p-4 md:p-8">
        <div className="flex gap-4 justify-between items-center mb-6"><h1 className="text-3xl font-bold">My appointments</h1>
            {!isDoctor && <Link to="/doctors" className="bg-indigo-600 text-white px-4 py-2 rounded">Book an appointment</Link>}
        </div>
        <label>Show <select className="border rounded p-2 mb-4 ml-2" value={query.period} disabled={busy} onChange={event => { setActionError(''); setQuery({ period: event.target.value, page: 1 }); }}>
            <option value="upcoming">Upcoming</option><option value="past">Past</option><option value="cancelled">Cancelled</option><option value="all">All</option>
        </select></label>
        <p className="text-sm text-gray-600 mb-4">Times are shown in {meta.timezone}.</p>
        {message && <p role="status" className="bg-green-50 text-green-800 p-3 rounded mb-4">{message}</p>}
        {actionError && <p role="alert" className="bg-red-50 text-red-800 p-3 rounded mb-4">{actionError}</p>}
        {loading && <p role="status">Loading appointments…</p>}
        {error && <p role="alert">{error} <button onClick={() => setRevision(value => value + 1)} className="underline">Try again</button></p>}
        {!loading && !error && <>
            {appointments.length === 0 && <p className="bg-white p-8 rounded shadow">No appointments found for this filter.</p>}
            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">{appointments.map(appointment => <article aria-label={`Appointment ${appointment.id}`} key={appointment.id} className="bg-white rounded-xl shadow p-5">
                <h2 className="text-lg font-bold">{isDoctor ? appointment.patient_name || 'Patient unavailable' : `Dr. ${appointment.doctor_name}`}</h2>
                {!isDoctor && <p className="text-gray-600">{appointment.speciality}</p>}
                <p className="my-3"><time dateTime={appointment.starts_at}>{formatAppointmentTime(appointment.starts_at)}</time></p>
                <p className="text-indigo-800 font-medium">{labels[appointment.statut] || appointment.statut}</p>
                <div className="flex flex-wrap gap-2 mt-4">
                    {!isDoctor && appointment.statut === 'terminé' && appointment.is_past && <Link to={`/patient/appointments/${appointment.id}/review`} className="border rounded px-3 py-2">Review this visit</Link>}
                    {isDoctor && appointment.statut === 'en_attente' && !appointment.is_past && <button disabled={busy} onClick={() => setConfirmation({ id: appointment.id, status: 'confirmé' })} className="border rounded px-3 py-2">Confirm appointment</button>}
                    {appointment.can_be_cancelled && <button disabled={busy} onClick={() => setConfirmation({ id: appointment.id, status: 'annulé' })} className="border border-red-300 text-red-800 rounded px-3 py-2">Cancel appointment</button>}
                    {isDoctor && appointment.statut === 'confirmé' && appointment.is_past && <button disabled={busy} onClick={() => setConfirmation({ id: appointment.id, status: 'terminé' })} className="border rounded px-3 py-2">Mark completed</button>}
                    {isDoctor && appointment.is_past && appointment.statut === 'confirmé' && <button disabled={busy} onClick={() => setConfirmation({ id: appointment.id, status: 'no_show' })} className="border rounded px-3 py-2">Mark no-show</button>}
                </div>
                {isDoctor && appointment.is_past && appointment.statut === 'en_attente' && <p className="mt-3 text-sm text-gray-600">This request was never confirmed. It cannot be recorded as a missed visit.</p>}
                {confirmation?.id === appointment.id && <div className="mt-4 border-t pt-3" role="group" aria-label="Confirm appointment change">
                    {confirmation.status === 'no_show' ? <p>Record this confirmed visit as missed only if you have checked that the patient did not attend. This decision is recorded in the audit history.</p> : <p>Change this appointment to {labels[confirmation.status].toLowerCase()}?</p>}
                    <div className="mt-3 flex gap-2"><button disabled={busy} onClick={update} className="bg-indigo-600 text-white rounded px-3 py-2">{busy ? 'Saving…' : 'Confirm change'}</button><button disabled={busy} onClick={() => setConfirmation(null)} className="border rounded px-3 py-2">Keep unchanged</button></div>
                </div>}
            </article>)}</div>
            {meta.last_page > 1 && <nav aria-label="Appointment pages" className="mt-6 flex items-center gap-4">
                <button disabled={busy || meta.current_page <= 1} onClick={() => setQuery(previous => ({ ...previous, page: meta.current_page - 1 }))} className="border rounded px-4 py-2 disabled:opacity-50">Previous</button>
                <span>Page {meta.current_page} of {meta.last_page}</span>
                <button disabled={busy || meta.current_page >= meta.last_page} onClick={() => setQuery(previous => ({ ...previous, page: meta.current_page + 1 }))} className="border rounded px-4 py-2 disabled:opacity-50">Next</button>
            </nav>}
        </>}
    </div>;
}
AppointmentList.propTypes = { kind: PropTypes.oneOf(['patient', 'doctor']).isRequired };
