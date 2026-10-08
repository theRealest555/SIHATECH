import { useEffect, useState } from 'react';
import PropTypes from 'prop-types';
import { getCatalogue, createCatalogueRecord, updateCatalogueRecord } from '../services/adminService';
import { apiError } from '../utils/apiErrors';

export default function CatalogueManagement({ kind }) {
    const speciality = kind === 'specialities';
    const title = speciality ? 'Specialities' : 'Languages';
    const [search, setSearch] = useState(''); const [filter, setFilter] = useState(''); const [page, setPage] = useState(1);
    const [reload, setReload] = useState(0); const [result, setResult] = useState(null); const [loading, setLoading] = useState(true);
    const [editor, setEditor] = useState(null); const [busy, setBusy] = useState(false); const [error, setError] = useState(''); const [saveError, setSaveError] = useState(''); const [message, setMessage] = useState('');
    useEffect(() => {
        const controller = new AbortController(); setLoading(true); setResult(null); setError('');
        getCatalogue(kind, { search: filter, page, per_page: 25 }, { signal: controller.signal }).then(response => {
            if (!controller.signal.aborted) setResult(response.data);
        }).catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load catalogue.')); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [kind, filter, page, reload]);
    function edit(record = null) { setSaveError(''); setMessage(''); setEditor(record ? { ...record, expected_nom: record.nom, expected_description: record.description } : { nom: '', description: '' }); }
    async function save(event) {
        event.preventDefault(); setBusy(true); setSaveError('');
        const body = { nom: editor.nom.trim(), ...(speciality ? { description: editor.description.trim() } : {}), ...(editor.id ? { expected_nom: editor.expected_nom, ...(speciality ? { expected_description: editor.expected_description } : {}) } : {}) };
        try {
            if (editor.id) await updateCatalogueRecord(kind, editor.id, body); else await createCatalogueRecord(kind, body);
            setEditor(null); setMessage('Record saved.'); setReload(value => value + 1);
        } catch (err) { setSaveError(apiError(err, 'Could not save this record.')); }
        finally { setBusy(false); }
    }
    return <main className="max-w-5xl mx-auto p-6 space-y-6">
        <h1 className="text-3xl font-bold">{title}</h1>
        <p>Manage the names used in doctor profiles and patient search. Existing doctor assignments are preserved when a name is edited.</p>
        <div className="flex gap-4"><button disabled={busy} onClick={() => edit()}>Add {speciality ? 'speciality' : 'language'}</button><button disabled={loading || busy} onClick={() => setReload(value => value + 1)}>Refresh catalogue</button></div>
        <form onSubmit={e => { e.preventDefault(); setPage(1); setFilter(search); setReload(value => value + 1); }} className="flex gap-3"><label>Search names <input value={search} onChange={e => setSearch(e.target.value)} maxLength={100} className="border rounded p-2" /></label><button>Search</button></form>
        {message && <p role="status">{message}</p>}
        {editor && <section className="border rounded p-4 space-y-3" aria-label="Record editor"><h2 className="text-xl font-semibold">{editor.id ? 'Edit record' : 'New record'}</h2>
            <form onSubmit={save} className="space-y-4"><label className="block">Name <input required maxLength={255} value={editor.nom} disabled={busy} onChange={e => setEditor(value => ({ ...value, nom: e.target.value }))} className="border rounded p-2 w-full" /></label>
                {speciality && <label className="block">Description <textarea required maxLength={255} value={editor.description} disabled={busy} onChange={e => setEditor(value => ({ ...value, description: e.target.value }))} className="border rounded p-2 w-full" /></label>}
                {saveError && <p role="alert">{saveError}</p>}
                <div className="flex gap-4"><button disabled={busy} className="bg-blue-700 text-white px-4 py-2 rounded">{busy ? 'Saving…' : 'Save record'}</button><button type="button" disabled={busy} onClick={() => setEditor(null)}>Cancel edit</button></div>
            </form></section>}
        {loading && <p role="status">Loading catalogue…</p>}
        {error && <p role="alert">{error} <button onClick={() => setReload(value => value + 1)}>Retry</button></p>}
        {result && <><p>{result.meta.total} records</p>
            {!result.data.length ? <p>No records match this search.</p> : <table className="w-full text-left"><thead><tr><th scope="col">Name</th>{speciality && <th scope="col">Description</th>}<th scope="col">Doctor profiles using this</th><th scope="col">Actions</th></tr></thead><tbody>{result.data.map(record => <tr key={record.id} className="border-t"><th scope="row" className="font-normal p-2">{record.nom}</th>{speciality && <td className="p-2">{record.description}</td>}<td className="p-2">{record.doctors_count}</td><td className="p-2"><button disabled={busy} onClick={() => edit(record)} aria-label={`Edit ${record.nom}`}>Edit</button></td></tr>)}</tbody></table>}
            <nav aria-label="Catalogue pages" className="flex gap-4"><button disabled={result.meta.current_page <= 1 || busy} onClick={() => setPage(value => value - 1)}>Previous page</button><span>Page {result.meta.current_page} of {result.meta.last_page}</span><button disabled={result.meta.current_page >= result.meta.last_page || busy} onClick={() => setPage(value => value + 1)}>Next page</button></nav>
        </>}
    </main>;
}
CatalogueManagement.propTypes = { kind: PropTypes.oneOf(['specialities', 'languages']).isRequired };
