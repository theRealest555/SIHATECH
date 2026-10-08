import { useEffect, useState } from 'react';
import { getDoctorStatistics, exportDoctorStatistics } from '../../services/doctorService';
import { apiError } from '../../utils/apiErrors';

const labels = { en_attente: 'Pending', 'confirmé': 'Confirmed', 'terminé': 'Completed', 'annulé': 'Cancelled', no_show: 'No-show', other: 'Other legacy statuses' };
export default function StatisticsPage() {
    const [period, setPeriod] = useState('month');
    const [dates, setDates] = useState({ start_date: '', end_date: '' });
    const [filters, setFilters] = useState({ period: 'month' });
    const [retry, setRetry] = useState(0);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [exportError, setExportError] = useState('');
    const [exporting, setExporting] = useState(false);
    useEffect(() => {
        const controller = new AbortController();
        setLoading(true); setData(null); setError(''); setExportError('');
        getDoctorStatistics(filters, { signal: controller.signal }).then(response => {
            if (!controller.signal.aborted) setData(response.data.data);
        }).catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load statistics.')); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [filters, retry]);
    function apply(event) {
        event.preventDefault();
        setFilters(period === 'custom' ? { ...dates } : { period });
    }
    async function download() {
        setExporting(true); setExportError('');
        try {
            // Use the displayed server range, so export always matches the visible report.
            const response = await exportDoctorStatistics({ start_date: data.range.start_date, end_date: data.range.end_date, type: 'overview', format: 'csv' });
            const url = URL.createObjectURL(response.data);
            const link = document.createElement('a'); link.href = url;
            link.download = `doctor-statistics-${data.range.start_date}-${data.range.end_date}.csv`;
            link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch { setExportError('Could not export statistics. Please try again.'); }
        finally { setExporting(false); }
    }
    return <main className="max-w-5xl mx-auto p-6 space-y-6">
        <h1 className="text-3xl font-bold">Practice statistics</h1>
        <p>Appointment activity by scheduled date. All counts apply to the selected period.</p>
        <form onSubmit={apply} className="flex flex-wrap gap-4 items-end">
            <label>Period <select value={period} onChange={e => setPeriod(e.target.value)} className="border rounded p-2"><option value="week">This week</option><option value="month">This month</option><option value="year">This year</option><option value="custom">Custom dates</option></select></label>
            {period === 'custom' && <><label>Start date <input type="date" required value={dates.start_date} onChange={e => setDates({ ...dates, start_date: e.target.value })} className="border rounded p-2" /></label><label>End date <input type="date" required min={dates.start_date} value={dates.end_date} onChange={e => setDates({ ...dates, end_date: e.target.value })} className="border rounded p-2" /></label></>}
            <button className="bg-blue-700 text-white rounded px-4 py-2">Apply period</button>
        </form>
        <p className="text-sm text-gray-600">Custom ranges include both dates and may cover up to 366 days.</p>
        {loading && <p role="status">Loading statistics…</p>}
        {error && <div role="alert">{error} <button onClick={() => setRetry(value => value + 1)}>Retry</button></div>}
        {data && <>
            <p>{data.range.start_date} – {data.range.end_date} · {data.range.timezone}</p>
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="border rounded p-4"><h2>Appointments</h2><strong className="text-2xl">{data.appointments.total}</strong></div>
                <div className="border rounded p-4"><h2>Unique booked patients</h2><strong className="text-2xl">{data.patients.total_unique}</strong></div>
                <div className="border rounded p-4"><h2>Patients seen</h2><strong className="text-2xl">{data.patients.seen}</strong><p>At least one completed consultation.</p></div>
            </div>
            {!data.appointments.total && <p>No appointments in this period.</p>}
            <section><h2 className="text-xl font-semibold">Appointment outcomes</h2><table className="w-full text-left"><thead><tr><th scope="col">Status</th><th scope="col">Count</th></tr></thead><tbody>{Object.entries(labels).map(([key, label]) => <tr key={key}><th scope="row" className="font-normal">{label}</th><td>{data.appointments.by_status[key]}</td></tr>)}</tbody></table></section>
            <p>Patients with multiple completed consultations in this period: {data.patients.repeat_completed}</p>
            <p>Approved reviews submitted in this period: {data.rating.total_reviews}. Average rating: {data.rating.average === null ? 'No approved reviews' : `${data.rating.average} / 5`}.</p>
            <details><summary>Daily appointment counts</summary><table className="w-full text-left"><thead><tr><th scope="col">Date</th><th scope="col">Appointments</th></tr></thead><tbody>{data.trends.appointments.map(day => <tr key={day.date}><th scope="row" className="font-normal">{day.date}</th><td>{day.count}</td></tr>)}</tbody></table></details>
            <p className="text-gray-600">{data.revenue.reason}</p>
            <button disabled={exporting} onClick={download} className="border border-blue-700 rounded px-4 py-2">{exporting ? 'Exporting…' : 'Download summary CSV'}</button>
            {exportError && <p role="alert">{exportError}</p>}
        </>}
    </main>;
}
