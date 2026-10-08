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
        <main className="workspace-dashboard">
            <div className="mx-auto max-w-5xl space-y-6">
                <header className="dashboard-header">
                    <div><p className="eyebrow">{doctor ? 'MAKE ROOM FOR GOOD CARE' : 'YOUR CARE, IN ONE PLACE'}</p><h1>{doctor ? 'Doctor' : 'Patient'} dashboard</h1>
                        <p>{doctor ? 'Your next consultations and the tools to keep them moving.' : 'Stay on top of your visits and find your next step.'}</p></div>
                    <button type="button" onClick={() => setRevision(value => value + 1)} className="premium-button secondary"><i className="bi bi-arrow-clockwise" aria-hidden="true" />Refresh dashboard</button>
                </header>
                <div className="dashboard-metrics">
                    <article className="metric-card"><h2>Upcoming visits <i className="bi bi-calendar2-week" aria-hidden="true" /></h2><p>{appointments?.meta.total ?? '—'}</p><small>Pending and confirmed appointments</small></article>
                    <article className="metric-card"><h2>Your workspace <i className="bi bi-person" aria-hidden="true" /></h2><p className="!text-xl">{doctor ? 'Doctor' : 'Patient'}</p><small>Appointments and account tools</small></article>
                    <article className="metric-card"><h2>{doctor ? 'Submitted credentials' : 'Next appointment'} <i className="bi bi-clock" aria-hidden="true" /></h2><p className="!text-xl">{doctor ? documents?.length ?? '—' : appointments ? appointments.data[0] ? formatAppointmentTime(appointments.data[0].starts_at) : 'None scheduled' : '—'}</p><small>{doctor ? 'Follow reviews in Documents' : 'Times shown in the clinic timezone'}</small></article>
                    <Link className="metric-card" to={doctor ? '/doctor/availability' : '/doctors'}><h2>{doctor ? 'Consultation hours' : 'Ready for your next visit?'} <i className="bi bi-arrow-up-right" aria-hidden="true" /></h2><p className="!text-xl">{doctor ? 'Set availability' : 'Find a doctor'}</p><small>{doctor ? 'Manage hours and time off' : 'Explore doctors and available times'}</small></Link>
                </div>
                <div className="dashboard-columns"><div className="space-y-6"><section aria-labelledby="next-appointments" className="dashboard-panel">
                    <div className="panel-heading">
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
                </div><div><nav aria-label="Account tools" className="dashboard-panel account-tools">
                    <div className="panel-heading"><h2>Your next step</h2><i className="bi bi-arrow-down-right" aria-hidden="true" /></div>
                    {[...links, ['/my-subscription', 'Subscription and billing', 'Review your plan, payments and subscription status.']].map(([path, title, description]) => <Link key={path} to={path}>
                        <h2 className="text-lg font-semibold text-blue-800">{title}</h2><p className="mt-2 text-slate-600">{description}</p>
                    </Link>)}
                </nav><section className="dashboard-note"><h2>{doctor ? 'A schedule that works for you.' : 'A little planning goes a long way.'}</h2><p>{doctor ? 'Keep your consultation hours up to date so patients can choose an available time.' : 'Review the date and clinic time before your appointment. You can follow confirmations in your appointment list.'}</p></section></div></div>
            </div>
        </main>
    );
}

WorkspaceDashboard.propTypes = { role: PropTypes.oneOf(['doctor', 'patient']).isRequired };
