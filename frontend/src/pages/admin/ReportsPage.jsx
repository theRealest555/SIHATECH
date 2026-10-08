import { useEffect, useRef, useState } from 'react';
import { getAdminReport, exportAdminReport } from '../../services/adminService';
import { apiError } from '../../utils/apiErrors';
const statusNames = { en_attente: 'Pending', 'confirmé': 'Confirmed', 'terminé': 'Completed', 'annulé': 'Cancelled', no_show: 'No-show', other: 'Other legacy statuses', pending: 'Pending', completed: 'Completed', failed: 'Failed', cancelled: 'Cancelled' };
const buttonClass = 'border border-blue-700 text-blue-800 rounded px-3 py-2 disabled:opacity-50';
export default function ReportsPage() {
    const [type, setType] = useState('financial');
    const [dates, setDates] = useState({ start_date: '', end_date: '' });
    const [query, setQuery] = useState({ type: 'financial', params: {} });
    const [revision, setRevision] = useState(0);
    const [data, setData] = useState(null); const [loading, setLoading] = useState(true); const [error, setError] = useState('');
    const [exporting, setExporting] = useState(false); const [exportError, setExportError] = useState(''); const exportController = useRef(null);
    useEffect(() => {
        const controller = new AbortController(); setLoading(true); setData(null); setError(''); setExportError(''); setExporting(false);
        getAdminReport(query.type, query.params, { signal: controller.signal }).then(response => { if (!controller.signal.aborted) setData(response.data.data); })
            .catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load this report.')); }).finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => { controller.abort(); exportController.current?.abort(); };
    }, [query, revision]);
    function apply(event) { event.preventDefault(); setData(null); setQuery({ type, params: dates.start_date || dates.end_date ? { ...dates } : {} }); }
    async function download() {
        const controller = new AbortController(); exportController.current = controller; setExporting(true); setExportError('');
        try {
            const response = await exportAdminReport(query.type, { start_date: data.range.start_date, end_date: data.range.end_date, format: 'csv' }, { signal: controller.signal });
            if (controller.signal.aborted) return;
            const url = URL.createObjectURL(response.data); const link = document.createElement('a'); link.href = url;
            link.download = `admin-${query.type}-${data.range.start_date}-${data.range.end_date}.csv`; link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch { if (!controller.signal.aborted) setExportError('Could not export this report. Please try again.'); }
        finally { if (!controller.signal.aborted) setExporting(false); }
    }
    const financial = query.type === 'financial';
    return <main className="max-w-5xl mx-auto p-6 space-y-5"><h1 className="text-3xl font-bold">System reports</h1>
        <p>Platform payment records and scheduled appointment activity. Reports contain aggregate figures.</p>
        <form onSubmit={apply} className="flex flex-wrap items-end gap-4 border rounded p-4">
            <label>Report type <select value={type} disabled={exporting} onChange={event => setType(event.target.value)} className="border rounded p-2"><option value="financial">Platform payments</option><option value="appointments">Appointments</option></select></label>
            <label>Start date <input type="date" value={dates.start_date} required={Boolean(dates.end_date)} disabled={exporting} onChange={event => setDates({ ...dates, start_date: event.target.value })} className="border rounded p-2" /></label>
            <label>End date <input type="date" value={dates.end_date} min={dates.start_date} required={Boolean(dates.start_date)} disabled={exporting} onChange={event => setDates({ ...dates, end_date: event.target.value })} className="border rounded p-2" /></label>
            <button className={buttonClass} disabled={exporting}>Generate report</button>
        </form>
        <p>Leave both dates blank for this month. Custom ranges include both dates and allow up to 366 days.</p>
        {loading && <p role="status">Loading report…</p>}{error && <p role="alert">{error} <button className={buttonClass} onClick={() => setRevision(value => value + 1)}>Retry</button></p>}
        {data && <section aria-label="Generated report" className="space-y-5"><h2 className="text-2xl font-semibold">{financial ? 'Platform payment report' : 'Scheduled appointment report'}</h2>
            <p>{data.range.start_date} to {data.range.end_date} inclusive · {data.range.timezone}</p>
            <div className="flex flex-wrap gap-4"><button className={buttonClass} disabled={exporting} onClick={download}>{exporting ? 'Exporting…' : 'Export aggregate CSV'}</button><button className={buttonClass} disabled={exporting} onClick={() => setRevision(value => value + 1)}>Refresh report</button></div>
            <p>Exports reload the displayed date range from current records.</p>{exportError && <p role="alert">{exportError}</p>}
            {financial ? <>
                <p>Payments are grouped by record creation date and current status. Completed amounts are gross recorded amounts, separated by currency. Refunds, fees, settlement and consultation billing are not tracked.</p>
                <h3 className="text-xl font-semibold">{data.payments.total} payment records</h3>
                {!data.payments.total && <p>No payment records in this range.</p>}
                <ul>{Object.entries(data.payments.by_status).map(([status, count]) => <li key={status}>{statusNames[status] || status}: {count}</li>)}</ul>
                <div className="overflow-x-auto"><table className="w-full bg-white border"><caption>Completed payment amounts by currency</caption><thead><tr><th>Currency</th><th>Completed records</th><th>Gross completed amount</th><th>Subscription-linked amount</th></tr></thead><tbody>{data.payments.by_currency.map(row => <tr key={row.currency}><th scope="row">{row.currency}</th><td>{row.transactions}</td><td>{row.completed_amount}</td><td>{row.subscription_amount}</td></tr>)}</tbody></table></div>
                {!data.payments.by_currency.length && <p>No completed payment amounts in this range.</p>}
                <h3 className="text-xl font-semibold">Subscription activity</h3><p>Created in range: {data.subscriptions.created_in_range} · Cancelled in range: {data.subscriptions.cancelled_in_range}</p>
                <p>Active now: {data.subscriptions.active_now} · Snapshot at {data.subscriptions.as_of}. This current snapshot applies to all dates, and is not a historical active count.</p>
            </> : <>
                <p>Grouped by scheduled visit date and current status. Specialities reflect current doctor assignments, including archived profiles.</p>
                <h3 className="text-xl font-semibold">{data.appointments.total} scheduled appointments</h3>{!data.appointments.total && <p>No appointments scheduled in this range.</p>}
                <ul>{Object.entries(data.appointments.by_status).map(([status, count]) => <li key={status}>{statusNames[status] || status}: {count}</li>)}</ul>
                <div className="overflow-x-auto"><table className="w-full bg-white border"><caption>Top 10 speciality assignments</caption><thead><tr><th>Speciality</th><th>Appointments</th></tr></thead><tbody>{data.specialities.map((row, index) => <tr key={index}><th scope="row">{row.name}</th><td>{row.count}</td></tr>)}</tbody></table></div>
                <div className="grid md:grid-cols-2 gap-5"><table className="w-full bg-white border"><caption>Daily scheduled visits</caption><thead><tr><th>Date</th><th>Appointments</th></tr></thead><tbody>{data.daily.map(row => <tr key={row.date}><th scope="row">{row.date}</th><td>{row.count}</td></tr>)}</tbody></table>
                    <table className="w-full bg-white border"><caption>Visits by scheduled hour</caption><thead><tr><th>Hour</th><th>Appointments</th></tr></thead><tbody>{data.hourly.map(row => <tr key={row.hour}><th scope="row">{row.hour}</th><td>{row.count}</td></tr>)}</tbody></table></div>
            </>}
        </section>}
    </main>;
}
