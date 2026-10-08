import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { getDoctorSlots, getPublicDoctorDetails } from '../../services/doctorService';
import { bookAppointment } from '../../services/patientService';
import DoctorAvatar from '../../components/ui/DoctorAvatar';
import { apiError } from '../../utils/apiErrors';

export default function PublicDoctorProfileViewPage() {
    const { doctorId } = useParams();
    const { user } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const requestedDate = new URLSearchParams(location.search).get('date');
    const [profile, setProfile] = useState(null);
    const [profileLoading, setProfileLoading] = useState(true);
    const [profileError, setProfileError] = useState('');
    const [date, setDate] = useState('');
    const [slots, setSlots] = useState([]);
    const [slotMeta, setSlotMeta] = useState({});
    const [slotLoading, setSlotLoading] = useState(false);
    const [slotError, setSlotError] = useState('');
    const [selectedSlot, setSelectedSlot] = useState('');
    const [booking, setBooking] = useState(false);
    const [bookingError, setBookingError] = useState('');
    const [retry, setRetry] = useState(0);
    const [slotRetry, setSlotRetry] = useState(0);

    useEffect(() => {
        const controller = new AbortController();
        setProfileLoading(true);
        setProfileError('');
        setProfile(null);
        setDate('');
        setSelectedSlot('');
        setBookingError('');
        getPublicDoctorDetails(doctorId, { signal: controller.signal }).then(response => {
            if (controller.signal.aborted) return;
            setProfile(response.data);
            const today = response.data.meta.today;
            setDate(/^\d{4}-\d{2}-\d{2}$/.test(requestedDate || '') && requestedDate >= today ? requestedDate : today);
        }).catch(err => {
            if (!controller.signal.aborted) setProfileError(err.response?.status === 404 ? 'This doctor is no longer available for booking.' : apiError(err, 'The doctor profile could not be loaded.'));
        }).finally(() => { if (!controller.signal.aborted) setProfileLoading(false); });
        return () => controller.abort();
    }, [doctorId, requestedDate, retry]);

    useEffect(() => {
        if (!date || !profile) return;
        const controller = new AbortController();
        setSlotLoading(true);
        setSlotError('');
        setSelectedSlot('');
        setSlots([]);
        getDoctorSlots(doctorId, date, { signal: controller.signal }).then(response => {
            if (controller.signal.aborted) return;
            setSlots(response.data.data);
            setSlotMeta(response.data.meta);
        }).catch(err => {
            if (!controller.signal.aborted) setSlotError(apiError(err, 'Available times could not be loaded. Please try again.'));
        }).finally(() => { if (!controller.signal.aborted) setSlotLoading(false); });
        return () => controller.abort();
    }, [doctorId, date, profile, slotRetry]);

    async function book(event) {
        event.preventDefault();
        if (!selectedSlot || booking || slotLoading || user?.role !== 'patient' || !user.email_verified_at) return;
        setBooking(true);
        setBookingError('');
        try {
            await bookAppointment({ doctor_id: doctorId, date_heure: `${date} ${selectedSlot}:00` });
            navigate('/patient/appointments', { state: { message: 'Your appointment request was sent. It is awaiting confirmation from the doctor.' } });
        } catch (err) {
            setBookingError(apiError(err, 'Booking could not be confirmed. Check My appointments before trying again.'));
            if (err.response?.status === 409) {
                setSelectedSlot('');
                setSlotRetry(value => value + 1);
            }
        } finally { setBooking(false); }
    }

    if (profileLoading) return <div className="max-w-4xl mx-auto p-8"><p role="status">Loading doctor profile…</p></div>;
    if (profileError) return <div className="max-w-4xl mx-auto p-8"><p role="alert">{profileError}</p><button onClick={() => setRetry(value => value + 1)} className="underline mt-4">Try again</button> <Link to="/doctors" className="underline ml-4">Find another doctor</Link></div>;
    const doctor = profile.data;
    const timezone = slotMeta.timezone || profile.meta.timezone;
    const canBook = user?.role === 'patient' && Boolean(user.email_verified_at);

    return <div className="max-w-4xl mx-auto p-4 md:p-8">
        <Link to="/doctors" className="text-indigo-700 underline">Back to doctor search</Link>
        <section className="mt-5 bg-white shadow rounded-xl p-6">
            <div className="flex gap-5 items-center"><DoctorAvatar key={doctor.id} name={doctor.name} photo={doctor.photo} /><div>
                <p className="text-indigo-700 font-semibold">{doctor.speciality?.nom}</p>
                <h1 className="text-3xl font-bold">Dr. {doctor.name}</h1><p className="text-green-700 text-sm mt-1">Verified doctor</p>
                <p className="text-sm text-gray-600 mt-1">{doctor.rating.total_reviews > 0 ? `${doctor.rating.formatted} / 5 · ${doctor.rating.total_reviews} reviews` : 'No reviews yet'}</p>
            </div></div>
            <p className="mt-5 whitespace-pre-line">{doctor.description || 'No biography provided.'}</p>
            <dl className="mt-5 grid gap-3">
                <div><dt className="font-semibold">Practice address</dt><dd>{doctor.address || 'Not provided'}</dd></div>
                <div><dt className="font-semibold">Phone</dt><dd>{doctor.phone || 'Not provided'}</dd></div>
                <div><dt className="font-semibold">Languages</dt><dd>{doctor.languages.map(language => language.nom).join(', ') || 'Not provided'}</dd></div>
            </dl>
        </section>
        <section className="mt-6 bg-white shadow rounded-xl p-6" aria-labelledby="booking-heading">
            <h2 id="booking-heading" className="text-2xl font-bold">Book an appointment</h2>
            <p className="text-gray-600 my-3">Appointments last 30 minutes. All times are in {timezone}.</p>
            <label className="block">Appointment date<input className="block border rounded p-2 mt-1" type="date" min={profile.meta.today} value={date} disabled={booking} onChange={event => { setSelectedSlot(''); setBookingError(''); setDate(event.target.value); }} /></label>
            {!date && <p className="mt-4">Choose a date to see available times.</p>}
            {date && slotLoading && <p role="status" className="mt-4">Loading available times…</p>}
            {date && slotError && <p role="alert" className="mt-4 text-red-800">{slotError} <button onClick={() => setSlotRetry(value => value + 1)} className="underline">Try again</button></p>}
            {date && !slotLoading && !slotError && slots.length === 0 && <p role="status" className="mt-4">{slotMeta.is_on_leave ? 'This doctor is on leave on this date.' : 'No available appointments on this date. Please choose another date.'}</p>}
            {date && !slotLoading && !slotError && slots.length > 0 && <form onSubmit={book} className="mt-5">
                <fieldset disabled={booking}>
                    <legend className="font-semibold mb-3">Available times</legend>
                    <div className="flex flex-wrap gap-3">{slots.map(slot => <label key={slot} className={`border rounded-lg p-3 cursor-pointer ${selectedSlot === slot ? 'border-indigo-600 bg-indigo-50' : ''}`}>
                        <input className="mr-2" type="radio" name="appointment-time" value={slot} checked={selectedSlot === slot} onChange={() => setSelectedSlot(slot)} />{slot}
                    </label>)}</div>
                </fieldset>
                {canBook && <button type="submit" disabled={!selectedSlot || booking} className="mt-5 bg-indigo-600 text-white rounded py-2 px-5 disabled:opacity-50">{booking ? 'Requesting appointment…' : 'Request appointment'}</button>}
            </form>}
            {bookingError && <p role="alert" className="text-red-800 mt-4">{bookingError} <Link to="/patient/appointments" className="underline">My appointments</Link></p>}
            {!user && <p className="mt-5"><Link to="/login" state={{ from: { pathname: location.pathname, search: date ? `?date=${date}` : location.search } }} className="text-indigo-700 underline">Sign in</Link> as a patient to book an appointment.</p>}
            {user?.role === 'patient' && !user.email_verified_at && <p className="mt-5"><Link to="/verify-email" className="text-indigo-700 underline">Verify your email</Link> before booking.</p>}
            {user && user.role !== 'patient' && <p className="mt-5">Appointments can be booked with a patient account.</p>}
        </section>
    </div>;
}
