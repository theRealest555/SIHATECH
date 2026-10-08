import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getLanguages, getSpecialities, searchDoctorsAdvanced } from '../../services/doctorService';
import DoctorAvatar from '../../components/ui/DoctorAvatar';
import { apiError } from '../../utils/apiErrors';

const emptyFilters = { name: '', speciality_id: '', location: '', language_id: '', date: '' };

export default function FindDoctorPage() {
    const [filters, setFilters] = useState(emptyFilters);
    const [query, setQuery] = useState({ page: 1 });
    const [specialities, setSpecialities] = useState([]);
    const [languages, setLanguages] = useState([]);
    const [optionsError, setOptionsError] = useState('');
    const [doctors, setDoctors] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [retry, setRetry] = useState(0);

    useEffect(() => {
        const controller = new AbortController();
        Promise.all([getSpecialities({ signal: controller.signal }), getLanguages({ signal: controller.signal })])
            .then(([specialityResponse, languageResponse]) => {
                if (controller.signal.aborted) return;
                setSpecialities(specialityResponse.data.data);
                setLanguages(languageResponse.data.data);
            }).catch(() => {
                if (!controller.signal.aborted) setOptionsError('Filter options could not be loaded. You can still search by name or location.');
            });
        return () => controller.abort();
    }, []);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError('');
        searchDoctorsAdvanced({ ...query, per_page: 12, sort_by: 'name', sort_order: 'asc' }, { signal: controller.signal })
            .then(response => {
                if (controller.signal.aborted) return;
                setDoctors(response.data.data);
                setMeta(response.data.meta);
            }).catch(err => {
                if (!controller.signal.aborted) setError(apiError(err, 'Doctor search is unavailable. Please try again.'));
            }).finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });
        return () => controller.abort();
    }, [query, retry]);

    function search(event) {
        event.preventDefault();
        setLoading(true);
        const { language_id, ...values } = filters;
        const criteria = Object.fromEntries(Object.entries(values).filter(([, value]) => value !== ''));
        if (language_id) criteria.language_ids = [language_id];
        setQuery({ ...criteria, page: 1 });
    }
    const change = event => setFilters(previous => ({ ...previous, [event.target.name]: event.target.value }));
    const inputClass = 'mt-1 w-full p-2 border border-gray-300 rounded-md';

    return <div className="max-w-6xl mx-auto p-4 md:p-8">
        <h1 className="text-3xl font-bold text-gray-900">Find a doctor</h1>
        <p className="text-gray-600 mt-2 mb-6">Browse verified doctors and check their available appointment times.</p>
        {optionsError && <p role="status" className="mb-4 text-amber-800">{optionsError}</p>}
        <form onSubmit={search} className="bg-white shadow rounded-xl p-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-6">
            <label>Doctor name<input className={inputClass} id="name" name="name" value={filters.name} onChange={change} maxLength={100} /></label>
            <label>Speciality<select className={inputClass} name="speciality_id" value={filters.speciality_id} onChange={change}>
                <option value="">All specialities</option>{specialities.map(item => <option key={item.id} value={item.id}>{item.nom}</option>)}
            </select></label>
            <label>Location<input className={inputClass} name="location" value={filters.location} onChange={change} maxLength={255} /></label>
            <label>Language<select className={inputClass} name="language_id" value={filters.language_id} onChange={change}>
                <option value="">All languages</option>{languages.map(item => <option key={item.id} value={item.id}>{item.nom}</option>)}
            </select></label>
            <label>Show slots on date<input className={inputClass} type="date" name="date" min={meta.today} value={filters.date} onChange={change} /></label>
            <button type="submit" disabled={loading} className="self-end bg-indigo-600 text-white rounded-md py-2 px-4 disabled:opacity-50">Search</button>
        </form>
        {loading && <p role="status">Searching for doctors…</p>}
        {error && <div role="alert" className="bg-red-50 text-red-800 rounded p-4">{error} <button onClick={() => setRetry(value => value + 1)} className="underline ml-2">Try again</button></div>}
        {!loading && !error && <>
            <p role="status" className="mb-4 text-gray-600">{meta.total} doctor{meta.total === 1 ? '' : 's'} found.</p>
            {doctors.length === 0 && <p className="p-8 bg-white shadow rounded-xl">No matching doctors. Try another name, speciality or location.</p>}
            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                {doctors.map(doctor => <article key={doctor.id} className="bg-white shadow rounded-xl p-5 flex flex-col gap-3">
                    <div className="flex gap-4 items-center"><DoctorAvatar name={doctor.name} photo={doctor.photo} /><div>
                        <h2 className="font-bold text-lg">Dr. {doctor.name}</h2><p>{doctor.speciality}</p><p className="text-sm text-gray-600">{doctor.location === 'N/A' ? 'Location not provided' : doctor.location}</p>
                    </div></div>
                    <p className="text-sm text-gray-600">{doctor.languages?.join(', ') || 'Languages not provided'}</p>
                    <p className="text-sm">{doctor.rating?.total_reviews > 0 ? `${doctor.rating.formatted} / 5 · ${doctor.rating.total_reviews} reviews` : 'No reviews yet'}</p>
                    {query.date && <p className="text-sm">{doctor.available_slots?.length || 0} available slots on {query.date} ({meta.timezone})</p>}
                    <Link to={`/doctors/${doctor.id}${query.date ? `?date=${query.date}` : ''}`} className="mt-auto bg-indigo-600 text-white rounded py-2 px-3 text-center">View profile and book</Link>
                </article>)}
            </div>
            {meta.last_page > 1 && <nav aria-label="Search results pages" className="mt-6 flex gap-4 items-center">
                <button disabled={meta.current_page <= 1} onClick={() => { setLoading(true); setQuery(previous => ({ ...previous, page: meta.current_page - 1 })); }} className="border rounded px-4 py-2 disabled:opacity-50">Previous</button>
                <span>Page {meta.current_page} of {meta.last_page}</span>
                <button disabled={meta.current_page >= meta.last_page} onClick={() => { setLoading(true); setQuery(previous => ({ ...previous, page: meta.current_page + 1 })); }} className="border rounded px-4 py-2 disabled:opacity-50">Next</button>
            </nav>}
        </>}
    </div>;
}
