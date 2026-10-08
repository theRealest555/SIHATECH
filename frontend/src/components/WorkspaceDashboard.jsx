import { useEffect, useState } from 'react';
import PropTypes from 'prop-types';
import { Link } from 'react-router-dom';
import { getDoctorAppointments, getDoctorDocuments } from '../services/doctorService';
import { getPatientAppointments } from '../services/patientService';
import { apiError } from '../utils/apiErrors';
import { formatAppointmentTime } from '../utils/appointmentTime';

const statuses = { en_attente: 'Awaiting confirmation', 'confirmé': 'Confirmed' };

export default function WorkspaceDashboard({ role }) {
    const doctor = role === 'doctor';
    const [appointments, setAppointments] = useState(null);
    const [documents, setDocuments] = useState(null);
    const [error, setError] = useState('');
    const [documentError, setDocumentError] = useState('');
    const [revision, setRevision] = useState(0);
    useEffect(() => {
        const controller = new AbortController();
        setAppointments(null);
        setDocuments(null);
        setError('');
        setDocumentError('');
        const fetchAppointments = doctor ? getDoctorAppointments : getPatientAppointments;
        fetchAppointments({ period: 'upcoming', per_page: 3 }, { signal: controller.signal })
            .then(response => { if (!controller.signal.aborted) setAppointments(response.data); })
            .catch(failure => { if (!controller.signal.aborted) setError(apiError(failure, 'Unable to load appointments.')); });
        if (doctor) {
            getDoctorDocuments({ signal: controller.signal })
                .then(response => { if (!controller.signal.aborted) setDocuments(response.data.documents); })
                .catch(failure => { if (!controller.signal.aborted) setDocumentError(apiError(failure, 'Unable to load document status.')); });
        }
        return () => controller.abort();
    }, [doctor, revision]);

    const links = doctor ? [
        ['/doctor/availability', 'Availability', 'Set consultation hours and time off.'],
        ['/doctor/documents', 'Credentials', 'Upload documents and follow their review.'],
        ['/doctor/profile', 'Profile', 'Update your contact details and speciality.'],
    ] : [
        ['/doctors', 'Find a doctor', 'Browse verified doctors and book an appointment.'],
        ['/patient/profile', 'Profile', 'Update your contact details and preferred doctor.'],
    ];
    return (
        <main className="min-h-screen bg-slate-50 px-4 py-8">
            <div className="mx-auto max-w-5xl space-y-6">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div><h1 className="text-3xl font-bold text-slate-900">{doctor ? 'Doctor' : 'Patient'} dashboard</h1>
                        <p className="mt-2 text-slate-600">Your appointments and account tools.</p></div>
                    <button type="button" onClick={() => setRevision(value => value + 1)} className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-slate-800">Refresh dashboard</button>
                </header>
                <section aria-labelledby="next-appointments" className="rounded-xl border border-slate-200 bg-white p-6">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="next-appointments" className="text-xl font-semibold text-slate-900">Upcoming appointments{appointments && ` (${appointments.meta.total})`}</h2>
                        <Link className="text-blue-700 underline" to={`/${role}/appointments`}>Manage appointments</Link>
                    </div>
                    {error ? <p role="alert" className="mt-4 text-red-700">{error}</p> : !appointments ? <p role="status" className="mt-4 text-slate-600">Loading appointments…</p> : <>
                        <p className="mt-2 text-sm text-slate-600">Clinic time: {appointments.meta.timezone}. Pending and confirmed visits only.</p>
                        {appointments.data.length === 0 ? <p className="mt-5 text-slate-700">No upcoming appointments.</p> : <ul className="mt-4 divide-y divide-slate-200">
                            {appointments.data.map(appointment => <li key={appointment.id} className="flex flex-wrap items-center justify-between gap-3 py-4">
                                <div><p className="font-semibold text-slate-900">{doctor ? appointment.patient_name : appointment.doctor_name}</p>
                                    <p className="text-slate-600">{formatAppointmentTime(appointment.starts_at)}</p>
                                    {!doctor && <p className="text-sm text-slate-600">{appointment.speciality}</p>}</div>
                                <span className="rounded-full bg-blue-50 px-3 py-1 text-sm text-blue-800">{statuses[appointment.statut] || appointment.statut}</span>
                            </li>)}
                        </ul>}
                        {appointments.meta.total > appointments.data.length && <p className="text-sm text-slate-600">Showing your next {appointments.data.length} visits. Open appointments to see all.</p>}
                    </>}
                </section>
                {doctor && <section aria-labelledby="credential-status" className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 id="credential-status" className="text-xl font-semibold text-slate-900">Credential review</h2>
                    {documentError ? <p role="alert" className="mt-3 text-red-700">{documentError}</p> : !documents ? <p role="status" className="mt-3">Loading document status…</p> : <div className="mt-3 flex flex-wrap gap-6 text-slate-700">
                        <p>Awaiting review: <strong>{documents.filter(document => document.status === 'pending').length}</strong></p>
                        <p>Approved: <strong>{documents.filter(document => document.status === 'approved').length}</strong></p>
                        <p>Rejected: <strong>{documents.filter(document => document.status === 'rejected').length}</strong></p>
                    </div>}
                    <Link className="mt-3 inline-block text-blue-700 underline" to="/doctor/documents">View credential details</Link>
                </section>}
                <nav aria-label="Account tools" className="grid gap-4 sm:grid-cols-2">
                    {[...links, ['/my-subscription', 'Subscription and billing', 'Review your plan, payments and subscription status.']].map(([path, title, description]) => <Link key={path} to={path} className="rounded-xl border border-slate-200 bg-white p-6 hover:border-blue-400">
                        <h2 className="text-lg font-semibold text-blue-800">{title}</h2><p className="mt-2 text-slate-600">{description}</p>
                    </Link>)}
                </nav>
            </div>
        </main>
    );
}

WorkspaceDashboard.propTypes = { role: PropTypes.oneOf(['doctor', 'patient']).isRequired };
